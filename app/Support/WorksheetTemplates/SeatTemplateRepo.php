<?php

namespace App\Support\WorksheetTemplates;

class SeatTemplateRepo
{
    public static function basePath(): string
    {
        return resource_path('worksheet_templates/seat');
    }

    public static function registry(): array
    {
        $path = static::basePath() . '/registry.php';
        $arr = is_file($path) ? include $path : [];
        return is_array($arr) ? $arr : [];
    }

    public static function list(): array
    {
        $out = [];
        $reg = static::registry();
        foreach ($reg as $key => $cfg) {
            if (!is_array($cfg)) continue;
            $latest = static::latestVersion($key);
            $manifest = static::manifest($key, $latest);
            $out[] = [
                'key'          => $key,
                'display_name' => $cfg['display_name'] ?? $key,
                'latest'       => $latest,
                'manifest'     => $manifest,
            ];
        }
        return $out;
    }

    public static function versions(string $key): array
    {
        $reg = static::registry();
        $cfg = $reg[$key] ?? [];
        $vers = $cfg['versions'] ?? [];
        return is_array($vers) ? $vers : [];
    }

    public static function latestVersion(string $key): string
    {
        $vers = static::versions($key);
        return $vers ? (string) end($vers) : 'v1';
    }

    public static function dirOf(string $key, ?string $version = null): string
    {
        $reg = static::registry();
        $cfg = $reg[$key] ?? [];
        $folder = $cfg['folder'] ?? $key;

        $version = $version ?: static::latestVersion($key);
        return static::basePath() . '/' . $folder . '/' . $version;
    }

    public static function manifest(string $key, ?string $version = null): array
    {
        $file = static::dirOf($key, $version) . '/manifest.php';
        $arr = is_file($file) ? include $file : [];
        return is_array($arr) ? $arr : [];
    }

    public static function datalists(string $key, ?string $version = null): array
    {
        $file = static::dirOf($key, $version) . '/datalists.php';
        $arr = is_file($file) ? include $file : [];
        return is_array($arr) ? $arr : [];
    }

    public static function defaults(string $key, ?string $version = null): array
    {
        $manifest = static::manifest($key, $version);

        $partsFile  = static::dirOf($key, $version) . '/defaults_parts.php';
        $detailFile = static::dirOf($key, $version) . '/defaults_detail.php';

        $parts  = is_file($partsFile)  ? include $partsFile  : [];
        $detail = is_file($detailFile) ? include $detailFile : [];

        return [
            'manifest' => $manifest,
            'parts'    => is_array($parts)  ? $parts  : [],
            'detail'   => is_array($detail) ? $detail : [],
        ];
    }
}
