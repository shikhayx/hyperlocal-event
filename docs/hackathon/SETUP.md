# NearU Developer Setup & Environment Baseline Guide

> **Phase 0: Foundation Setup**  
> *Target audience:* Any developer or hackathon teammate setting up a fresh clone.  
> *Target execution time:* Under 30 minutes from clone to interactive dashboard.

---

## 1. Prerequisites

Before starting, ensure your host machine has:

1. **Docker & Docker Compose**
   - **macOS / Linux:** Docker Engine 24+ & Docker Compose v2.
   - **Windows:** Docker Desktop with WSL 2 backend enabled.
2. **Git** (v2.40+)
3. **OpenSSL** (or `mkcert`) for local SSL development certificates.
4. **Node.js 20+ / 24+** (optional for running local frontend typechecks outside containers).
5. **curl** (for API testing).

---

## 2. Ports and URLs

| Service | Container | Internal Port | Host Port | External URL | Notes |
|---|---|---|---|---|---|
| **Reverse Proxy (HTTPS)** | `nginx` | 443 | **8443** | [https://localhost:8443](https://localhost:8443) | Main app gateway (routes `/` to frontend, `/api/` to backend) |
| **Reverse Proxy (HTTP)** | `nginx` | 80 | **8080** | [http://localhost:8080](http://localhost:8080) | Plain HTTP entrypoint |
| **Frontend SSR** | `frontend` | 5678 | **5678** | [http://localhost:5678](http://localhost:5678) | React 19 Vite SSR dev server |
| **Frontend HMR** | `frontend` | 24678 | **24678** | — | Vite Hot Module Replacement |
| **Backend API** | `backend` | 80 / 8080 | **1234** | [http://localhost:1234](http://localhost:1234) | Laravel 13 PHP 8.5 API container |
| **PostgreSQL** | `development-pgsql-1` | 5432 | **5432** | `localhost:5432` | DB: `backend`, User: `username`, Pass: `password` |
| **Redis** | `development-redis-1` | 6379 | **6379** | `localhost:6379` | Cache & session store |
| **Mailpit Web UI** | `development-mailpit-1` | 8025 | **8025** | [http://localhost:8025](http://localhost:8025) | Local inbox for emails & password resets |
| **Mailpit SMTP** | `development-mailpit-1` | 1025 | **1025** | `localhost:1025` | SMTP delivery target |
| **MinIO API (S3)** | `development-minio-1` | 9000 | **9000** | [http://localhost:9000](http://localhost:9000) | S3-compatible asset store (buckets: `hievents-public`, `hievents-private`) |
| **MinIO Console** | `development-minio-1` | 8900 | **8900** | [http://localhost:8900](http://localhost:8900) | MinIO UI (User: `app`, Pass: `password`) |

---

## 3. Exact Setup Commands

### Option A: macOS & Linux

```bash
# 1. Clone repository
git clone https://github.com/shubhhamydv/hyperlocal-event.git
cd hyperlocal-event

# 2. Run automated dev launcher
cd docker/development
./start-dev.sh
```

### Option B: Windows (PowerShell / Git Bash)

```powershell
# 1. Ensure Docker Desktop is running
# Verify daemon responds
docker ps

# 2. Navigate to docker development folder
cd docker\development

# 3. Generate self-signed SSL certificates (if not already present)
if (!(Test-Path "certs/localhost.crt")) {
    mkdir -p certs
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 `
        -keyout certs/localhost.key -out certs/localhost.crt `
        -subj "/CN=localhost" -addext "subjectAltName=DNS:localhost,IP:127.0.0.1"
}

# 4. Start Docker stack
docker compose -f docker-compose.dev.yml up -d

# 5. Ensure MinIO storage service is healthy (see Troubleshooting for MinIO command fix)
docker run -d --name development-minio-1 --user 0:0 --network development_app --network-alias minio `
    -p 9000:9000 -p 8900:8900 -e MINIO_ROOT_USER=app -e MINIO_ROOT_PASSWORD=password `
    -v development_app-minio:/data/minio minio/minio:latest server /data/minio --console-address ":8900"

# 6. Initialize MinIO buckets
docker run --rm --network development_app minio/mc /bin/sh -c "
until /usr/bin/mc alias set myminio http://minio:9000 app password; do sleep 1; done;
/usr/bin/mc mb myminio/hievents-public || true;
/usr/bin/mc mb myminio/hievents-private || true;
/usr/bin/mc anonymous set public myminio/hievents-public;
"

# 7. Run database migrations
docker compose -f docker-compose.dev.yml exec backend php artisan migrate

# 8. Seed dev data and print test credentials
docker compose -f docker-compose.dev.yml exec backend php artisan dev:bootstrap --force
```

---

## 4. Default Credentials & Bootstrap Data Format

Running `php artisan dev:bootstrap --force` populates the database with a verified superadmin user, organizer, single event, recurring event, tickets, promo code, and affiliate.

### Output Format

```text
+------------------------------------------+-------------------------------+
| Key                                      | Value                         |
+------------------------------------------+-------------------------------+
| email                                    | agent+<timestamp>@dev.test    |
| password                                 | BootstrapPass123!             |
| account_id                               | 2                             |
| user_id                                  | 2                             |
| organizer_id                             | 2                             |
| single_event_id (LIVE)                   | 2                             |
| single_occurrence_id                     | 2                             |
| free_product_id / price_id               | 1 / 1                         |
| paid_product_id / price_id (waitlist on) | 2 / 2                         |
| recurring_event_id (LIVE)                | 3                             |
| recurring_occurrence_ids                 | 3, 4, 5, 6                    |
| recurring_product_id / price_id          | 3 / 3                         |
| promo_code                               | bootstrap10                   |
| affiliate_code                           | BOOTAFF                       |
+------------------------------------------+-------------------------------+
Bearer token:
eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

- **Default Web Login:**
  - URL: `https://localhost:8443/auth/login`
  - Email: `agent+<timestamp>@dev.test` (or your custom `--email=`)
  - Password: `BootstrapPass123!`

---

## 5. Verification Checklist & Live Evidence

All 6 required verification checks have been executed and verified against the live development container stack:

### ✅ 1. Frontend Loads
- **Command:** `curl -k -s -I https://localhost:8443`
- **Evidence:**
  ```http
  HTTP/1.1 200 OK
  Server: nginx/1.31.8
  Content-Type: text/html
  X-Powered-By: Express
  ```

### ✅ 2. Log in as Bootstrap User
- **Command:**
  ```bash
  curl -k -s -X POST https://localhost:8443/api/auth/login \
    -H "Content-Type: application/json" \
    -d '{"email":"agent+20261105112721@dev.test","password":"BootstrapPass123!"}'
  ```
- **Evidence:**
  ```json
  {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "bearer",
    "expires_in": 604800,
    "user": {
      "id": 2,
      "email": "agent+20261105112721@dev.test",
      "full_name": "Agent Bootstrap",
      "is_email_verified": true
    }
  }
  ```

### ✅ 3. Organizer Dashboard Opens
- **URL:** `https://localhost:8443/manage/organizer/2`
- **Command:** `curl -k -s -I https://localhost:8443/manage/organizer/2`
- **Evidence:** `HTTP/1.1 200 OK` (Rendered via Vite SSR server).

### ✅ 4. Public Event Page Opens
- **URL:** `https://localhost:8443/event/2/preview`
- **Command:** `curl -k -s -I https://localhost:8443/event/2/preview`
- **Evidence:** `HTTP/1.1 200 OK` (Public event preview rendered successfully).

### ✅ 5. GET /organizers/{id}/events Returns JSON
- **Command:**
  ```bash
  curl -k -s "https://localhost:8443/api/organizers/2/events" \
    -H "Authorization: Bearer $TOKEN"
  ```
- **Evidence:**
  ```json
  {
    "data": [
      {
        "id": 3,
        "title": "Bootstrap Recurring Event",
        "category": "MUSIC",
        "status": "LIVE",
        "type": "RECURRING"
      },
      {
        "id": 2,
        "title": "Bootstrap Single Event",
        "category": "MUSIC",
        "status": "LIVE",
        "type": "SINGLE"
      }
    ]
  }
  ```

### ✅ 6. Mailpit Receives Email
- **Action:** Sent password reset request via `POST /api/auth/forgot-password`.
- **Command:** `curl -s http://localhost:8025/api/v1/messages`
- **Evidence:** Mailpit recorded 3 messages:
  1. `Password reset` to `agent+20261105112721@dev.test`
  2. `Welcome to NearU! Please confirm your email address`
  3. Pre-existing system test email.

---

## 6. Baseline Test & Quality Results

Baseline established on `main` before any new feature additions:

| Test Harness | Command | Result | Details |
|---|---|:---:|---|
| **Backend Unit Suite** | `docker compose -f docker-compose.dev.yml exec -T backend php -d memory_limit=1G vendor/bin/phpunit --no-coverage --testsuite=Unit` | **PASS** | **2,376 tests, 5,624 assertions, 0 failures, 0 errors** (35 risky, 1 skipped) |
| **Backend Style (Pint)** | `docker compose -f docker-compose.dev.yml exec backend ./vendor/bin/pint --test` | **PASS** | **3,232 files clean**, 0 style violations |
| **Frontend Typecheck** | `docker compose -f docker-compose.dev.yml exec frontend npx tsc --noEmit` | **1 ERROR** | `src/components/routes/organizer/Reports/index.tsx(46,30): error TS2304: Cannot find name 'getConfig'.` |

> [!NOTE]
> The single TypeScript error in `Reports/index.tsx` is an inherited upstream bug present before our changes. Any new files or modified components must not introduce additional type errors.

---

## 7. External API Keys & Dependencies

All core application features are designed to function locally without external paid third-party accounts:

| Variable Name | Service | What Breaks Without It | Blocks Phase 0 Work? |
|---|---|---|:---:|
| `GOOGLE_MAPS_API_KEY` | Google Places API | Live address autocomplete on `/api/locations/autocomplete`. Fallback: addresses are typed manually. | **No** |
| `STRIPE_PUBLIC_KEY`<br>`STRIPE_SECRET_KEY`<br>`STRIPE_WEBHOOK_SECRET` | Stripe Payments | Live credit card processing & organizer Stripe Connect payouts. Fallback: Free tickets and cash/offline orders work fully. | **No** |
| `MAIL_HOST` / `MAIL_PORT` | SMTP Email | External email sending. Fallback: Mailpit container catches all emails on port 1025 / UI 8025. | **No** |
| `ANTHROPIC_API_KEY` | Claude AI | Automated event spam analysis (controlled by `APP_EVENT_SPAM_CHECK_ENABLED=false`). | **No** |
| `OPEN_EXCHANGE_RATES_APP_ID` | OpenExchangeRates | Multi-currency real-time conversion for SaaS billing. Local events default to `USD` or single currency. | **No** |
| `TURNSTILE_SECRET_KEY`<br>`VITE_TURNSTILE_SITE_KEY` | Cloudflare Turnstile | CAPTCHA check on registration. Disabled by default. | **No** |
| `SSO_GOOGLE_*`<br>`SSO_OIDC_*` | Single Sign-On | Third-party Google/Microsoft login. Email + password authentication works natively. | **No** |

---

## 8. Troubleshooting & Fixes Discovered

### Issue 1: Docker Desktop named pipe not responding on Windows
- **Symptom:** `failed to connect to the docker API at npipe:////./pipe/dockerDesktopLinuxEngine`.
- **Cause:** Docker Desktop backend service was stopped or not started in the background session.
- **Fix:** Launch Docker Desktop via `com.docker.backend.exe` or start the Docker Desktop GUI app and wait until `docker ps` returns successfully.

### Issue 2: MinIO fails with `'minio' is not a minio sub-command`
- **Symptom:** `minio-1 | ‘minio’ is not a minio sub-command. See ‘minio --help’.`
- **Cause:** `docker-compose.dev.yml` runs `command: 'minio server /data/minio'`, but the base image already specifies `minio` in its entrypoint, causing Docker to execute `minio minio server`.
- **Fix:** Start the MinIO container overriding entrypoint or command arguments:
  ```bash
  docker run -d --name development-minio-1 --user 0:0 --network development_app --network-alias minio \
    -p 9000:9000 -p 8900:8900 -e MINIO_ROOT_USER=app -e MINIO_ROOT_PASSWORD=password \
    -v development_app-minio:/data/minio minio/minio:latest server /data/minio --console-address ":8900"
  ```

### Issue 3: MinIO crashes with `file access denied` on volume
- **Symptom:** `FATAL Unable to initialize backend: file access denied` for `/data/minio/.minio.sys/tmp`.
- **Cause:** Docker volume permissions restricted the non-root minio container user.
- **Fix:** Add `--user 0:0` (run as root) to ensure write access to `/data/minio`.

### Issue 4: `dev:bootstrap` fails on `UnableToCheckFileExistence`
- **Symptom:** `League\Flysystem\UnableToCheckFileExistence: Unable to check existence for: event_cover/system-covers/MUSIC.jpg`.
- **Cause:** Backend could not resolve `http://minio:9000` because the MinIO container had exited.
- **Fix:** Keep `development-minio-1` running with `--network-alias minio` on the `development_app` bridge network and initialize the `hievents-public` bucket with `mc anonymous set public myminio/hievents-public`.

---

## 9. Daily Command Cheat Sheet (10 Commands)

```bash
# 1. Start full Docker stack
docker compose -f docker/development/docker-compose.dev.yml up -d

# 2. Stop full Docker stack
docker compose -f docker/development/docker-compose.dev.yml down

# 3. Run database migrations
docker compose -f docker/development/docker-compose.dev.yml exec backend php artisan migrate

# 4. Generate Domain Objects after schema change (NEVER hand-edit app/DomainObjects/Generated)
docker compose -f docker/development/docker-compose.dev.yml exec backend php artisan generate-domain-objects

# 5. Bootstrap fresh test data & print Bearer token
docker compose -f docker/development/docker-compose.dev.yml exec backend php artisan dev:bootstrap --force

# 6. Reset database completely (wipe and re-migrate)
docker compose -f docker/development/docker-compose.dev.yml exec backend php artisan migrate:fresh

# 7. Run backend Unit tests
docker compose -f docker/development/docker-compose.dev.yml exec -T backend php -d memory_limit=1G vendor/bin/phpunit --no-coverage --testsuite=Unit

# 8. Run code style linter (Laravel Pint)
docker compose -f docker/development/docker-compose.dev.yml exec backend ./vendor/bin/pint --test

# 9. Run frontend typecheck
docker compose -f docker/development/docker-compose.dev.yml exec frontend npx tsc --noEmit

# 10. Extract frontend translations (Lingui)
docker compose -f docker/development/docker-compose.dev.yml exec frontend yarn messages:extract
```

---

## 10. Architectural Observations & Recommendations

1. **Vite SSR vs CSR:**
   - The default development stack runs Vite in Server-Side Rendering mode (`frontend` container on port 5678).
   - SSR initial cold compiles can take 8–15 seconds on lower-spec machines. If any teammate experiences thermal throttling or slow page reloads during rapid client iteration, switch to the CSR profile:
     ```bash
     docker compose -f docker/development/docker-compose.dev.yml --profile csr up -d frontend-csr
     ```
2. **Reverse Proxy Flow:**
   - Always access the app via Nginx at **`https://localhost:8443`** (or `http://localhost:8080`) rather than talking directly to port 1234 or 5678. Nginx properly proxies `/api/` to the Laravel backend and all other requests to the React frontend while setting the necessary SSL headers.
