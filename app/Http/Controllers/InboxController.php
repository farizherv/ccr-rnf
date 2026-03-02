<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Support\Inbox;
use App\Models\InboxMessage;
use Illuminate\Http\JsonResponse;

class InboxController extends Controller
{
    public function index()
    {
        $items  = Inbox::list(auth()->user(), 80);
        $unread = Inbox::unreadCount(auth()->user());

        return view('inbox.index', compact('items', 'unread'));
    }

    public function read($id)
    {
        Inbox::markRead((int) $id, auth()->user());
        return back();
    }

    public function readAll()
    {
        Inbox::markAllRead(auth()->user());
        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function clearAll()
    {
        Inbox::clearAll(auth()->user());
        return back()->with('success', 'Notifikasi berhasil dikosongkan.');
    }

    /**
     * Dropdown panel (AJAX).
     * WAJIB balikin JSON yang punya: unread + html (render blade).
     */
    public function panel(Request $request)
    {
        $user = auth()->user();

        $items = InboxMessage::query()
            ->where('to_user_id', $user->id)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id','title','message','url','is_read','created_at']);

        $unread = Inbox::unreadCount($user);

        // ✅ ini kuncinya biar dropdown kepake
        $html = view('inbox.panel', compact('items'))->render();

        return response()->json([
            'unread' => $unread,
            'html'   => $html,

            // optional: biar masih kompatibel kalau suatu saat mau render via JS
            'items'  => $items->map(function ($n) {
                return [
                    'id'         => $n->id,
                    'title'      => $n->title,
                    'message'    => $n->message,
                    'url'        => $n->url,
                    'is_read'    => (bool) $n->is_read,
                    'created_at' => optional($n->created_at)->format('d M Y H:i'),
                ];
            })->values(),
        ]);
    }

    public function readJson($id)
    {
        Inbox::markRead((int) $id, auth()->user());
        return response()->json(['ok' => true]);
    }

    public function clearRead()
    {
        $userId = auth()->id();

        InboxMessage::where('to_user_id', $userId)
            ->where('is_read', 1)
            ->delete();

        return back()->with('success', 'Notifikasi yang sudah dibaca berhasil dihapus.');
    }

    public function testSelf(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $title = trim((string) $request->input('title', 'CCR Test Notification'));
        $message = trim((string) $request->input('message', 'Test notification dari sistem.'));
        $url = trim((string) $request->input('url', '/inbox'));

        Inbox::toUser((int) $user->id, [
            'from_user_id' => (int) $user->id,
            'type' => 'ccr_submitted',
            'title' => $title !== '' ? $title : 'CCR Test Notification',
            'message' => $message !== '' ? $message : 'Test notification dari sistem.',
            'url' => str_starts_with($url, '/') ? $url : '/inbox',
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Test notification queued',
        ]);
    }

}
