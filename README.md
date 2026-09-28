# Laravel Pulse MCP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/huzaifaarain/laravel-pulse-mcp.svg?style=flat-square)](https://packagist.org/packages/huzaifaarain/laravel-pulse-mcp)
[![Tests](https://img.shields.io/github/actions/workflow/status/huzaifaarain/laravel-pulse-mcp/php-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/huzaifaarain/laravel-pulse-mcp/actions/workflows/php-tests.yml)
[![License](https://img.shields.io/packagist/l/huzaifaarain/laravel-pulse-mcp.svg?style=flat-square)](LICENSE.md)
[![PHP Version](https://img.shields.io/packagist/php-v/huzaifaarain/laravel-pulse-mcp.svg?style=flat-square)](https://packagist.org/packages/huzaifaarain/laravel-pulse-mcp)

Let AI agents like Claude Code, Codex, Cursor and VS Code read your production [Laravel Pulse](https://pulse.laravel.com) data through a secure, read-only [MCP](https://modelcontextprotocol.io) server. You no longer need to copy slow queries, exceptions or queue stats out of the dashboard by hand.

```text
You:    What's slow in production today?
Agent:  pulse_health → pulse_overview → pulse_slow_requests → pulse_slow_queries
        GET /orders averages 3.1s. OrderController.php:31 runs an unindexed
        orders lookup by customer_id and status (2.4s, 2 occurrences)...
```

The package runs inside your application. Agents connect to it over HTTPS with **OAuth** (Laravel Passport, including dynamic client registration and a browser consent screen) or with **Sanctum** personal access tokens. Only users you authorize can read the data.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Authentication](#authentication)
- [Connecting an agent](#connecting-an-agent)
- [Tools and prompts](#tools-and-prompts)
- [Laravel Boost skill](#laravel-boost-skill)
- [Configuration](#configuration)
- [Security model](#security-model)
- [Limitations](#limitations)

## Requirements

- PHP 8.3+
- Laravel 12.41.1+ or 13 (the minimum comes from `laravel/mcp`)
- `laravel/pulse` 1.4+ with database storage
- `laravel/passport` 13+ (OAuth) or `laravel/sanctum` 4+ (tokens)

## Installation

```bash
composer require huzaifaarain/laravel-pulse-mcp
```

The endpoint stays off until you enable it, typically only in production:

```dotenv
PULSE_MCP_ENABLED=true
```

It is then served at `POST /mcp/pulse`. If you cache routes, run `php artisan route:cache` again after enabling it.

## Authentication

Choose a driver with `PULSE_MCP_AUTH`. Either way, a user must pass the `viewPulseMcp` gate (see [Authorizing users](#authorizing-users)).

### OAuth with Passport (default)

This is the best experience: the agent opens a browser, you sign in to your app, and you approve access. No tokens need to be copied.

1. Install and configure [Passport](https://laravel.com/docs/passport) 13:

   ```bash
   composer require laravel/passport
   php artisan passport:install
   ```

2. Add `Laravel\Passport\HasApiTokens` to your `User` model, implement `Laravel\Passport\Contracts\OAuthenticatable`, and add an `api` guard with the `passport` driver in `config/auth.php`.

3. The package registers everything else:
   - OAuth discovery at `/.well-known/oauth-protected-resource` and `/.well-known/oauth-authorization-server`
   - dynamic client registration at `POST /oauth/register`
   - the `mcp:use` scope

If your application has no Passport authorization view, the package provides a self-contained consent screen. To customise it, publish it with `php artisan vendor:publish --tag=pulse-mcp-views`, or set your own with `Passport::authorizationView()`. Signing in uses your application's normal login.

### Sanctum tokens

```dotenv
PULSE_MCP_AUTH=sanctum
```

Add `Laravel\Sanctum\HasApiTokens` to your `User` model, then issue a token with the `mcp:use` ability:

```php
$token = $user->createToken('pulse-mcp', ['mcp:use'])->plainTextToken;
```

### Authorizing users

Access defaults to Pulse's own `viewPulse` gate, so dashboard viewers can connect. Define `viewPulseMcp` to narrow it:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewPulseMcp', fn (User $user) => $user->is_admin);
```

## Connecting an agent

The package prints the exact client configuration for you:

```bash
php artisan pulse-mcp:client claude --url=https://your-app.com/mcp/pulse
php artisan pulse-mcp:client codex
php artisan pulse-mcp:client cursor
php artisan pulse-mcp:client vscode
```

**Claude Code (OAuth):**

```bash
claude mcp add --transport http pulse https://your-app.com/mcp/pulse
```

**Codex (OAuth):**

```bash
codex mcp add pulse --url https://your-app.com/mcp/pulse
codex mcp login pulse
```

**Sanctum:** send the token as a header, for example:

```bash
claude mcp add --transport http pulse https://your-app.com/mcp/pulse --header "Authorization: Bearer <token>"
```

## Tools and prompts

All tools are read-only and idempotent. `period` is one of `1h`, `6h`, `24h` or `7d`, which are the windows Pulse aggregates. The default is `24h`.

| Tool | Purpose |
|---|---|
| `pulse_health` | Freshness check: recording status, ingest driver, latest entry, reporting servers, and warnings about stopped `pulse:work` / `pulse:check` |
| `pulse_overview` | Totals plus the top 5 rows of every card. Start here |
| `pulse_exceptions` | Exceptions by class and location, with count and latest occurrence |
| `pulse_slow_queries` | Slow SQL with code location, count, slowest duration and threshold |
| `pulse_slow_requests` | Slow routes with controller action |
| `pulse_slow_jobs` | Slow queued jobs |
| `pulse_slow_outgoing_requests` | Slow third-party HTTP calls |
| `pulse_queues` | Queued, processing, processed, released and failed jobs per queue, with an optional series |
| `pulse_cache` | Hit/miss totals, hit rate and the busiest keys |
| `pulse_servers` | CPU, memory and disk per server, with period averages and peaks |
| `pulse_usage` | Top users by requests, slow requests or jobs |
| `pulse_types` | Every recorded type, including custom and third-party cards |
| `pulse_aggregate` | Generic reader for any type, in `aggregate`, `graph` or `values` mode |

The list tools accept `limit`, `sort` and a case-insensitive `search` filter, and report `truncated` when more rows exist.

Prompts: `diagnose_performance` (`period`, `focus`) and `triage_errors` (`period`) give agents a guided investigation.

## Laravel Boost skill

The package ships a [Laravel Boost](https://github.com/laravel/boost) skill, `pulse-production-monitoring`, plus a short guideline. Together they teach agents in your codebase when to reach for the Pulse tools, how to investigate, and how to trace each finding back to your code. Install it with:

```bash
php artisan boost:install
```

Select `huzaifaarain/laravel-pulse-mcp` when asked for third-party packages. The package must be a direct dependency in your `composer.json`.

## Configuration

Publish the config file with:

```bash
php artisan vendor:publish --tag=pulse-mcp-config
```

| Key | Env | Default | Description |
|---|---|---|---|
| `enabled` | `PULSE_MCP_ENABLED` | `false` | Registers the endpoint |
| `path` | `PULSE_MCP_PATH` | `mcp/pulse` | Endpoint path |
| `auth.driver` | `PULSE_MCP_AUTH` | `passport` | `passport` or `sanctum` |
| `auth.guard` | `PULSE_MCP_GUARD` | `api` / `sanctum` | Auth guard for the route |
| `oauth.routes` | `PULSE_MCP_OAUTH_ROUTES` | `true` | OAuth discovery and client registration routes |
| `oauth.authorization_view` | | `pulse-mcp::authorize` | Consent view used when the app has none |
| `gate` | | `viewPulseMcp` | Gate every user must pass |
| `middleware` | | `[]` | Extra route middleware |
| `throttle` | `PULSE_MCP_THROTTLE` | `60,1` | Rate limit, `max,minutes`; empty disables it |
| `ignore_own_requests` | | `true` | Keeps agent calls out of Pulse's request and usage cards |
| `limits.default` / `limits.max` | | `20` / `100` | Rows per tool call |
| `limits.search_scan` | | `1000` | Rows scanned before a `search` filter |
| `cache_ttl` | `PULSE_MCP_CACHE_TTL` | `10` | Seconds to cache Pulse queries; `0` disables it |
| `redact.mask_sql_literals` | | `true` | Masks string and numeric literals in SQL |
| `redact.max_sql_length` | | `2000` | Truncates long SQL |
| `redact.strip_url_query_values` | | `true` | Masks outgoing URL query values, keeping the names |
| `redact.include_user_email` | | `false` | Includes emails in `pulse_usage` |
| `redact.cache_key_patterns` | | `[]` | Regexes masked in cache keys |

## Security model

- **Off by default.** Nothing is exposed until `PULSE_MCP_ENABLED=true`.
- **Authenticated and authorized.** Every request needs a valid token that carries the `mcp:use` scope or ability, and the user must pass `viewPulseMcp`.
- **Read-only.** No tool writes, purges or changes Pulse or application data.
- **Redacted by default.** SQL literals, URL query values and user emails are masked, and you can add patterns for cache keys. Pulse never stores SQL bindings in the first place.
- **Rate limited and cached.** A throttle and a short query cache protect the database from agent loops.
- **Invisible to Pulse.** Pulse does not record its own reads, and agent requests are excluded from its request cards.

## Limitations

- Pulse keeps at most 7 days of data and only aggregates the 1h, 6h, 24h and 7d windows.
- Pulse records slow entries only above each recorder's threshold, and it may sample, so counts are indicative.
- Exceptions include class, location and counts, but no messages or stack traces, because Pulse does not store them.
- With Redis ingest, data reaches the database only while `pulse:work` runs, and server metrics need `pulse:check`. `pulse_health` warns about both.

## Development

```bash
composer test       # PHPUnit
composer analyse    # Larastan
composer style:fix  # Pint
composer rector     # Rector
```

## Links

- [Changelog](CHANGELOG.md)
- [Contributing guide](.github/CONTRIBUTING.md)
- [Security policy](.github/SECURITY.md)

## Credits

- [Huzaifa Saif-ur-Rehman](https://muhammadhuzaifa.pro)
- [All contributors](../../contributors)

## License

Laravel Pulse MCP is open-source software licensed under the [MIT license](LICENSE.md).
