<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Server\Registrar;

class TokenCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'pulse-mcp:token
        {user : The user\'s id or email}
        {--model= : Eloquent user model, defaults to the default guard\'s provider model}
        {--name=pulse-mcp : Token name}
        {--expires= : Days until the token expires, never by default}';

    /**
     * The command description.
     */
    protected $description = 'Issue a Sanctum personal access token with the mcp:use ability for the Pulse MCP server.';

    /**
     * Execute the console command.
     */
    public function handle(Config $config, Gate $gate): int
    {
        $model = (string) ($this->option('model') ?: $this->defaultModel($config));

        if (! is_a($model, Model::class, true) || ! method_exists($model, 'createToken')) {
            $this->components->error("[{$model}] must be an Eloquent model using Laravel\\Sanctum\\HasApiTokens.");

            return self::FAILURE;
        }

        $identifier = (string) $this->argument('user');

        $user = str_contains($identifier, '@')
            ? $model::query()->where('email', $identifier)->first()
            : $model::query()->find($identifier);

        if ($user === null) {
            $this->components->error("No [{$model}] found for [{$identifier}].");

            return self::FAILURE;
        }

        $days = $this->option('expires');

        $token = $user->createToken(
            (string) $this->option('name'),
            [Registrar::OAUTH_SCOPE],
            is_numeric($days) ? now()->addDays((int) $days) : null,
        )->plainTextToken;

        if ($config->get('pulse-mcp.auth.driver') !== 'sanctum') {
            $this->components->warn('PULSE_MCP_AUTH is not "sanctum", so the MCP endpoint will not accept this token.');
        }

        if ($gate->forUser($user)->denies((string) $config->get('pulse-mcp.gate', 'viewPulseMcp'))) {
            $this->components->warn('This user fails the viewPulseMcp gate and will be refused access.');
        }

        $url = rtrim((string) $config->get('app.url'), '/').'/'.trim((string) $config->get('pulse-mcp.path'), '/');

        $this->components->info('Token created. It is shown only once.');
        $this->line($token);
        $this->newLine();
        $this->line('Connect Claude Code with:');
        $this->line("  claude mcp add --transport http pulse {$url} --header \"Authorization: Bearer {$token}\"");

        return self::SUCCESS;
    }

    private function defaultModel(Config $config): string
    {
        $guard = (string) $config->get('auth.defaults.guard', 'web');
        $provider = (string) $config->get("auth.guards.{$guard}.provider", 'users');

        return (string) $config->get("auth.providers.{$provider}.model");
    }
}
