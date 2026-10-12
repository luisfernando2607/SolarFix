<?php

namespace App\Support;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode as Generator;
use chillerlan\QRCode\QROptions;

/**
 * Código QR como imagen PNG en data URI (sirve en páginas y en el PDF).
 */
class QrCode
{
    public static function dataUri(string $text, int $scale = 4): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'scale' => $scale,
            'outputBase64' => true,
            'quietzoneSize' => 2,
        ]);

        return (new Generator($options))->render($text);
    }
}
