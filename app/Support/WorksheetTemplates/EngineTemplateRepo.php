<?php

namespace App\Support\WorksheetTemplates;

final class EngineTemplateRepo
{
    public static function basePath(): string
    {
        return resource_path('worksheet_templates/engine');
    }

    public static function registryPath(): string
    {
        return self::basePath() . '/registry.php';
    }

    public static function registry(): array
    {
        $path = self::registryPath();
        if (!is_file($path)) return [];

        $data = require $path;
        return is_array($data) ? $data : [];
    }

    public static function resolveManifestPath(string $templateKey): ?string
    {
        $reg = self::registry();
        if (!isset($reg[$templateKey])) return null;

        $rel    = $reg[$templateKey]['path'] ?? null;
        $latest = $reg[$templateKey]['latest'] ?? 'v1';
        if (!$rel) return null;

        $path = self::basePath() . '/' . $rel . '/' . $latest . '/manifest.php';
        return $path;
    }

    public static function manifest(string $templateKey): ?array
    {
        $path = self::resolveManifestPath($templateKey);
        if (!$path || !is_file($path)) return null;

        $m = include $path; // include supaya lebih aman untuk debug
        if (!is_array($m)) return null;

        // Tambahin info path biar gampang trace
        $m['_paths'] = [
            'manifest' => $path,
        ];

        return $m;
    }

    public static function defaults(string $templateKey): array
    {
        $m = self::manifest($templateKey);
        if (!$m) return [];

        $partsPath  = $m['defaults']['parts']  ?? null;
        $detailPath = $m['defaults']['detail'] ?? null;

        $parts  = (is_string($partsPath)  && is_file($partsPath))  ? include $partsPath  : [];
        $detail = (is_string($detailPath) && is_file($detailPath)) ? include $detailPath : [];

        return [
            'manifest' => $m,
            'parts'    => is_array($parts) ? $parts : [],
            'detail'   => is_array($detail) ? $detail : [],
        ];
    }
    /**
     * Load datalists for a template (per-template, versioned).
     *
     * No more resources/data/* fallback: source-of-truth is per-template datalists.php.
     * If a template has no datalists.php, we fallback to engine_blank (if exists).
     *
     * Return shape:
     *  [
     *    'uom'              => string[],
     *    'part_description' => string[],
     *    'part_section'     => string[],
     *  ]
     */
    public static function datalists(string $templateKey): array
    {
        $empty = [
            'uom' => [],
            'part_description' => [],
            'part_section' => [],
        ];

        $tpl = self::loadTemplateDatalists($templateKey);
        if (is_array($tpl)) return $tpl;

        // fallback: engine_blank
        if ($templateKey !== 'engine_blank') {
            $tpl = self::loadTemplateDatalists('engine_blank');
            if (is_array($tpl)) return $tpl;
        }

        return $empty;
    }

    private static function loadTemplateDatalists(string $templateKey): ?array
    {
        $m = self::manifest($templateKey);
        if (!$m || empty($m['_paths']['manifest'])) return null;

        $dir = dirname((string) $m['_paths']['manifest']);
        $path = $dir . '/datalists.php';
        if (!is_file($path)) return null;

        $tpl = include $path;
        if (!is_array($tpl)) return null;

        $out = [
            'uom' => [],
            'part_description' => [],
            'part_section' => [],
        ];

        foreach (['uom', 'part_description', 'part_section'] as $k) {
            if (isset($tpl[$k]) && is_array($tpl[$k])) {
                $vals = [];
                $seen = [];
                foreach ($tpl[$k] as $v) {
                    $s = trim((string) $v);
                    if ($s === '') continue;
                    $key = mb_strtolower($s);
                    if (isset($seen[$key])) continue;
                    $seen[$key] = true;
                    $vals[] = $s;
                }
                $out[$k] = array_values($vals);
            }
        }

        return $out;
    }

    public static function list(): array
    {
        $reg = self::registry();
        $out = [];

        foreach ($reg as $key => $info) {
            $m = self::manifest($key);
            $out[$key] = [
                'key'     => $key,
                'name'    => $m['name'] ?? ($info['name'] ?? $key),
                'version' => $m['version'] ?? ($info['latest'] ?? 'v1'),
                'notes'   => $m['notes'] ?? ($info['notes'] ?? ''),
            ];
        }

        return $out;
    }

    public static function debug(string $templateKey): array
    {
        $base = self::basePath();
        $regPath = self::registryPath();
        $reg = self::registry();

        $entry = $reg[$templateKey] ?? null;
        $manifestPath = self::resolveManifestPath($templateKey);

        $manifestExists = $manifestPath ? is_file($manifestPath) : false;
        $manifestReadable = $manifestPath ? is_readable($manifestPath) : false;

        $manifestType = null;
        $manifestIsArray = null;
        if ($manifestExists) {
            $tmp = include $manifestPath;
            $manifestType = gettype($tmp);
            $manifestIsArray = is_array($tmp);
        }

        return [
            'base_path'         => $base,
            'registry_path'     => $regPath,
            'registry_exists'   => is_file($regPath),
            'registry_keys'     => array_keys($reg),
            'has_key'           => array_key_exists($templateKey, $reg),
            'registry_entry'    => $entry,
            'manifest_path'     => $manifestPath,
            'manifest_exists'   => $manifestExists,
            'manifest_readable' => $manifestReadable,
            'manifest_type'     => $manifestType,
            'manifest_is_array' => $manifestIsArray,
        ];
    }
}
