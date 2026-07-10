---
paths:
  - "src/**"
  - "config/**"
---
# Architecture Rules

- Read `CLAUDE.md` first. It is the canonical source of repository-specific rules.
- Apply SOLID principles pragmatically.
- Prefer clean architecture and hexagonal boundaries when the repository is already structured that way.
- If API Platform already supports the required behavior cleanly, prefer the built-in API Platform feature over extra architectural layers.
- Keep Symfony entrypoints thin. Controllers, commands, and framework adapters should orchestrate, not decide.
- Keep business rules in handlers, use-cases, or domain services.
- Validate external input at the request or DTO boundary before side effects.
- Keep repositories focused on persistence. Do not hide business decisions inside queries or adapters.
- Mirror the local project shape instead of forcing a generic Symfony layout.
- Prefer incremental changes that reuse existing conventions over broad restructuring.
- Optimize first for readability and reviewability.
- If there is no measured performance issue, prefer the simpler and more readable solution.
- The final code should be easy for a human reviewer to understand quickly.
- Cross-cutting value objects shared by more than one bounded context (e.g. `CountryCode`) live in `src/Domain/Shared/`, not duplicated per-context.
