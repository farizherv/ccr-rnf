<?php

namespace App\Support\WorksheetTemplates;

/**
 * Seat worksheet templates repository (parity dengan EngineTemplateRepo).
 *
 * Source-of-truth:
 * - resources/worksheet_templates/seat/registry.php
 * - resources/worksheet_templates/seat/<template_folder>/<version>/manifest.php
 * - defaults_parts.php / defaults_detail.php / datalists.php di folder version
 */
final class SeatTemplateRepo
{
    // =========================
    // Base paths
    // =========================
    public static function basePath(): string
    {
        return resource_path('worksheet_templates/seat');
    }

    public static function registryPath(): string
    {
        return self::basePath() . '/registry.php';
    }

    // =========================
    // Registry
    // =========================
    public static function registry(): array
    {
        $path = self::registryPath();
        if (!is_file($path)) return [];

        $data = require $path;
        return is_array($data) ? $data : [];
    }

    /**
     * Resolve manifest path dari registry.
     * registry shape:
     *  key => ['name'=>..., 'path'=> 'blank', 'latest'=>'v1', ...]
     */
    public static function resolveManifestPath(string $templateKey, ?string $version = null): ?string
    {
        $reg = self::registry();
        if (!isset($reg[$templateKey])) return null;

        $entry = is_array($reg[$templateKey]) ? $reg[$templateKey] : [];

        // prefer schema baru: path/latest
        $rel = $entry['path'] ?? null;
        $latest = $entry['latest'] ?? null;

        // fallback schema lama: folder/versions[]
        if (!$rel && !empty($entry['folder'])) {
            $rel = $entry['folder'];
        }
        if (!$latest && !empty($entry['versions']) && is_array($entry['versions'])) {
            $latest = $entry['versions'][0] ?? null;
        }

        $latest = (string) ($latest ?? 'v1');
        if (!$rel) return null;

        $ver = $version ? (string) $version : $latest;
        $ver = trim($ver) !== '' ? trim($ver) : $latest;

        $path = self::basePath() . '/' . $rel . '/' . $ver . '/manifest.php';
        return $path;
    }

    // =========================
    // Manifest
    // =========================
    public static function manifest(string $templateKey, ?string $version = null): ?array
    {
        $path = self::resolveManifestPath($templateKey, $version);
        if (!$path || !is_file($path)) return null;

        $m = include $path;
        if (!is_array($m)) return null;

        $m['_paths'] = [
            'manifest' => $path,
        ];

        return $m;
    }

    // =========================
    // Defaults loader (static)
    // =========================
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
     * Instance API (parity dengan rencana EngineController): loadDefaults($key, $verInt)
     * Return BOTH keys:
     *  - parts_defaults/detail_defaults (dipakai controller)
     *  - parts/detail (compat)
     */
    public function loadDefaults(string $templateKey, int $versionInt = 1): array
    {
        $versionInt = max(1, (int) $versionInt);
        $ver = 'v' . $versionInt;

        // prefer requested version, fallback ke latest
        $m = self::manifest($templateKey, $ver);
        if (!$m) {
            $m = self::manifest($templateKey);
        }
        if (!$m) return [];

        $partsPath  = $m['defaults']['parts']  ?? null;
        $detailPath = $m['defaults']['detail'] ?? null;

        $parts  = (is_string($partsPath)  && is_file($partsPath))  ? include $partsPath  : [];
        $detail = (is_string($detailPath) && is_file($detailPath)) ? include $detailPath : [];

        $parts  = is_array($parts) ? $parts : [];
        $detail = is_array($detail) ? $detail : [];

        return [
            'manifest'        => $m,
            'parts_defaults'  => $parts,
            'detail_defaults' => $detail,
            'parts'           => $parts,
            'detail'          => $detail,
        ];
    }

    // =========================
    // Datalists
    // =========================

    /**
     * Load datalists per template (versioned).
     * Return shape minimal:
     *  [
     *    'uom' => string[],
     *    'part_description' => string[],
     *    'part_section' => string[],
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

        // fallback: seat_blank
        if ($templateKey !== 'seat_blank') {
            $tpl = self::loadTemplateDatalists('seat_blank');
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
            if (!isset($tpl[$k]) || !is_array($tpl[$k])) continue;

            $vals = [];
            $seen = [];
            foreach ($tpl[$k] as $v) {
                $s = trim((string) $v);
                if ($s === '') continue;

                $hash = mb_strtolower($s);
                if (isset($seen[$hash])) continue;

                $seen[$hash] = true;
                $vals[] = $s;
            }
            $out[$k] = array_values($vals);
        }

        // allow extra keys if template provides them (e.g. items master)
        foreach ($tpl as $k => $v) {
            if (isset($out[$k])) continue;
            if (is_array($v)) {
                $clean = [];
                $seen = [];
                foreach ($v as $vv) {
                    $s = trim((string) $vv);
                    if ($s === '') continue;
                    $hash = mb_strtolower($s);
                    if (isset($seen[$hash])) continue;
                    $seen[$hash] = true;
                    $clean[] = $s;
                }
                $out[$k] = array_values($clean);
            }
        }

        return $out;
    }

    // =========================
    // List templates for UI
    // =========================
    public static function list(): array
    {
        $reg = self::registry();
        $out = [];

        foreach ($reg as $key => $info) {
            $m = self::manifest($key);
            $latest = $info['latest'] ?? null;
            if (!$latest && !empty($info['versions']) && is_array($info['versions'])) {
                $latest = $info['versions'][0] ?? null;
            }
            $out[$key] = [
                'key'     => $key,
                'name'    => $m['name'] ?? ($info['name'] ?? $info['display_name'] ?? $info['title'] ?? $key),
                'version' => $m['version'] ?? ($latest ?? 'v1'),
                'notes'   => $m['notes'] ?? ($info['notes'] ?? ''),
            ];
        }

        return $out;
    }

    // =========================
    // Debug
    // =========================
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
