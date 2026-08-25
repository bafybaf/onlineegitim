<?php
declare(strict_types=1);

$root = dirname(__DIR__);
if (!extension_loaded('gd')) {
    fwrite(STDERR, "GD eklentisi yok.\n");
    exit(1);
}

$font = '';
foreach ([
    'C:\\Windows\\Fonts\\arialbd.ttf',
    'C:\\Windows\\Fonts\\arial.ttf',
    'C:\\Windows\\Fonts\\calibrib.ttf',
    'C:\\Windows\\Fonts\\segoeuib.ttf',
    'C:\\Windows\\Fonts\\tahoma.ttf',
] as $cand) {
    if (is_file($cand)) {
        $font = $cand;
        break;
    }
}
if ($font === '') {
    fwrite(STDERR, "TTF font bulunamadı.\n");
    exit(1);
}

function hex_rgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $n = hexdec($hex);
    return [($n >> 16) & 255, ($n >> 8) & 255, $n & 255];
}

function shade(array $rgb, float $f): array
{
    return [
        max(0, min(255, (int) round($rgb[0] * $f))),
        max(0, min(255, (int) round($rgb[1] * $f))),
        max(0, min(255, (int) round($rgb[2] * $f))),
    ];
}

function wrap_text(string $text, string $font, int $size, int $maxW): array
{
    $words = preg_split('/\s+/u', trim($text)) ?: [];
    $lines = [];
    $cur = '';
    foreach ($words as $w) {
        $try = $cur === '' ? $w : $cur . ' ' . $w;
        $box = imagettfbbox($size, 0, $font, $try);
        $wpx = abs($box[2] - $box[0]);
        if ($wpx > $maxW && $cur !== '') {
            $lines[] = $cur;
            $cur = $w;
        } else {
            $cur = $try;
        }
    }
    if ($cur !== '') {
        $lines[] = $cur;
    }
    return $lines ?: [$text];
}

function draw_centered(GdImage $im, string $font, int $size, int $cx, int $y, string $text, int $color): int
{
    $box = imagettfbbox($size, 0, $font, $text);
    $w = abs($box[2] - $box[0]);
    $h = abs($box[7] - $box[1]);
    imagettftext($im, $size, 0, (int) round($cx - $w / 2), $y, $color, $font, $text);
    return $h;
}

function fill_vgrad(GdImage $im, int $w, int $h, array $from, array $to): void
{
    for ($y = 0; $y < $h; $y++) {
        $t = $h > 1 ? $y / ($h - 1) : 0;
        $r = (int) round($from[0] + ($to[0] - $from[0]) * $t);
        $g = (int) round($from[1] + ($to[1] - $from[1]) * $t);
        $b = (int) round($from[2] + ($to[2] - $from[2]) * $t);
        $c = imagecolorallocate($im, $r, $g, $b);
        imageline($im, 0, $y, $w, $y, $c);
    }
}

function save_jpg(GdImage $im, string $path): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Klasör yok: ' . $dir);
    }
    if (!imagejpeg($im, $path, 88)) {
        throw new RuntimeException('Yazılamadı: ' . $path);
    }
}

function make_book(string $path, string $title, string $category, string $hex, string $font): void
{
    $w = 400;
    $h = 560;
    $im = imagecreatetruecolor($w, $h);
    $rgb = hex_rgb($hex);
    fill_vgrad($im, $w, $h, shade($rgb, 1.15), shade($rgb, 0.55));
    $white = imagecolorallocate($im, 255, 255, 255);
    $gold = imagecolorallocate($im, 232, 214, 160);
    $mute = imagecolorallocatealpha($im, 255, 255, 255, 70);
    imagerectangle($im, 18, 18, $w - 19, $h - 19, $gold);
    imagerectangle($im, 24, 24, $w - 25, $h - 25, $mute);
    $cat = mb_strtoupper($category, 'UTF-8');
    draw_centered($im, $font, 13, (int) ($w / 2), 70, $cat, $gold);
    $lines = wrap_text($title, $font, 28, 300);
    $lineH = 40;
    $block = count($lines) * $lineH;
    $y = (int) round(($h - $block) / 2) + 10;
    foreach ($lines as $line) {
        draw_centered($im, $font, 28, (int) ($w / 2), $y, $line, $white);
        $y += $lineH;
    }
    draw_centered($im, $font, 12, (int) ($w / 2), $h - 56, 'ONLINE İLAHİYAT', $gold);
    draw_centered($im, $font, 11, (int) ($w / 2), $h - 36, 'Kitap Mağazası', $mute);
    save_jpg($im, $path);
    imagedestroy($im);
}

function make_program(string $path, string $title, string $level, string $hex, string $font): void
{
    $w = 800;
    $h = 500;
    $im = imagecreatetruecolor($w, $h);
    $rgb = hex_rgb($hex);
    fill_vgrad($im, $w, $h, shade($rgb, 1.2), shade($rgb, 0.5));
    $white = imagecolorallocate($im, 255, 255, 255);
    $gold = imagecolorallocate($im, 232, 214, 160);
    imagerectangle($im, 22, 22, $w - 23, $h - 23, $gold);
    draw_centered($im, $font, 14, (int) ($w / 2), 80, 'EĞİTİM PROGRAMI', $gold);
    $lines = wrap_text($title, $font, 36, 680);
    $y = 210;
    foreach ($lines as $line) {
        draw_centered($im, $font, 36, (int) ($w / 2), $y, $line, $white);
        $y += 52;
    }
    draw_centered($im, $font, 16, (int) ($w / 2), $h - 70, $level, $white);
    draw_centered($im, $font, 12, (int) ($w / 2), $h - 42, 'ONLINE İLAHİYAT', $gold);
    save_jpg($im, $path);
    imagedestroy($im);
}

$books = [
    ['tecvid', 'Tecvid Atlası', 'Kıraat', '#0f2a7a'],
    ['riyazu', 'Riyazü’s-Salihin', 'Hadis', '#0f2a7a'],
    ['usul', 'Fıkıh Usulü', 'Fıkıh', '#1a3fad'],
    ['nahiv', 'Nahiv', 'Arapça', '#0c5444'],
    ['muvatta', 'Muvatta', 'Hadis', '#0a1a4e'],
    ['siyer', 'Siyer', 'Siyer', '#0a1a4e'],
    ['tefsir-ozet', 'Tefsir Usulü', 'Tefsir', '#1a3fad'],
    ['akaid-ders', 'Akaid Ders Notları', 'Akaid', '#12705a'],
];
$programs = [
    ['tefsir', 'Tefsir Programı', 'İlahiyat / İhtisas', '#1a3fad'],
    ['hadis', 'Hadis Programı', 'İhtisas', '#0f2a7a'],
    ['fikih', 'Fıkıh Programı', 'Temel + İhtisas', '#12705a'],
    ['akaid', 'Akaid & Kelam', 'Temel', '#0c5444'],
    ['arapca', 'Klasik Arapça', 'A1–B2', '#0a1a4e'],
    ['kiraat', 'Kıraat & Tecvid', 'Tüm seviyeler', '#1a3fad'],
    ['hafizlik', 'Hafızlık Takibi', 'Birebir + grup', '#0f2a7a'],
    ['vaizlik', 'Vaizlik & Hitabet', 'İleri', '#12705a'],
];

$n = 0;
foreach ($books as [$slug, $title, $cat, $hex]) {
    $path = $root . '/assets/img/books/' . $slug . '.jpg';
    make_book($path, $title, $cat, $hex, $font);
    echo "kitap: $path\n";
    $n++;
}
foreach ($programs as [$slug, $title, $level, $hex]) {
    $path = $root . '/assets/img/programs/' . $slug . '.jpg';
    make_program($path, $title, $level, $hex, $font);
    echo "program: $path\n";
    $n++;
}
echo "Toplam $n görsel yazıldı.\n";
