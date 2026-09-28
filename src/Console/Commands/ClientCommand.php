<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;

class ClientCommand extends Command
{
    private const array AGENTS = ['claude', 'codex', 'cursor', 'vscode'];

    /**
     * The command signature.
     */
    protected $signature = 'pulse-mcp:client
        {agent=claude : The MCP client to configure: claude, codex, cursor or vscode}
        {--url= : Public URL of the MCP endpoint, defaults to APP_URL plus the configured path}
        {--name=pulse : Server name to register in the client}';

    /**
     * The command description.
     */
    protected $description = "Print the configuration an MCP client needs to connect to this application's Pulse MCP server.";

    /**
     * Execute the console command.
     */
    public function handle(Config $config): int
    {
        $agent = (string) $this->argument('agent');

        if (! in_array($agent, self::AGENTS, true)) {
            $this->components->error('Unknown agent ['.$agent.']. Use one of: '.implode(', ', self::AGENTS).'.');

            return self::FAILURE;
        }

        $url = (string) ($this->option('url') ?: rtrim((string) $config->get('app.url'), '/').'/'.trim((string) $config->get('pulse-mcp.path'), '/'));
        $name = (string) $this->option('name');
        $sanctum = $config->get('pulse-mcp.auth.driver') === 'sanctum';

        if (! $config->get('pulse-mcp.enabled')) {
            $this->components->warn('The Pulse MCP endpoint is disabled. Set PULSE_MCP_ENABLED=true on the server first.');
        }

        $instructions = match ($agent) {
            'claude' => $this->claude($name, $url, $sanctum),
            'codex' => $this->codex($name, $url, $sanctum),
            'cursor' => $this->json(['mcpServers' => [$name => $this->server($url, $sanctum)]], '.cursor/mcp.json'),
            default => $this->json(['servers' => [$name => ['type' => 'http', ...$this->server($url, $sanctum)]]], '.vscode/mcp.json'),
        };

        foreach (explode("\n", $instructions) as $line) {
            $this->line($line);
        }

        $this->newLine();

        $this->line($sanctum
            ? 'Create a Sanctum token with the "mcp:use" ability (or "*") for a user allowed by the viewPulseMcp gate, and use it in place of <token>.'
            : 'On first use the client opens your browser to sign in and approve access (OAuth). Only users allowed by the viewPulseMcp gate can connect.');

        return self::SUCCESS;
    }

    private function claude(string $name, string $url, bool $sanctum): string
    {
        return "Run in your project:\n\n  claude mcp add --transport http {$name} {$url}"
            .($sanctum ? ' --header "Authorization: Bearer <token>"' : '');
    }

    private function codex(string $name, string $url, bool $sanctum): string
    {
        if ($sanctum) {
            return "Add to ~/.codex/config.toml, then export PULSE_MCP_TOKEN=<token>:\n\n"
                ."[mcp_servers.{$name}]\nurl = \"{$url}\"\nbearer_token_env_var = \"PULSE_MCP_TOKEN\"";
        }

        return "Run:\n\n  codex mcp add {$name} --url {$url}\n  codex mcp login {$name}";
    }

    /**
     * @return array<string, mixed>
     */
    private function server(string $url, bool $sanctum): array
    {
        return $sanctum
            ? ['url' => $url, 'headers' => ['Authorization' => 'Bearer <token>']]
            : ['url' => $url];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function json(array $config, string $file): string
    {
        return "Add to {$file}:\n\n".json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
