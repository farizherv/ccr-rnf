<?php

namespace App\Http\Controllers;

use App\Models\CcrReport;
use App\Models\User;
use App\Notifications\CcrReviewedNotification;
use Illuminate\Http\Request;

class CcrApprovalController extends Controller
{
    public function approve(Request $request, string $type, int $id)
    {
        $report = CcrReport::findOrFail($id);

        $report->approval_status = 'approved';
        $report->reviewed_by = auth()->id();
        $report->reviewed_at = now();
        $report->review_note = $request->input('note'); // optional
        $report->save();

        // notify balik ke pembuat/submittter
        if ($report->submitted_by) {
            $maker = User::find($report->submitted_by);
            if ($maker) {
                $maker->notify(new CcrReviewedNotification(
                    reportId: $report->id,
                    type: $type,
                    status: 'approved',
                    byUsername: auth()->user()->username
                ));
            }
        }

        return back()->with('success', 'CCR berhasil di-approve.');
    }

    public function reject(Request $request, string $type, int $id)
    {
        $report = CcrReport::findOrFail($id);

        $report->approval_status = 'rejected';
        $report->reviewed_by = auth()->id();
        $report->reviewed_at = now();
        $report->review_note = $request->input('note'); // boleh wajibin
        $report->save();

        if ($report->submitted_by) {
            $maker = User::find($report->submitted_by);
            if ($maker) {
                $maker->notify(new CcrReviewedNotification(
                    reportId: $report->id,
                    type: $type,
                    status: 'rejected',
                    byUsername: auth()->user()->username
                ));
            }
        }

        return back()->with('success', 'CCR berhasil di-reject.');
    }
}
