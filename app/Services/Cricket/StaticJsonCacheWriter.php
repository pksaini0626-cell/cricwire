<?php

namespace App\Services\Cricket;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class StaticJsonCacheWriter
{
    /**
     * Directory path where public static JSON live files reside.
     */
    public static function getStorageDir(): string
    {
        return public_path('live-data');
    }

    /**
     * Atomically write an array payload to a JSON file in public/live-data/
     * using a temp file and rename() to prevent concurrent readers from reading partial writes.
     */
    public static function write(string $filename, array $payload): bool
    {
        try {
            $dir = self::getStorageDir();

            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true, true);
            }

            // Clean filename
            $filename = basename($filename);
            if (!str_ends_with($filename, '.json')) {
                $filename .= '.json';
            }

            $targetPath = $dir . DIRECTORY_SEPARATOR . $filename;
            $tempPath = $dir . DIRECTORY_SEPARATOR . '.tmp_' . $filename . '_' . uniqid('', true);

            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if ($json === false) {
                Log::error("Failed to json_encode payload for {$filename}");
                return false;
            }

            // Write temporary file
            if (file_put_contents($tempPath, $json, LOCK_EX) === false) {
                Log::error("Failed to write temporary file {$tempPath}");
                return false;
            }

            // Atomic rename/move
            if (@rename($tempPath, $targetPath) === false) {
                // Windows fallback if target exists and rename fails
                @unlink($targetPath);
                if (@rename($tempPath, $targetPath) === false) {
                    @unlink($tempPath);
                    Log::error("Failed to atomically rename {$tempPath} to {$targetPath}");
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            Log::error("StaticJsonCacheWriter exception for {$filename}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Read static JSON file payload if available.
     */
    public static function read(string $filename): ?array
    {
        $filename = basename($filename);
        if (!str_ends_with($filename, '.json')) {
            $filename .= '.json';
        }

        $targetPath = self::getStorageDir() . DIRECTORY_SEPARATOR . $filename;
        if (!file_exists($targetPath)) {
            return null;
        }

        $content = @file_get_contents($targetPath);
        if ($content === false || empty($content)) {
            return null;
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }
}
