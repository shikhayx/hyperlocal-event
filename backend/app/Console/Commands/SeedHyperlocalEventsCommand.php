<?php

declare(strict_types=1);

namespace HiEvents\Console\Commands;

use Carbon\CarbonImmutable;
use HiEvents\Console\Commands\Hyperlocal\CityConfig;
use HiEvents\Console\Commands\Hyperlocal\HyperlocalEventData;
use HiEvents\Console\Commands\Hyperlocal\HyperlocalImageGenerator;
use HiEvents\DataTransferObjects\AddressDTO;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\DomainObjects\Enums\LocationType;
use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Models\User;
use HiEvents\Services\Application\Handlers\Account\CreateAccountHandler;
use HiEvents\Services\Application\Handlers\Account\DTO\CreateAccountDTO;
use HiEvents\Services\Application\Handlers\Event\CreateEventHandler;
use HiEvents\Services\Application\Handlers\Event\CreateEventImageHandler;
use HiEvents\Services\Application\Handlers\Event\DeleteEventHandler;
use HiEvents\Services\Application\Handlers\Event\DTO\CreateEventDTO;
use HiEvents\Services\Application\Handlers\Event\DTO\CreateEventImageDTO;
use HiEvents\Services\Application\Handlers\Event\DTO\DeleteEventDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\GenerateOccurrencesDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\GenerateOccurrencesFromRuleHandler;
use HiEvents\Services\Application\Handlers\Location\CreateLocationHandler;
use HiEvents\Services\Application\Handlers\Location\DTO\UpsertLocationDTO;
use HiEvents\Services\Application\Handlers\Organizer\CreateOrganizerHandler;
use HiEvents\Services\Application\Handlers\Organizer\DTO\CreateOrganizerDTO;
use HiEvents\Services\Application\Handlers\Product\CreateProductHandler;
use HiEvents\Services\Application\Handlers\Product\DTO\UpsertProductDTO;
use HiEvents\Services\Domain\EventLocation\EventLocationData;
use HiEvents\Services\Domain\Product\DTO\ProductPriceDTO;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;

class SeedHyperlocalEventsCommand extends Command
{
    public const SEED_EMAIL = 'hyperlocal-seed@example.com';

    public const SEED_PASSWORD = 'HyperLocal123!';

    protected $signature = 'hyperlocal:seed
        {--confirm : Required confirmation to write seed data}
        {--fresh : Remove previous seed data before recreating}
        {--seed=42 : Deterministic random seed}';

    protected $description = 'Seed realistic hyperlocal events for Mumbai across all 24 categories with organizers, locations, images, and prices.';

    public function handle(
        CreateAccountHandler $createAccountHandler,
        CreateOrganizerHandler $createOrganizerHandler,
        CreateEventHandler $createEventHandler,
        DeleteEventHandler $deleteEventHandler,
        CreateProductHandler $createProductHandler,
        CreateLocationHandler $createLocationHandler,
        CreateEventImageHandler $createEventImageHandler,
        GenerateOccurrencesFromRuleHandler $generateOccurrencesHandler,
        DatabaseManager $db,
    ): int {
        if (! $this->option('confirm')) {
            $this->error('hyperlocal:seed writes realistic hyperlocal data to '.$db->connection()->getDatabaseName().'.');
            $this->line('Re-run with --confirm to proceed.');

            return self::FAILURE;
        }

        $seed = (int) $this->option('seed');
        mt_srand($seed);
        srand($seed);

        $this->ensureInrCurrencyConfiguration($db);

        $existingUser = User::where('email', self::SEED_EMAIL)->first();

        if ($existingUser !== null && ! $this->option('fresh')) {
            $this->error('Seed account '.self::SEED_EMAIL.' already exists. Re-run with --fresh to wipe and re-seed.');

            return self::FAILURE;
        }

        $user = $existingUser;
        $accountId = null;

        if ($existingUser !== null && $this->option('fresh')) {
            $this->info('Cleaning previous seeded events for '.self::SEED_EMAIL.'...');
            $accountUser = $db->table('account_users')->where('user_id', $existingUser->id)->first();
            if ($accountUser !== null) {
                $accountId = (int) $accountUser->account_id;
                $previousEvents = $db->table('events')
                    ->where('account_id', $accountId)
                    ->whereNull('deleted_at')
                    ->pluck('id');

                foreach ($previousEvents as $eventId) {
                    $deleteEventHandler->handle(new DeleteEventDTO(
                        eventId: (int) $eventId,
                        accountId: $accountId,
                    ));
                }
            }
        }

        if ($user === null) {
            $account = $createAccountHandler->handle(new CreateAccountDTO(
                email: self::SEED_EMAIL,
                password: self::SEED_PASSWORD,
                first_name: 'Hyperlocal',
                locale: 'en',
                last_name: 'Curator',
                timezone: CityConfig::TIMEZONE,
                currency_code: CityConfig::CURRENCY,
            ));

            $accountId = $account->getId();
            $user = User::where('email', self::SEED_EMAIL)->firstOrFail();

            $db->table('accounts')->where('id', $accountId)->update(['account_verified_at' => now()]);
            $db->table('account_users')->where('user_id', $user->id)->update(['role' => 'SUPERADMIN']);
        }

        auth()->login($user);

        $organizerIds = $this->resolveOrganizers(
            $createOrganizerHandler,
            $db,
            $accountId,
            HyperlocalEventData::getOrganizers()
        );

        $eventsData = HyperlocalEventData::getEvents();
        $this->info(sprintf('Seeding %d hyperlocal events for %s...', count($eventsData), CityConfig::CITY));

        $locationCache = [];
        $createdEventsCount = 0;
        $now = CarbonImmutable::now(CityConfig::TIMEZONE);

        $progressBar = $this->output->createProgressBar(count($eventsData));
        $progressBar->start();

        foreach ($eventsData as $eventSpec) {
            $organizerId = $organizerIds[$eventSpec['organizer_index']];

            $eventLocation = null;
            if ($eventSpec['lat'] !== null && $eventSpec['lng'] !== null && $eventSpec['venue_name'] !== null) {
                $cacheKey = $organizerId.'_'.$eventSpec['venue_name'];
                if (! isset($locationCache[$cacheKey])) {
                    $areaKey = $eventSpec['area_key'] ?? '';
                    $area = CityConfig::AREAS[$areaKey] ?? CityConfig::DISTANT_AREAS[$areaKey] ?? [];
                    $postalCode = $area['postal_code'] ?? '400001';

                    $location = $createLocationHandler->handle(new UpsertLocationDTO(
                        organizer_id: $organizerId,
                        account_id: $accountId,
                        name: $eventSpec['venue_name'],
                        structured_address: new AddressDTO(
                            venue_name: $eventSpec['venue_name'],
                            address_line_1: $eventSpec['address'] ?? $eventSpec['venue_name'],
                            address_line_2: null,
                            city: CityConfig::CITY,
                            state_or_region: 'Maharashtra',
                            zip_or_postal_code: $postalCode,
                            country: 'IN',
                        ),
                        latitude: (float) $eventSpec['lat'],
                        longitude: (float) $eventSpec['lng'],
                    ));

                    $locationCache[$cacheKey] = $location->getId();
                }

                $eventLocation = new EventLocationData(
                    type: LocationType::IN_PERSON,
                    location_id: $locationCache[$cacheKey],
                );
            } else {
                $eventLocation = new EventLocationData(
                    type: LocationType::ONLINE,
                    online_event_connection_details: 'https://meet.jit.si/hyperlocal-mumbai-'.($createdEventsCount + 1),
                );
            }

            $isRecurring = (bool) ($eventSpec['is_recurring'] ?? false);
            [$startDate, $endDate] = $this->calculateDates($eventSpec, $now);

            $event = $createEventHandler->handle(new CreateEventDTO(
                title: $eventSpec['title'],
                organizer_id: $organizerId,
                account_id: $accountId,
                user_id: $user->id,
                start_date: $isRecurring ? null : $startDate->toDateTimeString(),
                end_date: $isRecurring ? null : $endDate->toDateTimeString(),
                description: $eventSpec['description'],
                timezone: CityConfig::TIMEZONE,
                currency: CityConfig::CURRENCY,
                category: $eventSpec['category'],
                event_location: $eventLocation,
                status: EventStatus::LIVE->name,
                type: $isRecurring ? EventType::RECURRING : EventType::SINGLE,
            ));

            $eventId = $event->getId();

            if ($isRecurring) {
                $this->generateRecurringOccurrences(
                    $generateOccurrencesHandler,
                    $eventId,
                    $eventSpec,
                    $now
                );
            }

            try {
                $coverFile = HyperlocalImageGenerator::generateCover($eventSpec['category']);
                $createEventImageHandler->handle(new CreateEventImageDTO(
                    eventId: $eventId,
                    accountId: $accountId,
                    image: $coverFile,
                    imageType: ImageType::EVENT_COVER,
                ));
            } catch (\Throwable) {
            }

            $categoryId = (int) $db->table('product_categories')
                ->where('event_id', $eventId)
                ->whereNull('deleted_at')
                ->value('id');

            $priceType = $eventSpec['price_type'];
            $price = (float) $eventSpec['price'];
            $capacity = (int) $eventSpec['capacity'];

            $createProductHandler->handle(new UpsertProductDTO(
                account_id: $accountId,
                event_id: $eventId,
                product_category_id: $categoryId,
                title: $priceType === ProductPriceType::FREE ? 'Free Registration' : 'General Admission',
                type: $priceType,
                product_type: ProductType::TICKET,
                prices: collect([new ProductPriceDTO(price: $price)]),
                initial_quantity_available: $capacity,
            ));

            $createdEventsCount++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->displaySummary($db, $accountId);

        return self::SUCCESS;
    }

    private function resolveOrganizers(
        CreateOrganizerHandler $createOrganizerHandler,
        DatabaseManager $db,
        int $accountId,
        array $organizersSpec,
    ): array {
        $existing = $db->table('organizers')
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('name');

        $organizerIds = [];

        foreach ($organizersSpec as $index => $spec) {
            if ($existing->has($spec['name'])) {
                $organizerIds[$index] = (int) $existing->get($spec['name'])->id;

                continue;
            }

            $created = $createOrganizerHandler->handle(new CreateOrganizerDTO(
                name: $spec['name'],
                email: $spec['email'],
                account_id: $accountId,
                timezone: CityConfig::TIMEZONE,
                currency: CityConfig::CURRENCY,
            ));

            $organizerIds[$index] = $created->getId();
        }

        return $organizerIds;
    }

    private function calculateDates(array $eventSpec, CarbonImmutable $now): array
    {
        $timeType = $eventSpec['time_type'];
        $durationHours = (int) ($eventSpec['duration_hours'] ?? 2);

        $start = match ($timeType) {
            'today_morning' => $now->setTime(9, 0),
            'today_afternoon' => $now->setTime(14, 0),
            'today_evening' => $now->setTime(18, 30),
            'weekend_sat_morning' => ($now->isSaturday() ? $now : $now->next(CarbonImmutable::SATURDAY))->setTime(6, 30),
            'weekend_sat_afternoon' => ($now->isSaturday() ? $now : $now->next(CarbonImmutable::SATURDAY))->setTime(14, 0),
            'weekend_sat_evening' => ($now->isSaturday() ? $now : $now->next(CarbonImmutable::SATURDAY))->setTime(19, 30),
            'weekend_sun_morning' => ($now->isSunday() ? $now : $now->next(CarbonImmutable::SUNDAY))->setTime(7, 0),
            'weekend_sun_afternoon' => ($now->isSunday() ? $now : $now->next(CarbonImmutable::SUNDAY))->setTime(13, 0),
            'weekend_sun_evening' => ($now->isSunday() ? $now : $now->next(CarbonImmutable::SUNDAY))->setTime(18, 0),
            'next_3_weeks', 'weeks_4_to_8', 'past' => $now->addDays((int) $eventSpec['day_offset'])->setTime((int) $eventSpec['hour'], 0),
            default => $now->addDays(7)->setTime(10, 0),
        };

        return [$start, $start->addHours($durationHours)];
    }

    private function generateRecurringOccurrences(
        GenerateOccurrencesFromRuleHandler $generateOccurrencesHandler,
        int $eventId,
        array $eventSpec,
        CarbonImmutable $now,
    ): void {
        $timeType = $eventSpec['time_type'];

        if ($timeType === 'recurring_weekly_sat') {
            $startDate = $now->isSaturday() ? $now : $now->next(CarbonImmutable::SATURDAY);
            $endDate = $startDate->addWeeks(12);

            $generateOccurrencesHandler->handle(new GenerateOccurrencesDTO(
                event_id: $eventId,
                recurrence_rule: [
                    'frequency' => 'weekly',
                    'interval' => 1,
                    'days_of_week' => ['saturday'],
                    'times_of_day' => [
                        ['time' => '07:00', 'label' => 'Morning Practice', 'duration_minutes' => 60],
                    ],
                    'range' => [
                        'type' => 'until',
                        'start' => $startDate->toDateString(),
                        'until' => $endDate->toDateString(),
                    ],
                    'default_capacity' => (int) $eventSpec['capacity'],
                ],
            ));
        } elseif ($timeType === 'recurring_weekly_wed') {
            $startDate = $now->isWednesday() ? $now : $now->next(CarbonImmutable::WEDNESDAY);
            $endDate = $startDate->addWeeks(12);

            $generateOccurrencesHandler->handle(new GenerateOccurrencesDTO(
                event_id: $eventId,
                recurrence_rule: [
                    'frequency' => 'weekly',
                    'interval' => 1,
                    'days_of_week' => ['wednesday'],
                    'times_of_day' => [
                        ['time' => '18:30', 'label' => 'Midweek Circle', 'duration_minutes' => 120],
                    ],
                    'range' => [
                        'type' => 'until',
                        'start' => $startDate->toDateString(),
                        'until' => $endDate->toDateString(),
                    ],
                    'default_capacity' => (int) $eventSpec['capacity'],
                ],
            ));
        }
    }

    private function ensureInrCurrencyConfiguration(DatabaseManager $db): void
    {
        $exists = $db->table('organizer_configurations')
            ->where('default_for_currency', CityConfig::CURRENCY)
            ->whereNull('deleted_at')
            ->exists();

        if (! $exists) {
            $db->table('organizer_configurations')->insert([
                'name' => 'Standard ('.CityConfig::CURRENCY.')',
                'is_system_default' => false,
                'application_fees' => json_encode([
                    'percentage' => 1.25,
                    'fixed' => 20.00,
                    'currency' => CityConfig::CURRENCY,
                ], JSON_THROW_ON_ERROR),
                'default_for_currency' => CityConfig::CURRENCY,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function displaySummary(DatabaseManager $db, int $accountId): void
    {
        $eventCount = $db->table('events')->where('account_id', $accountId)->whereNull('deleted_at')->count();
        $organizerCount = $db->table('organizers')->where('account_id', $accountId)->whereNull('deleted_at')->count();
        $categoriesCount = $db->table('events')->where('account_id', $accountId)->whereNull('deleted_at')->distinct('category')->count('category');
        $freeCount = $db->table('products')
            ->join('events', 'events.id', '=', 'products.event_id')
            ->where('events.account_id', $accountId)
            ->whereNull('events.deleted_at')
            ->where('products.type', ProductPriceType::FREE->name)
            ->whereNull('products.deleted_at')
            ->count();
        $paidCount = $db->table('products')
            ->join('events', 'events.id', '=', 'products.event_id')
            ->where('events.account_id', $accountId)
            ->whereNull('events.deleted_at')
            ->where('products.type', ProductPriceType::PAID->name)
            ->whereNull('products.deleted_at')
            ->count();

        $this->info('Hyperlocal event seed completed successfully!');
        $this->table(
            ['Metric', 'Value'],
            [
                ['City', CityConfig::CITY],
                ['Timezone', CityConfig::TIMEZONE],
                ['Currency', CityConfig::CURRENCY],
                ['Seed Account', self::SEED_EMAIL],
                ['Password', self::SEED_PASSWORD],
                ['Organizers Created', (string) $organizerCount],
                ['Total Events Seeded', (string) $eventCount],
                ['Categories Covered', (string) $categoriesCount.' / 24'],
                ['Free Events', (string) $freeCount],
                ['Paid Events', (string) $paidCount],
            ]
        );
    }
}
