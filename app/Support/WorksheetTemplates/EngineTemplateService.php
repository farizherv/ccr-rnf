<?php

namespace App\Support\WorksheetTemplates;

use App\Models\CcrReport;
use Illuminate\Support\Facades\DB;

class EngineTemplateService
{
    /**
     * Pastikan report punya payload dari template jika payload masih kosong.
     * Tidak menimpa data user yang sudah ada.
     */
    public static function ensureInitialized(CcrReport $report): void
    {
        if (!$report->template_key) return;

        $partsEmpty  = empty($report->parts_payload)  || $report->parts_payload === [];
        $detailEmpty = empty($report->detail_payload) || $report->detail_payload === [];

        // Kalau sudah ada data, jangan overwrite.
        if (!$partsEmpty || !$detailEmpty) return;

        $defaults = EngineTemplateRepo::defaults($report->template_key);
        if (empty($defaults['manifest'])) return;

        DB::transaction(function () use ($report, $defaults) {
            // re-fetch + lock biar aman kalau kebuka bersamaan
            $r = CcrReport::whereKey($report->id)->lockForUpdate()->first();

            $partsEmpty  = empty($r->parts_payload)  || $r->parts_payload === [];
            $detailEmpty = empty($r->detail_payload) || $r->detail_payload === [];

            if ($partsEmpty)  $r->parts_payload  = $defaults['parts']  ?? [];
            if ($detailEmpty) $r->detail_payload = $defaults['detail'] ?? [];

            // (opsional tapi bagus) simpan versi template ke meta payload
            $m = $defaults['manifest'] ?? [];
            if (is_array($m)) {
                $r->parts_payload  = self::putMeta($r->parts_payload,  'template', ['key' => $m['key'] ?? $r->template_key, 'version' => $m['version'] ?? null]);
                $r->detail_payload = self::putMeta($r->detail_payload, 'template', ['key' => $m['key'] ?? $r->template_key, 'version' => $m['version'] ?? null]);
            }

            $r->save();
        });
    }

    /**
     * Apply template key ke report.
     * - replace=false (default): kalau sudah ada payload, tidak overwrite.
     * - replace=true: overwrite payload dengan defaults template (confirm di UI).
     */
    public static function applyTemplate(CcrReport $report, string $templateKey, bool $replace = false): array
    {
        $manifest = EngineTemplateRepo::manifest($templateKey);
        if (!$manifest) {
            return ['ok' => false, 'message' => 'Template tidak ditemukan.'];
        }

        $defaults = EngineTemplateRepo::defaults($templateKey);

        DB::transaction(function () use ($report, $templateKey, $replace, $defaults) {
            $r = CcrReport::whereKey($report->id)->lockForUpdate()->first();
            $r->template_key = $templateKey;

            $partsEmpty  = empty($r->parts_payload)  || $r->parts_payload === [];
            $detailEmpty = empty($r->detail_payload) || $r->detail_payload === [];

            // overwrite hanya jika replace=true atau payload kosong
            if ($replace || $partsEmpty)  $r->parts_payload  = $defaults['parts']  ?? [];
            if ($replace || $detailEmpty) $r->detail_payload = $defaults['detail'] ?? [];

            // simpan meta template
            $m = $defaults['manifest'] ?? [];
            if (is_array($m)) {
                $r->parts_payload  = self::putMeta($r->parts_payload,  'template', ['key' => $m['key'] ?? $templateKey, 'version' => $m['version'] ?? null]);
                $r->detail_payload = self::putMeta($r->detail_payload, 'template', ['key' => $m['key'] ?? $templateKey, 'version' => $m['version'] ?? null]);
            }

            $r->save();
        });

        return ['ok' => true];
    }

    private static function putMeta($payload, string $k, array $v): array
    {
        $payload = is_array($payload) ? $payload : [];
        if (!isset($payload['meta']) || !is_array($payload['meta'])) $payload['meta'] = [];
        $payload['meta'][$k] = $v;
        return $payload;
    }
}
