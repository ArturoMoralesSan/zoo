<?php

namespace Tests\Unit\Licensing;

use App\Services\Licensing\AttributionBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Mismos casos que tests/js/attribution.test.ts.
 */
class AttributionBuilderTest extends TestCase
{
    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function cases(): array
    {
        $cases = json_decode(
            file_get_contents(__DIR__.'/../../Fixtures/credits/attribution-cases.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        return collect($cases)
            ->mapWithKeys(fn (array $case) => [$case['name'] => [$case['input'], $case['expected']]])
            ->all();
    }

    #[DataProvider('cases')]
    public function test_builds_the_attribution_line(array $input, string $expected): void
    {
        $this->assertSame($expected, AttributionBuilder::build($input));
    }
}
