<?php

namespace App\Controllers;

use App\Models\Product;

class DebugController extends Controller
{
    public function product(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $class = Product::class;
        $file = null;
        $mtime = null;
        try {
            $reflection = new \ReflectionClass($class);
            $file = $reflection->getFileName();
            if ($file !== false && $file !== null) {
                $mtime = @filemtime($file) ?: null;
            }
        } catch (\Throwable $e) {
            // keep nulls
        }

        $opcacheEnabled = null;
        if (function_exists('opcache_get_status')) {
            $status = opcache_get_status(false);
            if (is_array($status)) {
                $opcacheEnabled = $status['opcache_enabled'] ?? null;
            }
        }

        $data = [
            'class' => $class,
            'file' => $file,
            'file_mtime' => $mtime,
            'php_sapi' => PHP_SAPI,
            'cwd' => getcwd(),
            'app_path' => defined('APP_PATH') ? APP_PATH : null,
            'opcache_enabled' => $opcacheEnabled,
            'methods' => [
                'buildProductDisplayName' => method_exists($class, 'buildProductDisplayName'),
                'resolveBaseUnitFromPayload' => method_exists($class, 'resolveBaseUnitFromPayload'),
                'normalizeShortText' => method_exists($class, 'normalizeShortText'),
                'normalizeColorHex' => method_exists($class, 'normalizeColorHex'),
            ],
        ];

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
