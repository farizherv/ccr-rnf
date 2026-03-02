<?php

namespace App\Support\WorksheetTemplates;

use App\Models\CcrReport;
use Illuminate\Support\Facades\DB;

class SeatTemplateService
{
    /**
     * Pastikan report Seat punya payload template jika payload masih kosong.
     * Tidak menimpa data user yang sudah ada.
     */
    public static function ensureInitialized(CcrReport $report): void
    {
        $key = trim((string) ($report->template_key ?? ''));
        if ($key === '') return;

        $partsEmpty  = empty($report->parts_payload)  || $report->parts_payload === [];
        $detailEmpty = empty($report->detail_payload) || $report->detail_payload === [];

        // Kalau sudah ada data, jangan overwrite.
        if (!$partsEmpty && !$detailEmpty) return;

        $defaults = SeatTemplateRepo::defaults($key);
        if (empty($defaults['manifest'])) return;

        $manifest = $defaults['manifest'] ?? [];
        $versionStr = is_array($manifest) ? trim((string) ($manifest['version'] ?? '')) : '';
        $versionInt = self::parseTemplateVersion($versionStr) ?? ((int) ($report->template_version ?: 1));

        DB::transaction(function () use ($report, $key, $versionInt, $defaults, $manifest) {
            // re-fetch + lock biar aman kalau kebuka bersamaan
            $r = CcrReport::whereKey($report->id)->lockForUpdate()->first();

            $partsEmpty  = empty($r->parts_payload)  || $r->parts_payload === [];
            $detailEmpty = empty($r->detail_payload) || $r->detail_payload === [];

            $r->template_key = $key;
            $r->template_version = $versionInt;

            if ($partsEmpty)  $r->parts_payload  = $defaults['parts']  ?? [];
            if ($detailEmpty) $r->detail_payload = $defaults['detail'] ?? [];

            // simpan meta template (biar UI & DB sinkron)
            if (is_array($manifest)) {
                $r->parts_payload  = self::putTemplateMeta($r->parts_payload,  $key, $manifest);
                $r->detail_payload = self::putTemplateMeta($r->detail_payload, $key, $manifest);
            }

            $r->save();
        });
    }

    /**
     * Apply template ke report Seat.
     * - replace=false (default): kalau sudah ada payload, tidak overwrite.
     * - replace=true: overwrite payload dengan defaults template.
     */
    public static function applyTemplate(CcrReport $report, string $templateKey, bool $replace = false): array
    {
        $templateKey = trim($templateKey);
        if ($templateKey === '') {
            return ['ok' => false, 'message' => 'Template key kosong.'];
        }

        $manifest = SeatTemplateRepo::manifest($templateKey);
        if (!$manifest) {
            return ['ok' => false, 'message' => 'Template tidak ditemukan.'];
        }

        $defaults = SeatTemplateRepo::defaults($templateKey);

        $versionStr = is_array($manifest) ? trim((string) ($manifest['version'] ?? '')) : '';
        $versionInt = self::parseTemplateVersion($versionStr) ?? 1;

        DB::transaction(function () use ($report, $templateKey, $versionInt, $replace, $defaults, $manifest) {
            $r = CcrReport::whereKey($report->id)->lockForUpdate()->first();

            $r->template_key = $templateKey;
            $r->template_version = $versionInt;

            $partsEmpty  = empty($r->parts_payload)  || $r->parts_payload === [];
            $detailEmpty = empty($r->detail_payload) || $r->detail_payload === [];

            // overwrite hanya jika replace=true atau payload kosong
            if ($replace || $partsEmpty)  $r->parts_payload  = $defaults['parts']  ?? [];
            if ($replace || $detailEmpty) $r->detail_payload = $defaults['detail'] ?? [];

            // simpan meta template
            if (is_array($manifest)) {
                $r->parts_payload  = self::putTemplateMeta($r->parts_payload,  $templateKey, $manifest);
                $r->detail_payload = self::putTemplateMeta($r->detail_payload, $templateKey, $manifest);
            }

            // export Word harus regenerasi kalau payload berubah
            $r->docx_generated_at = null;
            $r->save();
        });

        return ['ok' => true];
    }

    // ==========================================================
    // Helpers
    // ==========================================================

    private static function parseTemplateVersion(?string $v): ?int
    {
        $s = trim((string) $v);
        if ($s === '') return null;

        // terima '1', 'v1', 'V2', 'version 3', dll
        if (preg_match('/(\d+)/', $s, $m)) {
            $n = (int) $m[1];
            return $n > 0 ? $n : null;
        }

        return null;
    }

    private static function putTemplateMeta($payload, string $templateKey, array $manifest): array
    {
        $payload = is_array($payload) ? $payload : [];
        if (!isset($payload['meta']) || !is_array($payload['meta'])) $payload['meta'] = [];

        $payload['meta']['template_key'] = $templateKey;

        $ver = (string) ($manifest['version'] ?? '');
        if (trim($ver) !== '') {
            $payload['meta']['template_version'] = trim($ver);
        }

        // optional object (selaras dengan Engine controller sanitize)
        $payload['meta']['template'] = [
            'key'     => (string) ($manifest['key'] ?? $templateKey),
            'version' => (string) ($manifest['version'] ?? ''),
            'name'    => (string) ($manifest['name'] ?? ''),
        ];

        return $payload;
    }
}
