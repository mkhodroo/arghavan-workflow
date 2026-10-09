<?php

namespace ArghavanWorkflow\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * سرو فایل‌های استاتیک پکیج (bpmn-js + فایل‌های خود پکیج).
 *
 * با این مسیر نیازی به publish کردن assetها نیست؛ فقط مسیرهای
 * سفیدسازی‌شده با پسوندهای مجاز ارسال می‌شوند.
 */
class AssetController extends Controller
{
    private const ALLOWED_EXTENSIONS = ['js', 'css', 'svg', 'woff', 'woff2', 'ttf', 'eot', 'map'];

    public function show(string $path)
    {
        $roots = [
            'bpmn-js/' => $this->packagePath('resources/assets/bpmn-js'),
            'css/' => $this->packagePath('resources/css'),
            'js/' => $this->packagePath('resources/js'),
        ];

        foreach ($roots as $prefix => $root) {
            if (! str_starts_with($path, $prefix)) {
                continue;
            }

            $file = $this->resolve($root, substr($path, strlen($prefix)));

            if ($file === null) {
                abort(404);
            }

            return response()->file($file, [
                'Content-Type' => $this->mime($file),
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        abort(404);
    }

    /**
     * تبدیل مسیر نسبی به فایل واقعی؛ null اگر از پوشه پکیج خارج شود.
     */
    private function resolve(string $root, string $relative): ?string
    {
        if ($relative === '' || str_contains($relative, "\0")) {
            return null;
        }

        $root = realpath($root);

        if ($root === false) {
            return null;
        }

        $file = realpath($root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative));

        if ($file === false || ! is_file($file)) {
            return null;
        }

        // جلوگیری از Path Traversal
        if (! str_starts_with($file, $root.DIRECTORY_SEPARATOR)) {
            return null;
        }

        if (! in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::ALLOWED_EXTENSIONS, true)) {
            return null;
        }

        return $file;
    }

    private function packagePath(string $relative): string
    {
        return dirname(__DIR__, 3).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function mime(string $file): string
    {
        return match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'js' => 'application/javascript; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            default => 'application/octet-stream',
        };
    }
}
