<?php

namespace App\Support;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\HtmlString;

/**
 * Inline SVG QR codes, for ID cards and printed documents that can be checked online.
 */
final class QrCode
{
    public static function svg(string $text, int $size = 120): HtmlString
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 0, fill: Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(16, 24, 40))),
            new SvgImageBackEnd,
        );

        $svg = (new Writer($renderer))->writeString($text);

        return new HtmlString(trim((string) preg_replace('/^<\?xml[^>]*\?>/', '', $svg)));
    }
}
