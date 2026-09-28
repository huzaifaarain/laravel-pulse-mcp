<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Pulse MCP Server
    |--------------------------------------------------------------------------
    |
    | The MCP endpoint exposes production monitoring data, so it stays off
    | until explicitly enabled for an environment.
    |
    */

    'enabled' => env('PULSE_MCP_ENABLED', false),

    'path' => env('PULSE_MCP_PATH', 'mcp/pulse'),

    /*
    |--------------------------------------------------------------------------
    | Authentication & Authorization
    |--------------------------------------------------------------------------
    |
    | "passport" enables OAuth with dynamic client registration, so MCP clients
    | such as Claude Code can sign in through the browser. "sanctum" accepts
    | personal access tokens instead. Tokens need the `mcp:use` scope/ability
    | (Sanctum's default `*` ability satisfies it), and the user must pass
    | the gate below, which defaults to Pulse's own `viewPulse` gate.
    |
    */

    'auth' => [
        'driver' => env('PULSE_MCP_AUTH', 'passport'),

        // Defaults to "api" for Passport and "sanctum" for Sanctum.
        'guard' => env('PULSE_MCP_GUARD'),
    ],

    'oauth' => [
        // Register the OAuth discovery and client registration routes.
        'routes' => env('PULSE_MCP_OAUTH_ROUTES', true),

        // Consent screen used when the application has not set its own Passport authorization view.
        'authorization_view' => 'pulse-mcp::authorize',
    ],

    'gate' => 'viewPulseMcp',

    // Additional middleware for the MCP route.
    'middleware' => [],

    // Laravel rate limit for the MCP route ("max,minutes"). Empty disables it.
    'throttle' => env('PULSE_MCP_THROTTLE', '60,1'),

    // Keep agent traffic to the MCP endpoint out of Pulse's request and usage cards.
    'ignore_own_requests' => true,

    /*
    |--------------------------------------------------------------------------
    | Query Limits & Caching
    |--------------------------------------------------------------------------
    |
    | Tools return at most `max` rows. A `search` filter scans up to
    | `search_scan` rows before filtering. Results are cached for
    | `cache_ttl` seconds (0 disables) since Pulse queries are heavy.
    |
    */

    'limits' => [
        'default' => 20,
        'max' => 100,
        'search_scan' => 1000,
    ],

    'cache_ttl' => env('PULSE_MCP_CACHE_TTL', 10),

    /*
    |--------------------------------------------------------------------------
    | Redaction
    |--------------------------------------------------------------------------
    |
    | Pulse keeps raw SQL, outgoing URLs, cache keys, and user details. These
    | options mask values before they are sent to an AI agent.
    |
    */

    'redact' => [
        'mask_sql_literals' => true,
        'max_sql_length' => 2000,
        'strip_url_query_values' => true,
        'include_user_email' => false,

        // Regular expressions whose matches are masked in cache keys.
        'cache_key_patterns' => [],
    ],

];
