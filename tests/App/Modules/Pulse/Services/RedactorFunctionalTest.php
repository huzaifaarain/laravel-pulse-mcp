<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\App\Modules\Pulse\Services;

use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services\Redactor;
use HuzaifaArain\LaravelPulseMcp\Tests\TestCase;

final class RedactorFunctionalTest extends TestCase
{
    public function test_it_masks_sql_literals_but_keeps_identifiers(): void
    {
        // Act

        $sql = resolve(Redactor::class)->sql('select * from "users" where "email" = \'a@b.co\' and "t1"."id" = 42 limit 10');

        // Assert

        $this->assertSame('select * from "users" where "email" = \'?\' and "t1"."id" = ? limit ?', $sql);
    }

    public function test_it_keeps_sql_literals_when_masking_is_disabled(): void
    {
        // Arrange

        config(['pulse-mcp.redact.mask_sql_literals' => false]);

        // Act

        $sql = resolve(Redactor::class)->sql("select * from users where email = 'a@b.co'");

        // Assert

        $this->assertSame("select * from users where email = 'a@b.co'", $sql);
    }

    public function test_it_truncates_long_sql(): void
    {
        // Arrange

        config(['pulse-mcp.redact.max_sql_length' => 10]);

        // Act

        $sql = resolve(Redactor::class)->sql('select a, b, c from table_name');

        // Assert

        $this->assertSame('select a, …', $sql);
    }

    public function test_it_masks_url_query_values_and_keeps_their_names(): void
    {
        // Act

        $url = resolve(Redactor::class)->url('https://api.example.com/pay?token=secret&amount=5');

        // Assert

        $this->assertSame('https://api.example.com/pay?token=[redacted]&amount=[redacted]', $url);
    }

    public function test_it_leaves_urls_without_a_query_untouched(): void
    {
        // Act & Assert

        $this->assertSame('https://api.example.com/pay', resolve(Redactor::class)->url('https://api.example.com/pay'));
    }

    public function test_it_masks_cache_keys_matching_configured_patterns(): void
    {
        // Arrange

        config(['pulse-mcp.redact.cache_key_patterns' => ['/user:\d+/']]);

        // Act

        $key = resolve(Redactor::class)->cacheKey('profile:user:12:settings');

        // Assert

        $this->assertSame('profile:[redacted]:settings', $key);
    }

    public function test_it_hides_user_emails_by_default(): void
    {
        // Act & Assert

        $this->assertFalse(resolve(Redactor::class)->includesUserEmail());
    }
}
