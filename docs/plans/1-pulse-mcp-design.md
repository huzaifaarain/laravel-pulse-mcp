# huzaifaarain/laravel-pulse-mcp — Design & Build Plan

**Identity:**
- Install command: `composer require huzaifaarain/laravel-pulse-mcp`.
  - The `huzaifaarain/` vendor prefix is currently unused on Packagist. It will be claimed by the existing `muhammadhuzaifa` Packagist account on first submit.
  - Neither the GitHub nor the Packagist username changes.
- GitHub: `github.com/huzaifaarain/laravel-pulse-mcp`.
- PHP namespace: `HuzaifaArain\LaravelPulseMcp\` (PSR-4 → `src/`).
- `composer.json` author: `{ "name": "Huzaifa Saif-ur-Rehman", "homepage": "https://muhammadhuzaifa.pro", "role": "Developer" }`.
- `composer.json` package `homepage`: the GitHub repo.

## Context
Laravel Pulse records production performance data, but CLI agents like Claude Code and Codex can't reach it. Today I copy slow queries, exceptions and similar data from the dashboard into the agent by hand. Laravel's paid Nightwatch product already has an official OAuth MCP. **Self-hosted Pulse has nothing equivalent**; that gap is this project.
- `movinginfo/laravel-herd-mcp` only covers local Herd.
- `martinsoenen/pulse-mcp` does the reverse: it adds Pulse cards that monitor MCP servers.

**Deliverable:** a Composer package installed *into the production app*. It exposes Pulse data as read-only MCP tools on an HTTP endpoint. Authentication is Passport OAuth, with a Sanctum-token fallback, and access is gated per user.

A **bundled Laravel Boost skill** ships with the package, so that agents in the consuming app know when and how to use the Pulse MCP tools.

Sources studied, all cloned into `research/` (gitignored):
- `pulse/` v1.8.1
- `mcp/` v1.0.1
- `boost/` v2.10.0
- the `sunchayn/fresh-package` skeleton, read via the GitHub API

## Key findings that shape the design

**Pulse**
- **Read API is public:** `Laravel\Pulse\Contracts\Storage`, also reachable through the `Pulse` facade. Methods: `aggregate()`, `aggregateTypes()`, `aggregateTotal()`, `graph()` and `values()`.
  - The facade's `__call` wraps each call in `Pulse::ignore()`, so reads are not self-recorded (`pulse/src/Pulse.php:624`).
  - No Livewire is needed. Use the contract, not the `@internal` `DatabaseStorage`.
- **Only 4 windows work:** 1h, 6h, 24h and 7d (`DatabaseStorage.php:469`). Retention is capped at 7 days.
- **Keys are JSON tuples** that must be decoded:
  - `slow_query` → `[sql, location]`
  - `slow_request` → `[method, route, action]`
  - `slow_outgoing_request` → `[method, uri]`
  - `exception` → `[class, location]`, where `max` = latest occurrence timestamp
  - `slow_job` → class name
  - queues → `conn:queue`
  - usage → user id
- **Bucket-only types have no key in `aggregate()`.** Queues, `cpu` and `memory` must be read via `graph()`.
- **Server info:** `system` comes from `values('system')` as JSON. It counts as stale if older than 30s.
- **Exceptions store no message and no stack trace**, only class and location. This is a known limitation; it is documented and pointed to in the server instructions.
- **Sensitive data:**
  - SQL (without bindings, but inline literals stay)
  - full outgoing URLs including query strings
  - cache keys and user emails (via `ResolvesUsers`)
  - hostnames and disk paths
- **No auth on the read API.** The dashboard uses the `viewPulse` gate.
- **Freshness:** with the Redis ingest, the DB only updates while `pulse:work` runs. Server metrics only update while `pulse:check` runs.
- **Custom cards** write arbitrary `type` strings to the same three tables. That allows generic discovery (`SELECT DISTINCT type, aggregate`).

**Laravel MCP**
- A package can call `Mcp::web($path, Server::class)->middleware([...])` from its service provider.
- Tools are built from:
  - `schema(JsonSchema)`, `outputSchema()`, and `handle(Request)` with dependency injection
  - `Response::structured()` and `Response::error()`
  - `#[IsReadOnly]` and `#[IsIdempotent]`
  - `shouldRegister()`
- **OAuth:** `Mcp::oauthRoutes()` provides well-known metadata plus RFC 7591 dynamic client registration. It is Passport 13 only, with a single `mcp:use` scope.
  - A 401 automatically adds `WWW-Authenticate` pointing at the resource metadata.
  - Sanctum works via `auth:sanctum` with a bearer PAT, but without discovery.
- **Testing:** `MyServer::actingAs($u)->tool(Tool::class, [...])->assertStructuredContent(...)`, plus `mcp:inspector`.
- **Hard floor:** laravel/mcp 1.x needs **PHP 8.2+ and Laravel 12.41.1+**, because of `illuminate/json-schema`. The package inherits this; Laravel 11 apps are out.

**Laravel Boost v2.10.0** (from reading `boost/src`)
- **Third-party skill discovery:** `ThirdPartyPackage::skillDirectories()` (`boost/src/Install/ThirdPartyPackage.php`) finds `vendor/<pkg>/resources/boost/skills/*/`. The resolved path comes from `PackageRegistry::boostPath()`. Discovery only includes packages that are:
  - **direct** dependencies of the app, and
  - **not first-party** (`laravel/*`, livewire etc. are excluded).
- **Skill format:** `SkillComposer::parseSkill()` accepts `SKILL.blade.php` (rendered through Boost's Blade pipeline) or `SKILL.md`.
  - YAML frontmatter **must** have `name` and `description`, otherwise the skill is silently dropped or recorded as a parse failure.
  - Skills are keyed by `name`, so it must be globally unique.
- **Third-party guidelines:** `resources/boost/guidelines/*.blade.php`, keyed `<package>/<file>`.
- **Opt-in:** third-party skills and guidelines are only installed if the user selects the package during `boost:install`. `GuidelineConfig::$aiGuidelines` filters them.
- **MCP config:** Boost cannot write our MCP server into agent configs. `McpWriter::write()` only installs `laravel-boost`, plus a hard-coded Nightwatch HTTP MCP when `laravel/nightwatch` is installed (`boost/src/Install/Nightwatch.php`, `McpWriter.php`).
  - So the package must provide its own client-setup helper.
  - Longer term: an upstream PR to Boost adding a generic "third-party HTTP MCP" hook.

**Skeleton: `sunchayn/fresh-package`** (Mazen Touati), installed with `composer create-project`, configured through `php .template/init template:init`.
- **Tooling:** PHP `^8.3`, Laravel 12/13, PHPUnit 11 + Paratest, Larastan, Pint, Rector, all configured under `./tools/`.
- **Workbench:** a Testbench workbench (`composer serve`) and the `switch:l12` / `switch:l13` version switchers.
- **CI:** GitHub Actions for tests, static analysis, code-fix and release-please.
- **Layout:** domain-based `src/Modules/<Domain>/{Actions,DataTransferObjects,Enums}`, plus `src/Http/<Domain>/…`.
- **Boost support:** the `boost_skill` choice adds `resources/boost/skills/<name>/SKILL.md` and a maintainer skill, `package-generate-skill`. That skill keeps the bundled skill in sync with the code.
- **Constraints this adds:**
  - The PHP floor becomes **8.3**.
  - Tests use **PHPUnit, not Pest**.
  - Releases go through **release-please**, not manual tags.

## Architecture

```
MCP client (Claude Code / Codex)
  │  HTTPS + Bearer (Passport OAuth via DCR, or Sanctum PAT)
  ▼
POST /mcp/pulse  ── middleware: auth:<guard>, throttle, EnsureCanViewPulseMcp (gate + mcp:use scope)
  ▼
PulseServer (Laravel\Mcp\Server) ── read-only tools
  ▼
PulseRepository  (period → CarbonInterval, key decoding, limits, caching)
  ├─ Laravel\Pulse\Contracts\Storage   (aggregate / graph / values)
  ├─ ResolvesUsers                     (usage tool)
  └─ Redactor                          (config-driven masking)
```

**Namespace:** `HuzaifaArain\LaravelPulseMcp`

### Package layout
- `config/pulse-mcp.php` holds the following settings:
  - `enabled`: default `false`, via `PULSE_MCP_ENABLED`, so the package is off in production until opted in.
  - `path`: `mcp/pulse`.
  - `auth`: `passport` | `sanctum`, plus the `guard`.
  - `oauth_routes`: bool, calls `Mcp::oauthRoutes()`. Default is true when Passport is present.
  - `gate`: `viewPulseMcp`, which by default delegates to `viewPulse`.
  - `middleware` extra and `throttle` (`60,1`).
  - `cache_ttl`: 10s.
  - `limits`: default 20, max 100.
  - `redact`: see the Redactor section below.
- File locations follow the skeleton's domain layout:
  - `src/Modules/Pulse/`: `Actions/` (queries), `DataTransferObjects/` (decoded rows), `Enums/Period`, `Support/{PulseRepository,Redactor}`
  - `src/Mcp/{Servers,Tools,Prompts}`
  - `src/Http/Middleware`
  - `src/Console/Commands`

  Below, the paths are shortened.
- `src/PulseMcpServiceProvider.php` registers config, the gate definition (`viewPulseMcp` falls back to `Gate::allows('viewPulse')`), the route through `Mcp::web()` when enabled, and the optional `Mcp::oauthRoutes()`.
  - It also recommends adding the MCP path to Pulse's `ignore` list, so agent traffic doesn't skew `slow_request`/`user_request`.
- `src/Http/Middleware/EnsureCanViewPulseMcp.php`:
  - Checks the gate.
  - For Passport tokens, checks `tokenCan('mcp:use')`.
  - Returns 403 otherwise.
- `src/Server/PulseServer.php` sets `#[Name]`, `#[Version]` and `#[Instructions]` and lists the tools. The instructions cover:
  - periods and units (ms)
  - "call `pulse_overview` first"
  - no exception messages or traces (check logs)
  - data is sampled and threshold-based
- `src/Support/PulseRepository.php` is the single wrapper over `Storage`:
  - `period(string): CarbonInterval`, accepting only the enum `1h|6h|24h|7d`
  - per-type decoders
  - `Cache::remember` keyed by tool and arguments
- `src/Support/Redactor.php`, config-driven:
  - strips query strings from outgoing URLs (keeping the parameter names)
  - regex masking of cache keys
  - hides user emails and avatars (`include_user_email` defaults to false)
  - optional SQL literal masking, e.g. `'...'` → `?`
  - `max_sql_length`
- `src/Tools/*`: every tool is `#[IsReadOnly] #[IsIdempotent]` and returns `Response::structured()` with an `outputSchema()`.
- `php artisan pulse-mcp:client {agent=claude|codex|cursor|vscode} {--url=}` prints the exact client registration for the production URL, e.g. `claude mcp add --transport http pulse https://app.com/mcp/pulse` or a Codex `config.toml` block.
  - It only prints; it does not write agent config. This fills the gap Boost can't cover (see above).

### Boost skill (bundled with the package)
- **Location:** `resources/boost/skills/pulse-production-monitoring/SKILL.md`. The name is unique so it can't collide with other packages' skills.
- **Frontmatter:** `name` and `description` (both required by Boost), `license: MIT`, `metadata.author`.
  - The description should trigger on things like "production errors", "slow queries in prod", "what's slow", "Pulse", "performance regression" or "queue backlog".
- **Body**, following the skeleton's structure (goal → workflow → references → examples → anti-patterns):
  1. **Prerequisite check:** is a `pulse` MCP server connected? If not, tell the user to run `php artisan pulse-mcp:client claude`. Don't try to read Pulse through the local DB.
  2. **Investigation workflow:**
     - start with `pulse_health`, which catches stale data or a stopped `pulse:work`/`pulse:check`
     - then `pulse_overview` with the right period
     - then drill into the specific tool
     - map each `location` (`path:line`) to the local code, read it, then propose a fix
  3. **Semantics:**
     - only the periods 1h/6h/24h/7d exist
     - durations are in ms; rows are threshold-based and sampled
     - `exception` has no message or trace, so ask the user for logs, or check `storage/logs` locally only if the data is reproducible
     - SQL has no bindings
  4. **Tool catalog:** a one-line purpose for each tool, and which one answers which question.
  5. **Safety:** the tools are read-only production data. Don't repeat redacted values or guess secrets. Keep call volume low, because the endpoint is throttled.
  6. **Anti-patterns:** e.g. asking for periods like "30m", treating counts as exact totals, or assuming a local DB mirrors production.
- `references/tools.md`: per-tool input and output reference, kept in sync by the skeleton's `package-generate-skill` maintainer skill.
- **Optional:** `resources/boost/guidelines/core.blade.php`, a three-line always-loaded pointer ("this app exposes production Pulse via the `pulse` MCP; use the pulse-production-monitoring skill").
- **README step for consumers:** after `composer require`, run `php artisan boost:install` (or `boost:update`) and tick `huzaifaarain/laravel-pulse-mcp`. The package must be a **direct** `require` for Boost to find it.

### Tools (v1)
| Tool | Source | Inputs |
|---|---|---|
| `pulse_overview` | `aggregateTotal` + top-5 of each card + health | `period` |
| `pulse_slow_queries` | `aggregate('slow_query',['max','count'])` | period, sort `slowest\|count`, limit, `search` |
| `pulse_exceptions` | `aggregate('exception',['max','count'])` | period, sort `count\|latest`, limit, search |
| `pulse_slow_requests` | `aggregate('slow_request',…)` | period, sort, limit, search (route/method) |
| `pulse_slow_jobs` | `aggregate('slow_job',…)` | period, sort, limit, search |
| `pulse_slow_outgoing_requests` | `aggregate('slow_outgoing_request',…)` | period, sort, limit, search (host) |
| `pulse_queues` | `graph([queued,processing,processed,released,failed],'count')` | period, `queue?`, `include_series` (default false → totals + trend summary) |
| `pulse_cache` | `aggregateTotal` + `aggregateTypes(['cache_hit','cache_miss'])` | period, limit |
| `pulse_servers` | `values('system')` + `graph(['cpu','memory'],'avg')` | period, include_series |
| `pulse_usage` | `aggregate(user_request\|slow_user_request\|user_job,'count')` + `ResolvesUsers` | period, `type`, limit |
| `pulse_health` | ingest driver, newest entry/`system` timestamp, stale flags | — |
| `pulse_types` | distinct `type`/`aggregate` from the Pulse tables (custom cards) | — |
| `pulse_aggregate` | generic `aggregate`/`aggregateTypes`/`values` for any discovered type | type(s), aggregates, period, order, limit |

**Output conventions:**
- Durations are in ms and timestamps are ISO-8601.
- Each row includes a `threshold_ms` taken from the recorder config, via `Pulse::recorders()`.
- Results are capped by `limits.max`, and a `truncated` flag is set when the cap is hit.

**Prompts (v1):** `diagnose_performance` and `triage_errors`. These are canned instructions that chain the tools.

## Repo & task management
- **Remote:** `git@github.com:huzaifaarain/laravel-pulse-mcp.git`. It is currently private and empty; `main` becomes the default branch on the first push.
- **Local:** generate the package from the skeleton into `~/workspace/pulse-mcp`:
  - `composer create-project sunchayn/fresh-package` into a temp dir, then move it in, keeping the research clones.
  - Then `php .template/init template:init --no-interaction --package-name=huzaifaarain/laravel-pulse-mcp --vendor-namespace=HuzaifaArain …`.
  - The exact flag names are to be confirmed from `TemplateInitCommand`'s signature before running.
  - Target namespace: `HuzaifaArain\LaravelPulseMcp`, class `PulseMcp`.
  - Then `git init`, add the remote and push `main`.
- **Skeleton choices:**
  - **Select:** config, commands, workbench, ai_support, boost_skill, auto-release (release-please), dependabot, issue template, security policy.
  - **Decline:** routes (the route is registered with `Mcp::web()` in the provider), migrations, facade, assets, translations, blade, vue, funding.
- Move the `pulse/`, `mcp/` and `boost/` clones into `research/`. It is gitignored and kept only for reference.
- **Conflict with the skeleton's `.ai/GUIDELINES.md`:** it says plans go in `.ai/artifacts/`. Your baseline rule, plans in `docs/plans/`, wins, and I'll edit that line in the generated `.ai/GUIDELINES.md`.
- **Tracking:** GitHub milestone `v0.1.0`. Issues are created in the order below so the numbers come out predictable.
  - Each issue body holds its checklist and acceptance criteria.
  - Commits go to `main` with a Conventional Commit message and `Closes #n`.
- **This plan** is committed as `docs/plans/1-pulse-mcp-design.md`, linked to issue #1.

### Issues (milestone v0.1.0)
1. **Design & research:** commit this plan document. Label: `documentation`.
2. **Scaffold from fresh-package:** `enhancement`.
   - Run `template:init` with the choices above.
   - Set the `composer.json` name/author/homepage as above.
     - Require `php ^8.3`, `illuminate/support ^12.41.1||^13.0`, `laravel/pulse ^1.4`, `laravel/mcp ^1.0`.
     - Suggest `laravel/passport ^13` and `laravel/sanctum`.
   - Verify the CI matrix still passes against the laravel/mcp floor (L12.41.1+).
   - `declare(strict_types=1)` in every PHP file. Pint, Rector and Larastan must be green.
3. **Core read layer:** `PulseRepository` (periods, caching, limits), the key decoders, and `Redactor`.
4. **MCP server and meta tools:** `PulseServer` with its instructions, plus `pulse_overview`, `pulse_health` and `pulse_types`.
5. **"Slow" and exception tools:** slow queries, exceptions, slow requests, slow jobs, slow outgoing requests.
6. **Operational tools:** queues, cache, servers, usage (with `ResolvesUsers` and the email redaction).
7. **Generic custom-type tool:** `pulse_aggregate`, for cards from third-party packages.
8. **Prompts:** `diagnose_performance` and `triage_errors`.
9. **Auth, Passport OAuth:**
   - `Mcp::oauthRoutes()` and DCR.
   - Instructions for publishing and wiring the consent view.
   - The `mcp:use` scope check.
   - An end-to-end run with Claude Code.
10. **Auth, Sanctum fallback and access gate:** the `viewPulseMcp` gate, `EnsureCanViewPulseMcp`, and the `auth` config switch.
11. **Hardening and client setup:**
    - throttle, `enabled` flag (default off), cache TTL, and the Pulse ignore for the MCP path
    - the `pulse-mcp:client` command
12. **Boost skill:**
    - `resources/boost/skills/pulse-production-monitoring/SKILL.md`, `references/tools.md`, and the optional guideline.
    - Verify in a real app that `boost:install` lists the package and writes the skill to `.claude/skills/`.
13. **Docs and release:**
    - README covering:
      - install and both auth modes
      - Claude Code and Codex client setup
      - the Boost step
      - the security model and limitations
    - Make the repo **public**, which Packagist requires.
    - Merge the release-please PR to cut `v0.1.0`, submit to Packagist, and confirm the GitHub webhook.

## Verification
- Use the workbench app with Pulse + SQLite. Seed it with `Pulse::record(...)`/`Pulse::set(...)` entries across all types, plus a custom type.
- Run `php artisan mcp:inspector` against the HTTP server:
  - list the tools
  - call each tool for each period
  - confirm the redaction output
- Check the auth paths:
  - An unauthenticated request returns 401 with a `WWW-Authenticate` resource_metadata header.
  - A user who fails the gate gets 403.
  - With `enabled=false`, the route returns 404.
- Point real Claude Code at a local Laravel 12 app with Passport and go through the full OAuth flow (DCR → consent → token → tool calls). Repeat with a Sanctum PAT.
- Run Pint and PHPStan on the changed files.
- Boost check: in a consuming app, run `composer require` via a path repo, then `php artisan boost:install`.
  - Confirm the package appears in the third-party list and the skill lands in `.claude/skills/pulse-production-monitoring/`.
  - Confirm Claude Code picks it up for "what's slow in production?".
- The skeleton comes with a PHPUnit suite, and CI runs it. Writing new PHPUnit tests with laravel/mcp's `Server::actingAs()->tool()` helpers is planned, but only if you ask.

## Open items / known limitations
- Exceptions have no message or stack trace; this is a Pulse limitation. A possible v2 is an opt-in tool that reads the latest matching log entry.
- Laravel 11 is unsupported, because laravel/mcp requires 12.41.1+. PHP 8.3+ is required by the skeleton.
- Boost can't auto-register the MCP server in agent configs; `pulse-mcp:client` covers this for now. A possible upstream Boost PR could add a generic third-party HTTP MCP hook.
- Packagist cannot index the repo while it is private. Making it public is part of issue #13.
