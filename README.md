# World Cities API

[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-ffdd00?style=for-the-badge&logo=buy-me-a-coffee&logoColor=black)](https://www.buymeacoffee.com/thomaslaure)

Symfony/API Platform API exposing city data for any country (identified by ISO 3166-1 alpha-2 country code) and address autocomplete. Cities are currently imported for France (from [geo.api.gouv.fr](https://geo.api.gouv.fr)) and Germany (from [GeoNames](https://download.geonames.org/export/dump/)). Address search is powered by a self-hosted [Photon](https://github.com/komoot/photon) instance, which covers every country by design since it's built on OpenStreetMap data.

## Table of Contents

- [Overview](#overview)
- [Stack](#stack)
- [Prerequisites](#prerequisites)
- [Environment Variables](#environment-variables)
- [Quick Start](#quick-start)
- [API Reference](#api-reference)
- [Observability](#observability)
- [Architecture](#architecture)
- [Import](#import)
- [Extending Data Sources](#extending-data-sources)
  - [Adding a new country's cities](#adding-a-new-countrys-cities)
  - [Extending address search coverage](#extending-address-search-coverage)
- [Operations](#operations)
- [Main Commands](#main-commands)
- [Agent Docs](#agent-docs)

## Overview

The project has two distinct concerns:

- **Write/import flow**: fetch city data from one or more external data providers and persist it into PostgreSQL via a CLI command.
- **Read API**: expose city search/lookup and address search through API Platform over HTTP.

The write side (City import) follows an explicit `Application` + `Domain` layered split.
The City read side is decoupled from Doctrine: `App\UI\ApiResource\CityResource` is the API resource, mapped from `App\Entity\City` by a Provider (`src/Infrastructure/Http/Provider`).
Address search has no persistence at all — it's a live passthrough to Photon, mapped by its own Provider straight from the `Address` domain model.

## Stack

- PHP 8.5
- Symfony 7.4
- API Platform 4.x
- PostgreSQL 16
- FrankenPHP
- Docker / Docker Compose
- PHPUnit, Behat, PHPStan, PHP CS Fixer, Rector, Enlightn Security Checker

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/) and Docker Compose
- `make`

No local PHP installation is required. Everything runs inside Docker.

## Environment Variables

Copy `.env` to `.env.local` and set the following variables:

| Variable | Description | Example |
|---|---|---|
| `APP_ENV` | Symfony environment | `dev` |
| `APP_SECRET` | Symfony secret key | any random string |
| `DATABASE_URL` | PostgreSQL DSN | `postgresql://insee:insee@postgres:5432/insee_city` |
| `INSEE_API_BASE_URL` | Base URL for the French geo API | `https://geo.api.gouv.fr` |
| `GEONAMES_BASE_URL` | Base URL for GeoNames per-country dumps | `https://download.geonames.org/export/dump` |
| `PHOTON_BASE_URL` | Base URL for the self-hosted Photon address search service | `http://photon:2322` |
| `CORS_ALLOW_ORIGIN` | Allowed CORS origin (regex) | `^https?://localhost(:[0-9]+)?$` |
| `DEFAULT_URI` | Base URI for CLI-generated URLs | `http://localhost:8001` |

In Docker Compose development, these are already pre-configured in `docker-compose.yml`.

## Quick Start

```bash
make install   # build containers, install dependencies, run migrations
make import    # populate the database from every tagged city data provider (France + Germany)
```

Address search additionally requires the `photon` service (`docker compose up -d photon`), which downloads a ~5GB OSM search index into a named volume on first run — see [Extending Data Sources](#extending-data-sources).

API entrypoint: `http://localhost:8001/api/v1`
API documentation (dev only): `http://localhost:8001/api`

Local ports:

| Service | Port |
|---|---|
| App (HTTP) | `8001` |
| PostgreSQL | `5433` |

## API Reference

### Endpoints

| Method | Path | Description |
|---|---|---|
| `GET` | `/api/v1/cities` | Paginated city collection |
| `GET` | `/api/v1/cities/{countryCode}/{localCode}` | Single city by country code and local city code |
| `GET` | `/api/v1/addresses/search` | Address autocomplete (any country, via Photon) |
| `GET` | `/health` | Health check (DB connectivity) |

### Authentication

None. Every endpoint above is fully public and unauthenticated by design — this is a read-only reference API with no user data and no state-changing operations. Access control is limited to the global per-IP [rate limit](#rate-limiting). If you need to restrict access (e.g. an internal-only deployment), put it behind your own gateway/auth layer; nothing in this app enforces it.

### Collection Filters

All filters are optional. Omitting a filter returns all cities.

| Parameter | Type | Match | Example |
|---|---|---|---|
| `name` | string | Partial | `?name=par` |
| `exactName` | string | Exact | `?exactName=Paris` |
| `countryCode` | string | Exact | `?countryCode=FR` |
| `departmentCode` | string | Exact | `?departmentCode=75` |
| `regionCode` | string | Exact | `?regionCode=11` |

### Pagination

Default page size: 30. Maximum: 1000.

```
GET /api/v1/cities?page=2&itemsPerPage=100
```

### Response Format

City resource fields:

| Field | Type | Notes |
|---|---|---|
| `countryCode` | string | ISO 3166-1 alpha-2 country code (e.g. `FR`), part of the identifier. Validated against the full ISO list at write time and on the `countryCode` filter. |
| `localCode` | string | Country-local city code, part of the identifier. Semantics are provider-defined, not a portable government code for every country: France's is the official INSEE commune code, Germany's is GeoNames' internal `geonameid` (an arbitrary numeric ID, not an administrative code) |
| `name` | string | City name |
| `departmentCode` | string\|null | Department code, `null` if not applicable for the country |
| `regionCode` | string\|null | Region code, `null` if not applicable for the country |
| `postalCode` | string\|null | First postal code, `null` if unavailable |

Example response (`application/ld+json`):

```json
{
  "@context": "/api/v1/contexts/City",
  "@id": "/api/v1/cities/FR/75056",
  "@type": "City",
  "countryCode": "FR",
  "localCode": "75056",
  "name": "Paris",
  "departmentCode": "75",
  "regionCode": "11",
  "postalCode": "75001"
}
```

Errors use RFC 7807 `application/problem+json`.

### Address Search

```
GET /api/v1/addresses/search?q=10+rue+de+la+paix+paris&countryCode=FR&limit=5
```

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `q` | string | yes | Partial or full-text address query |
| `countryCode` | string | no | Restrict results to this ISO 3166-1 alpha-2 country code. **Known limitation**: Photon has no server-side country filter, so this is applied client-side *after* Photon has already ranked and truncated to `limit` global matches — a query whose top worldwide matches are mostly outside the target country can come back empty or short even when good matches exist further down Photon's ranking |
| `limit` | integer | no | 1-20, default 10 |

Address resource fields: `label`, `houseNumber`, `street`, `postalCode`, `city`, `countryCode`, `latitude`, `longitude` (all nullable except `label`/`latitude`/`longitude`).

Plain JSON only (`application/json`) — no JSON-LD/Hydra, since a search hit has no natural identifier to mint an `@id` for.

Example response:

```json
[
  {
    "label": "10 Rue de la Paix, 75002 Paris",
    "houseNumber": "10",
    "street": "Rue de la Paix",
    "postalCode": "75002",
    "city": "Paris",
    "countryCode": "FR",
    "latitude": 48.8689953,
    "longitude": 2.3311419
  }
]
```

Returns `503 application/problem+json` if the Photon service is unreachable (e.g. still warming up after a fresh `docker compose up`).

### Caching

Cache behavior depends on the environment:

- `dev`: responses are not cacheable (`Cache-Control: no-store`)
- `prod`: responses are public cacheable for up to one hour (`Cache-Control: public, max-age=3600`)

### Rate Limiting

200 requests per minute per IP. Exceeding the limit returns `429 application/problem+json`.

### Health

```
GET /health
```

Returns `{"status":"ok"}` (200) or `{"status":"error","detail":"Database unavailable"}` (503). Intended for readiness/liveness probes.

## Observability

Every request under `/api/` produces a structured JSON log entry on the `api_access` channel.

Log fields: `request_id`, `consumer`, `method`, `path`, `status`, `ip`, `user_agent`, `duration_ms`.

**Consumer identification**: send `X-App-Name: <your-app-name>` on every request. The value is recorded in logs and allows identifying which internal application made each call. This header is voluntary and not enforced.

**Request tracing**: send `X-Request-Id: <id>` to propagate your own trace ID. If absent, one is generated. The value is always echoed back in the response `X-Request-Id` header.

Log destinations:

| Environment | Destination |
|---|---|
| `dev` | `var/log/api_access.log` |
| `prod` | `php://stdout` (JSON) |

## Architecture

```text
src/
├── Application/
│   └── City/
│       ├── DTO/
│       └── Handler/         # ImportCitiesHandler — loops over every tagged CityDataProviderInterface
├── Domain/
│   ├── City/
│   │   ├── Exception/
│   │   ├── Model/
│   │   └── Port/
│   ├── Address/
│   │   ├── Exception/
│   │   ├── Model/
│   │   └── Port/
│   └── Shared/
│       └── Model/            # CountryCode — shared by both City and Address
├── Entity/
│   └── City.php               # Doctrine entity
├── Infrastructure/
│   ├── External/               # GeoApiClient (France), GeoNamesClient (any GeoNames country), PhotonClient (address search)
│   ├── Http/
│   │   ├── Listener/           # Request logging (ApiRequestLogListener) and rate limiting (RateLimitListener)
│   │   └── Provider/            # API Platform Providers mapping entities/domain models to API resources
│   └── Persistence/             # DoctrineCityRepository
└── UI/
    ├── ApiResource/               # CityResource, AddressResource (decoupled from Doctrine)
    ├── Command/                   # ImportCitiesCommand
    └── Controller/                # HealthController
```

### Write Flow (City import)

```
ImportCitiesCommand → ImportCitiesHandler → City (domain model) → CityRepositoryInterface → DoctrineCityRepository
                                          ↑
                          tagged app.city_data_provider iterable
                          (GeoApiClient, GeoNamesClient.DE, ...)
```

### Read Flow

```
HTTP Request → API Platform → Provider → Doctrine ORM (City) or live HTTP call (Address) → JSON(-LD) response
```

## Import

```bash
make import
```

Runs every service tagged `app.city_data_provider` (currently `GeoApiClient` for France and `GeoNamesClient.DE` for Germany) and persists their combined output. France is fetched department by department from `geo.api.gouv.fr` to avoid loading the full dataset into memory; Germany is fetched as one bulk file from GeoNames and filtered to populated places. Existing records are updated via upsert on `(country_code, local_code)`. Postal codes are only available for the French import — GeoNames' bulk gazetteer dump doesn't include them, so German cities have `postalCode: null`.

## Extending Data Sources

### Adding a new country's cities

Implement `App\Domain\City\Port\CityDataProviderInterface` (`fetchAllCities(): iterable<City>`) and tag the service `app.city_data_provider` in `config/services.yaml` — `ImportCitiesHandler` picks up every tagged instance automatically (`!tagged_iterator app.city_data_provider`), no other code changes needed.

If the new country is covered by [GeoNames](https://download.geonames.org/export/dump/) (most are), you don't need a new class at all — `GeoNamesClient` already takes `CountryCode` as a constructor argument, so adding a country is a pure DI config change:

```yaml
App\Infrastructure\External\GeoNamesClient.<CODE>:
    parent: App\Infrastructure\External\GeoNamesClient
    arguments:
        $countryCode: !php/enum App\Domain\Shared\Model\CountryCode::<CODE>
    tags: ['app.city_data_provider']
```

**Memory**: `GeoNamesClient` streams the HTTP download straight to a temp file and reads the archived country file line by line — never loading the whole per-country file into memory as one string. Memory use stays low regardless of country size, so adding a much larger country this way (e.g. the US) doesn't need a `memory_limit` guard rail, unlike `GeoApiClient`, which fetches France department by department for the same reason (avoiding one large response).

Useful links:
- [GeoNames per-country dumps](https://download.geonames.org/export/dump/) — one `.zip` per ISO country code
- [GeoNames dump format (`readme.txt`)](https://download.geonames.org/export/dump/readme.txt) — column definitions, feature classes

Only countries with their own dedicated government API (like France's `geo.api.gouv.fr`) need a bespoke adapter mirroring `GeoApiClient`.

### Extending address search coverage

Photon already covers every country by design — it's built on OpenStreetMap data, which is worldwide. There's no "add a country" step for Address the way there is for City. What *can* change is index coverage: `docker/photon/entrypoint.sh`'s `INDEX_URL` currently points at a France+Monaco-scoped extract to keep the download small (~5GB); pointing it at a larger [GraphHopper Photon dump](https://download1.graphhopper.com/public/) (continental or planet-wide) trades index size/RAM/download time for broader coverage.

Useful links:
- [GraphHopper Photon index dumps](https://download1.graphhopper.com/public/) — planet-wide, continental, and per-country extracts
- [komoot/photon](https://github.com/komoot/photon) — the Photon project itself

Per `CLAUDE.md`, changing the external city data source strategy needs review/confirmation first.

## Operations

This repo documents local Docker Compose development in detail; it does **not** currently document or automate a production deployment target (no Kubernetes manifests, no CD pipeline — CI only lints/tests/scans, see `.github/workflows/ci.yaml`). The notes below are what you need to know if you deploy this yourself.

### Resource sizing (measured, not estimated)

| Service | Idle RAM | Disk |
|---|---|---|
| `app` (FrankenPHP) | ~150MB | — |
| `database` (Postgres) | ~90MB | grows with imported cities (France + Germany: low hundreds of MB) |
| `photon` (address search) | ~830MB | ~6.4GB (France+Monaco OSM index) |

Numbers are from `docker stats` on the France+Monaco index configuration. RAM for `photon` scales with index size — a larger [Photon index](#extending-address-search-coverage) (continental/planet-wide) needs proportionally more.

### Production Docker Compose

`docker-compose.yml` (base) declares no host ports for `database`/`photon` and no dev bind-mounts for `app`. Those dev conveniences (source bind-mount, xdebug, `8001`/`4444`/`5433`/`2322` published ports) live in `docker-compose.override.yml`, which plain `docker compose ...` picks up automatically — so `make up` and CI are unaffected.

For a real deployment, layer `docker-compose.prod.yml` explicitly instead, which switches `app` to the production Dockerfile, publishes only `80`/`443`, sets `APP_ENV=prod`/`APP_DEBUG=0`, and requires `APP_SECRET` to be set (fails loudly otherwise):

```bash
APP_SECRET=<your-secret> docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

Passing `-f` disables Compose's automatic `docker-compose.override.yml` pickup, so none of the dev-only ports/bind-mounts apply. This is also why `database`/`photon` end up unreachable from outside the Docker network in production — neither file publishes a host port for them, and `photon` has no authentication of its own, so it must never be exposed directly.

### Database migrations

Migrations are Doctrine Migrations (`migrations/`), run via `docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction` (what `make db-migrate` does locally). There is no automated migrate-on-deploy step in this repo — run it explicitly as part of whatever deploy process you build, before traffic hits the new version.

### Backup / restore (Postgres)

No backup automation exists in this repo. Manually, against the compose setup:

```bash
docker compose exec database pg_dump -U insee insee_city > backup.sql
docker compose exec -T database psql -U insee insee_city < backup.sql
```

### CORS

`CORS_ALLOW_ORIGIN` (see [Environment Variables](#environment-variables)) defaults to a localhost-only regex for dev. Set it to your actual production origin(s) when deploying — the default would reject every real browser client.

### Metrics

None beyond the structured `api_access` log (see [Observability](#observability)) — no `/metrics` endpoint, no APM integration currently.

## Main Commands

```bash
# Docker
make up
make down
make build
make rebuild
make install       # full setup: build + up + composer install + migrations

# Code quality
make lint          # PHP CS Fixer
make analyse       # PHPStan
make rector        # Rector
make quality       # lint + analyse + rector
make security      # Enlightn dependency vulnerability scan

# Tests
make tests-unit
make tests-integration
make tests-api     # Behat
make tests         # all PHPUnit suites, testdox output + coverage report (var/coverage)

# Database
make db-migrate
make db-reset
make import

# Utilities
make shell         # enter app container
make logs          # tail all container logs
make routes        # list Symfony routes
```

## Agent Docs

Project-specific agent instructions live in [CLAUDE.md](CLAUDE.md).
