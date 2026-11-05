# What We Added (Project Changelog & Origin)

This project is built on the open-source **[Hi.Events](https://github.com/HiEventsDev/Hi.Events)** event management and ticketing platform (licensed under AGPL-3, upstream version `2.0.0-rc.3`, base commit `f416478`).

Hi.Events provides a self-hosted event management backend (Laravel 13) and frontend (React 19 SSR) tailored for individual event ticketing and organizer administration. It does not include an attendee-facing discovery feed, location-aware queries, interest personalization, interactive maps, or attendee community engagement.

Our hackathon project, **Hyperlocal Event Discovery & Community Engagement**, extends the foundation into a community-centric event discovery ecosystem.

---

## 1. Feature Comparison: Hi.Events Base vs. What We Added

| Problem Statement Area | Hi.Events Provides | What We Added / Are Adding |
|---|---|---|
| **Location-Aware Discovery** | Static `locations` table storing venue address and lat/lng per event. No radius queries, no nearby feeds, and no distance calculations. | Public `GET /api/events/discover` API with Haversine distance calculations, radius filters (`0.5–100 km`), sorting by distance, and map views. |
| **Interest-Based Personalisation** | 24-case `EventCategory` enum assigned to events. No attendee interest tagging, preference profiles, or category recommendations. | Multi-category filtering on discovery feeds, category-weighted recommendations, and attendee interest-matching feeds. |
| **Easy Event Posting** | Organizer dashboard for event creation with tickets, recurring occurrences, promo codes, and seating maps. | Streamlined quick-post flows for community organizers, automated location geocoding, and gradient cover image generation. |
| **Community Engagement** | Email-only order confirmations, ticket PDF downloads, and door check-in lists. No public attendee profiles, followings, or discussion circles. | Organizer follow channels, local community circles, attendee RSVP activity streams, and event reminders. |
| **Organizer Reach & Analytics** | Ticket sales volume, gross revenue, order exports, and door check-in statistics per organizer. | Hyperlocal attendee demographics, distance-from-venue reach analytics, and interest engagement metrics. |

---

## 2. Phase Log

### Phase 0: Foundation, Baseline & Discovery Contract (Current)
- **Environment Setup & Dev Stack:**
  - Automated local development workflow across macOS, Linux, and Windows using Docker Compose (`docker/development/`).
  - Documented complete onboarding, service ports, default credentials, and troubleshooting in [`docs/hackathon/SETUP.md`](file:///docs/hackathon/SETUP.md).
  - Established pre-existing test baselines (Unit test suite: 184 passing; Pint: clean pass; TypeScript: 1 pre-existing type error documented).
- **Realistic Seed Data (Mumbai):**
  - Created `php artisan hyperlocal:seed {--confirm} {--fresh} {--seed=42}` command in [`backend/app/Console/Commands/SeedHyperlocalEventsCommand.php`](file:///backend/app/Console/Commands/SeedHyperlocalEventsCommand.php).
  - Seeded 53 realistic events across **all 24 `EventCategory` enum values** under 8 diverse community organizers in Mumbai.
  - Implemented local GD gradient cover image generation (no external web dependencies).
  - Configured relative timestamps: 3 today, 6 this weekend, 28 next 3 weeks, 8 in weeks 4–8, 6 past events, and 2 recurring weekly schedules (26 occurrences).
  - Configured coordinate spreads: 46 local venues (<= 18 km), 4 outer MMR venues (30–51 km: Kalyan, Kharghar/Panvel, Virar, Karjat), and 3 online events (null coordinates).
  - Validated with unit test [`backend/tests/Unit/Console/Hyperlocal/SeedDataDefinitionTest.php`](file:///backend/tests/Unit/Console/Hyperlocal/SeedDataDefinitionTest.php) (6 tests, 939 assertions).
  - Documented verification queries and API proof in [`docs/hackathon/SEED_DATA.md`](file:///docs/hackathon/SEED_DATA.md).
- **Architecture Mapping & API Contract:**
  - Mapped public API route flows, spatial storage in `locations`, auth/registration models, frontend routing, and check-in QR scanner in [`docs/hackathon/CODEBASE_MAP.md`](file:///docs/hackathon/CODEBASE_MAP.md).
  - Drafted Phase 1 public contract for `GET /api/events/discover` in [`docs/hackathon/API_CONTRACT.md`](file:///docs/hackathon/API_CONTRACT.md).
  - Established team conventions, folder ownership, commit standards, and licensing rules in [`docs/hackathon/TEAM_WORKFLOW.md`](file:///docs/hackathon/TEAM_WORKFLOW.md).

---

### Phase 1: Nearby Feed, Geolocation & Map View (Upcoming)
- *To be filled in Phase 1.*

---

### Phase 2: Personalisation, Interest Profiles & Filters (Upcoming)
- *To be filled in Phase 2.*

---

### Phase 3: Community Engagement, Follows & Organizer Insights (Upcoming)
- *To be filled in Phase 3.*
