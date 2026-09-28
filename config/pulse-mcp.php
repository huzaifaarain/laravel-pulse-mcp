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

];
