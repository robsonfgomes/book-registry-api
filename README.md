# Book Registry API

A REST API where authenticated users manage their own books.

**Stack:** PHP 8.5 · Laravel 13 · MySQL 8.4 · Redis 8 · Laravel Sanctum (API tokens) · Scramble (OpenAPI docs) ·
PHPUnit 12 · Laravel Pint · Docker Compose

## Getting started

The only requirement is Docker with Docker Compose v2. PHP and Composer run inside the containers.

```bash
git clone <repository-url> book-registry-api
cd book-registry-api

cp .env.example .env
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose up -d
```

On the first start, MySQL needs a few seconds to initialize. Wait until the health check reports `ok`, then create
the tables and the demo data:

```bash
curl http://localhost:8000/api/health
# {"status":"ok","checks":{"app":"ok","database":"ok","redis":"ok"}}

docker compose exec app php artisan migrate --seed
```

The API is now available at `http://localhost:8000/api`. The seeder creates a demo user with 20 books:

| Email | Password |
|---|---|
| `demo@example.com` | `password` |

## Running the tests

```bash
docker compose exec app php artisan test
```

## API documentation

All endpoints are under `/api/v1`, except the health check. Every response, including errors, is JSON.

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/api/health` | No | Application, database and Redis status |
| POST | `/api/v1/register` | No | Create an account and get a token |
| POST | `/api/v1/login` | No | Get a token |
| POST | `/api/v1/logout` | Yes | Revoke the current token |
| GET | `/api/v1/me` | Yes | The authenticated user |
| GET | `/api/v1/books` | Yes | List your books (paginated) |
| POST | `/api/v1/books` | Yes | Create a book |
| GET | `/api/v1/books/{id}` | Yes | Show a book |
| PUT/PATCH | `/api/v1/books/{id}` | Yes | Update a book |
| DELETE | `/api/v1/books/{id}` | Yes | Delete a book |

```bash
# Log in and keep the token
TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"demo@example.com","password":"password"}' | jq -r .token)

# Create a book
curl -s -X POST http://localhost:8000/api/v1/books \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"name":"Clean Code","isbn":"9780132350884","value":"89.90"}'
```

The interactive documentation, generated from the code by [Scramble](https://scramble.dedoc.co), is available at
**<http://localhost:8000/docs/api>**.

## Architecture

A light domain-driven structure: the business rules live in `app/Domain` and do not depend on HTTP. The HTTP
layer follows Laravel's standard structure and only translates requests into calls to the domain. The same
`CreateBook` action could be called by a queued bulk import, without going through HTTP.

```
app/
├── Domain/
│   ├── Books/
│   │   ├── Actions/        CreateBook, UpdateBook
│   │   ├── Casts/          IsbnCast
│   │   ├── Exceptions/     DuplicateIsbnException
│   │   ├── Models/         Book
│   │   ├── Policies/       BookPolicy
│   │   └── ValueObjects/   Isbn
│   └── Exceptions/         BusinessRuleException (base class for violated business rules)
├── Http/
│   ├── Controllers/Api/    HealthController, V1/AuthController, V1/BookController
│   ├── Requests/           Form requests (validation at the HTTP boundary)
│   └── Resources/          BookResource, UserResource (the JSON format, decoupled from the models)
│       └── Concerns/       FormatsDates (ISO 8601 dates for every resource)
├── Models/                 User (identity for authentication, kept where Laravel expects it)
└── Providers/              AppServiceProvider (policy, {book} binding, rate limiters, API docs)
```

## Assumptions and decisions

| Topic | Decision |
|---|---|
| Authentication | Sanctum **API tokens** (stateless, so the API scales horizontally). Sanctum is configured to accept only Bearer tokens, never sessions. Logout revokes only the current token. |
| Registration | Without it there is no way to create users through the API. `POST /register` returns `201` with the user and a token, so the client does not need a second call. |
| Login errors | An unknown email and a wrong password return the same `422` message, so the API does not reveal which accounts exist. |
| `isbn` | A string (keeps leading zeros) of exactly 13 digits (ISBN-13, the standard since 2007). ISBN-10 is not accepted, because it may end with `X` as its check digit. Accepting ISBN-10 or validating the check digit would be a change inside the `Isbn` value object only. |
| ISBN uniqueness | Unique **per user**, with a composite unique index `(user_id, isbn)`: different users may register the same book. Enforced in two layers: the form request (common case) and the database index (concurrent requests), both returning `422`, never `500`. |
| `value` | `DECIMAL(10,2)`, from `0` to `99999999.99`, at most 2 decimal places. The maximum matches the column, so an out-of-range value is a `422`, never a database error. Returned as a **string** (`"89.90"`), because a JSON number could lose precision in some clients. Never a float. |
| `name` | Required, at most 255 characters. |
| Another user's book | `404`, exactly like a book that does not exist. A `403` would confirm that the record exists. |
| Listing | Paginated, newest first (`created_at`, then `id` as a tie-breaker, so pages are stable). `per_page` is optional: default 15, between 1 and 100; outside that range it is a `422`. |
| Dates | ISO 8601 in UTC, without microseconds: `2026-01-15T10:30:00+00:00`. |
| Versioning | Routes under `/api/v1`. The health check stays outside the version: it is infrastructure, not part of the API contract. |
| Health check | `GET /api/health` checks the application, the database (`SELECT 1`) and Redis (`PING`). Any failure returns `503`, and the other checks are still reported. Error details are only logged, never returned. |
| Rate limiting | Two named limiters, with counters in Redis (shared across instances). `auth` (login and register): 10 per minute, keyed by IP + email on login (slows down password guessing on one account) and by IP on register (prevents bulk account creation). `api` (every authenticated route): 60 per minute per user. They differ in limit and key because guests have no user to key by. |
| Token expiration | 24 hours. Sanctum rejects an expired token with `401`; the scheduler deletes, daily, the tokens expired for more than 24 hours (`sanctum:prune-expired --hours=24`). |
| Deletion | Hard delete. Soft delete is a possible evolution. |
| A user with books | The `user_id` foreign key uses `RESTRICT`: a user cannot be deleted while they have books, avoiding accidental data loss. |
| N+1 queries | `Model::preventLazyLoading()` is enabled outside production, so a lazy-loaded relation fails in development and in the tests. |

## Configuration

All settings come from environment variables, with safe defaults in `.env.example`.

Application settings (`config/api.php` and `config/sanctum.php`):

| Variable | Default | Description |
|---|---|---|
| `API_AUTH_RATE_LIMIT` | `10` | Requests per minute on login (per IP + email) and register (per IP) |
| `API_RATE_LIMIT` | `60` | Requests per minute on the authenticated routes, per user |
| `API_DEFAULT_PER_PAGE` | `15` | Page size when `per_page` is not sent |
| `API_MAX_PER_PAGE` | `100` | Largest accepted `per_page` |
| `SANCTUM_TOKEN_EXPIRATION` | `1440` | Token lifetime in minutes (24 hours) |

Infrastructure settings (the defaults in `.env.example` work with Docker Compose as they are):

| Variable | Default | Description |
|---|---|---|
| `APP_ENV` | `local` | `production` in production; also controls access to the API documentation |
| `APP_DEBUG` | `true` | **Must be `false` in production**: when `true`, error responses include the stack trace |
| `APP_URL` | `http://localhost:8000` | Base URL, used in the pagination links |
| `DB_HOST`, `DB_PORT` | `mysql`, `3306` | MySQL host and port inside the Docker network |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `book_registry`, `book_registry`, `secret` | Database credentials, also used by the `mysql` container on its first start |
| `REDIS_HOST`, `REDIS_PORT` | `redis`, `6379` | Redis host and port |
| `CACHE_STORE` | `redis` | Cache store, also used by the rate limiter |
| `QUEUE_CONNECTION` | `redis` | Queue connection (configured, no jobs yet) |
| `SESSION_DRIVER` | `array` | The API is stateless, so sessions are never persisted |
| `BCRYPT_ROUNDS` | `12` | Cost of the password hash |
| `LOG_LEVEL` | `debug` | Minimum log level |

## Scaling and future growth

The API is built to grow in features, users and data, following one principle: **measure before scaling**. Each
next step below has a trigger, and none was implemented ahead of that need.

| Axis | Already in place | Next step | Trigger |
|---|---|---|---|
| Traffic and users | Stateless token auth, rate limits in Redis shared across instances, `/api/health` for load balancers | Several app instances behind a load balancer | Response times or CPU of one instance at the limit |
| Database reads | Queries scoped by user and indexed; no N+1 (`preventLazyLoading`) | Read replicas, with `sticky` enabled so a request reads its own writes | The primary database saturated by reads |
| Data volume | Paginated listing; `(user_id, isbn)` unique index, which also serves the filter by user | Cursor pagination, an index on `(user_id, created_at)` for the ordering, then archiving or partitioning | Users with many books making deep pages or the sort slow (use `EXPLAIN`) |
| Authentication | One primary-key lookup per request | Token caching | Token lookups or `last_used_at` writes showing up as database load |
| Features | Actions reusable outside HTTP; queue already configured for Redis; `failed_jobs` table | Bulk import through queued jobs (retries, failed jobs); domain events | A bulk import feature, or side effects (emails, auditing) after writes |

**Web server.** The container runs `php artisan serve`, which is single-threaded and meant for development only. In
production it would be replaced by PHP-FPM behind Nginx, or Laravel Octane, with several instances behind a load
balancer. That works because nothing is tied to one instance: tokens are validated against the database and
rate-limit counters live in Redis.

**Why nothing is cached yet.** Token lookups are already primary-key queries, and caching them would keep a revoked
token valid until the cache expired. Book lists are per-user and change with every write, so the hit rate would be low.

### Measuring first

Bottlenecks would be found, not guessed:

- **In production:** the MySQL slow query log and an APM tool (Laravel Pulse or Nightwatch) show which endpoints and
  queries are slow, and how often.
- **To understand a slow query:** `EXPLAIN` / `EXPLAIN ANALYZE` show whether an index is used and confirm that a new
  index actually helps.
- **N+1 in development:** `Model::preventLazyLoading()` is enabled outside production (in `AppServiceProvider`), so a
  relation loaded in a loop throws an exception in development and in the tests instead of reaching production.