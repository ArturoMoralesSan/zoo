<?php

namespace Tests\Feature\Licensing;

use App\Models\User;
use App\Services\Licensing\CommonsMetadataFetcher;
use Database\Seeders\LicenseSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

class CommonsMetadataFetcherTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    private const FILE_URL = 'https://commons.wikimedia.org/wiki/File:Jaguar_(Panthera_onca_palustris)_male.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LicenseSeeder::class);
    }

    /**
     * Respuesta de la API de Commons con los metadatos dados.
     *
     * @param  array<string, string>  $metadata
     */
    private function commonsResponse(array $metadata): array
    {
        return [
            'query' => [
                'pages' => [
                    '123' => [
                        'title' => 'File:Jaguar (Panthera onca palustris) male.jpg',
                        'imageinfo' => [[
                            'url' => 'https://upload.wikimedia.org/wikipedia/commons/a/ab/Jaguar.jpg',
                            'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:Jaguar_(Panthera_onca_palustris)_male.jpg',
                            'extmetadata' => collect($metadata)
                                ->map(fn (string $value) => ['value' => $value])
                                ->all(),
                        ]],
                    ],
                ],
            ],
        ];
    }

    public function test_reads_title_author_and_license(): void
    {
        Http::fake([
            'commons.wikimedia.org/*' => Http::response($this->commonsResponse([
                'ObjectName' => 'Jaguar &amp; cachorro',
                'Artist' => '<a href="//commons.wikimedia.org/wiki/User:Jane_Doe" title="User:Jane Doe">Jane&nbsp;Doe</a>',
                'LicenseShortName' => 'CC BY-SA 4.0',
                'LicenseUrl' => 'https://creativecommons.org/licenses/by-sa/4.0',
            ])),
        ]);

        $result = app(CommonsMetadataFetcher::class)->fetch(self::FILE_URL);

        $this->assertSame('Jaguar & cachorro', $result['title']);
        $this->assertSame('Jane Doe', $result['author_name']);
        $this->assertSame('https://commons.wikimedia.org/wiki/User:Jane_Doe', $result['author_url']);
        $this->assertSame('cc-by-sa-4.0', $result['license_slug']);
        $this->assertNotNull($result['license_id']);
        $this->assertSame(self::FILE_URL, $result['source_url']);
        $this->assertSame([], $result['warnings']);

        Http::assertSent(function (Request $request) {
            return $request['titles'] === 'File:Jaguar (Panthera onca palustris) male.jpg'
                && $request['iiprop'] === 'extmetadata|url'
                && str_contains($request->header('User-Agent')[0] ?? '', '(');
        });
    }

    public function test_falls_back_to_the_file_name_and_ignores_several_author_links(): void
    {
        Http::fake([
            'commons.wikimedia.org/*' => Http::response($this->commonsResponse([
                'Artist' => '<a href="https://a.example">Ana</a> y <a href="https://b.example">Beto</a>',
                'LicenseShortName' => 'CC0',
            ])),
        ]);

        $result = app(CommonsMetadataFetcher::class)->fetch(self::FILE_URL);

        $this->assertSame('Jaguar (Panthera onca palustris) male', $result['title']);
        $this->assertSame('Ana y Beto', $result['author_name']);
        $this->assertNull($result['author_url']);
        $this->assertSame('cc0-1.0', $result['license_slug']);
    }

    public function test_unknown_license_is_left_empty_with_a_warning(): void
    {
        Http::fake([
            'commons.wikimedia.org/*' => Http::response($this->commonsResponse([
                'ObjectName' => 'Jaguar',
                'Artist' => 'Jane Doe',
                'LicenseShortName' => 'Licencia rara 1.0',
            ])),
        ]);

        $result = app(CommonsMetadataFetcher::class)->fetch(self::FILE_URL);

        $this->assertNull($result['license_id']);
        $this->assertNull($result['license_slug']);
        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('Licencia rara 1.0', $result['warnings'][0]);
    }

    public function test_restricted_license_is_not_selected(): void
    {
        Http::fake([
            'commons.wikimedia.org/*' => Http::response($this->commonsResponse([
                'LicenseShortName' => 'GFDL',
            ])),
        ]);

        $result = app(CommonsMetadataFetcher::class)->fetch(self::FILE_URL);

        $this->assertNull($result['license_id']);
        $this->assertStringContainsString('opción Creative Commons', $result['warnings'][0]);
    }

    public function test_timeout_returns_a_warning(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        $result = app(CommonsMetadataFetcher::class)->fetch(self::FILE_URL);

        $this->assertNull($result['title']);
        $this->assertStringContainsString('no respondió a tiempo', $result['warnings'][0]);
    }

    public function test_api_error_returns_a_warning(): void
    {
        Http::fake(['commons.wikimedia.org/*' => Http::response('error', 503)]);

        $result = app(CommonsMetadataFetcher::class)->fetch(self::FILE_URL);

        $this->assertNull($result['title']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_missing_file_returns_a_warning(): void
    {
        Http::fake(['commons.wikimedia.org/*' => Http::response([
            'query' => ['pages' => ['-1' => ['title' => 'File:Nada.jpg', 'missing' => '']]],
        ])]);

        $result = app(CommonsMetadataFetcher::class)->fetch(self::FILE_URL);

        $this->assertStringContainsString('no existe', $result['warnings'][0]);
    }

    public function test_response_is_cached_by_file_title(): void
    {
        Http::fake([
            'commons.wikimedia.org/*' => Http::response($this->commonsResponse([
                'ObjectName' => 'Jaguar',
                'LicenseShortName' => 'CC BY 4.0',
            ])),
        ]);

        $fetcher = app(CommonsMetadataFetcher::class);

        $fetcher->fetch(self::FILE_URL);
        // Misma página, otra forma de la URL.
        $second = $fetcher->fetch('https://commons.wikimedia.org/w/index.php?title=File:Jaguar_(Panthera_onca_palustris)_male.jpg');

        $this->assertSame('Jaguar', $second['title']);
        Http::assertSentCount(1);
    }

    public function test_non_commons_urls_are_not_requested(): void
    {
        Http::fake();

        $result = app(CommonsMetadataFetcher::class)->fetch('https://en.wikipedia.org/wiki/File:Jaguar.jpg');

        $this->assertNotEmpty($result['warnings']);
        Http::assertNothingSent();
    }

    public function test_lookup_endpoint_requires_edit_permission_and_returns_json(): void
    {
        Http::fake([
            'commons.wikimedia.org/*' => Http::response($this->commonsResponse([
                'ObjectName' => 'Jaguar',
                'LicenseShortName' => 'CC BY 4.0',
            ])),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('admin.credits.commons-lookup'), ['url' => self::FILE_URL])
            ->assertForbidden();

        Permission::findOrCreate('cards.edit');
        $user->givePermissionTo('cards.edit');

        $this->actingAs($user)
            ->postJson(route('admin.credits.commons-lookup'), ['url' => self::FILE_URL])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Jaguar')
            ->assertJsonPath('data.license_slug', 'cc-by-4.0');
    }
}
