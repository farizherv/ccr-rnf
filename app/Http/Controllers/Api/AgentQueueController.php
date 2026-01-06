<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CcrAgentJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentQueueController extends Controller
{
    public function ping()
    {
        return response()->json(['ok' => true, 'message' => 'agent api alive']);
    }

    public function pending(Request $request)
    {
        $worker = $request->header('X-CCR-AGENT-WORKER', 'mac-agent');

        $job = DB::transaction(function () use ($worker) {
            // ambil 1 pending yang belum terkunci
            $job = CcrAgentJob::where('status', 'pending')
                ->whereNull('locked_at')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->first();

            if (!$job) return null;

            $job->status = 'processing';
            $job->locked_at = now();
            $job->locked_by = $worker;
            $job->attempts = $job->attempts + 1;
            $job->save();

            return $job;
        });

        if (!$job) {
            return response()->json(['ok' => true, 'job' => null]);
        }

        return response()->json([
            'ok' => true,
            'job' => [
                'id' => $job->id,
                'group' => $job->group,
                'component' => $job->component,
                'inspection_date' => optional($job->inspection_date)->format('Y-m-d'),
                'payload' => $job->payload,
            ]
        ]);
    }

    public function done(Request $request, $id)
    {
        $job = CcrAgentJob::findOrFail($id);

        $job->status = 'done';
        $job->result = $request->input('result', []);
        $job->last_error = null;
        $job->save();

        return response()->json(['ok' => true]);
    }

    public function failed(Request $request, $id)
    {
        $job = CcrAgentJob::findOrFail($id);

        $job->status = 'failed';
        $job->last_error = $request->input('error', 'unknown error');
        $job->save();

        return response()->json(['ok' => true]);
    }
}
