# Codebase Architecture Map (Hi.Events Base)

This document maps the architectural flow of the open-source Hi.Events codebase to guide where our Hyperlocal Event Discovery & Community Engagement features will plug in during Phase 1 and beyond.

---

## 1. Public API Route Flow

### Route Definition
- **File:** [`backend/routes/api.php`](file:///backend/routes/api.php#L930-L935)
- Public routes live in the unauthenticated group wrapped with CORS and rate-limiting middleware.
- The public organizer events endpoint is registered as:
  ```php
  $router->get('/organizers/{organizer_id}/events', GetOrganizerEventsPublicAction::class)
      ->middleware('cache.guest:10,page,per_page,sort_by,sort_direction,query,filter_fields,eventsStatus');
  ```

### Controller / Action Layer
- **File:** [`backend/app/Http/Actions/Events/GetOrganizerEventsPublicAction.php`](file:///backend/app/Http/Actions/Events/GetOrganizerEventsPublicAction.php)
- Extends `BaseAction`, extracts pagination query params via `$this->getPaginationQueryParams($request)`, and instantiates `GetPublicOrganizerEventsDTO`.
- Delegates execution directly to `GetPublicEventsHandler` and formats the response through `EventResourcePublic::class` via `$this->resourceResponse()`.

### Handler & Domain Service Layer
- **File:** [`backend/app/Services/Application/Handlers/Event/GetPublicEventsHandler.php`](file:///backend/app/Services/Application/Handlers/Event/GetPublicEventsHandler.php)
- Injected with `EventRepositoryInterface`, `OrganizerRepositoryInterface`, and product/seating lookup services.
- Eager-loads required relations using `Relationship` specifications (`EventLocationDomainObject`, `LocationDomainObject`, `EventOccurrenceDomainObject`, `ProductCategoryDomainObject`, `ImageDomainObject`).
- Queries public live events (`status = 'LIVE'`) through `$query->findEvents()`, redacts internal organizer configurations, and returns a Laravel `LengthAwarePaginator`.

### Repository & Serialization
- **Repository Interface:** [`backend/app/Repository/Interfaces/EventRepositoryInterface.php`](file:///backend/app/Repository/Interfaces/EventRepositoryInterface.php)
- **Repository Implementation:** [`backend/app/Repository/Eloquent/EventRepository.php`](file:///backend/app/Repository/Eloquent/EventRepository.php)
- **Public Resource:** [`backend/app/Resources/Event/EventResourcePublic.php`](file:///backend/app/Resources/Event/EventResourcePublic.php)
- Filters out internal fields (e.g. `account_id`, internal notes) and serializes nested public DTOs (`EventLocationResourcePublic`, `LocationPublicResource`, `ImageResource`).

---

## 2. Models & Spatial Data (Where Coordinates Live)

### Data Models
- **Event Model:** [`backend/app/Models/Event.php`](file:///backend/app/Models/Event.php)
  - Table: `events`.
  - Foreign key: `event_location_id` nullable integer pointing to `event_locations.id`.
  - **Important:** `events.start_date` and `events.end_date` are deprecated legacy columns retained only for rollback compatibility; active event dates live exclusively in `event_occurrences`.
- **EventLocation Model:** [`backend/app/Models/EventLocation.php`](file:///backend/app/Models/EventLocation.php)
  - Table: `event_locations`.
  - Join model linking an event to a physical or virtual location. Contains `type` (`IN_PERSON` or `ONLINE`), `online_event_connection_details`, and foreign key `location_id` pointing to `locations.id`.
- **Location Model:** [`backend/app/Models/Location.php`](file:///backend/app/Models/Location.php)
  - Table: `locations`.
  - **Where coordinates live:** `latitude` and `longitude` are stored as standard `float` columns (`numeric(10, 7)`) directly on the `locations` table, alongside `name`, `structured_address` (JSON), and `organizer_id`.
  - Spatial queries must join: `events -> event_locations (id = events.event_location_id) -> locations (id = event_locations.location_id)`.

---

## 3. Interest Taxonomy: The `EventCategory` Enum

- **File:** [`backend/app/DomainObjects/Enums/EventCategory.php`](file:///backend/app/DomainObjects/Enums/EventCategory.php)
- 24 standardized backed string enum values representing the category/interest taxonomy:
  - **Community:** `SOCIAL`, `FAMILY`, `HOBBIES`, `FOOD_DRINK`, `WELLNESS`, `SPIRITUALITY`, `OUTDOORS`, `TOURS`, `CHARITY`
  - **Creative & Culture:** `MUSIC`, `ART`, `COMEDY`, `THEATER`, `FILM`, `DANCE`
  - **Professional & Learning:** `BUSINESS`, `TECH`, `EDUCATION`, `WORKSHOP`
  - **Leisure & Nightlife:** `SPORTS`, `FESTIVAL`, `SEASONAL`, `NIGHTLIFE`
  - **Catch-all:** `OTHER`
- Stored as uppercase strings in `events.category`. Every seeded event maps to one of these values, providing the taxonomy for our Phase 1 interest-based discovery filters and attendee preference profiles.

---

## 4. Authentication Architecture & Registration Flow

### Auth Configuration & Guards
- **File:** [`backend/config/auth.php`](file:///backend/config/auth.php)
- Three guards configured:
  - `web`: Session driver using provider `users`.
  - `api`: JWT token driver using provider `users`.
  - `sanctum`: Personal access token driver using provider `users`.
- A single user provider `users` queries Eloquent model [`backend/app/Models/User.php`](file:///backend/app/Models/User.php).

### Registration Action & Handler
- **Action:** [`backend/app/Http/Actions/Accounts/CreateAccountAction.php`](file:///backend/app/Http/Actions/Accounts/CreateAccountAction.php)
- **Handler:** [`backend/app/Services/Application/Handlers/Account/CreateAccountHandler.php`](file:///backend/app/Services/Application/Handlers/Account/CreateAccountHandler.php)
- **What registration creates:**
  1. An `Account` row in `accounts` (tenant container with currency and timezone).
  2. A `User` row in `users` (credentials and locale).
  3. A pivot row in `account_users` binding the user to the account with `role = 'ADMIN'` and `is_account_owner = true`.
- **Does registration create an Organizer? NO.**
  - An organizer is **never** created during registration.
  - Organizers are separate tenant entities created explicitly via [`CreateOrganizerAction.php`](file:///backend/app/Http/Actions/Organizers/CreateOrganizerAction.php) / [`CreateOrganizerHandler.php`](file:///backend/app/Services/Application/Handlers/Organizer/CreateOrganizerHandler.php).
  - Attendees currently have no dedicated profile or interest entity; they only exist as ticket-level `Attendee` records.

---

## 5. Frontend Architecture & Routing

### Router & Public Routes
- **File:** [`frontend/src/router.tsx`](file:///frontend/src/router.tsx)
- Root path `""` currently redirects to `/manage/events` (if authenticated) or `/auth/login` (if guest). **There is no global event discovery feed or attendee landing page.**
- Public routes:
  - `/event/:eventId/:eventSlug` -> [`PublicEvent`](file:///frontend/src/components/layouts/PublicEvent/index.tsx) loaded via `publicEventRouteLoader`.
  - `/events/:organizerId/:organizerSlug` -> [`PublicOrganizer`](file:///frontend/src/components/layouts/PublicOrganizer/index.tsx) loaded via `publicOrganizerRouteLoader`.
  - `/widget/*` -> Embedded ticket and lineup widgets.
  - `/check-in/:checkInListShortId` -> Door check-in scanner.

### Data Fetching Organization
- **Queries:** [`frontend/src/queries/`](file:///frontend/src/queries/)
  - Wraps TanStack React Query (`useQuery`), e.g. `useGetEvents.ts`, `useGetPublicEvent.ts`, `useGetPublicOrganizerEvents.ts`.
- **Mutations:** [`frontend/src/mutations/`](file:///frontend/src/mutations/)
  - Wraps React Query mutations (`useMutation`), e.g. `useCreateEvent.ts`, `useCreateCheckInPublic.ts`.
- **API Clients:** [`frontend/src/api/`](file:///frontend/src/api/)
  - Axios HTTP wrappers with base URL and error handling, e.g. `event.client.ts`, `organizer.client.ts`.

---

## 6. Asynchronous Jobs & Email Dispatch

### Mail Architecture
- **Base Mail Class:** [`backend/app/Mail/BaseMail.php`](file:///backend/app/Mail/BaseMail.php)
- Implements `ShouldQueue` and calls `$this->afterCommit()` in the constructor. Emails dispatched inside a database transaction are automatically held until transaction commit; if rolled back, emails are discarded.
- Concrete mails live in `backend/app/Mail/` (e.g. `AttendeeTicketMail`, `OrderConfirmationMail`).

### Queues & Background Workers
- **Configuration:** [`backend/config/queue.php`](file:///backend/config/queue.php)
- Configured with `redis` (default connection in dev stack via container `development-redis-1`) and `database` fallback table `jobs`.
- Asynchronous tasks (webhooks, email dispatch, check-in indexing) implement `ShouldQueue` and use `Dispatchable`.

---

## 7. Check-In & QR Code Architecture

### Ticket QR Code Generation
- **Frontend Ticket View:** [`frontend/src/components/common/AttendeeTicket/index.tsx`](file:///frontend/src/components/common/AttendeeTicket/index.tsx#L155-L161)
- Uses `react-qr-code` to encode the attendee's unique `attendee.public_id` directly onto digital and printed tickets.

### Door Check-In Scanner & Handlers
- **Frontend Controller:** [`frontend/src/hooks/useCheckInController.tsx`](file:///frontend/src/hooks/useCheckInController.tsx) and layout [`frontend/src/components/layouts/CheckIn/index.tsx`](file:///frontend/src/components/layouts/CheckIn/index.tsx)
- Scans QR code via camera / hardware barcode reader and issues an API call to:
  `POST /api/public/check-in-lists/{check_in_list_short_id}/attendees/{attendee_short_id}/check-in`
- **Backend Action:** [`backend/app/Http/Actions/CheckInLists/Public/CreateAttendeeCheckInPublicAction.php`](file:///backend/app/Http/Actions/CheckInLists/Public/CreateAttendeeCheckInPublicAction.php)
- Records check-in timestamp in `attendee_check_ins` and broadcasts live stats without exposing buyer PII.

---

## 8. Potential Blockers & Architectural Constraints for Phase 1

1. **No Spatial Database Index (PostGIS) by Default:**
   - Coordinates are stored as standard floating-point numbers in `locations` without a GIS geometry column or spatial index.
   - *Strategy:* Use the Haversine trigonometric formula directly in SQL (`6371 * acos(...)`) for radius filtering up to 100 km. This avoids container re-compilation while remaining performant for local sets.
2. **Occurrences are the Source of Truth for Dates:**
   - Event dates cannot be filtered using `events.start_date` (it is deprecated and ignored). All date filtering (`when = today | weekend | date range`) must join `event_occurrences` and inspect active occurrences.
3. **Derived Pricing & Sold-Out Statuses:**
   - `events` table has no `min_price` or `is_free` boolean column; pricing is computed dynamically across products in `product_prices`.
   - *Strategy:* The discovery query handler must aggregate min product price via SQL subquery or relation loading.
4. **Missing Attendee Profile Schema:**
   - There is currently no attendee user table for storing discovery interests, follows, or home coordinates. Phase 1 discovery must work as an unauthenticated guest API, while attendee profiles will be introduced in subsequent phases.
