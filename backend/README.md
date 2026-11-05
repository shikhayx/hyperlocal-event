# NearU Backend

The backend for NearU is built on **Laravel 13** (PHP >=8.3) and implements a **Domain-Driven Design (DDD)** architecture.

NearU is built on the Hi.Events open-source platform.

## Architecture

Request flow: **Action → Handler → Domain Service → Repository**.
- **Actions**: HTTP entry points extending `BaseAction`.
- **Handlers**: Application use cases executing within database transactions.
- **Domain Services**: Business domain logic.
- **Repositories**: Database queries returning domain objects.

## Key Artisan Commands

```bash
# Run database migrations
php artisan migrate

# Generate domain objects from schema
php artisan generate-domain-objects

# Export OpenAPI reference documentation
php artisan api-reference:export

# Run test suite
php -d memory_limit=1G vendor/bin/phpunit --no-coverage --testsuite=Unit
```

For in-depth architecture documentation, see [docs/README.md](docs/README.md).
