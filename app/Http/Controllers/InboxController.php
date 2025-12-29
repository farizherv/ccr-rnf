<?php

namespace App\Http\Controllers;

use App\Support\Inbox;

class InboxController extends Controller
{
    public function index()
    {
        $items = Inbox::list(auth()->user(), 80);
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
    \App\Support\Inbox::clearAll(auth()->user());
    return back()->with('success', 'Notifikasi berhasil dikosongkan.');
    }

    public function panel()
    {
    $user = auth()->user();

        $items = \App\Models\InboxMessage::query()
            ->where('to_user_id', $user->id)
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id','title','message','url','is_read','created_at']);

        $unread = \App\Support\Inbox::unreadCount($user);

        return response()->json([
            'unread' => $unread,
            'items'  => $items->map(function ($n) {
                return [
                    'id' => $n->id,
                    'title' => $n->title,
                    'message' => $n->message,
                    'url' => $n->url,
                    'is_read' => (bool) $n->is_read,
                    'created_at' => $n->created_at->format('d M Y H:i'),
                ];
            }),
        ]);
    }

    public function readJson($id)
    {
        \App\Support\Inbox::markRead((int) $id, auth()->user());
        return response()->json(['ok' => true]);
    }

}
