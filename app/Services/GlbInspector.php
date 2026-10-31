<?php

namespace App\Services;

use App\Exceptions\InvalidGlbException;
use JsonException;

/**
 * Valida archivos GLB en PHP puro.
 *
 * Solo lee el encabezado y el bloque JSON del archivo; nunca carga el bloque
 * binario (BIN). Las medidas se calculan con los valores min/max del atributo
 * POSITION de cada malla, transformados por la matriz mundial de su nodo.
 *
 * Contrato del archivo escalado: una sola escena con un único nodo raíz
 * "ScaleRoot" (escala uniforme, sin rotación ni malla propia), y el origen
 * [0,0,0] en el centro inferior de la caja del modelo.
 */
class GlbInspector
{
    public const SCALE_ROOT_NAME = 'ScaleRoot';

    /**
     * Extensiones de compresión: ViroReact no las admite y el navegador las quita.
     */
    public const COMPRESSION_EXTENSIONS = [
        'KHR_draco_mesh_compression',
        'EXT_meshopt_compression',
        'KHR_mesh_quantization',
    ];

    private const MAGIC = 'glTF';

    private const JSON_CHUNK = 'JSON';

    /**
     * Margen para considerar una escala uniforme o una rotación nula.
     */
    private const EPSILON = 1e-4;

    /**
     * Validar el encabezado y leer el JSON de un GLB (por ejemplo, el original).
     *
     * @return array<string, mixed>
     *
     * @throws InvalidGlbException
     */
    public function readJson(string $path): array
    {
        $size = @filesize($path);
        $handle = @fopen($path, 'rb');

        if ($size === false || $handle === false) {
            throw new InvalidGlbException('No se pudo leer el archivo GLB.');
        }

        try {
            $header = fread($handle, 12);

            if ($header === false || strlen($header) < 12) {
                throw new InvalidGlbException('El archivo no es un GLB válido.');
            }

            ['magic' => $magic, 'version' => $version, 'length' => $length] =
                unpack('a4magic/Vversion/Vlength', $header);

            if ($magic !== self::MAGIC) {
                throw new InvalidGlbException('El archivo no es un GLB válido.');
            }

            if ($version !== 2) {
                throw new InvalidGlbException('Solo se admiten archivos GLB de la versión 2 de glTF.');
            }

            if ($length !== $size) {
                throw new InvalidGlbException('El archivo GLB está incompleto o dañado.');
            }

            $chunkHeader = fread($handle, 8);

            if ($chunkHeader === false || strlen($chunkHeader) < 8) {
                throw new InvalidGlbException('El archivo GLB está incompleto o dañado.');
            }

            ['length' => $chunkLength, 'type' => $chunkType] =
                unpack('Vlength/a4type', $chunkHeader);

            if ($chunkType !== self::JSON_CHUNK) {
                throw new InvalidGlbException('El archivo GLB no empieza con un bloque JSON.');
            }

            /*
             * Se valida el tamaño antes de leer el bloque.
             */
            if ($chunkLength > (int) config('glb.max_json_chunk_size')) {
                throw new InvalidGlbException('La descripción del modelo (JSON) es demasiado grande.');
            }

            if ($chunkLength === 0 || 20 + $chunkLength > $length) {
                throw new InvalidGlbException('El archivo GLB está incompleto o dañado.');
            }

            $json = '';

            while (strlen($json) < $chunkLength && ! feof($handle)) {
                $read = fread($handle, $chunkLength - strlen($json));

                if ($read === false || $read === '') {
                    break;
                }

                $json .= $read;
            }

            if (strlen($json) !== $chunkLength) {
                throw new InvalidGlbException('El archivo GLB está incompleto o dañado.');
            }
        } finally {
            fclose($handle);
        }

        try {
            $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidGlbException('La descripción del modelo (JSON) no es válida.');
        }

        if (! is_array($document) || ! is_array($document['asset'] ?? null)) {
            throw new InvalidGlbException('La descripción del modelo (JSON) no es válida.');
        }

        return $document;
    }

    /**
     * Validar el archivo escalado que descargará el juego.
     *
     * @throws InvalidGlbException
     */
    public function inspectResized(string $path, float $targetHeight): GlbInspection
    {
        $gltf = $this->readJson($path);
        $warnings = $this->extensionWarnings($gltf);

        $rootIndex = $this->scaleRootIndex($gltf);
        $nodes = $this->array($gltf, 'nodes');

        $this->assertScaleRoot($nodes[$rootIndex]);

        /*
         * Recorrido desde ScaleRoot con las matrices mundiales.
         */
        $min = [INF, INF, INF];
        $max = [-INF, -INF, -INF];
        $isSkinned = false;
        $isExact = true;

        $stack = [[$rootIndex, self::identity()]];
        $visited = [];

        while ($stack) {
            [$index, $parentMatrix] = array_pop($stack);

            if (isset($visited[$index])) {
                throw new InvalidGlbException('La jerarquía de nodos del modelo no es válida.');
            }

            $visited[$index] = true;
            $node = $nodes[$index] ?? null;

            if (! is_array($node)) {
                throw new InvalidGlbException('La jerarquía de nodos del modelo no es válida.');
            }

            $world = self::multiply($parentMatrix, $this->localMatrix($node));

            if (isset($node['mesh'])) {
                if (isset($node['skin'])) {
                    $isSkinned = true;
                } else {
                    $isExact = $isExact && self::isAxisAligned($world);

                    foreach ($this->meshCorners($gltf, $node['mesh']) as $corner) {
                        $point = self::transformPoint($world, $corner);

                        for ($axis = 0; $axis < 3; $axis++) {
                            $min[$axis] = min($min[$axis], $point[$axis]);
                            $max[$axis] = max($max[$axis], $point[$axis]);
                        }
                    }
                }
            }

            foreach ($node['children'] ?? [] as $child) {
                if (! is_int($child)) {
                    throw new InvalidGlbException('La jerarquía de nodos del modelo no es válida.');
                }

                $stack[] = [$child, $world];
            }
        }

        if ($isSkinned) {
            $warnings[] = 'El modelo tiene esqueleto (skinning): el servidor solo validó su estructura; revisa el tamaño en la vista previa.';

            return new GlbInspection(null, null, null, true, $warnings);
        }

        if (! is_finite($min[1]) || ! is_finite($max[1])) {
            throw new InvalidGlbException('El modelo no tiene geometría que se pueda medir.');
        }

        if (! $isExact) {
            $warnings[] = 'El modelo tiene piezas rotadas en ángulos no rectos: el servidor no pudo verificar el tamaño exacto; revísalo en la vista previa.';

            return new GlbInspection(null, null, null, false, $warnings);
        }

        $height = $max[1] - $min[1];
        $width = $max[0] - $min[0];
        $depth = $max[2] - $min[2];

        $this->assertSize($targetHeight, $height, $width, $depth, $min, $max);

        return new GlbInspection($height, $width, $depth, false, $warnings);
    }

    /**
     * Rechaza la compresión y avisa de las extensiones no compatibles con la app.
     *
     * @return array<int, string>
     */
    private function extensionWarnings(array $gltf): array
    {
        $used = array_values(array_unique(array_filter(
            [
                ...(is_array($gltf['extensionsUsed'] ?? null) ? $gltf['extensionsUsed'] : []),
                ...(is_array($gltf['extensionsRequired'] ?? null) ? $gltf['extensionsRequired'] : []),
            ],
            'is_string'
        )));

        $compressed = array_values(array_intersect($used, self::COMPRESSION_EXTENSIONS));

        if ($compressed) {
            throw new InvalidGlbException(
                'El modelo está comprimido ('.implode(', ', $compressed).') y la app no lo admite. Vuelve a subir el archivo original para procesarlo.'
            );
        }

        $unsupported = array_values(array_diff(
            $used,
            config('glb.supported_extensions', [])
        ));

        return $unsupported
            ? ['El modelo usa extensiones que podrían no mostrarse bien en la app: '.implode(', ', $unsupported).'.']
            : [];
    }

    /**
     * Índice del único nodo raíz (ScaleRoot) de la única escena.
     */
    private function scaleRootIndex(array $gltf): int
    {
        $scenes = $this->array($gltf, 'scenes');

        if (count($scenes) !== 1) {
            throw new InvalidGlbException(
                $scenes
                    ? 'El modelo tiene '.count($scenes).' escenas. Solo se admite una escena.'
                    : 'El modelo no tiene ninguna escena.'
            );
        }

        $scene = $scenes[$gltf['scene'] ?? 0] ?? null;
        $roots = is_array($scene) ? ($scene['nodes'] ?? []) : [];

        if (count($roots) !== 1 || ! is_int($roots[0])) {
            throw new InvalidGlbException('El modelo debe tener un único nodo raíz "'.self::SCALE_ROOT_NAME.'".');
        }

        $node = $this->array($gltf, 'nodes')[$roots[0]] ?? null;

        if (! is_array($node) || ($node['name'] ?? null) !== self::SCALE_ROOT_NAME) {
            throw new InvalidGlbException('El modelo debe tener un único nodo raíz "'.self::SCALE_ROOT_NAME.'".');
        }

        return $roots[0];
    }

    /**
     * ScaleRoot: escala uniforme, sin rotación ni deformación y sin malla propia.
     */
    private function assertScaleRoot(array $node): void
    {
        if (isset($node['mesh'])) {
            throw new InvalidGlbException('El nodo "'.self::SCALE_ROOT_NAME.'" no debe tener una malla propia.');
        }

        $matrix = $this->localMatrix($node);
        $scales = [];

        for ($column = 0; $column < 3; $column++) {
            $vector = array_slice($matrix, $column * 4, 3);
            $scale = sqrt($vector[0] ** 2 + $vector[1] ** 2 + $vector[2] ** 2);

            if ($scale <= 0) {
                throw new InvalidGlbException('La escala del nodo "'.self::SCALE_ROOT_NAME.'" no es válida.');
            }

            /*
             * Sin rotación ni deformación: cada eje conserva su dirección.
             */
            foreach ($vector as $row => $value) {
                $expected = $row === $column ? 1.0 : 0.0;

                if (abs($value / $scale - $expected) > self::EPSILON) {
                    throw new InvalidGlbException('El nodo "'.self::SCALE_ROOT_NAME.'" no debe tener rotación.');
                }
            }

            $scales[] = $scale;
        }

        if ((max($scales) - min($scales)) > self::EPSILON * max($scales)) {
            throw new InvalidGlbException('La escala del nodo "'.self::SCALE_ROOT_NAME.'" debe ser uniforme.');
        }
    }

    /**
     * Alto pedido y origen en el centro inferior.
     *
     * @param  array<int, float>  $min
     * @param  array<int, float>  $max
     */
    private function assertSize(
        float $targetHeight,
        float $height,
        float $width,
        float $depth,
        array $min,
        array $max
    ): void {
        $tolerance = (float) config('glb.height_tolerance');

        if (abs($height - $targetHeight) > $targetHeight * $tolerance) {
            throw new InvalidGlbException(sprintf(
                'El modelo mide %s m de alto, pero se pidió %s m.',
                number_format($height, 3, '.', ''),
                number_format($targetHeight, 3, '.', '')
            ));
        }

        $absolute = (float) config('glb.origin_tolerance.absolute');
        $relative = (float) config('glb.origin_tolerance.relative');

        if (abs($min[1]) > max($absolute, $height * $relative)) {
            throw new InvalidGlbException('El modelo no está apoyado en el suelo (su base no está en Y = 0).');
        }

        $horizontal = max($absolute, max($width, $depth) * $relative);

        if (
            abs(($min[0] + $max[0]) / 2) > $horizontal ||
            abs(($min[2] + $max[2]) / 2) > $horizontal
        ) {
            throw new InvalidGlbException('El modelo no está centrado en el origen (X/Z).');
        }
    }

    /**
     * Esquinas de las cajas (POSITION min/max) de cada primitiva de la malla.
     *
     * @return array<int, array<int, float>>
     */
    private function meshCorners(array $gltf, mixed $meshIndex): array
    {
        $mesh = is_int($meshIndex) ? ($this->array($gltf, 'meshes')[$meshIndex] ?? null) : null;

        if (! is_array($mesh) || ! is_array($mesh['primitives'] ?? null)) {
            throw new InvalidGlbException('Una malla del modelo no es válida.');
        }

        $accessors = $this->array($gltf, 'accessors');
        $corners = [];

        foreach ($mesh['primitives'] as $primitive) {
            $index = $primitive['attributes']['POSITION'] ?? null;

            if ($index === null) {
                continue;
            }

            $accessor = is_int($index) ? ($accessors[$index] ?? null) : null;
            $min = $accessor['min'] ?? null;
            $max = $accessor['max'] ?? null;

            if (! self::isVector($min, 3) || ! self::isVector($max, 3)) {
                throw new InvalidGlbException('El modelo no indica los límites (min/max) de sus vértices.');
            }

            foreach ([$min[0], $max[0]] as $x) {
                foreach ([$min[1], $max[1]] as $y) {
                    foreach ([$min[2], $max[2]] as $z) {
                        $corners[] = [(float) $x, (float) $y, (float) $z];
                    }
                }
            }
        }

        return $corners;
    }

    /**
     * Matriz local del nodo (matrix o TRS), en orden de columnas.
     *
     * @return array<int, float>
     */
    private function localMatrix(array $node): array
    {
        if (isset($node['matrix'])) {
            if (! self::isVector($node['matrix'], 16)) {
                throw new InvalidGlbException('La transformación de un nodo no es válida.');
            }

            return array_map('floatval', $node['matrix']);
        }

        $t = $node['translation'] ?? [0, 0, 0];
        $r = $node['rotation'] ?? [0, 0, 0, 1];
        $s = $node['scale'] ?? [1, 1, 1];

        if (! self::isVector($t, 3) || ! self::isVector($r, 4) || ! self::isVector($s, 3)) {
            throw new InvalidGlbException('La transformación de un nodo no es válida.');
        }

        [$x, $y, $z, $w] = array_map('floatval', $r);

        return [
            (1 - 2 * ($y * $y + $z * $z)) * $s[0], (2 * ($x * $y + $z * $w)) * $s[0], (2 * ($x * $z - $y * $w)) * $s[0], 0.0,
            (2 * ($x * $y - $z * $w)) * $s[1], (1 - 2 * ($x * $x + $z * $z)) * $s[1], (2 * ($y * $z + $x * $w)) * $s[1], 0.0,
            (2 * ($x * $z + $y * $w)) * $s[2], (2 * ($y * $z - $x * $w)) * $s[2], (1 - 2 * ($x * $x + $y * $y)) * $s[2], 0.0,
            (float) $t[0], (float) $t[1], (float) $t[2], 1.0,
        ];
    }

    /**
     * Lista de nivel superior del JSON (scenes, nodes, meshes...).
     *
     * @return array<int, mixed>
     */
    private function array(array $gltf, string $key): array
    {
        $value = $gltf[$key] ?? [];

        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidGlbException('La descripción del modelo (JSON) no es válida.');
        }

        return $value;
    }

    private static function isVector(mixed $value, int $length): bool
    {
        if (! is_array($value) || count($value) !== $length || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $number) {
            if (! is_int($number) && ! is_float($number)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Si cada eje queda alineado con un eje del mundo (rotaciones de 90°),
     * la caja transformada coincide con la de los vértices.
     */
    private static function isAxisAligned(array $matrix): bool
    {
        for ($column = 0; $column < 3; $column++) {
            $vector = array_map('abs', array_slice($matrix, $column * 4, 3));
            $largest = max($vector);

            if ($largest <= 0) {
                return false;
            }

            $significant = array_filter($vector, fn (float $value) => $value > $largest * self::EPSILON);

            if (count($significant) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, float>
     */
    private static function identity(): array
    {
        return [1.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 1.0];
    }

    /**
     * @return array<int, float>
     */
    private static function multiply(array $a, array $b): array
    {
        $out = [];

        for ($column = 0; $column < 4; $column++) {
            for ($row = 0; $row < 4; $row++) {
                $sum = 0.0;

                for ($k = 0; $k < 4; $k++) {
                    $sum += $a[$k * 4 + $row] * $b[$column * 4 + $k];
                }

                $out[$column * 4 + $row] = $sum;
            }
        }

        return $out;
    }

    /**
     * @return array<int, float>
     */
    private static function transformPoint(array $m, array $p): array
    {
        return [
            $m[0] * $p[0] + $m[4] * $p[1] + $m[8] * $p[2] + $m[12],
            $m[1] * $p[0] + $m[5] * $p[1] + $m[9] * $p[2] + $m[13],
            $m[2] * $p[0] + $m[6] * $p[1] + $m[10] * $p[2] + $m[14],
        ];
    }
}
