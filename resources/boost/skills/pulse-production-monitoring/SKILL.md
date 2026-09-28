---
name: pulse-production-monitoring
description: >
  Investigate this Laravel application's production performance and errors through the Laravel Pulse MCP
  server (huzaifaarain/laravel-pulse-mcp). Use when the user asks what is slow or failing in production,
  about slow queries, slow requests or endpoints, slow or failed jobs, queue backlogs, exceptions, cache hit
  rates, server CPU/memory, heavy users, performance regressions, or anything "in Pulse". Also use to set up
  or troubleshoot the Pulse MCP connection.
license: MIT
metadata:
  author: Huzaifa Saif-ur-Rehman
---

# Pulse Production Monitoring

## Primary Goal

Answer production questions with evidence from Laravel Pulse, then trace each finding to the code in this repository and propose a fix, without asking the user to copy data from the Pulse dashboard.

## Workflow

### 1. Confirm the connection

- Look for the `pulse_*` tools (for example `pulse_overview`) from an MCP server usually named `pulse`.
- If they are missing, do not query a local database: local data does not mirror production. Tell the user to connect the production app, following [references/setup.md](references/setup.md), for example `php artisan pulse-mcp:client claude` on the server.

### 2. Check freshness

- Call `pulse_health` first. Report its `warnings` before drawing conclusions: a stopped `pulse:work` (Redis ingest) or `pulse:check` means missing or stale data.

### 3. Get the overview, then drill down

- Call `pulse_overview` with a period matching the question (`1h`, `6h`, `24h`, `7d`; default `24h`).
- Drill into the worst area with the specific tool. See [references/tools.md](references/tools.md) for inputs and outputs.
- Use `search` to connect evidence, for example a slow route (`pulse_slow_requests`) to the queries it runs (`pulse_slow_queries` with the table or model name).
- For custom or third-party Pulse cards, call `pulse_types`, then `pulse_aggregate`.

### 4. Trace to the code

- `location` values are `path:line` relative to the application root. Open them, and open controller actions from `pulse_slow_requests`, before judging a cause.
- Rank findings by impact (`slowest_ms` × `count`, or exception `count` and `latest_at`).

### 5. Report

- For each issue: the Pulse evidence, the root cause in code, and a concrete fix (index, eager loading, caching, chunking, queueing, timeout or retry settings).
- Say which period the numbers cover.

## Rules

- Only the periods `1h`, `6h`, `24h` and `7d` exist. Pulse keeps at most 7 days.
- Durations are milliseconds. Slow entries are recorded only above `threshold_ms`, and recorders may sample, so counts are indicative, not exact totals.
- Exceptions carry class, location, count and latest time only. Pulse stores no message or stack trace; ask the user for the log entry when the code does not explain it.
- SQL never contains bindings. `'?'`, `?` and `[redacted]` are masked values; never guess or reconstruct them.
- The tools are read-only and rate limited. Prefer one overview plus targeted calls over sweeping every tool and period.

## Examples

- "What's slow in production?" → `pulse_health`, `pulse_overview` (`24h`), then `pulse_slow_requests` and `pulse_slow_queries` for the top items, then read the code at each `location`.
- "Why is checkout failing?" → `pulse_exceptions` with `search: "checkout"` (or the relevant class), `sort: "latest"`, then open each `location`.
- "Are queues backing up?" → `pulse_queues` (`include_series: true`) and `pulse_slow_jobs`, comparing `queued` with `processed` and `failed`.
- "Did yesterday's deploy make things worse?" → compare `pulse_overview` for `6h` against `24h` or `7d`.

## Anti-Patterns

- Requesting periods like `30m` or `2d`.
- Treating Pulse counts as exact totals or as complete coverage of all requests.
- Querying the local database or local Pulse tables as if they were production.
- Proposing fixes from Pulse data alone without reading the code at `location`.
- Repeating or guessing redacted values, tokens, or user data.
