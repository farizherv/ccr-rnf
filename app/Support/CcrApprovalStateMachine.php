<?php

namespace App\Support;

use App\Models\CcrApprovalAction;
use App\Models\CcrReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CcrApprovalStateMachine
{
    private const STATUS_WAITING = 'waiting';
    private const STATUS_IN_REVIEW = 'in_review';
    private const STATUS_APPROVED = 'approved';
    private const STATUS_REJECTED = 'rejected';

    public function resolveIdempotencyKey(?string $raw): string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return (string) Str::uuid();
        }

        $value = preg_replace('/[^A-Za-z0-9:_\-]/', '', $value) ?: '';
        if ($value === '') {
            return (string) Str::uuid();
        }

        if (strlen($value) > 120) {
            $value = substr($value, 0, 120);
        }

        return $value;
    }

    /**
     * @return array{report:CcrReport,updated:bool,idempotent:bool,invalid_transition:bool}
     */
    public function decide(
        int $reportId,
        string $action,
        int $actorId,
        ?string $note,
        ?string $idempotencyKey = null,
    ): array {
        $action = strtolower(trim($action)) === 'reject' ? 'reject' : 'approve';
        $toStatus = $action === 'approve' ? self::STATUS_APPROVED : self::STATUS_REJECTED;
        $noteText = trim((string) $note);
        $noteHash = $noteText !== '' ? hash('sha256', $noteText) : null;
        $idempotencyKey = $this->resolveIdempotencyKey($idempotencyKey);

        return DB::transaction(function () use (
            $reportId,
            $action,
            $toStatus,
            $actorId,
            $noteText,
            $noteHash,
            $idempotencyKey
        ) {
            $report = CcrReport::query()->lockForUpdate()->findOrFail($reportId);

            $existing = CcrApprovalAction::query()
                ->where('ccr_report_id', $report->id)
                ->where('action', $action)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return [
                    'report' => $report,
                    'updated' => false,
                    'idempotent' => true,
                    'invalid_transition' => false,
                ];
            }

            $fromStatus = strtolower(trim((string) ($report->approval_status ?: 'draft')));

            if ($fromStatus === self::STATUS_WAITING) {
                // enforce state machine step: waiting -> in_review -> approved/rejected
                $report->approval_status = self::STATUS_IN_REVIEW;
                $report->save();
                $fromStatus = self::STATUS_IN_REVIEW;
            }

            if ($fromStatus !== self::STATUS_IN_REVIEW) {
                CcrApprovalAction::query()->create([
                    'ccr_report_id' => $report->id,
                    'actor_id' => $actorId,
                    'action' => $action,
                    'idempotency_key' => $idempotencyKey,
                    'from_status' => $fromStatus,
                    'to_status' => (string) ($report->approval_status ?? ''),
                    'was_applied' => false,
                    'note_hash' => $noteHash,
                ]);

                return [
                    'report' => $report,
                    'updated' => false,
                    'idempotent' => false,
                    'invalid_transition' => true,
                ];
            }

            $report->approval_status = $toStatus;
            $report->reviewed_by = $actorId;
            $report->reviewed_at = now();

            if ($action === 'approve') {
                $report->director_note = $noteText !== '' ? $noteText : null;
                $report->review_note = $noteText !== '' ? $noteText : null;
            } else {
                $report->director_note = $noteText;
                $report->review_note = $noteText;
            }

            $report->save();

            CcrApprovalAction::query()->create([
                'ccr_report_id' => $report->id,
                'actor_id' => $actorId,
                'action' => $action,
                'idempotency_key' => $idempotencyKey,
                'from_status' => self::STATUS_IN_REVIEW,
                'to_status' => $toStatus,
                'was_applied' => true,
                'note_hash' => $noteHash,
            ]);

            return [
                'report' => $report,
                'updated' => true,
                'idempotent' => false,
                'invalid_transition' => false,
            ];
        });
    }
}
