<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Registrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires an MCP-scoped token and the configured gate on top of authentication.
 */
final readonly class EnsureCanViewPulseMcp
{
    public function __construct(
        private Gate $gate,
        private Config $config,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->deny(401, 'Unauthenticated.');
        }

        if (method_exists($user, 'tokenCan') && ! $user->tokenCan(Registrar::OAUTH_SCOPE)) {
            return $this->deny(403, 'The access token is missing the '.Registrar::OAUTH_SCOPE.' scope.');
        }

        if ($this->gate->forUser($user)->denies((string) $this->config->get('pulse-mcp.gate', 'viewPulseMcp'))) {
            return $this->deny(403, 'This user may not access Pulse data.');
        }

        return $next($request);
    }

    private function deny(int $status, string $message): Response
    {
        return response()->json(['message' => $message], $status);
    }
}
