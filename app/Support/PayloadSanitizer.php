<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * PayloadSanitizer — Shared sanitization & validation helpers for CCR worksheet payloads.
 *
 * Extracted from CcrEngineController & CcrSeatController to eliminate duplication (~500 lines shared).
 * Both controllers can now call these methods via dependency injection or direct instantiation.
 */
class PayloadSanitizer
{
    // =====================================================================
    // CONSTANTS (shared between Engine and Seat)
    // =====================================================================
    public const MAX_WORKSHEET_PAYLOAD_BYTES = 24 * 1024 * 1024;
    public const WORKSHEET_MAX_ROWS = 500;
    public const WORKSHEET_MAX_COLS = 10;
    public const WORKSHEET_MAX_STYLE_ENTRIES = self::WORKSHEET_MAX_ROWS * self::WORKSHEET_MAX_COLS;
    public const WORKSHEET_MAX_NOTE_ENTRIES = self::WORKSHEET_MAX_ROWS * self::WORKSHEET_MAX_COLS;
    public const WORKSHEET_NOTE_MAX_CHARS = 1000;
    public const WORKSHEET_TOOL_TEXT_MAX_CHARS = 1000;
    public const WORKSHEET_TEXT_CELL_MAX_CHARS = 300;

    // Seat-specific constants
    public const SEAT_ITEMS_MAX_PAYLOAD_BYTES = 8 * 1024 * 1024;
    public const SEAT_ITEMS_MAX_ROWS = 3000;
    public const SEAT_ITEMS_MAX_PHOTOS_PER_ROW = 10;
    public const SEAT_ITEMS_MAX_CATEGORY_CHARS = 80;
    public const SEAT_ITEMS_MAX_PN_CHARS = 80;
    public const SEAT_ITEMS_MAX_ITEM_CHARS = 255;

    // =====================================================================
    // JSON decode / validate
    // =====================================================================

    /**
     * Decode JSON string or pass-through array.
     */
    public function decodeJsonInput($raw, ?string $field = null): array
    {
        if ($raw === null) return [];
        if (is_array($raw)) return $raw;

        $raw = trim((string) $raw);
        if ($raw === '') return [];

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Check if raw input is invalid JSON.
     */
    public function isInvalidJsonInput($raw): bool
    {
        if (!is_string($raw)) return false;
        $text = trim($raw);
        if ($text === '') return false;

        json_decode($text, true);
        return json_last_error() !== JSON_ERROR_NONE;
    }

    // =====================================================================
    // Payload size guards
    // =====================================================================

    /**
     * Calculate payload byte size.
     */
    public function payloadByteSize($raw): int
    {
        if ($raw === null) return 0;
        if (is_string($raw)) return strlen($raw);
        if (is_array($raw)) {
            $json = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return is_string($json) ? strlen($json) : 0;
        }

        return strlen((string) $raw);
    }

    /**
     * Ensure payload is within size limit, or throw ValidationException.
     */
    public function ensurePayloadWithinLimit($raw, string $field, int $maxBytes = self::MAX_WORKSHEET_PAYLOAD_BYTES): void
    {
        $bytes = $this->payloadByteSize($raw);
        if ($bytes <= $maxBytes) {
            return;
        }

        throw ValidationException::withMessages([
            $field => [
                'Payload worksheet terlalu besar (' . number_format($bytes / 1024 / 1024, 2) . ' MB). Batas saat ini '
                    . number_format($maxBytes / 1024 / 1024, 2)
                    . ' MB per section.',
            ],
        ]);
    }

    /**
     * Ensure seat items payload within its specific limit.
     */
    public function ensureSeatItemsPayloadWithinLimit($raw, string $field = 'seat_items_payload'): void
    {
        $this->ensurePayloadWithinLimit($raw, $field, self::SEAT_ITEMS_MAX_PAYLOAD_BYTES);
    }

    // =====================================================================
    // Cell key parsing
    // =====================================================================

    /**
     * Parse and normalize a worksheet cell key like "3:5" (row:col).
     */
    public function parseWorksheetCellKey($raw): ?string
    {
        if ($raw === null) return null;
        $text = trim((string) $raw);
        if ($text === '') return null;

        if (!preg_match('/^(\d+):(\d+)$/', $text, $m)) {
            return null;
        }

        $row = (int) $m[1];
        $col = (int) $m[2];

        if ($row < 0 || $row >= self::WORKSHEET_MAX_ROWS) return null;
        if ($col < 0 || $col >= self::WORKSHEET_MAX_COLS) return null;

        return $row . ':' . $col;
    }

    // =====================================================================
    // Style entry helpers
    // =====================================================================

    /**
     * Check if a worksheet style entry is empty (has no meaningful formatting).
     */
    public function isWorksheetStyleEntryEmpty(array $entry): bool
    {
        $hasExplicitBool = array_key_exists('bold', $entry)
            || array_key_exists('italic', $entry)
            || array_key_exists('underline', $entry);
        $bold = !empty($entry['bold']);
        $italic = !empty($entry['italic']);
        $underline = !empty($entry['underline']);
        $align = trim((string) ($entry['align'] ?? ''));
        $color = trim((string) ($entry['color'] ?? ''));
        $bg = trim((string) ($entry['bg'] ?? ''));

        return !$hasExplicitBool && !$bold && !$italic && !$underline && $align === '' && $color === '' && $bg === '';
    }

    // =====================================================================
    // Sanitize worksheet sub-payloads
    // =====================================================================

    public function sanitizeWorksheetStyles(array $styles): array
    {
        $out = [];
        foreach ($styles as $rawCell => $entry) {
            if (count($out) >= self::WORKSHEET_MAX_STYLE_ENTRIES) break;

            $cell = $this->parseWorksheetCellKey($rawCell);
            if ($cell === null || !is_array($entry)) continue;

            $next = [];

            foreach (['bold', 'italic', 'underline'] as $flag) {
                if (!array_key_exists($flag, $entry)) continue;
                $val = filter_var($entry[$flag], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($val !== null) {
                    $next[$flag] = $val;
                }
            }

            if (array_key_exists('align', $entry)) {
                $align = strtolower(trim((string) $entry['align']));
                if (in_array($align, ['left', 'center', 'right'], true)) {
                    $next['align'] = $align;
                }
            }

            if (array_key_exists('color', $entry) || array_key_exists('fontColor', $entry)) {
                $rawColor = array_key_exists('color', $entry) ? $entry['color'] : $entry['fontColor'];
                $color = $this->normalizeHexColor($rawColor);
                if ($color !== '') $next['color'] = $color;
            }

            if (array_key_exists('bg', $entry) || array_key_exists('fillColor', $entry)) {
                $rawBg = array_key_exists('bg', $entry) ? $entry['bg'] : $entry['fillColor'];
                $bg = $this->normalizeHexColor($rawBg);
                if ($bg !== '') $next['bg'] = $bg;
            }

            if (!$this->isWorksheetStyleEntryEmpty($next)) {
                $out[$cell] = $next;
            }
        }

        return $out;
    }

    public function sanitizeWorksheetNotes(array $notes): array
    {
        $out = [];
        foreach ($notes as $rawCell => $text) {
            if (count($out) >= self::WORKSHEET_MAX_NOTE_ENTRIES) break;

            $cell = $this->parseWorksheetCellKey($rawCell);
            if ($cell === null) continue;

            $value = trim((string) $text);
            if ($value === '') continue;
            $out[$cell] = $this->limitTextLength($value, self::WORKSHEET_NOTE_MAX_CHARS);
        }

        return $out;
    }

    public function sanitizeWorksheetTools(array $tools): array
    {
        if (empty($tools)) return [];

        $out = [];

        if (array_key_exists('last_action', $tools)) {
            $action = strtolower(trim((string) $tools['last_action']));
            $allowed = [
                'format_toggle', 'align', 'font_color', 'fill_color',
                'clear_format', 'save_note', 'remove_note',
            ];
            if (in_array($action, $allowed, true)) {
                $out['last_action'] = $action;
            }
        }

        if (array_key_exists('last_action_at', $tools)) {
            $at = (int) $tools['last_action_at'];
            if ($at > 0) $out['last_action_at'] = $at;
        }

        if (array_key_exists('last_cell', $tools)) {
            $cell = $this->parseWorksheetCellKey($tools['last_cell']);
            if ($cell !== null) $out['last_cell'] = $cell;
        }

        $range = $tools['selected_range'] ?? $tools['selection'] ?? null;
        $normalizedRange = $this->sanitizeWorksheetRange($range);
        if ($normalizedRange !== null) {
            $out['selected_range'] = $normalizedRange;
        }

        if (array_key_exists('format_prop', $tools)) {
            $prop = strtolower(trim((string) $tools['format_prop']));
            if (in_array($prop, ['bold', 'italic', 'underline'], true)) {
                $out['format_prop'] = $prop;
            }
        }

        if (array_key_exists('format_value', $tools)) {
            $val = filter_var($tools['format_value'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($val !== null) $out['format_value'] = $val;
        }

        if (array_key_exists('align', $tools)) {
            $align = strtolower(trim((string) $tools['align']));
            if (in_array($align, ['left', 'center', 'right'], true)) {
                $out['align'] = $align;
            }
        }

        if (array_key_exists('active', $tools)) {
            $active = trim((string) $tools['active']);
            if ($active !== '') {
                $out['active'] = $this->limitTextLength($active, 64);
            }
        }

        if (array_key_exists('font_color', $tools)) {
            $color = $this->normalizeHexColor($tools['font_color']);
            $out['font_color'] = $color;
        }

        if (array_key_exists('last_color', $tools)) {
            $lastColor = $this->normalizeHexColor($tools['last_color']);
            if ($lastColor !== '') {
                $out['last_color'] = $lastColor;
            }
        }

        if (array_key_exists('fill_color', $tools)) {
            $bg = $this->normalizeHexColor($tools['fill_color']);
            $out['fill_color'] = $bg;
        }

        if (array_key_exists('note_text', $tools)) {
            $note = trim((string) $tools['note_text']);
            $out['note_text'] = $this->limitTextLength($note, self::WORKSHEET_TOOL_TEXT_MAX_CHARS);
        }

        return $out;
    }

    public function sanitizeWorksheetRange($range): ?array
    {
        if (!is_array($range)) return null;

        $r1 = is_numeric($range['r1'] ?? null) ? (int) $range['r1'] : null;
        $r2 = is_numeric($range['r2'] ?? null) ? (int) $range['r2'] : null;
        $c1 = is_numeric($range['c1'] ?? null) ? (int) $range['c1'] : null;
        $c2 = is_numeric($range['c2'] ?? null) ? (int) $range['c2'] : null;

        if ($r1 === null || $r2 === null || $c1 === null || $c2 === null) return null;

        $minR = max(0, min($r1, $r2));
        $maxR = min(self::WORKSHEET_MAX_ROWS - 1, max($r1, $r2));
        $minC = max(0, min($c1, $c2));
        $maxC = min(self::WORKSHEET_MAX_COLS - 1, max($c1, $c2));

        if ($minR > $maxR || $minC > $maxC) return null;
        if ((($maxR - $minR) + 1) * (($maxC - $minC) + 1) > self::WORKSHEET_MAX_STYLE_ENTRIES) {
            return null;
        }

        return ['r1' => $minR, 'r2' => $maxR, 'c1' => $minC, 'c2' => $maxC];
    }

    // =====================================================================
    // Text / color helpers
    // =====================================================================

    public function normalizeHexColor($raw): string
    {
        $text = strtoupper(trim((string) $raw));
        if ($text === '') return '';

        if (preg_match('/^#[0-9A-F]{6}$/', $text)) return $text;
        if (preg_match('/^[0-9A-F]{6}$/', $text)) return '#' . $text;

        return '';
    }

    public function limitTextLength(string $value, int $maxChars): string
    {
        $value = trim($value);
        if ($value === '' || $maxChars <= 0) return '';

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value) > $maxChars) {
                return mb_substr($value, 0, $maxChars);
            }
            return $value;
        }

        if (strlen($value) > $maxChars) {
            return substr($value, 0, $maxChars);
        }

        return $value;
    }

    // =====================================================================
    // Full payload sanitization
    // =====================================================================

    /**
     * Sanitize parts payload: clean rows, meta, styles, notes, tools.
     */
    public function sanitizePartsPayload(array $payload, ?string $templateKey = null, ?string $templateVersion = null, ?array $manifest = null): array
    {
        if (empty($payload)) return [];

        $clean = $payload;

        // meta
        if (!isset($clean['meta']) || !is_array($clean['meta'])) {
            $clean['meta'] = [];
        }

        $meta = $clean['meta'];
        foreach ($meta as $k => $v) {
            if (is_string($v)) $meta[$k] = trim($v);
        }

        // inject template meta
        if ($templateKey) {
            $meta['template_key'] = $templateKey;
            if ($templateVersion !== null) {
                $meta['template_version'] = trim((string) $templateVersion);
            }
            if ($manifest && is_array($manifest)) {
                $meta['template'] = [
                    'key'     => (string) ($manifest['key'] ?? $templateKey),
                    'version' => (string) ($manifest['version'] ?? $templateVersion ?? ''),
                    'name'    => (string) ($manifest['name'] ?? ''),
                ];
            }
        }

        // clean meta money keys
        $metaMoneyKeys = ['footer_total', 'footer_extended'];
        foreach ($metaMoneyKeys as $mk) {
            if (isset($meta[$mk])) {
                $meta[$mk] = preg_replace('/[^\d]/', '', (string) $meta[$mk]);
            }
        }

        // footer modes
        if (isset($meta['footer_total_mode'])) {
            $m = strtolower(trim((string) $meta['footer_total_mode']));
            $meta['footer_total_mode'] = in_array($m, ['auto', 'manual'], true) ? $m : 'auto';
        }
        if (isset($meta['footer_extended_mode'])) {
            $m = strtolower(trim((string) $meta['footer_extended_mode']));
            $meta['footer_extended_mode'] = in_array($m, ['auto', 'manual'], true) ? $m : 'auto';
        }

        if (array_key_exists('rows_count', $meta)) {
            $rowsCount = (int) $meta['rows_count'];
            if ($rowsCount <= 0) $rowsCount = 1;
            $meta['rows_count'] = min(self::WORKSHEET_MAX_ROWS, $rowsCount);
        }

        $clean['meta'] = $meta;

        // rows
        if (!isset($clean['rows']) || !is_array($clean['rows'])) {
            $clean['rows'] = [];
        }

        $moneyKeys = [
            'purchase_price', 'sales_price', 'total', 'extended_price',
            'unit_price', 'amount', 'cost', 'price',
        ];

        $cleanRows = [];
        foreach ($clean['rows'] as $row) {
            if (!is_array($row)) {
                $cleanRows[] = [];
                continue;
            }

            // remove private keys
            foreach (array_keys($row) as $k) {
                if ($k === '_id' || str_starts_with((string) $k, '_')) {
                    unset($row[$k]);
                }
            }

            foreach ($row as $k => $v) {
                if (is_string($v)) $v = trim($v);

                if ($k === 'qty' || $k === 'quantity') {
                    $row[$k] = preg_replace('/[^\d]/', '', (string) $v);
                    continue;
                }

                if (in_array($k, $moneyKeys, true)) {
                    $row[$k] = preg_replace('/[^\d]/', '', (string) $v);
                    continue;
                }

                if (str_contains((string) $k, 'percent') || str_contains((string) $k, 'pct')) {
                    $row[$k] = preg_replace('/[^\d.]/', '', (string) $v);
                    continue;
                }

                if (is_string($v)) {
                    $v = $this->limitTextLength($v, self::WORKSHEET_TEXT_CELL_MAX_CHARS);
                }

                $row[$k] = $v;
            }

            $cleanRows[] = $row;
        }

        $clean['rows'] = $cleanRows;

        // sanitize sub-payloads
        if (array_key_exists('styles', $clean)) {
            $clean['styles'] = $this->sanitizeWorksheetStyles(is_array($clean['styles']) ? $clean['styles'] : []);
        }
        if (array_key_exists('notes', $clean)) {
            $clean['notes'] = $this->sanitizeWorksheetNotes(is_array($clean['notes']) ? $clean['notes'] : []);
        }
        if (array_key_exists('tools', $clean)) {
            $clean['tools'] = $this->sanitizeWorksheetTools(is_array($clean['tools']) ? $clean['tools'] : []);
        }

        // replay styles/notes from tools
        $toolsForReplay = (isset($clean['tools']) && is_array($clean['tools'])) ? $clean['tools'] : [];
        if (!empty($toolsForReplay)) {
            $currentStyles = (isset($clean['styles']) && is_array($clean['styles'])) ? $clean['styles'] : [];
            $replayedStyles = $this->replayStylesFromTools($currentStyles, $toolsForReplay);
            if (is_array($replayedStyles)) {
                $clean['styles'] = $this->sanitizeWorksheetStyles($replayedStyles);
            }

            $currentNotes = (isset($clean['notes']) && is_array($clean['notes'])) ? $clean['notes'] : [];
            $replayedNotes = $this->replayNotesFromTools($currentNotes, $toolsForReplay);
            if (is_array($replayedNotes)) {
                $clean['notes'] = $this->sanitizeWorksheetNotes($replayedNotes);
            }
        }

        // anti overwrite guard
        $hasAnyRows = is_array($clean['rows']) && count($clean['rows']) > 0;
        $hasAnyMeta = is_array($clean['meta']) && count(array_filter($clean['meta'], fn ($v) => $v !== '' && $v !== null && $v !== [])) > 0;
        $hasOther   = count(array_diff(array_keys($clean), ['meta', 'rows', 'styles', 'notes', 'tools', 'ts'])) > 0;

        if (!$hasAnyRows && !$hasAnyMeta && !$hasOther) {
            return [];
        }

        return $clean;
    }

    /**
     * Sanitize detail payload: preserve fields, inject template meta.
     */
    public function sanitizeDetailPayload(array $payload, ?string $templateKey = null, ?string $templateVersion = null, ?array $manifest = null): array
    {
        if (empty($payload)) return [];

        $clean = $payload;

        if (!isset($clean['meta']) || !is_array($clean['meta'])) {
            $clean['meta'] = [];
        }

        $meta = $clean['meta'];
        foreach ($meta as $k => $v) {
            if (is_string($v)) $meta[$k] = trim($v);
        }

        if ($templateKey) {
            $meta['template_key'] = $templateKey;
            if ($templateVersion !== null) {
                $meta['template_version'] = trim((string) $templateVersion);
            }
            if ($manifest && is_array($manifest)) {
                $meta['template'] = [
                    'key'     => (string) ($manifest['key'] ?? $templateKey),
                    'version' => (string) ($manifest['version'] ?? $templateVersion ?? ''),
                    'name'    => (string) ($manifest['name'] ?? ''),
                ];
            }
        }

        $clean['meta'] = $meta;

        return $clean;
    }

    // =====================================================================
    // Style/Note replay from tools
    // =====================================================================

    public function replayStylesFromTools(array $currentStyles, array $incomingTools): ?array
    {
        $action = strtolower(trim((string) ($incomingTools['last_action'] ?? '')));
        if ($action === '') return null;

        $targetKeys = $this->extractWorksheetToolTargetKeys($incomingTools);
        if (empty($targetKeys)) return null;

        $styles = $currentStyles;

        foreach ($targetKeys as $cellKey) {
            if (!isset($styles[$cellKey]) || !is_array($styles[$cellKey])) {
                $styles[$cellKey] = [];
            }

            if ($action === 'format_toggle') {
                $prop = strtolower(trim((string) ($incomingTools['format_prop'] ?? '')));
                if (in_array($prop, ['bold', 'italic', 'underline'], true)) {
                    $newVal = filter_var($incomingTools['format_value'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($newVal !== null) {
                        $styles[$cellKey][$prop] = $newVal;
                    }
                }
            } elseif ($action === 'align') {
                $align = strtolower(trim((string) ($incomingTools['align'] ?? '')));
                if (in_array($align, ['left', 'center', 'right'], true)) {
                    $styles[$cellKey]['align'] = $align;
                }
            } elseif ($action === 'font_color') {
                $color = $this->normalizeHexColor($incomingTools['font_color'] ?? '');
                if ($color !== '') {
                    $styles[$cellKey]['color'] = $color;
                } else {
                    unset($styles[$cellKey]['color']);
                }
            } elseif ($action === 'fill_color') {
                $bg = $this->normalizeHexColor($incomingTools['fill_color'] ?? '');
                if ($bg !== '') {
                    $styles[$cellKey]['bg'] = $bg;
                } else {
                    unset($styles[$cellKey]['bg']);
                }
            } elseif ($action === 'clear_format') {
                $styles[$cellKey] = [];
            }

            if ($this->isWorksheetStyleEntryEmpty($styles[$cellKey])) {
                unset($styles[$cellKey]);
            }
        }

        return $styles;
    }

    public function replayNotesFromTools(array $currentNotes, array $incomingTools): ?array
    {
        $action = strtolower(trim((string) ($incomingTools['last_action'] ?? '')));
        if (!in_array($action, ['save_note', 'remove_note'], true)) return null;

        $targetKeys = $this->extractWorksheetToolTargetKeys($incomingTools);
        if (empty($targetKeys)) return null;

        $notes = $currentNotes;

        if ($action === 'save_note') {
            $noteText = trim((string) ($incomingTools['note_text'] ?? ''));
            if ($noteText === '') return null;
            foreach ($targetKeys as $cellKey) {
                $notes[$cellKey] = $this->limitTextLength($noteText, self::WORKSHEET_NOTE_MAX_CHARS);
            }
        } elseif ($action === 'remove_note') {
            foreach ($targetKeys as $cellKey) {
                unset($notes[$cellKey]);
            }
        }

        return $notes;
    }

    public function extractWorksheetToolTargetKeys(array $incomingTools): array
    {
        $keys = [];

        // single cell
        if (isset($incomingTools['last_cell'])) {
            $cell = $this->parseWorksheetCellKey($incomingTools['last_cell']);
            if ($cell !== null) $keys[] = $cell;
        }

        // range
        $range = $incomingTools['selected_range'] ?? $incomingTools['selection'] ?? null;
        if (is_array($range)) {
            $r1 = is_numeric($range['r1'] ?? null) ? (int) $range['r1'] : null;
            $r2 = is_numeric($range['r2'] ?? null) ? (int) $range['r2'] : null;
            $c1 = is_numeric($range['c1'] ?? null) ? (int) $range['c1'] : null;
            $c2 = is_numeric($range['c2'] ?? null) ? (int) $range['c2'] : null;

            if ($r1 !== null && $r2 !== null && $c1 !== null && $c2 !== null) {
                $minR = max(0, min($r1, $r2));
                $maxR = min(self::WORKSHEET_MAX_ROWS - 1, max($r1, $r2));
                $minC = max(0, min($c1, $c2));
                $maxC = min(self::WORKSHEET_MAX_COLS - 1, max($c1, $c2));

                $cellCount = (($maxR - $minR) + 1) * (($maxC - $minC) + 1);
                if ($cellCount <= self::WORKSHEET_MAX_STYLE_ENTRIES) {
                    for ($r = $minR; $r <= $maxR; $r++) {
                        for ($c = $minC; $c <= $maxC; $c++) {
                            $keys[] = $r . ':' . $c;
                        }
                    }
                }
            }
        }

        return array_unique($keys);
    }

    // =====================================================================
    // Payload completeness checks
    // =====================================================================

    public function isLikelyIncompleteSectionPayload(string $section, array $payload): bool
    {
        return match ($section) {
            'parts' => $this->isLikelyIncompletePartsPayload($payload),
            'detail' => $this->isLikelyIncompleteDetailPayload($payload),
            default => false,
        };
    }

    public function isLikelyIncompletePartsPayload(array $payload): bool
    {
        if (empty($payload)) return true;
        $meta = (isset($payload['meta']) && is_array($payload['meta'])) ? $payload['meta'] : [];
        $rows = (isset($payload['rows']) && is_array($payload['rows'])) ? $payload['rows'] : [];

        $hasAnyMeta = count(array_filter($meta, fn ($v) => $v !== '' && $v !== null && $v !== [])) > 0;
        $hasAnyRows = count($rows) > 0;

        return !$hasAnyMeta && !$hasAnyRows;
    }

    public function isLikelyIncompleteDetailPayload(array $payload): bool
    {
        if (empty($payload)) return true;

        $meta = (isset($payload['meta']) && is_array($payload['meta'])) ? $payload['meta'] : [];
        $totals = (isset($payload['totals']) && is_array($payload['totals'])) ? $payload['totals'] : [];
        $rows = (isset($payload['rows']) && is_array($payload['rows'])) ? $payload['rows'] : [];

        $hasAnyMeta = count(array_filter($meta, fn ($v) => $v !== '' && $v !== null && $v !== [])) > 0;
        $hasAnyTotals = count(array_filter($totals, fn ($v) => $v !== '' && $v !== null && $v !== 0 && $v !== '0')) > 0;
        $hasAnyRows = count($rows) > 0;

        return !$hasAnyMeta && !$hasAnyTotals && !$hasAnyRows;
    }

    // =====================================================================
    // Payload merge (for create flow draft merging)
    // =====================================================================

    public function mergeCreatePartsPayload(array $incoming, array $draft): array
    {
        if (empty($incoming) && empty($draft)) return [];
        if (empty($incoming)) return $draft;
        if (empty($draft)) return $incoming;

        $merged = $incoming;

        // merge meta
        $inMeta = (isset($incoming['meta']) && is_array($incoming['meta'])) ? $incoming['meta'] : [];
        $drMeta = (isset($draft['meta']) && is_array($draft['meta'])) ? $draft['meta'] : [];
        $merged['meta'] = array_merge($drMeta, $inMeta);

        // merge rows: prefer incoming if has meaningful rows
        $inRows = (isset($incoming['rows']) && is_array($incoming['rows'])) ? $incoming['rows'] : [];
        $drRows = (isset($draft['rows']) && is_array($draft['rows'])) ? $draft['rows'] : [];
        $inMeaningful = $this->countMeaningfulPartsRows($inRows);
        $drMeaningful = $this->countMeaningfulPartsRows($drRows);

        if ($inMeaningful >= $drMeaningful) {
            $merged['rows'] = $inRows;
        } else {
            $merged['rows'] = $drRows;
        }

        // merge styles
        $inStyles = (isset($incoming['styles']) && is_array($incoming['styles'])) ? $incoming['styles'] : [];
        $drStyles = (isset($draft['styles']) && is_array($draft['styles'])) ? $draft['styles'] : [];
        if (!empty($drStyles) && empty($inStyles)) {
            $merged['styles'] = $drStyles;
        } elseif (!empty($inStyles)) {
            $merged['styles'] = array_merge($drStyles, $inStyles);
        }

        // merge notes
        $inNotes = (isset($incoming['notes']) && is_array($incoming['notes'])) ? $incoming['notes'] : [];
        $drNotes = (isset($draft['notes']) && is_array($draft['notes'])) ? $draft['notes'] : [];
        if (!empty($drNotes) && empty($inNotes)) {
            $merged['notes'] = $drNotes;
        } elseif (!empty($inNotes)) {
            $merged['notes'] = array_merge($drNotes, $inNotes);
        }

        // ts: pick latest
        $inTs = $incoming['ts'] ?? null;
        $drTs = $draft['ts'] ?? null;
        if ($inTs !== null || $drTs !== null) {
            $merged['ts'] = max((int) ($inTs ?? 0), (int) ($drTs ?? 0));
        }

        return $merged;
    }

    public function mergeCreateDetailPayload(array $incoming, array $draft): array
    {
        if (empty($incoming) && empty($draft)) return [];
        if (empty($incoming)) return $draft;
        if (empty($draft)) return $incoming;

        // Start with draft as base, then overlay incoming
        $merged = array_merge($draft, $incoming);

        $inMeta = (isset($incoming['meta']) && is_array($incoming['meta'])) ? $incoming['meta'] : [];
        $drMeta = (isset($draft['meta']) && is_array($draft['meta'])) ? $draft['meta'] : [];
        $merged['meta'] = array_merge($drMeta, $inMeta);

        $inTotals = (isset($incoming['totals']) && is_array($incoming['totals'])) ? $incoming['totals'] : [];
        $drTotals = (isset($draft['totals']) && is_array($draft['totals'])) ? $draft['totals'] : [];
        $merged['totals'] = array_merge($drTotals, $inTotals);

        $inTs = $incoming['ts'] ?? null;
        $drTs = $draft['ts'] ?? null;
        if ($inTs !== null || $drTs !== null) {
            $merged['ts'] = max((int) ($inTs ?? 0), (int) ($drTs ?? 0));
        }

        return $merged;
    }

    public function countMeaningfulPartsRows(array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $filtered = array_filter($row, fn ($v) => $v !== '' && $v !== null && $v !== 0 && $v !== '0');
            if (!empty($filtered)) $count++;
        }
        return $count;
    }

    // =====================================================================
    // Template & revision helpers
    // =====================================================================

    public function toTemplateVersionInt(?string $versionStr): ?int
    {
        $versionStr = trim((string) $versionStr);
        if ($versionStr === '') return null;

        if (preg_match('/(\d+)/', $versionStr, $m)) {
            return max(1, (int) $m[1]);
        }

        return null;
    }

    public function parsePayloadRevision($raw): ?int
    {
        if ($raw === null) return null;
        $text = trim((string) $raw);
        if ($text === '' || !is_numeric($text)) return null;

        $value = (int) $text;
        return $value >= 0 ? $value : null;
    }

    public function payloadTimestampFromArray(array $payload): ?int
    {
        $candidates = [
            $payload['ts'] ?? null,
            data_get($payload, 'meta.ts'),
            data_get($payload, 'meta.saved_at_ts'),
        ];

        foreach ($candidates as $raw) {
            if ($raw === null) continue;
            $text = trim((string) $raw);
            if ($text === '' || !is_numeric($text)) continue;
            $value = (int) $text;
            if ($value > 0) return $value;
        }

        return null;
    }

    public function readPayloadTemplateKey(array $payload): ?string
    {
        $key = trim((string) data_get($payload, 'meta.template_key', ''));
        return $key !== '' ? $key : null;
    }
}
