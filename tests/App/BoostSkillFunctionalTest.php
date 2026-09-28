<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\App;

use HuzaifaArain\LaravelPulseMcp\Mcp\Servers\PulseServer;
use HuzaifaArain\LaravelPulseMcp\Tests\TestCase;
use Laravel\Mcp\Server\Primitive;
use ReflectionProperty;
use Symfony\Component\Yaml\Yaml;

final class BoostSkillFunctionalTest extends TestCase
{
    private const string SKILL = __DIR__.'/../../resources/boost/skills/pulse-production-monitoring';

    public function test_it_ships_frontmatter_boost_requires(): void
    {
        // Arrange

        preg_match('/^---\R(.*?)\R---/s', (string) file_get_contents(self::SKILL.'/SKILL.md'), $matches);

        // Act

        $frontmatter = Yaml::parse($matches[1] ?? '');

        // Assert

        $this->assertSame('pulse-production-monitoring', $frontmatter['name'] ?? null);

        $this->assertNotEmpty($frontmatter['description'] ?? null);
    }

    public function test_it_documents_every_registered_tool_and_prompt(): void
    {
        // Arrange

        $reference = (string) file_get_contents(self::SKILL.'/references/tools.md');

        // Act

        $names = array_map(
            static fn (string $primitive): string => resolve($primitive)->name(),
            [...$this->registered('tools'), ...$this->registered('prompts')],
        );

        // Assert

        $this->assertCount(15, $names);

        foreach ($names as $name) {
            $this->assertStringContainsString("`{$name}`", $reference, "The Boost skill does not document [{$name}].");
        }
    }

    /**
     * @return list<class-string<Primitive>>
     */
    private function registered(string $property): array
    {
        return (new ReflectionProperty(PulseServer::class, $property))->getDefaultValue();
    }
}
