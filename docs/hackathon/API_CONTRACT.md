# API Contract: Public Hyperlocal Event Discovery

> **Status:** Proposed, v0.1  
> **Target Endpoint:** `GET /api/events/discover`  
> **Authentication:** None (Public Guest Route)  
> **Phase:** Phase 1 Implementation Baseline

This contract defines the public endpoint for discovering events near a user based on geolocation coordinates, categories/interests, dates, text queries, and pricing filters. Frontend and backend engineers will build against this specification in Phase 1.

---

## 1. Endpoint Overview

- **Route:** `GET /api/events/discover`
- **Controller/Action:** `HiEvents\Http\Actions\Events\DiscoverEventsPublicAction`
- **Handler:** `HiEvents\Services\Application\Handlers\Event\DiscoverEventsPublicHandler`
- **Rate Limit:** `throttle:60,1,public-discover` (60 requests per minute per IP)
- **Cache Policy:** `cache.guest:5,lat,lng,radius_km,categories,date_from,date_to,when,free,q,sort,page,per_page` (TTL: 5 minutes)

---

## 2. Request Parameters

All parameters are optional query parameters passed in the URL.

| Parameter | Type | Default | Description & Validation |
|---|---|---|---|
| `lat` | `float` | `null` | User latitude in decimal degrees. Must be between `-90.0` and `90.0`. Required if `lng` is supplied. |
| `lng` | `float` | `null` | User longitude in decimal degrees. Must be between `-180.0` and `180.0`. Required if `lat` is supplied. |
| `radius_km` | `float` | `10.0` | Search radius in kilometres. Must be between `0.5` and `100.0`. Ignored if `lat`/`lng` are null. |
| `categories[]` | `array[string]` | `[]` | Array of `EventCategory` enum strings (e.g. `TECH`, `MUSIC`, `FOOD_DRINK`). Invalid enums return 422. |
| `date_from` | `string` | `null` | ISO 8601 start timestamp filter (e.g. `2026-11-06T00:00:00Z`). |
| `date_to` | `string` | `null` | ISO 8601 end timestamp filter (e.g. `2026-11-20T23:59:59Z`). Must be `>= date_from`. |
| `when` | `string` | `null` | Preset relative time window. Allowed values: `today`, `tonight`, `weekend`. Mutually exclusive with `date_from`/`date_to`. |
| `free` | `boolean` | `null` | When `true`, filters to free events (`min_price == 0`). When `false`, paid only. |
| `q` | `string` | `null` | Case-insensitive text search matching event title, description, and venue name. Max length 100 characters. |
| `sort` | `string` | *Dynamic* | Sorting criterion: `distance`, `date`, or `relevance`. **Default:** `distance` when `lat`/`lng` are present, otherwise `date`. |
| `page` | `integer` | `1` | Page number for pagination. Must be `>= 1`. |
| `per_page` | `integer` | `20` | Results per page. Min `1`, Max `50`. |

---

## 3. Response Schema

### Success Response (`200 OK`)

The response structure mirrors Hi.Events standard paginated resource responses:

```typescript
interface DiscoverResponse {
  data: DiscoverEventItem[];
  links: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
  meta: {
    current_page: number;
    from: number;
    last_page: number;
    path: string;
    per_page: number;
    to: number;
    total: number;
  };
}

interface DiscoverEventItem {
  id: number;
  short_id: string;
  title: string;
  category: string; // One of the 24 EventCategory enum values
  start_date: string; // ISO 8601 UTC
  end_date: string; // ISO 8601 UTC
  timezone: string; // e.g. "Asia/Kolkata"
  is_free: boolean;
  min_price: number | null; // Lowest active product price in event currency, or 0.00 if free
  currency: string; // ISO 4217 code, e.g. "INR"
  cover_image_url: string | null; // Public CDN/S3 URL of cover
  organizer: {
    id: number;
    name: string;
  };
  location: {
    venue_name: string;
    city: string;
    latitude: number;
    longitude: number;
  } | null; // null for online events
  distance_km: number | null; // Computed Haversine distance from request lat/lng, rounded to 2 decimals; null if lat/lng omitted
  url: string; // Canonical frontend URL, e.g. "https://localhost:8443/event/114/hands-on-rust"
}
```

---

## 4. Full Request & Response Examples

### Example 1: Geolocation Search Near Bandra (Upcoming Weekend)

**Request:**
```http
GET /api/events/discover?lat=19.0596&lng=72.8295&radius_km=15&when=weekend HTTP/1.1
Host: localhost:8443
Accept: application/json
```

**Response (`200 OK`):**
```json
{
  "data": [
    {
      "id": 114,
      "short_id": "ev_8xM9K2aL1Q",
      "title": "Hands-On Full-Stack Rust & WebAssembly Workshop",
      "category": "WORKSHOP",
      "start_date": "2026-11-07T08:30:00.000000Z",
      "end_date": "2026-11-07T12:30:00.000000Z",
      "timezone": "Asia/Kolkata",
      "is_free": false,
      "min_price": 599.00,
      "currency": "INR",
      "cover_image_url": "http://localhost:9000/hievents-public/event_cover/cover-workshop-aMqEn.jpg",
      "organizer": {
        "id": 3,
        "name": "VJTI Coding Club & ACM Student Chapter"
      },
      "location": {
        "venue_name": "IIT Bombay Innovation Hall",
        "city": "Mumbai",
        "latitude": 19.1176,
        "longitude": 72.9060
      },
      "distance_km": 10.24,
      "url": "https://localhost:8443/event/114/hands-on-full-stack-rust-webassembly-workshop"
    },
    {
      "id": 117,
      "short_id": "ev_3jL8P4zR9T",
      "title": "Bandra Seaside Sunrise 10K & Post-Run Charcha",
      "category": "SPORTS",
      "start_date": "2026-11-07T01:00:00.000000Z",
      "end_date": "2026-11-07T03:00:00.000000Z",
      "timezone": "Asia/Kolkata",
      "is_free": true,
      "min_price": 0.00,
      "currency": "INR",
      "cover_image_url": "http://localhost:9000/hievents-public/event_cover/cover-sports-Bx7L2.jpg",
      "organizer": {
        "id": 6,
        "name": "Marine Drive Runners & Turf League"
      },
      "location": {
        "venue_name": "Bandstand Promenade",
        "city": "Mumbai",
        "latitude": 19.0435,
        "longitude": 72.8198
      },
      "distance_km": 2.05,
      "url": "https://localhost:8443/event/117/bandra-seaside-sunrise-10k-post-run-charcha"
    }
  ],
  "links": {
    "first": "https://localhost:8443/api/events/discover?page=1",
    "last": "https://localhost:8443/api/events/discover?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "https://localhost:8443/api/events/discover",
    "per_page": 20,
    "to": 2,
    "total": 2
  }
}
```

---

### Example 2: Category & Text Search Across Entire City (Sorted by Date)

**Request:**
```http
GET /api/events/discover?categories[]=TECH&categories[]=WORKSHOP&q=Rust&sort=date HTTP/1.1
Host: localhost:8443
Accept: application/json
```

**Response (`200 OK`):**
```json
{
  "data": [
    {
      "id": 114,
      "short_id": "ev_8xM9K2aL1Q",
      "title": "Hands-On Full-Stack Rust & WebAssembly Workshop",
      "category": "WORKSHOP",
      "start_date": "2026-11-07T08:30:00.000000Z",
      "end_date": "2026-11-07T12:30:00.000000Z",
      "timezone": "Asia/Kolkata",
      "is_free": false,
      "min_price": 599.00,
      "currency": "INR",
      "cover_image_url": "http://localhost:9000/hievents-public/event_cover/cover-workshop-aMqEn.jpg",
      "organizer": {
        "id": 3,
        "name": "VJTI Coding Club & ACM Student Chapter"
      },
      "location": {
        "venue_name": "IIT Bombay Innovation Hall",
        "city": "Mumbai",
        "latitude": 19.1176,
        "longitude": 72.9060
      },
      "distance_km": null,
      "url": "https://localhost:8443/event/114/hands-on-full-stack-rust-webassembly-workshop"
    },
    {
      "id": 139,
      "short_id": "ev_5rT2M8pQ3Z",
      "title": "Global Remote Rust Developers Sprint & Review",
      "category": "TECH",
      "start_date": "2026-11-12T10:30:00.000000Z",
      "end_date": "2026-11-12T13:30:00.000000Z",
      "timezone": "Asia/Kolkata",
      "is_free": true,
      "min_price": 0.00,
      "currency": "INR",
      "cover_image_url": "http://localhost:9000/hievents-public/event_cover/cover-tech-ZnHR7.jpg",
      "organizer": {
        "id": 3,
        "name": "VJTI Coding Club & ACM Student Chapter"
      },
      "location": null,
      "distance_km": null,
      "url": "https://localhost:8443/event/139/global-remote-rust-developers-sprint-review"
    }
  ],
  "links": {
    "first": "https://localhost:8443/api/events/discover?page=1",
    "last": "https://localhost:8443/api/events/discover?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "https://localhost:8443/api/events/discover",
    "per_page": 20,
    "to": 2,
    "total": 2
  }
}
```

---

### Example 3: Free Events Search with Location Filter (Today)

**Request:**
```http
GET /api/events/discover?lat=19.0760&lng=72.8777&radius_km=10&free=true&when=today HTTP/1.1
Host: localhost:8443
Accept: application/json
```

**Response (`200 OK`):**
```json
{
  "data": [
    {
      "id": 111,
      "short_id": "ev_7kL1B9cX4Q",
      "title": "BKC Morning Coffee & Tech Sprint",
      "category": "TECH",
      "start_date": "2026-11-05T03:30:00.000000Z",
      "end_date": "2026-11-05T05:30:00.000000Z",
      "timezone": "Asia/Kolkata",
      "is_free": true,
      "min_price": 0.00,
      "currency": "INR",
      "cover_image_url": "http://localhost:9000/hievents-public/event_cover/cover-tech-ZnHR7.jpg",
      "organizer": {
        "id": 4,
        "name": "Mumbai Tech Founders & Ventures"
      },
      "location": {
        "venue_name": "WeWork Enam Sambhav, BKC",
        "city": "Mumbai",
        "latitude": 19.0688,
        "longitude": 72.8703
      },
      "distance_km": 1.12,
      "url": "https://localhost:8443/event/111/bkc-morning-coffee-tech-sprint"
    }
  ],
  "links": {
    "first": "https://localhost:8443/api/events/discover?page=1",
    "last": "https://localhost:8443/api/events/discover?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "https://localhost:8443/api/events/discover",
    "per_page": 20,
    "to": 1,
    "total": 1
  }
}
```

---

## 5. Validation Rules & Error Handling

Standard Laravel validation errors are returned as JSON with HTTP `422 Unprocessable Content`:

### 1. Latitude / Longitude Missing Pair
If `lat` is provided without `lng` (or vice-versa):
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "lng": ["The longitude field is required when latitude is present."]
  }
}
```

### 2. Radius Exceeds Maximum Limit
If `radius_km` exceeds `100.0`:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "radius_km": ["The radius_km field must not be greater than 100."]
  }
}
```

### 3. Invalid EventCategory Enum
If a category does not match the 24 `EventCategory` cases:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "categories.0": ["The selected category 'GAMING' is invalid."]
  }
}
```

### 4. Conflicting Date Parameters
If both `when` and `date_from` are provided:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "when": ["The when parameter cannot be used together with date_from or date_to."]
  }
}
```

---

## 6. Open Questions for Phase 1 Alignment

The following architectural questions should be settled before backend implementation begins:

1. **Online Events Distance Ordering:**  
   When the user filters by location (`lat`, `lng`) with `sort=distance`, should virtual/online events (`latitude = null`) be:
   - Excluded from radius results?
   - Pinned at the very end with `distance_km = null`?
   - Controlled via a separate `include_online=true/false` flag?
2. **Recurring Event Deduplication in Feed:**  
   If a weekly recurring event (e.g. *Prana Yoga*) has 12 upcoming occurrences, should the discover feed:
   - Return only the **next upcoming occurrence** as a single card?
   - Or display each distinct occurrence separately? *(Recommended: Return 1 card per event showing the next occurrence date to prevent one recurring event from dominating the local feed).*
3. **Database Performance: Raw SQL Haversine vs PostGIS:**  
   Should Phase 1 execute the trigonometric Haversine formula in raw SQL on `locations.latitude` / `locations.longitude`, or should we introduce a PostGIS geometry extension via a database migration? *(Recommended: Use Haversine in raw SQL for Phase 1 to preserve dev environment simplicity across macOS/Linux/Windows).*
4. **Attendee Client Location Consent:**  
   When a user visits `/discover` for the first time without browser geolocation enabled, should the API default to the configured city center (`CityConfig::CENTER_LAT, CityConfig::CENTER_LNG`), or should it prompt the user with a neighbourhood selector dropdown?
