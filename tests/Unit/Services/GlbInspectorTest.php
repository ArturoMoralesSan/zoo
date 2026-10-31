<?php

namespace Tests\Unit\Services;

use App\Exceptions\InvalidGlbException;
use App\Services\GlbInspector;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Archivos de prueba: tests/Fixtures/glb (se generan con generate.mjs).
 */
class GlbInspectorTest extends TestCase
{
    private GlbInspector $inspector;

    protected function setUp(): void
    {
        parent::setUp();

        config(['glb.supported_extensions' => ['KHR_materials_unlit']]);

        $this->inspector = new GlbInspector;
    }

    private function fixture(string $name): string
    {
        return base_path('tests/Fixtures/glb/'.$name);
    }

    /**
     * GLB mínimo con el JSON indicado (sin bloque binario).
     */
    private function glbWithJson(array $json): string
    {
        $content = json_encode($json);
        $content .= str_repeat(' ', (4 - strlen($content) % 4) % 4);

        $path = tempnam(sys_get_temp_dir(), 'glb');

        file_put_contents(
            $path,
            'glTF'.pack('VV', 2, 20 + strlen($content)).pack('V', strlen($content)).'JSON'.$content
        );

        return $path;
    }

    public function test_valid_file_passes_with_its_dimensions(): void
    {
        $result = $this->inspector->inspectResized($this->fixture('valid.glb'), 1.5);

        $this->assertTrue($result->isVerified());
        $this->assertEqualsWithDelta(1.5, $result->height, 0.0001);
        $this->assertEqualsWithDelta(0.375, $result->width, 0.0001);
        $this->assertEqualsWithDelta(0.75, $result->depth, 0.0001);
        $this->assertFalse($result->isSkinned);
        $this->assertSame([], $result->warnings);
    }

    public function test_height_within_tolerance_is_accepted(): void
    {
        // 1.5 m está a menos del 1 % de 1.51 m.
        $result = $this->inspector->inspectResized($this->fixture('valid.glb'), 1.51);

        $this->assertEqualsWithDelta(1.5, $result->height, 0.0001);
    }

    /**
     * @return array<string, array{0: string, 1: float, 2: string}>
     */
    public static function invalidFiles(): array
    {
        return [
            'magic incorrecto' => ['bad-magic.glb', 1.5, 'no es un GLB válido'],
            'archivo truncado' => ['truncated.glb', 1.5, 'incompleto'],
            'draco' => ['draco.glb', 1.5, 'KHR_draco_mesh_compression'],
            'meshopt' => ['meshopt.glb', 1.5, 'EXT_meshopt_compression'],
            'cuantizado' => ['quantized.glb', 1.5, 'KHR_mesh_quantization'],
            'alto incorrecto' => ['valid.glb', 2, 'se pidió 2.000 m'],
            'descentrado en X' => ['off-center-x.glb', 1.5, 'centrado'],
            'descentrado en Z' => ['off-center-z.glb', 1.5, 'centrado'],
            'Y mínima distinta de 0' => ['min-y.glb', 1.5, 'apoyado en el suelo'],
            'sin ScaleRoot' => ['missing-scale-root.glb', 1.5, 'ScaleRoot'],
            'ScaleRoot rotado' => ['rotated-scale-root.glb', 1.5, 'rotación'],
            'escala no uniforme' => ['non-uniform-scale-root.glb', 1.5, 'uniforme'],
            'ScaleRoot con malla' => ['scale-root-with-mesh.glb', 1.5, 'malla propia'],
            'dos nodos raíz' => ['two-roots.glb', 1.5, 'único nodo raíz'],
            'varias escenas' => ['multi-scene.glb', 1.5, 'escenas'],
        ];
    }

    #[DataProvider('invalidFiles')]
    public function test_invalid_files_are_rejected(string $file, float $target, string $message): void
    {
        $this->expectException(InvalidGlbException::class);
        $this->expectExceptionMessage($message);

        $this->inspector->inspectResized($this->fixture($file), $target);
    }

    public function test_skinned_file_is_validated_structurally_with_a_warning(): void
    {
        $result = $this->inspector->inspectResized($this->fixture('skinned.glb'), 1.5);

        $this->assertTrue($result->isSkinned);
        $this->assertFalse($result->isVerified());
        $this->assertStringContainsString('esqueleto', implode(' ', $result->warnings));
    }

    public function test_unsupported_extension_only_warns(): void
    {
        $result = $this->inspector->inspectResized($this->fixture('unsupported-extension.glb'), 1.5);

        $this->assertTrue($result->isVerified());
        $this->assertCount(1, $result->warnings);
        $this->assertStringContainsString('KHR_materials_clearcoat', $result->warnings[0]);
    }

    public function test_supported_extensions_do_not_warn(): void
    {
        config(['glb.supported_extensions' => ['KHR_materials_clearcoat']]);

        $result = $this->inspector->inspectResized($this->fixture('unsupported-extension.glb'), 1.5);

        $this->assertSame([], $result->warnings);
    }

    public function test_json_chunk_size_is_checked_before_reading(): void
    {
        config(['glb.max_json_chunk_size' => 16]);

        $this->expectException(InvalidGlbException::class);
        $this->expectExceptionMessage('demasiado grande');

        $this->inspector->readJson($this->fixture('valid.glb'));
    }

    public function test_position_without_min_max_is_rejected(): void
    {
        $path = $this->glbWithJson([
            'asset' => ['version' => '2.0'],
            'scene' => 0,
            'scenes' => [['nodes' => [0]]],
            'nodes' => [
                ['name' => 'ScaleRoot', 'children' => [1]],
                ['mesh' => 0],
            ],
            'meshes' => [['primitives' => [['attributes' => ['POSITION' => 0]]]]],
            'accessors' => [['componentType' => 5126, 'count' => 3, 'type' => 'VEC3']],
        ]);

        $this->expectException(InvalidGlbException::class);
        $this->expectExceptionMessage('min/max');

        try {
            $this->inspector->inspectResized($path, 1);
        } finally {
            unlink($path);
        }
    }

    public function test_matrix_transforms_are_supported(): void
    {
        // ScaleRoot con matriz (escala 0.5) y un hijo con matriz (traslación).
        $path = $this->glbWithJson([
            'asset' => ['version' => '2.0'],
            'scenes' => [['nodes' => [0]]],
            'nodes' => [
                ['name' => 'ScaleRoot', 'matrix' => [0.5, 0, 0, 0, 0, 0.5, 0, 0, 0, 0, 0.5, 0, 0, 0, 0, 1], 'children' => [1]],
                ['mesh' => 0, 'matrix' => [1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 1, 0, 1]],
            ],
            'meshes' => [['primitives' => [['attributes' => ['POSITION' => 0]]]]],
            'accessors' => [[
                'componentType' => 5126, 'count' => 8, 'type' => 'VEC3',
                'min' => [-1, -1, -1], 'max' => [1, 1, 1],
            ]],
        ]);

        try {
            $result = $this->inspector->inspectResized($path, 1);
        } finally {
            unlink($path);
        }

        $this->assertEqualsWithDelta(1, $result->height, 0.0001);
    }

    public function test_non_right_angle_child_rotation_skips_exact_check_with_warning(): void
    {
        $s = sin(M_PI / 8);
        $c = cos(M_PI / 8);

        $path = $this->glbWithJson([
            'asset' => ['version' => '2.0'],
            'scenes' => [['nodes' => [0]]],
            'nodes' => [
                ['name' => 'ScaleRoot', 'children' => [1]],
                ['mesh' => 0, 'rotation' => [0, $s, 0, $c]],
            ],
            'meshes' => [['primitives' => [['attributes' => ['POSITION' => 0]]]]],
            'accessors' => [[
                'componentType' => 5126, 'count' => 8, 'type' => 'VEC3',
                'min' => [-1, 0, -1], 'max' => [1, 2, 1],
            ]],
        ]);

        try {
            $result = $this->inspector->inspectResized($path, 2);
        } finally {
            unlink($path);
        }

        $this->assertFalse($result->isVerified());
        $this->assertStringContainsString('ángulos no rectos', $result->warnings[0]);
    }

    public function test_original_file_header_is_read_even_if_compressed(): void
    {
        $json = $this->inspector->readJson($this->fixture('input/draco.glb'));

        $this->assertContains('KHR_draco_mesh_compression', $json['extensionsUsed']);
    }
}
