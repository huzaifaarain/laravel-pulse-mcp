# Pulse MCP Tools

Every tool is read-only. `period` accepts `1h`, `6h`, `24h` or `7d` (default `24h`). `limit` defaults to 20 and is capped by the server (100 by default). List tools return `rows` and `truncated`, which is true when more rows exist.

| Tool | Answers | Inputs | Row fields |
|---|---|---|---|
| `pulse_health` | Can the data be trusted? | none | `pulse_enabled`, `ingest_driver`, `latest_entry_at`, `servers[]`, `warnings[]` |
| `pulse_overview` | Where should I look first? | `period` | `totals` per type, `top` 5 rows per card, `warnings` |
| `pulse_exceptions` | Which errors happen, and how often? | `period`, `sort` (`count`, `latest`), `limit`, `search` | `class`, `location`, `count`, `latest_at` |
| `pulse_slow_queries` | Which queries are slow? | `period`, `sort` (`slowest`, `count`), `limit`, `search` | `sql`, `location`, `count`, `slowest_ms`, `threshold_ms` |
| `pulse_slow_requests` | Which endpoints are slow? | same as above | `method`, `route`, `action`, `count`, `slowest_ms`, `threshold_ms` |
| `pulse_slow_jobs` | Which queued jobs are slow? | same as above | `job`, `count`, `slowest_ms`, `threshold_ms` |
| `pulse_slow_outgoing_requests` | Which third-party APIs are slow? | same as above | `method`, `uri`, `count`, `slowest_ms`, `threshold_ms` |
| `pulse_queues` | Are queues backing up or failing? | `period`, `queue`, `include_series` | `queue`, `queued`, `processing`, `processed`, `released`, `failed`, optional `series` |
| `pulse_cache` | Is caching effective? | `period`, `sort` (`hits`, `misses`), `limit`, `search` | `hits`, `misses`, `hit_rate`, `keys[]` |
| `pulse_servers` | Are servers under pressure? | `period`, `include_series` | `name`, `reporting`, `cpu_percent`, `memory_used_mb`, `memory_total_mb`, `storage[]`, `cpu` and `memory_mb` avg/peak |
| `pulse_usage` | Who generates the most load? | `period`, `type` (`requests`, `slow_requests`, `jobs`), `limit` | `user_id`, `name`, optional `email`, `count` |
| `pulse_types` | Which types exist, including custom cards? | none | `aggregate_types[]` (with `aggregates`), `value_types[]` |
| `pulse_aggregate` | Read any recorded type directly | `mode` (`aggregate`, `graph`, `values`), `types`, `aggregates`, `order_by`, `period`, `limit`, `keys` | `key`, optional `key_parts`, aggregate values, or `series` / `value` |

## Prompts

- `diagnose_performance` (`period`, `focus`): guided performance investigation.
- `triage_errors` (`period`): guided exception triage.

## `search`

A case-insensitive substring filter over the decoded fields, applied before `limit`. Use a table name, route, class, host or job name.
