<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Logo del negocio: se guarda reducido (lado mayor 400 px, PNG con transparencia) y se
 * entrega como data URI, que sirve igual en páginas, tickets y PDF.
 */
class Branding
{
    public const PATH = 'branding/logo.png';
    public const MAX_SIDE = 400;

    private static ?string $dataUri = null;
    private static bool $loaded = false;

    public static function storeLogo(UploadedFile $file): void
    {
        $image = match ($file->getMimeType()) {
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/webp' => @imagecreatefromwebp($file->getRealPath()),
            default => false,
        };
        if (! $image) {
            throw new \DomainException('No se pudo leer la imagen del logo (usa PNG, JPG o WebP).');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $logo = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagealphablending($logo, false);
        imagesavealpha($logo, true);
        imagefill($logo, 0, 0, imagecolorallocatealpha($logo, 0, 0, 0, 127));
        imagecopyresampled($logo, $image, 0, 0, 0, 0, imagesx($logo), imagesy($logo), $width, $height);

        ob_start();
        imagepng($logo, null, 9);
        Storage::disk('local')->put(self::PATH, ob_get_clean());
        imagedestroy($image);
        imagedestroy($logo);

        Setting::put('company_logo', self::PATH, 'general');
        self::flush();
    }

    public static function removeLogo(): void
    {
        Storage::disk('local')->delete(self::PATH);
        Setting::put('company_logo', '', 'general');
        self::flush();
    }

    /**
     * Logo como data URI, o null si no hay (memorizado por petición).
     */
    public static function logoDataUri(): ?string
    {
        if (! self::$loaded) {
            self::$loaded = true;
            $path = Setting::get('company_logo');
            self::$dataUri = $path && Storage::disk('local')->exists($path)
                ? 'data:image/png;base64,' . base64_encode(Storage::disk('local')->get($path))
                : null;
        }

        return self::$dataUri;
    }

    /**
     * Bytes del PNG (para incrustarlo en correos, donde los data URI se bloquean).
     */
    public static function logoPng(): ?string
    {
        $uri = self::logoDataUri();

        return $uri ? base64_decode(substr($uri, strlen('data:image/png;base64,'))) : null;
    }

    public static function flush(): void
    {
        self::$loaded = false;
        self::$dataUri = null;
    }
}
