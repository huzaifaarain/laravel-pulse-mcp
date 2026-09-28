# Connecting the Pulse MCP Server

The package runs inside the production application. The developer's agent connects to it over HTTPS.

## On the server (production app)

1. Require the package and enable it:

   ```bash
   composer require huzaifaarain/laravel-pulse-mcp
   ```

   ```dotenv
   PULSE_MCP_ENABLED=true
   ```

2. Choose authentication with `PULSE_MCP_AUTH`:
   - `passport` (default): OAuth with dynamic client registration. Requires `laravel/passport` ^13 installed and configured (`php artisan passport:install`), an `api` guard using the `passport` driver, and `Laravel\Passport\HasApiTokens` on the user model.
   - `sanctum`: bearer personal access tokens. Requires `laravel/sanctum` and `Laravel\Sanctum\HasApiTokens`. Issue tokens with the `mcp:use` ability via `php artisan pulse-mcp:token {id-or-email} [--expires=30] [--model=...]`.

3. Authorize users. Access defaults to Pulse's `viewPulse` gate. Define `viewPulseMcp` to narrow it:

   ```php
   Gate::define('viewPulseMcp', fn (User $user) => $user->isAdmin());
   ```

4. Optionally publish the config (`php artisan vendor:publish --tag=pulse-mcp-config`) to change the path (`mcp/pulse`), throttle (`60,1`), limits, cache TTL, or redaction.

## On the developer machine

Run `php artisan pulse-mcp:client {claude|codex|cursor|vscode} --url=https://your-app.com/mcp/pulse` (anywhere the package is installed) and apply the printed configuration. For Claude Code with OAuth:

```bash
claude mcp add --transport http pulse https://your-app.com/mcp/pulse
```

The first call opens a browser to sign in to the production app and approve access.

## Troubleshooting

- `404`: `PULSE_MCP_ENABLED` is not true, or routes were cached before enabling it (`php artisan route:cache` again).
- `401`: the token is missing or expired. Re-authenticate the client.
- `403` "missing the mcp:use scope": the token lacks the `mcp:use` scope or ability.
- `403` "may not access Pulse data": the user fails the `viewPulseMcp` gate.
- `429`: the rate limit was hit. Wait, or raise `PULSE_MCP_THROTTLE`.
