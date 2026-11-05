<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Hyperlocal;

use HiEvents\Console\Commands\Hyperlocal\CityConfig;
use HiEvents\Console\Commands\Hyperlocal\HyperlocalEventData;
use HiEvents\DomainObjects\Enums\EventCategory;
use HiEvents\DomainObjects\Enums\ProductPriceType;
use Tests\TestCase;

class SeedDataDefinitionTest extends TestCase
{
    public function test_all_24_event_categories_are_represented(): void
    {
        $events = HyperlocalEventData::getEvents();
        $categories = array_map(fn ($e) => $e['category']->value, $events);
        $uniqueCategories = array_unique($categories);

        $allEnumValues = array_map(fn (EventCategory $c) => $c->value, EventCategory::cases());

        $this->assertCount(24, $allEnumValues);
        $this->assertCount(24, $uniqueCategories);
        $this->assertEqualsCanonicalizing($allEnumValues, array_values($uniqueCategories));
    }

    public function test_every_non_online_venue_has_valid_coordinates_inside_mumbai_bounds(): void
    {
        $events = HyperlocalEventData::getEvents();
        $onlineCount = 0;
        $distantCount = 0;
        $localCount = 0;

        foreach ($events as $event) {
            if ($event['lat'] === null && $event['lng'] === null) {
                $onlineCount++;
                $this->assertNull($event['venue_name']);
                $this->assertNull($event['address']);

                continue;
            }

            $this->assertNotNull($event['lat']);
            $this->assertNotNull($event['lng']);
            $this->assertNotEmpty($event['venue_name']);
            $this->assertNotEmpty($event['address']);

            $lat = (float) $event['lat'];
            $lng = (float) $event['lng'];

            $this->assertGreaterThanOrEqual(18.5, $lat);
            $this->assertLessThanOrEqual(19.8, $lat);
            $this->assertGreaterThanOrEqual(72.5, $lng);
            $this->assertLessThanOrEqual(73.6, $lng);

            $distanceFromCenter = CityConfig::distanceKm(
                CityConfig::CENTER_LAT,
                CityConfig::CENTER_LNG,
                $lat,
                $lng
            );

            if ($distanceFromCenter > 25.0) {
                $distantCount++;
                $this->assertLessThanOrEqual(65.0, $distanceFromCenter);
            } else {
                $localCount++;
                $this->assertLessThanOrEqual(22.0, $distanceFromCenter);
            }
        }

        $this->assertSame(3, $onlineCount);
        $this->assertSame(4, $distantCount);
        $this->assertSame(46, $localCount);
        $this->assertCount(53, $events);
    }

    public function test_no_duplicate_event_titles(): void
    {
        $events = HyperlocalEventData::getEvents();
        $titles = array_column($events, 'title');

        $this->assertCount(53, $titles);
        $this->assertCount(count($titles), array_unique($titles));
    }

    public function test_price_free_ratio_is_balanced(): void
    {
        $events = HyperlocalEventData::getEvents();
        $total = count($events);
        $freeCount = 0;
        $paidCount = 0;

        foreach ($events as $event) {
            $priceType = $event['price_type'];
            $price = (float) $event['price'];

            if ($priceType === ProductPriceType::FREE) {
                $freeCount++;
                $this->assertSame(0.0, $price);
            } else {
                $paidCount++;
                $this->assertSame(ProductPriceType::PAID, $priceType);
                $this->assertGreaterThanOrEqual(99.0, $price);
                $this->assertLessThanOrEqual(1500.0, $price);
            }
        }

        $freeRatio = $freeCount / (float) $total;

        $this->assertSame(22, $freeCount);
        $this->assertSame(31, $paidCount);
        $this->assertGreaterThanOrEqual(0.38, $freeRatio);
        $this->assertLessThanOrEqual(0.44, $freeRatio);
    }

    public function test_organizers_have_distinct_types_and_valid_attributes(): void
    {
        $organizers = HyperlocalEventData::getOrganizers();

        $this->assertCount(8, $organizers);

        $names = [];
        $emails = [];

        foreach ($organizers as $org) {
            $this->assertNotEmpty($org['name']);
            $this->assertNotEmpty($org['description']);
            $this->assertNotEmpty($org['email']);
            $this->assertStringContainsString('@', $org['email']);

            $names[] = $org['name'];
            $emails[] = $org['email'];
        }

        $this->assertCount(8, array_unique($names));
        $this->assertCount(8, array_unique($emails));
    }

    public function test_time_spread_distribution_matches_requirements(): void
    {
        $events = HyperlocalEventData::getEvents();

        $todayCount = 0;
        $weekendCount = 0;
        $recurringCount = 0;
        $next3WeeksCount = 0;
        $weeks4To8Count = 0;
        $pastCount = 0;

        foreach ($events as $event) {
            $timeType = $event['time_type'];

            if (str_starts_with($timeType, 'today_')) {
                $todayCount++;
            } elseif (str_starts_with($timeType, 'weekend_')) {
                $weekendCount++;
            } elseif (str_starts_with($timeType, 'recurring_')) {
                $recurringCount++;
            } elseif ($timeType === 'next_3_weeks') {
                $next3WeeksCount++;
            } elseif ($timeType === 'weeks_4_to_8') {
                $weeks4To8Count++;
            } elseif ($timeType === 'past') {
                $pastCount++;
            }
        }

        $this->assertSame(3, $todayCount);
        $this->assertSame(6, $weekendCount);
        $this->assertSame(2, $recurringCount);
        $this->assertSame(28, $next3WeeksCount);
        $this->assertSame(8, $weeks4To8Count);
        $this->assertSame(6, $pastCount);
    }
}
