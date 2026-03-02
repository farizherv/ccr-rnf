<?php

namespace App\Http\Controllers;

use App\Models\CcrReport;
use Illuminate\Database\Eloquent\Builder;

class CcrReportController extends Controller
{
    // =====================================================================
    // HALAMAN INDEX — MENU UTAMA
    // =====================================================================
    public function index()
    {
        // Halaman menu tidak butuh data report; hindari query besar yang sia-sia.
        return view('ccr.index');
    }

    // LIST CCR ENGINE UNTUK DI EDIT
    public function editEngineList()
    {
        [$reports, $customers, $statusStats] = $this->buildManageListPayload(
            CcrReport::query()->where('group_folder', 'Engine'),
            includeUnit: false
        );

        return view('ccr.manage-engine', compact('reports', 'customers', 'statusStats'));
    }

    // LIST CCR OPERATOR SEAT UNTUK DI EDIT
    public function editSeatList()
    {
        [$reports, $customers, $statusStats] = $this->buildManageListPayload(
            CcrReport::query()->where('group_folder', 'Operator Seat'),
            includeUnit: true
        );

        return view('ccr.manage-seat', compact('reports', 'customers', 'statusStats'));
    }

    private function buildManageListPayload(Builder $baseQuery, bool $includeUnit = false): array
    {
        $baseQuery = $baseQuery->whereNull('deleted_at');

        $totalReports = (clone $baseQuery)->count();
        $draftReports = (clone $baseQuery)
            ->where(function (Builder $query) {
                $query->whereNull('approval_status')->orWhere('approval_status', 'draft');
            })
            ->count();
        $reviewReports = (clone $baseQuery)
            ->whereIn('approval_status', ['waiting', 'in_review'])
            ->count();
        $approvedReports = (clone $baseQuery)->where('approval_status', 'approved')->count();
        $rejectedReports = (clone $baseQuery)->where('approval_status', 'rejected')->count();

        $statusStats = [
            'total' => $totalReports,
            'draft' => $draftReports,
            'review' => $reviewReports,
            'approved' => $approvedReports,
            'rejected' => $rejectedReports,
        ];

        $customers = (clone $baseQuery)
            ->select('customer')
            ->whereNotNull('customer')
            ->where('customer', '<>', '')
            ->orderBy('customer')
            ->distinct()
            ->limit(300)
            ->pluck('customer')
            ->values();

        $columns = [
            'id',
            'component',
            'customer',
            'make',
            'model',
            'sn',
            'inspection_date',
            'updated_at',
            'created_at',
            'approval_status',
            'director_note',
        ];

        if ($includeUnit) {
            $columns[] = 'unit';
        }

        $reports = (clone $baseQuery)
            ->select($columns)
            ->orderByDesc('created_at')
            ->simplePaginate(50)
            ->withQueryString();

        return [$reports, $customers, $statusStats];
    }
}
