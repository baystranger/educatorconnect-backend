<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Placeholder files for demo data: SVG photos, logos and avatars, and small real PDFs.
 * Pure PHP (no GD/Imagick), so it runs on any shared host. Everything lives under
 * `demo/` on the media disks, which `php artisan demo:purge` removes again.
 */
class DemoMedia
{
    public const DIRECTORY = 'demo';

    private const PALETTES = [
        ['#7c5cff', '#b9a8ff', '#ffd166'],
        ['#2ec4b6', '#9be7df', '#ff9f1c'],
        ['#ef476f', '#ffb3c6', '#ffd166'],
        ['#118ab2', '#8fd3f4', '#ffe066'],
        ['#06d6a0', '#a8f0d8', '#f78c6b'],
        ['#f4a261', '#ffd6a5', '#2a9d8f'],
    ];

    public function __construct(private readonly string $publicDisk, private readonly string $privateDisk) {}

    /** A landscape "photo": sky, sun, hills and a caption. Returns the stored path. */
    public function photo(string $path, string $title, string $subtitle, int $seed): string
    {
        [$main, $soft, $accent] = self::PALETTES[$seed % count(self::PALETTES)];
        $sunX = 200 + ($seed * 137) % 800;
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="800" viewBox="0 0 1200 800">
  <defs><linearGradient id="sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="{$soft}"/><stop offset="1" stop-color="#ffffff"/></linearGradient></defs>
  <rect width="1200" height="800" fill="url(#sky)"/>
  <circle cx="{$sunX}" cy="220" r="90" fill="{$accent}" opacity="0.9"/>
  <path d="M0 560 Q 300 420 600 540 T 1200 500 V 800 H 0 Z" fill="{$main}" opacity="0.55"/>
  <path d="M0 640 Q 350 540 700 640 T 1200 610 V 800 H 0 Z" fill="{$main}"/>
  <text x="60" y="720" font-family="Helvetica, Arial, sans-serif" font-size="54" font-weight="700" fill="#ffffff">{$this->escape($title)}</text>
  <text x="60" y="770" font-family="Helvetica, Arial, sans-serif" font-size="30" fill="#ffffff" opacity="0.9">{$this->escape($subtitle)}</text>
</svg>
SVG;

        return $this->putPublic($path, $svg);
    }

    /** A square badge with initials, used for logos and profile photos. */
    public function badge(string $path, string $name, int $seed, bool $round = false): string
    {
        [$main, , $accent] = self::PALETTES[$seed % count(self::PALETTES)];
        $initials = $this->escape($this->initials($name));
        $shape = $round
            ? "<circle cx=\"200\" cy=\"200\" r=\"200\" fill=\"{$main}\"/>"
            : "<rect width=\"400\" height=\"400\" rx=\"72\" fill=\"{$main}\"/>";
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400">
  {$shape}
  <circle cx="320" cy="80" r="36" fill="{$accent}"/>
  <text x="200" y="250" text-anchor="middle" font-family="Helvetica, Arial, sans-serif" font-size="150" font-weight="700" fill="#ffffff">{$initials}</text>
</svg>
SVG;

        return $this->putPublic($path, $svg);
    }

    /** A one-page PDF with a title and lines of text. Returns [path, size]. */
    public function pdf(string $path, string $title, array $lines, bool $private = false): array
    {
        $content = 'BT /F1 22 Tf 72 730 Td ('.$this->pdfText($title).") Tj ET\n";
        $content .= "BT /F1 12 Tf 72 696 Td 18 TL\n";
        foreach ($lines as $line) {
            $content .= '('.$this->pdfText($line).") Tj T*\n";
        }
        $content .= "ET\n";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($content)." >>\nstream\n{$content}endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer'."\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        $path = self::DIRECTORY.'/'.$path;
        Storage::disk($private ? $this->privateDisk : $this->publicDisk)->put($path, $pdf);

        return [$path, strlen($pdf)];
    }

    /** Removes every demo file from both disks. */
    public function purge(): void
    {
        Storage::disk($this->publicDisk)->deleteDirectory(self::DIRECTORY);
        Storage::disk($this->privateDisk)->deleteDirectory(self::DIRECTORY);
    }

    private function putPublic(string $path, string $contents): string
    {
        $path = self::DIRECTORY.'/'.$path;
        Storage::disk($this->publicDisk)->put($path, $contents);

        return $path;
    }

    private function initials(string $name): string
    {
        $words = array_values(array_filter(preg_split('/[\s&,]+/', Str::ascii($name)) ?: [], fn ($w) => ctype_alpha($w[0] ?? '')));

        return strtoupper(implode('', array_map(fn ($w) => $w[0], array_slice($words, 0, 2)))) ?: '?';
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** PDF base fonts are Latin-1; keep to ASCII and escape the string delimiters. */
    private function pdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], Str::ascii($text));
    }
}
