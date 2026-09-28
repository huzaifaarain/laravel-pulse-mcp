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
