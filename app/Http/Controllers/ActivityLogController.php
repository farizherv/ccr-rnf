<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::query()->orderByDesc('created_at');

        // Filter: action
        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        // Filter: user
        if ($userId = $request->input('user_id')) {
            $query->where('user_id', (int) $userId);
        }

        // Filter: date range
        if ($from = $request->input('from')) {
            $query->where('created_at', '>=', $from . ' 00:00:00');
        }
        if ($to = $request->input('to')) {
            $query->where('created_at', '<=', $to . ' 23:59:59');
        }

        // Filter: subject (search component name in meta)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('meta', 'LIKE', '%' . $search . '%')
                  ->orWhere('user_name', 'LIKE', '%' . $search . '%');
            });
        }

        $logs = $query->simplePaginate(25)->withQueryString();

        // Distinct actions for filter dropdown
        $actions = ActivityLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        // Distinct users for filter dropdown
        $users = ActivityLog::query()
            ->select('user_id', 'user_name')
            ->whereNotNull('user_id')
            ->distinct()
            ->orderBy('user_name')
            ->limit(100)
            ->get();

        return view('admin.activity-log', compact('logs', 'actions', 'users'));
    }
}
