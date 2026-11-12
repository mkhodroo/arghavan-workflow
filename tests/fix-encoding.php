<?php

/**
 * ابزار تعمیر انکدینگ: فایل‌هایی که متن فارسی‌شان به mojibake
 * (UTF-8 -> CP1252 -> UTF-8) تبدیل شده را به UTF-8 صحیح برمی‌گرداند.
 *
 * اجرا:  php tests/fix-encoding.php [--write]
 */

$root = dirname(__DIR__);
$write = in_array('--write', $argv, true);

$files = $argv;
unset($files[0]);
$files = array_values(array_filter(
    $files,
    static fn ($a) => ! str_starts_with($a, '-')
        && ! preg_match('#^[A-Za-z]:[\\\\/]#', $a)
        && (str_ends_with($a, '.php') || str_ends_with($a, '.js') || str_ends_with($a, '.md'))
));
$files = $files ?: ['tests/run.php', 'tests/designer-palette.test.js'];

// جدول برگشتی CP1252
$probe = '';
for ($b = 0x80; $b <= 0xFF; $b++) {
    $probe .= chr($b);
}
$chars = preg_split('//u', mb_convert_encoding($probe, 'UTF-8', 'Windows-1252'), -1, PREG_SPLIT_NO_EMPTY);
$reverse = [];
foreach ($chars as $i => $ch) {
    $reverse[$ch] = chr(0x80 + $i);
}

function restore(string $text, array $reverse): string
{
    $out = '';
    $len = mb_strlen($text, 'UTF-8');

    for ($i = 0; $i < $len; $i++) {
        $ch = mb_substr($text, $i, 1, 'UTF-8');
        $out .= $reverse[$ch] ?? $ch;
    }

    return $out;
}

function stats(string $text): array
{
    return [
        'persian' => preg_match_all('/[\x{0600}-\x{06FF}]/u', $text),
        'mojibake' => preg_match_all('/[\x{00C0}-\x{00FF}][\x{0080}-\x{00FF}\x{2013}\x{2014}\x{2018}-\x{201E}\x{2020}-\x{2022}\x{2026}\x{2030}\x{2039}\x{203A}\x{20AC}\x{0152}\x{0153}\x{0160}\x{0161}\x{0178}\x{017D}\x{017E}\x{0192}\x{02C6}\x{02DC}]/u', $text),
        'valid' => mb_check_encoding($text, 'UTF-8'),
    ];
}

foreach ($files as $f) {
    $path = $root.'/'.ltrim($f, '/');
    $raw = file_get_contents($path);
    $before = stats($raw);
    $fixed = restore($raw, $reverse);
    $after = stats($fixed);

    printf(
        "%-40s persian %d->%d | mojibake %d->%d | utf8 %s\n",
        $f,
        $before['persian'],
        $after['persian'],
        $before['mojibake'],
        $after['mojibake'],
        $after['valid'] ? 'valid' : 'INVALID'
    );

    if ($write && ($before['mojibake'] > 0 || $before['persian'] === 0) && $after['persian'] > 0) {
        // حفظ CRLF برای یکسانی با بقیه فایل‌های پروژه
        $crlf = strpos($raw, "\r\n") !== false;
        $body = $crlf ? str_replace("\n", "\r\n", $fixed) : $fixed;

        file_put_contents($path, $body);
        echo "  -> written\n";
    }
}

if (! $write) {
    echo "\n(dry-run؛ برای اعمال از --write استفاده کنید)\n";
}
