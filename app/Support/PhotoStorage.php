<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda las fotos de las órdenes reducidas: lado mayor de 1600 px, JPEG al 82 %, con la
 * orientación corregida según la cámara. Una foto de celular pasa de 3–8 MB a ~300 KB.
 * Si la imagen no se puede procesar (GIF, formato raro), se guarda tal cual.
 */
class PhotoStorage
{
    public const MAX_SIDE = 1600;
    public const QUALITY = 82;

    public static function store(UploadedFile $file, string $folder = 'order_photos'): string
    {
        $image = self::load($file);
        if (! $image) {
            return $file->store($folder, 'local');
        }

        $image = self::orient($image, $file);
        $image = self::shrink($image);

        ob_start();
        imagejpeg($image, null, self::QUALITY);
        $jpeg = ob_get_clean();
        imagedestroy($image);

        $path = $folder . '/' . Str::uuid() . '.jpg';
        Storage::disk('local')->put($path, $jpeg);

        return $path;
    }

    private static function load(UploadedFile $file): \GdImage|false
    {
        $image = match ($file->getMimeType()) {
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file->getRealPath()) : false,
            default => false,
        };
        if (! $image) {
            return false;
        }

        // Fondo blanco para las transparencias (JPEG no las tiene).
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imagedestroy($image);

        return $flat;
    }

    private static function orient(\GdImage $image, UploadedFile $file): \GdImage
    {
        if ($file->getMimeType() !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;

        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);
        imagedestroy($image);

        return $rotated;
    }

    private static function shrink(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = self::MAX_SIDE / max($width, $height);
        if ($scale >= 1) {
            return $image;
        }

        $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale), IMG_BICUBIC);
        imagedestroy($image);

        return $resized;
    }
}
