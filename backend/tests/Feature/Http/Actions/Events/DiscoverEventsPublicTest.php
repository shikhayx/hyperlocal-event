<?php

namespace Tests\Feature\Http\Actions\Events;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\EventCategory;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrganizerStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class DiscoverEventsPublicTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    public function test_validation_errors(): void
    {
        $this->getJson('/public/events/discover?lat=19.0596')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lng']);

        $this->getJson('/public/events/discover?lng=72.8295')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat']);

        $this->getJson('/public/events/discover?lat=19.0596&lng=72.8295&radius_km=150')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['radius_km']);

        $this->getJson('/public/events/discover?lat=19.0596&lng=72.8295&radius_km=0.1')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['radius_km']);

        $this->getJson('/public/events/discover?sort=distance')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sort']);

        $this->getJson('/public/events/discover?when=today&date_from=2026-11-06')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['when', 'date_from']);

        $this->getJson('/public/events/discover?categories[]=INVALID_CATEGORY')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['categories.0']);
    }

    public function test_discover_events_structure_and_fields(): void
    {
        [$user, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId, 'Tech Club');
        DB::table('organizers')->where('id', $organizerId)->update(['status' => OrganizerStatus::LIVE->name]);

        $locationId = DB::table('locations')->insertGetId([
            'account_id' => $accountId,
            'organizer_id' => $organizerId,
            'name' => 'Bandra Kurla Complex',
            'latitude' => 19.0596,
            'longitude' => 72.8295,
            'structured_address' => json_encode(['city' => 'Mumbai']),
            'short_id' => 'loc_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eventId = DB::table('events')->insertGetId([
            'title' => 'Hands-on Rust Workshop',
            'account_id' => $accountId,
            'user_id' => $user->id,
            'organizer_id' => $organizerId,
            'category' => EventCategory::TECH->value,
            'description' => '<p>Learn systems programming in Rust.</p>',
            'status' => EventStatus::LIVE->name,
            'type' => 'SINGLE',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'short_id' => 'ev_rust_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eventLocationId = DB::table('event_locations')->insertGetId([
            'event_id' => $eventId,
            'location_id' => $locationId,
            'type' => 'IN_PERSON',
            'short_id' => 'eloc_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('events')->where('id', $eventId)->update(['event_location_id' => $eventLocationId]);

        DB::table('event_occurrences')->insert([
            'event_id' => $eventId,
            'start_date' => Carbon::now()->addDays(2)->toIso8601String(),
            'end_date' => Carbon::now()->addDays(2)->addHours(3)->toIso8601String(),
            'status' => 'ACTIVE',
            'short_id' => 'occ_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('products')->insertGetId([
            'event_id' => $eventId,
            'title' => 'General Admission',
            'is_hidden' => false,
            'is_hidden_without_promo_code' => false,
            'is_addon_only' => false,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_prices')->insert([
            'product_id' => $productId,
            'price' => 499.00,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/public/events/discover?lat=19.0596&lng=72.8295&radius_km=10')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'short_id',
                        'slug',
                        'title',
                        'category',
                        'description_preview',
                        'type',
                        'start_date',
                        'end_date',
                        'next_start_date',
                        'timezone',
                        'is_free',
                        'min_price',
                        'currency',
                        'cover_image_url',
                        'organizer' => ['id', 'name'],
                        'is_online',
                        'location' => ['venue_name', 'city', 'latitude', 'longitude'],
                        'distance_km',
                        'url',
                    ],
                ],
                'links',
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'per_page',
                    'to',
                    'total',
                ],
            ]);

        $item = collect($response->json('data'))->firstWhere('id', $eventId);
        $this->assertNotNull($item);
        $this->assertSame('Hands-on Rust Workshop', $item['title']);
        $this->assertSame('TECH', $item['category']);
        $this->assertSame('SINGLE', $item['type']);
        $this->assertFalse($item['is_free']);
        $this->assertEquals(499.0, $item['min_price']);
        $this->assertSame('INR', $item['currency']);
        $this->assertFalse($item['is_online']);
        $this->assertNotNull($item['location']);
        $this->assertSame('Bandra Kurla Complex', $item['location']['venue_name']);
        $this->assertSame('Mumbai', $item['location']['city']);
        $this->assertEquals(0.0, $item['distance_km']);
    }

    public function test_geolocation_radius_and_online_filtering(): void
    {
        [$user, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId, 'Geo Test Organizer');
        DB::table('organizers')->where('id', $organizerId)->update(['status' => OrganizerStatus::LIVE->name]);

        $bandraLocId = DB::table('locations')->insertGetId([
            'account_id' => $accountId,
            'organizer_id' => $organizerId,
            'name' => 'Bandra Venue',
            'latitude' => 19.0596,
            'longitude' => 72.8295,
            'short_id' => 'loc_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $nearEventId = DB::table('events')->insertGetId([
            'title' => 'Near Bandra Event',
            'account_id' => $accountId,
            'user_id' => $user->id,
            'organizer_id' => $organizerId,
            'status' => EventStatus::LIVE->name,
            'category' => EventCategory::TECH->value,
            'short_id' => 'ev_near_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $bandraEventLocId = DB::table('event_locations')->insertGetId([
            'event_id' => $nearEventId,
            'location_id' => $bandraLocId,
            'type' => 'IN_PERSON',
            'short_id' => 'eloc_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('events')->where('id', $nearEventId)->update(['event_location_id' => $bandraEventLocId]);
        DB::table('event_occurrences')->insert([
            'event_id' => $nearEventId,
            'start_date' => Carbon::now()->addDays(1)->toIso8601String(),
            'status' => 'ACTIVE',
            'short_id' => 'occ_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $distantLocId = DB::table('locations')->insertGetId([
            'account_id' => $accountId,
            'organizer_id' => $organizerId,
            'name' => 'Distant Kharghar Venue',
            'latitude' => 18.9300,
            'longitude' => 73.1200,
            'short_id' => 'loc_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $distantEventId = DB::table('events')->insertGetId([
            'title' => 'Distant Kharghar Event',
            'account_id' => $accountId,
            'user_id' => $user->id,
            'organizer_id' => $organizerId,
            'status' => EventStatus::LIVE->name,
            'category' => EventCategory::TECH->value,
            'short_id' => 'ev_dist_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $distantEventLocId = DB::table('event_locations')->insertGetId([
            'event_id' => $distantEventId,
            'location_id' => $distantLocId,
            'type' => 'IN_PERSON',
            'short_id' => 'eloc_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('events')->where('id', $distantEventId)->update(['event_location_id' => $distantEventLocId]);
        DB::table('event_occurrences')->insert([
            'event_id' => $distantEventId,
            'start_date' => Carbon::now()->addDays(1)->toIso8601String(),
            'status' => 'ACTIVE',
            'short_id' => 'occ_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $onlineEventId = DB::table('events')->insertGetId([
            'title' => 'Online Webinar',
            'account_id' => $accountId,
            'user_id' => $user->id,
            'organizer_id' => $organizerId,
            'status' => EventStatus::LIVE->name,
            'category' => EventCategory::TECH->value,
            'short_id' => 'ev_online_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $onlineEventLocId = DB::table('event_locations')->insertGetId([
            'event_id' => $onlineEventId,
            'type' => 'ONLINE',
            'short_id' => 'eloc_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('events')->where('id', $onlineEventId)->update(['event_location_id' => $onlineEventLocId]);
        DB::table('event_occurrences')->insert([
            'event_id' => $onlineEventId,
            'start_date' => Carbon::now()->addDays(1)->toIso8601String(),
            'status' => 'ACTIVE',
            'short_id' => 'occ_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $idsRadius10 = $this->getJson('/public/events/discover?lat=19.0596&lng=72.8295&radius_km=10')
            ->assertOk()
            ->json('data.*.id');

        $this->assertContains($nearEventId, $idsRadius10);
        $this->assertNotContains($distantEventId, $idsRadius10);
        $this->assertNotContains($onlineEventId, $idsRadius10);

        $idsWithOnline = $this->getJson('/public/events/discover?lat=19.0596&lng=72.8295&radius_km=10&include_online=true')
            ->assertOk()
            ->json('data.*.id');

        $this->assertContains($nearEventId, $idsWithOnline);
        $this->assertContains($onlineEventId, $idsWithOnline);
        $this->assertNotContains($distantEventId, $idsWithOnline);

        $idsWithoutCoords = $this->getJson('/public/events/discover')
            ->assertOk()
            ->json('data.*.id');

        $this->assertContains($nearEventId, $idsWithoutCoords);
        $this->assertContains($distantEventId, $idsWithoutCoords);
        $this->assertContains($onlineEventId, $idsWithoutCoords);
    }

    public function test_category_and_free_and_search_filters(): void
    {
        [$user, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId, 'Filter Organizer');
        DB::table('organizers')->where('id', $organizerId)->update(['status' => OrganizerStatus::LIVE->name]);

        $freeTechEventId = DB::table('events')->insertGetId([
            'title' => 'Free Python Hackathon',
            'account_id' => $accountId,
            'user_id' => $user->id,
            'organizer_id' => $organizerId,
            'status' => EventStatus::LIVE->name,
            'category' => EventCategory::TECH->value,
            'short_id' => 'ev_free_tech_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_occurrences')->insert([
            'event_id' => $freeTechEventId,
            'start_date' => Carbon::now()->addDays(1)->toIso8601String(),
            'status' => 'ACTIVE',
            'short_id' => 'occ_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $freeProdId = DB::table('products')->insertGetId([
            'event_id' => $freeTechEventId,
            'title' => 'Free RSVP',
            'is_hidden' => false,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_prices')->insert([
            'product_id' => $freeProdId,
            'price' => 0.00,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paidMusicEventId = DB::table('events')->insertGetId([
            'title' => 'Acoustic Guitar Night',
            'account_id' => $accountId,
            'user_id' => $user->id,
            'organizer_id' => $organizerId,
            'status' => EventStatus::LIVE->name,
            'category' => EventCategory::MUSIC->value,
            'short_id' => 'ev_paid_mus_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_occurrences')->insert([
            'event_id' => $paidMusicEventId,
            'start_date' => Carbon::now()->addDays(2)->toIso8601String(),
            'status' => 'ACTIVE',
            'short_id' => 'occ_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $paidProdId = DB::table('products')->insertGetId([
            'event_id' => $paidMusicEventId,
            'title' => 'Entry Pass',
            'is_hidden' => false,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_prices')->insert([
            'product_id' => $paidProdId,
            'price' => 750.00,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $techOnly = $this->getJson('/public/events/discover?categories[]=TECH')
            ->assertOk()
            ->json('data.*.id');
        $this->assertContains($freeTechEventId, $techOnly);
        $this->assertNotContains($paidMusicEventId, $techOnly);

        $freeOnly = $this->getJson('/public/events/discover?free=true')
            ->assertOk()
            ->json('data.*.id');
        $this->assertContains($freeTechEventId, $freeOnly);
        $this->assertNotContains($paidMusicEventId, $freeOnly);

        $paidOnly = $this->getJson('/public/events/discover?free=false')
            ->assertOk()
            ->json('data.*.id');
        $this->assertNotContains($freeTechEventId, $paidOnly);
        $this->assertContains($paidMusicEventId, $paidOnly);

        $searchMatch = $this->getJson('/public/events/discover?q=Guitar')
            ->assertOk()
            ->json('data.*.id');
        $this->assertContains($paidMusicEventId, $searchMatch);
        $this->assertNotContains($freeTechEventId, $searchMatch);
    }
}
