# Claude Guide

Canonical agent instructions. Always-loaded core only — detailed, path-scoped
rules live in `.claude/rules/*` and load automatically when you edit matching
files. Don't restate rule-file content here.

## Project

- Symfony/API Platform API for city data (any country, ISO 3166-1 alpha-2) and address autocomplete. PHP 8.5, Symfony 7.4, API Platform 4.x, PostgreSQL, FrankenPHP.
- Primary workflows: import city data from multiple country-specific providers (write) and expose read-only city search/lookup plus address autocomplete (read).
- Preserve API version prefix `/api/v1`.

## Architecture

- `Application`: use-case orchestration. `Domain`: business models, domain exceptions, ports. `Infrastructure`: Doctrine/API clients/runtime adapters. `UI`: entrypoints (console commands, controllers).
- Write flow stays layered and explicit: `UI Command → Application Handler → Domain Model → Domain Port → Infrastructure Adapter`.
- Read flow is API Platform native: City goes `API Platform → Provider → Doctrine ORM → App\Entity\City`; Address has no persistence — it's a live `API Platform → Provider → external HTTP call (Photon) → Address domain model` passthrough. Read resources live in `src/UI/ApiResource/`, decoupled from Doctrine; providers under `src/Infrastructure/Http/Provider/` map entities/domain models to resources. Prefer this Provider pattern over a custom controller for new read-only endpoints.
- If API Platform already supports the required behavior cleanly, prefer the built-in feature over adding a custom layer.

## Always

- `declare(strict_types=1);` in every file
- Tests for behavior changes, in the same session — see `.claude/rules/testing.md`
- Run verification after changes
- Fix PHPStan in code/types/PHPDoc, not by loosening `phpstan.neon`
- Prefer readability and reviewability over premature optimization; surgical changes over opportunistic refactors

## Ask first (no harness guard — you are the only gate)

- adding composer packages
- changing the PostgreSQL schema
- changing the external city data source strategy
- changing `phpstan.neon`
- `git commit` — confirm in the current conversation first
- editing `.claude/settings.json` permissions or deleting a tracked directory — confirm that specific action on its own, even when it's part of a broader task the user already approved

## Never

- put business logic in controllers or framework entrypoints
- commit directly to `main`, `master`, or `develop`

> `git push`, commits to protected branches, secret/`.env` writes, and
> out-of-project writes are blocked at the permission layer (`.claude/settings.json`
> + hooks), not by asking nicely. If one of these gets denied, don't retry —
> tell the user to run it directly (`! <command>`) or adjust `settings.json`.

## Detail (open on demand)

- `.claude/rules/architecture.md` — layering, SOLID, entrypoint boundaries
- `.claude/rules/testing.md` — test scope, naming, paths, coverage expectations
- `.claude/rules/security.md` — secrets, AI/MCP policy, static-analysis stance
- `.claude/patterns.md`

## Workflows (skills)

scan-project · new-feature · bug-fix · review-change · security-review ·
verify-quality · prepare-commit · improve-instructions · karpathy-guidelines.
Same workflows are also available as `.claude/commands/symfony/*` for explicit invocation.
Deep review agents: `.claude/agents/{qa,security}-reviewer.md`.

## API

`GET /api/v1/cities` and `GET /api/v1/cities/{countryCode}/{localCode}` — filters: `name` (partial), `exactName`, `countryCode`, `departmentCode`, `regionCode` (exact). `postalCode`/`departmentCode`/`regionCode` are `?string`, never `""`. `GET /api/v1/addresses/search` — `q` (required), `countryCode`, `limit` (1-20), plain JSON only. Errors are RFC 7807 `application/problem+json`. Full reference (pagination, response shape, rate limiting, observability headers): `README.md`.

## Verification

`make lint` · `make analyse` · `make rector` · `make tests-unit` · `make tests-integration` · `make tests-api` · `make security`
Preferred full pass: `make quality` · `make tests` · `make tests-api` · `make security`

## Pull Request Process

When asked to create a PR: don't push (assume already pushed), use `gh pr create` against `main`, block if the current branch is `main`/`master`/`develop`, has no commits ahead, or doesn't exist on the remote. Title from the branch's Conventional Commit. Body: what changed, why, how, what was verified, remaining risks.

## AI Tool & MCP Policy

MCP servers are blocked project-wide (`allowedMcpServers: []`) — any exception needs explicit team approval added to `settings.json`. Full policy: `.claude/rules/security.md` rules 10-14.

## Instruction-file policy

Living docs. Change only on durable evidence of drift (repeated corrections,
Makefile/composer/structure changes, conventions that changed in practice,
duplication). Propose first, apply only after explicit confirmation. No
one-off/local context.
