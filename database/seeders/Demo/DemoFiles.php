<?php

namespace Database\Seeders\Demo;

/**
 * Small made-up files for demo hand-ins and school crests: a photo-like page from an
 * exercise book (PNG), a typed-up answer sheet (PDF) and an SVG crest.
 */
final class DemoFiles
{
    private const SENTENCES = [
        'First I wrote down what the question gave us.',
        'Then I used the method from the textbook.',
        'Working: multiply both sides by the same number.',
        'This gives the answer shown below.',
        'I checked my answer by putting it back in.',
        'Example from my environment: our compound in Ikeja.',
        'Diagram drawn on the next page.',
        'Answer = see box.',
        'Reason: because the total must stay the same.',
        'In conclusion, the statement is correct.',
        'Step 2: simplify and divide.',
        'Note: I used a ruler for the table.',
    ];

    /**
     * @return list<string>
     */
    public static function lines(string $title, int $count): array
    {
        $lines = [];

        for ($line = 0; $line < $count; $line++) {
            $lines[] = ($line % 4 === 0 ? ($line / 4 + 1).'. ' : '   ').self::SENTENCES[mt_rand(0, count(self::SENTENCES) - 1)];
        }

        return $lines;
    }

    /**
     * A ruled exercise-book page with blue "handwriting", as a PNG.
     *
     * @param  list<string>  $lines
     */
    public static function page(string $title, int $page, array $lines): string
    {
        $width = 820;
        $height = 1100;
        $image = imagecreatetruecolor($width, $height);
        $paper = (int) imagecolorallocate($image, 252, 251, 245);
        $rule = (int) imagecolorallocate($image, 190, 214, 236);
        $margin = (int) imagecolorallocate($image, 232, 150, 150);
        $ink = (int) imagecolorallocate($image, 30, 52, 140);

        imagefill($image, 0, 0, $paper);

        for ($y = 90; $y < $height; $y += 34) {
            imageline($image, 0, $y, $width, $y, $rule);
        }

        imageline($image, 90, 0, 90, $height, $margin);
        imageline($image, 92, 0, 92, $height, $margin);

        // GD's built-in font is tiny; draw each line small, then scale it up so it reads like handwriting.
        $write = function (string $text, int $x, int $y) use ($image): void {
            $strip = imagecreate(max(1, strlen($text) * 9), 16);
            $white = (int) imagecolorallocate($strip, 252, 251, 245);
            imagefill($strip, 0, 0, $white);
            imagestring($strip, 5, 0, 0, $text, (int) imagecolorallocate($strip, 30, 52, 140));
            imagecolortransparent($strip, $white);
            imagecopyresized($image, $strip, $x, $y, 0, 0, (int) (imagesx($strip) * 1.6), 26, imagesx($strip), 16);
        };

        $write(substr($title, 0, 40), 110, 50);
        $write('Page '.$page, 650, 50);

        foreach ($lines as $index => $line) {
            $write(substr($line, 0, 44), 110 + mt_rand(-3, 6), 98 + $index * 34 + mt_rand(-2, 2));
        }

        imagerectangle($image, 600, $height - 160, 780, $height - 90, $ink);
        $write('Ans: '.mt_rand(10, 99), 620, $height - 140);

        ob_start();
        imagepng($image, null, 6);

        return (string) ob_get_clean();
    }

    /**
     * A one-page PDF with a heading and lines of text, built by hand (no PDF library needed).
     *
     * @param  list<string>  $lines
     */
    public static function pdf(string $title, array $lines): string
    {
        $escape = fn (string $text): string => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text));
        $text = "BT /F1 20 Tf 60 780 Td ({$escape($title)}) Tj ET\n";

        foreach ($lines as $index => $line) {
            $text .= 'BT /F1 12 Tf 60 '.(740 - $index * 22)." Td ({$escape($line)}) Tj ET\n";
        }

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($text)." >>\nstream\n{$text}endstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    /**
     * A simple shield crest with the school's initials, as SVG.
     */
    public static function crest(string $initials, string $colour, string $accent): string
    {
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 220" width="200" height="220">
  <path d="M100 6 L188 34 V110 C188 162 150 196 100 214 C50 196 12 162 12 110 V34 Z" fill="{$colour}"/>
  <path d="M100 20 L174 44 V110 C174 152 143 181 100 197 C57 181 26 152 26 110 V44 Z" fill="none" stroke="{$accent}" stroke-width="5"/>
  <path d="M60 136 Q100 116 140 136 V150 Q100 130 60 150 Z" fill="{$accent}"/>
  <text x="100" y="112" text-anchor="middle" font-family="Georgia, 'Times New Roman', serif" font-size="58" font-weight="700" fill="#ffffff">{$initials}</text>
  <circle cx="100" cy="48" r="9" fill="{$accent}"/>
</svg>
SVG;
    }
}
