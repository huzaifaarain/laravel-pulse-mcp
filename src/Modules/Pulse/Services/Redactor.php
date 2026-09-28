<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services;

use Illuminate\Contracts\Config\Repository;

/**
 * Masks production values an agent does not need before they leave the application.
 */
final readonly class Redactor
{
    public const string MASK = '[redacted]';

    public function __construct(private Repository $repository) {}

    public function sql(?string $sql): ?string
    {
        if ($sql === null) {
            return null;
        }

        if ($this->repository->get('pulse-mcp.redact.mask_sql_literals', true)) {
            $sql = (string) preg_replace(
                // Single-quoted string literals, then bare numeric literals. Double quotes are identifiers on pgsql/sqlite.
                ["/'(?:[^'\\\\]|\\\\.|'')*'/", '/(?<![\w.$`"])-?\d+(?:\.\d+)?(?![\w`"])/'],
                ["'?'", '?'],
                $sql,
            );
        }

        $max = (int) $this->repository->get('pulse-mcp.redact.max_sql_length', 2000);

        return $max > 0 && mb_strlen($sql) > $max ? mb_substr($sql, 0, $max).'…' : $sql;
    }

    public function url(?string $url): ?string
    {
        if ($url === null || ! $this->repository->get('pulse-mcp.redact.strip_url_query_values', true)) {
            return $url;
        }

        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return $url;
        }

        $names = array_map(
            static fn (string $pair): string => explode('=', $pair, 2)[0].'='.self::MASK,
            array_filter(explode('&', $query), static fn (string $pair): bool => $pair !== ''),
        );

        return str_replace('?'.$query, '?'.implode('&', $names), $url);
    }

    public function cacheKey(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        /** @var list<string> $patterns */
        $patterns = $this->repository->get('pulse-mcp.redact.cache_key_patterns', []);

        foreach ($patterns as $pattern) {
            $key = (string) preg_replace($pattern, self::MASK, $key);
        }

        return $key;
    }

    public function includesUserEmail(): bool
    {
        return (bool) $this->repository->get('pulse-mcp.redact.include_user_email', false);
    }
}
