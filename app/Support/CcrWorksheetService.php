<?php

namespace App\Support;

use App\Models\CcrReport;

/**
 * CcrWorksheetService — Shared worksheet payload management logic.
 *
 * Extracted from CcrEngineController & CcrSeatController to eliminate duplication.
 * Handles revision tracking, stale detection, payload section apply, and formatting protection.
 */
class CcrWorksheetService
{
    public function __construct(
        private readonly PayloadSanitizer $sanitizer,
    ) {}

    // =====================================================================
    // Payload column / revision helpers
    // =====================================================================

    public function payloadRevisionColumn(string $section): string
    {
        return $section === 'detail' ? 'detail_payload_rev' : 'parts_payload_rev';
    }

    public function payloadColumn(string $section): string
    {
        return $section === 'detail' ? 'detail_payload' : 'parts_payload';
    }

    public function sectionPayloadArray(CcrReport $report, string $section): array
    {
        $column = $this->payloadColumn($section);
        $payload = $report->{$column} ?? [];
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        return is_array($payload) ? $payload : [];
    }

    public function currentPayloadRevision(CcrReport $report, string $section): int
    {
        $column = $this->payloadRevisionColumn($section);
        return max(0, (int) ($report->{$column} ?? 0));
    }

    public function nextPayloadRevision(int $currentRevision, ?int $incomingTs): int
    {
        $next = max(0, $currentRevision) + 1;
        if ($incomingTs !== null && $incomingTs > 0) {
            $next = max($next, $incomingTs);
        }
        return $next;
    }

    // =====================================================================
    // Stale detection
    // =====================================================================

    public function staleSectionsFromClientRevision(CcrReport $report, ?int $partsClientRev, ?int $detailClientRev): array
    {
        $sections = [];

        if ($partsClientRev !== null && $partsClientRev < $this->currentPayloadRevision($report, 'parts')) {
            $sections[] = 'parts';
        }

        if ($detailClientRev !== null && $detailClientRev < $this->currentPayloadRevision($report, 'detail')) {
            $sections[] = 'detail';
        }

        return $sections;
    }

    public function isStalePayloadWrite(CcrReport $report, string $section, ?int $incomingTs, ?int $clientRevision = null): bool
    {
        $currentRevision = $this->currentPayloadRevision($report, $section);
        $currentPayloadTs = $this->sanitizer->payloadTimestampFromArray($this->sectionPayloadArray($report, $section)) ?? 0;

        if ($incomingTs !== null) {
            if ($clientRevision !== null && $clientRevision < $currentRevision && $incomingTs <= $currentRevision) {
                return true;
            }

            $watermark = max($currentRevision, $currentPayloadTs);
            return $incomingTs < $watermark;
        }

        if ($clientRevision === null) {
            return $currentRevision > 0;
        }

        return $clientRevision < $currentRevision;
    }

    // =====================================================================
    // Apply worksheet payload section (core logic)
    // =====================================================================

    public function applyWorksheetPayloadSection(CcrReport $report, string $section, array $payload, ?int $clientRevision = null): array
    {
        if ($section === 'parts') {
            $payload = $this->protectPartsFormattingPayload($report, $payload);
        }

        $currentPayload = $this->sectionPayloadArray($report, $section);
        $currentRevision = $this->currentPayloadRevision($report, $section);

        if (!empty($currentPayload) && $this->sanitizer->isLikelyIncompleteSectionPayload($section, $payload)) {
            return [
                'saved' => false,
                'payload_changed' => false,
                'stale' => false,
                'incomplete' => true,
                'rev' => $currentRevision,
                'incoming_ts' => $this->sanitizer->payloadTimestampFromArray($payload),
            ];
        }

        $incomingTs = $this->sanitizer->payloadTimestampFromArray($payload);

        if ($this->isStalePayloadWrite($report, $section, $incomingTs, $clientRevision)) {
            return [
                'saved' => false,
                'payload_changed' => false,
                'stale' => true,
                'incomplete' => false,
                'rev' => $currentRevision,
                'incoming_ts' => $incomingTs,
            ];
        }

        $column = $this->payloadColumn($section);
        $revColumn = $this->payloadRevisionColumn($section);

        $payloadChanged = $currentPayload !== $payload;
        $revisionChanged = false;

        if ($payloadChanged) {
            $report->{$column} = $payload;
        }

        if ($payloadChanged || ($incomingTs !== null && $currentRevision <= 0)) {
            $nextRevision = $this->nextPayloadRevision($currentRevision, $incomingTs);
            if ((int) ($report->{$revColumn} ?? 0) !== $nextRevision) {
                $report->{$revColumn} = $nextRevision;
                $revisionChanged = true;
            }
        }

        return [
            'saved' => ($payloadChanged || $revisionChanged),
            'payload_changed' => $payloadChanged,
            'stale' => false,
            'incomplete' => false,
            'rev' => (int) ($report->{$revColumn} ?? $currentRevision),
            'incoming_ts' => $incomingTs,
        ];
    }

    // =====================================================================
    // Parts formatting protection
    // =====================================================================

    public function protectPartsFormattingPayload(CcrReport $report, array $incoming): array
    {
        $current = $this->sectionPayloadArray($report, 'parts');
        if (empty($current)) {
            return $incoming;
        }

        $currentStyles = (isset($current['styles']) && is_array($current['styles'])) ? $current['styles'] : [];
        $currentNotes  = (isset($current['notes']) && is_array($current['notes'])) ? $current['notes'] : [];
        $currentTools  = (isset($current['tools']) && is_array($current['tools'])) ? $current['tools'] : [];

        $incomingStyles = (isset($incoming['styles']) && is_array($incoming['styles'])) ? $incoming['styles'] : [];
        $incomingNotes  = (isset($incoming['notes']) && is_array($incoming['notes'])) ? $incoming['notes'] : [];
        $incomingTools  = (isset($incoming['tools']) && is_array($incoming['tools'])) ? $incoming['tools'] : [];

        $formatAction = strtolower(trim((string) ($incomingTools['last_action'] ?? '')));
        $toolTargets = $this->sanitizer->extractWorksheetToolTargetKeys($incomingTools);

        $stylesMissingOrEmpty = !array_key_exists('styles', $incoming) || empty($incomingStyles);
        $notesMissingOrEmpty = !array_key_exists('notes', $incoming) || empty($incomingNotes);
        $toolsMissingOrEmpty = !array_key_exists('tools', $incoming) || empty($incomingTools);

        $stylesReplayed = false;
        if ($stylesMissingOrEmpty) {
            $replayedStyles = $this->sanitizer->replayStylesFromTools($currentStyles, $incomingTools);
            if (is_array($replayedStyles)) {
                $incoming['styles'] = $replayedStyles;
                $stylesReplayed = true;
            } else {
                $incoming['styles'] = $currentStyles;
            }
        }

        if ($notesMissingOrEmpty) {
            $replayedNotes = $this->sanitizer->replayNotesFromTools($currentNotes, $incomingTools);
            if (is_array($replayedNotes)) {
                $incoming['notes'] = $replayedNotes;
            } else {
                $incoming['notes'] = $currentNotes;
            }
        }

        if ($toolsMissingOrEmpty && !empty($currentTools)) {
            $incoming['tools'] = $currentTools;
        }

        return $incoming;
    }
}
