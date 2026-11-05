# Hyperlocal Seed Data (Mumbai)

Realistic seed data generator for the **Hyperlocal Event Discovery & Community Engagement** hackathon project.

This Artisan command fills the database with 53 believable local events across **all 24 event categories**, 8 distinct community organizers, multiple time buckets (today, this weekend, next 3 weeks, weeks 4–8, past 30 days, and weekly recurring), realistic pricing in INR, and coordinates spanning Mumbai neighbourhoods, outer MMR hubs (30–51 km), and online sessions.

---

## 1. City Configuration: Mumbai

Defined in [`backend/app/Console/Commands/Hyperlocal/CityConfig.php`](file:///backend/app/Console/Commands/Hyperlocal/CityConfig.php):

| Setting | Value |
| --- | --- |
| **City** | `Mumbai` |
| **Center Coordinates** | `19.0760, 72.8777` (BKC / Kurla geographic center) |
| **Timezone** | `Asia/Kolkata` (IST) |
| **Currency** | `INR` (₹) |

### Areas & Coordinate Distribution

1. **Local Neighbourhoods (Within 18 km of Center):**
   - **Bandra West** (`19.0596, 72.8295`) - Joggers Park, Bandstand, Pali Hill
   - **BKC** (`19.0688, 72.8703`) - WeWork Enam Sambhav, Jio World Garden, Innovation Arena
   - **Lower Parel** (`19.0016, 72.8302`) - Phoenix Palladium, Todi Mill Social, High Street Phoenix
   - **Andheri West** (`19.1136, 72.8292`) - Lokhandwala, The Habitat Comedy Club, Toit Tasting Cellar
   - **Powai** (`19.1176, 72.9060`) - Hiranandani Gardens, IIT Bombay Innovation Hall
   - **Dadar** (`19.0178, 72.8478`) - Shivaji Park, Aaswad Sweets, Portuguese Church
   - **Juhu** (`19.1075, 72.8263`) - Juhu Beach Shoreline, Prithvi Theatre Garden, Soho House
   - **Colaba** (`18.9220, 72.8346`) - Gateway of India Promenade, NCPA Experimental Theatre
   - **Ghatkopar** (`19.0860, 72.9090`) - Vallabh Baug Lane, R Odeon Mall Complex
   - **Goregaon East** (`19.1663, 72.8593`) - Aarey Colony Green Nursery, NESCO Center

2. **Distant MMR Locations (30–51 km away, to test radius filtering):**
   - **Kalyan West** (`19.2437, 73.1355`, 32.88 km) - Durgadi Fort & Ulhas River Promontory
   - **Kharghar & Panvel Hills** (`18.9300, 73.1200`, 30.21 km) - Panvel-Kharghar Foothills Trailhead
   - **Virar Coastal Edge** (`19.4674, 72.8041`, 44.20 km) - Arnala Beach Coastal Reserve
   - **Karjat Valley** (`18.9100, 73.3280`, 50.82 km) - Karjat Foothills Astronomy Campground

3. **Online Events (Null Coordinates):**
   - 3 virtual events without physical venue or coordinates (`lat = null, lng = null`) to verify null-handling in maps and radius filters.

---

## 2. Artisan Command

```bash
# Development container execution (from docker/development):
docker compose -f docker-compose.dev.yml exec backend php artisan hyperlocal:seed --confirm

# Or directly in backend container:
docker exec backend php artisan hyperlocal:seed --confirm

# Wipe and re-seed:
docker exec backend php artisan hyperlocal:seed --confirm --fresh

# Custom random seed (default: 42):
docker exec backend php artisan hyperlocal:seed --confirm --fresh --seed=1337
```

### Safety Guarantees & CLI Flags

- **`--confirm` (Required):** Refuses execution without confirmation to prevent accidental writes.
- **`--fresh` (Idempotent cleanup):** If `hyperlocal-seed@example.com` already exists, refuses to run unless `--fresh` is passed. When `--fresh` is supplied, cleanly deletes all previously seeded events via domain `DeleteEventHandler` before re-seeding.
- **`--seed=42` (Deterministic):** Seeds PHP's pseudorandom generator so outputs, image gradients, and data structures are 100% reproducible across machines.
- **Relative Timestamps:** All dates are computed relative to `now('Asia/Kolkata')` at execution time so events are never stale or expired in local demos.

---

## 3. Seed Account & 8 Community Organizers

### Seed Account Credentials
- **Email:** `hyperlocal-seed@example.com`
- **Password:** `HyperLocal123!`
- **Role:** Superadmin (account verified upon creation, publicly visible)

### The 8 Community Organizers

| ID | Organizer Name | Persona / Description | Focus Categories |
|---|---|---|---|
| 3 | **VJTI Coding Club & ACM Student Chapter** | Student-led engineering & developer society | `TECH`, `WORKSHOP` |
| 4 | **Mumbai Tech Founders & Ventures** | Early-stage founders, angel syndicates, venture circles | `TECH`, `BUSINESS`, `EDUCATION` |
| 5 | **Bombay Indie Music Scene** | Live music collective, acoustic rooftops, jam circles | `MUSIC`, `THEATER`, `FESTIVAL`, `NIGHTLIFE` |
| 6 | **Marine Drive Runners & Turf League** | Community fitness club, seafront runs, box cricket, turf soccer | `SPORTS`, `OUTDOORS` |
| 7 | **Bandra Culinary Guild & Supper Club** | Home chefs, artisan bakers, fermenters, street food walks | `FOOD_DRINK`, `WORKSHOP`, `OTHER` |
| 8 | **Prana Yoga & Mindfulness Space** | Sunrise Ashtanga, sound baths, breathwork circles | `WELLNESS`, `SPIRITUALITY`, `WORKSHOP` |
| 9 | **Clean Shores Mumbai & Youth Volunteers** | Coastal cleanups, mangrove eco-tours, climate activism | `CHARITY`, `OUTDOORS`, `FAMILY`, `SEASONAL` |
| 10 | **Bombay UX & Product Design Circle** | Product designers, UX researchers, portfolio critiques | `WORKSHOP`, `EDUCATION`, `ART` |

---

## 4. Distribution & Metrics

### Event Counts by Category (All 24 Enums Covered)

| Category | Count | Example Event Title |
|---|---|---|
| `TECH` | 6 | *Applied Generative AI Architecture for SaaS Founders* |
| `WORKSHOP` | 6 | *Hands-On Full-Stack Rust & WebAssembly Workshop* |
| `SPORTS` | 5 | *Marine Drive Seafront 10K & Sunrise Stride* |
| `FOOD_DRINK` | 4 | *Dadar Heritage Culinary Walk & Tasting* |
| `MUSIC` | 3 | *Bandra Sunset Rooftop Acoustic Showcase* |
| `EDUCATION` | 3 | *Distributed Database Consensus & Raft Architecture* |
| `BUSINESS` | 3 | *Angel Syndicate Pitch Night & Mumbai Founders Mixer* |
| `OUTDOORS` | 3 | *Sanjay Gandhi National Park Biodiversity Birding Trail* |
| `HOBBIES` | 2 | *Mumbai Specialty Coffee Brewing & Cupping Lab* |
| `SOCIAL` | 2 | *Bombay Board Games & Analog Strategy Social* |
| `WELLNESS` | 2 | *Prana Sunrise Vinyasa & Prānāyāma Community Flow* |
| `CHARITY` | 2 | *Versova Beach Mangrove Cleanup & Eco Restoration* |
| `NIGHTLIFE` | 1 | *Deep Minimal Techno & Audio-Visual Rooftop Gathering* |
| `OTHER` | 1 | *Craft Beer Brewing Science & Hop Tasting Workshop* |
| `SEASONAL` | 1 | *Coastal Monsoon Photography Walk & Chhatrapati Shivaji Terminus* |
| `FAMILY` | 1 | *Kala Ghoda Heritage Architecture Sketching Walk* |
| `THEATER` | 1 | *Prithvi Theatre Garden Stage: New Marathi Play Readings* |
| `ART` | 1 | *Bandra Street Art Walking Tour & Graffiti Stencil Jam* |
| `SPIRITUALITY` | 1 | *Himalayan Singing Bowl Sound Bath & Kundalini Meditation* |
| `TOURS` | 1 | *Chhatrapati Shivaji Maharaj Vastu Sangrahalaya Nocturnal Tour* |
| `DANCE` | 1 | *Juhu Sunset Ecstatic Dance & Ocean Percussion Circle* |
| `FESTIVAL` | 1 | *Mahim Koliwada Seafood & Folk Culture Evening* |
| `FILM` | 1 | *Matunga Single-Screen 35mm Cinema Appreciation Night* |
| `COMEDY` | 1 | *Bandra Underground Standup Comedy: New Material Night* |
| **Total** | **53** | |

### Time Spread

| Time Bucket | Events | Notes |
|---|---|---|
| **Today** | 3 | 1 morning (09:00), 1 afternoon (14:00), 1 evening (18:30) |
| **This Weekend** | 6 | 3 Saturday (morning, afternoon, evening), 3 Sunday (morning, afternoon, evening) |
| **Next 3 Weeks** | 28 | Days +1 to +21; includes 25 local events and 3 virtual online events |
| **Weeks 4 to 8** | 8 | Days +28 to +50; includes 4 distant MMR events (30–51 km) |
| **Past 30 Days** | 6 | Days -4, -8, -14, -19, -23, -28 (for history and attendee tracking) |
| **Recurring (Weekly)** | 2 | 26 total occurrences generated across a 12-week schedule |

### Pricing Mix

- **Free Events:** 22 events (41.5%, target ~40%)
- **Paid Events:** 31 events (58.5%)
- **Price Range:** ₹99.00 to ₹1,499.00 INR with sensible capacities (20 to 250 attendees)

---

## 5. Verification Evidence

### 1. Database Verification Query

Run via PostgreSQL in container:

```bash
docker exec development-pgsql-1 psql -U username -d backend -c "
SELECT category, COUNT(*) as count 
FROM events 
WHERE deleted_at IS NULL AND account_id = 3 
GROUP BY category 
ORDER BY count DESC;"
```

**Output:**
```
   category   | count 
--------------+-------
 TECH         |     6
 WORKSHOP     |     6
 SPORTS       |     5
 FOOD_DRINK   |     4
 MUSIC        |     3
 EDUCATION    |     3
 BUSINESS     |     3
 OUTDOORS     |     3
 HOBBIES      |     2
 SOCIAL       |     2
 WELLNESS     |     2
 CHARITY      |     2
 NIGHTLIFE    |     1
 OTHER        |     1
 SEASONAL     |     1
 FAMILY       |     1
 THEATER      |     1
 ART          |     1
 SPIRITUALITY |     1
 TOURS        |     1
 DANCE        |     1
 FESTIVAL     |     1
 FILM         |     1
 COMEDY       |     1
(24 rows)
```

### 2. API Verification (`GET /organizers/{id}/events`)

```bash
TOKEN=$(docker exec backend php -r "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo app(HiEvents\Services\Domain\Auth\LoginService::class)->authenticate('hyperlocal-seed@example.com', 'HyperLocal123!', null)->token;")

docker exec backend curl -sk https://nginx/api/organizers/3/events -H "Authorization: Bearer $TOKEN"
```

**Response (HTTP 200 OK):**
```json
{
  "data": [
    {
      "id": 153,
      "title": "Mumbai FinTech & Web3 Builders Hackathon Finale",
      "category": "TECH",
      "status": "LIVE",
      "currency": "INR",
      "timezone": "Asia/Kolkata",
      "images": [
        {
          "url": "http://localhost:9000/hievents-public/event_cover/cover-tech-ZnHR7.jpg",
          "type": "EVENT_COVER"
        }
      ],
      "event_location": {
        "type": "IN_PERSON",
        "location": {
          "name": "BKC Innovation Arena",
          "latitude": 19.0688,
          "longitude": 72.8703
        }
      }
    }
  ]
}
```

### 3. Public Event API Verification (`GET /api/public/events/{id}`)

```bash
docker exec backend curl -sk https://nginx/api/public/events/114
```

**Response (HTTP 200 OK):**
```json
{
  "data": {
    "id": 114,
    "title": "Hands-On Full-Stack Rust & WebAssembly Workshop",
    "category": "WORKSHOP",
    "status": "LIVE",
    "currency": "INR",
    "event_location": {
      "type": "IN_PERSON",
      "location": {
        "name": "IIT Bombay Innovation Hall",
        "latitude": 19.1176,
        "longitude": 72.906
      }
    },
    "product_categories": [
      {
        "name": "Registration",
        "products": [
          {
            "title": "General Admission",
            "type": "PAID",
            "price": 599
          }
        ]
      }
    ]
  }
}
```

### 4. Unit Test Suite Pass

```bash
docker exec backend php -d memory_limit=1G vendor/bin/phpunit --no-coverage --filter=SeedDataDefinitionTest
```

**Output:**
```
PHPUnit 12.5.40 by Sebastian Bergmann and contributors.
......                                                              6 / 6 (100%)
Time: 00:13.175, Memory: 42.00 MB
OK (6 tests, 939 assertions)
```

### 5. Laravel Pint Code Style Pass

```bash
docker exec backend ./vendor/bin/pint --test app/Console/Commands/SeedHyperlocalEventsCommand.php app/Console/Commands/Hyperlocal tests/Unit/Console/Hyperlocal
```

**Output:**
```
PASS ........................................................... 5 files
```
