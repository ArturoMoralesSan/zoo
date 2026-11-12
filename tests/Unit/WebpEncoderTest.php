<?php

namespace Tests\Unit;

use App\Services\ImageEncoding\GdWebpEncoder;
use App\Services\ImageEncoding\ImageProfile;
use App\Services\ImageEncoding\ImagickWebpEncoder;
use App\Services\ImageEncoding\WebpEncoder;
use Imagick;
use ImagickPixel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WebpEncoderTest extends TestCase
{
    public function test_target_size_keeps_ratio_and_never_upscales(): void
    {
        $profile = new ImageProfile(1144, 2032, 88);

        // Render a 3× de la plantilla → 2×.
        $this->assertSame([1144, 2032], $profile->targetSize(1716, 3048));
        $this->assertSame([500, 900], $profile->targetSize(500, 900));
        $this->assertSame([1144, 572], $profile->targetSize(4000, 2000));
        $this->assertTrue($profile->fits(1144, 2032));
        $this->assertFalse($profile->fits(1145, 2032));
    }

    /**
     * @return array<string, array{class-string<WebpEncoder>}>
     */
    public static function encoders(): array
    {
        return [
            'imagick' => [ImagickWebpEncoder::class],
            'gd' => [GdWebpEncoder::class],
        ];
    }

    /**
     * @param  class-string<WebpEncoder>  $class
     */
    #[DataProvider('encoders')]
    public function test_encoder_writes_a_resized_webp_with_alpha(string $class): void
    {
        $encoder = new $class;

        if (! $encoder->isAvailable()) {
            $this->markTestSkipped("{$encoder->name()} no está disponible con WebP.");
        }

        $source = $this->transparentPng(600, 400);
        $output = $encoder->encode($source, new ImageProfile(300, 300, 85));

        try {
            $info = getimagesize($output);

            $this->assertNotFalse($info);
            $this->assertSame('image/webp', $info['mime']);
            $this->assertSame([300, 200], [$info[0], $info[1]]);

            // Encabezado VP8X con la bandera de transparencia.
            $header = (string) file_get_contents($output, length: 30);
            $this->assertSame('VP8X', substr($header, 12, 4));
            $this->assertNotSame(0, ord($header[20]) & 0x10);
        } finally {
            @unlink($source);
            @unlink($output);
        }
    }

    private function transparentPng(int $width, int $height): string
    {
        $path = tempnam(sys_get_temp_dir(), 'png').'.png';

        if (extension_loaded('imagick')) {
            $image = new Imagick;
            $image->newImage($width, $height, new ImagickPixel('rgba(24, 119, 68, 0.5)'));
            $image->setImageFormat('png');
            $image->writeImage($path);

            return $path;
        }

        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 24, 119, 68, 64));
        imagepng($image, $path);

        return $path;
    }
}
