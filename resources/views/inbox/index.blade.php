@extends('layout')

@section('content')
@php
    $role = strtolower(trim((string) auth()->user()->role));

    $backUrl = match ($role) {
        'director' => route('director.monitoring'),
        'admin', 'operator' => route('ccr.index'),
        default => route('dashboard'),
    };

    $roleLabel = ($role === 'operator') ? 'PLANNER' : strtoupper($role);
@endphp

<a href="{{ $backUrl }}" class="btn-back">← Kembali</a>

<div class="page-card">
    <div class="head">
        <div>
            <h1 class="title">Inbox</h1>
            <p class="sub">Notifikasi untuk {{ $roleLabel }}</p>
        </div>

        <form method="POST" action="{{ route('inbox.clearAll') }}"
              onsubmit="return confirm('Hapus semua notifikasi?');">
            @csrf
            <button class="btn-danger" type="submit">Clear all</button>
        </form>
    </div>

    <div class="list">
        @forelse($items as $n)
            <div class="item {{ $n->is_read ? 'read' : 'unread' }}">
                <div class="item-left">
                    <div class="item-title">{{ $n->title }}</div>
                    <div class="item-msg">{{ $n->message }}</div>
                    <div class="item-meta">
                        {{ $n->created_at->format('d M Y H:i') }}
                        @if($n->url)
                            • <a class="item-link" href="{{ $n->url }}">Buka</a>
                        @endif
                    </div>
                </div>

                @if(!$n->is_read)
                    <form method="POST" action="{{ route('inbox.read', $n->id) }}">
                        @csrf
                        <button class="btn-ghost" type="submit">Read</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="empty">Belum ada notifikasi.</div>
        @endforelse
    </div>
</div>

<style>
.btn-back{display:inline-block;color:#fff;padding:10px 18px;border-radius:12px;background:#5f656a;font-weight:900;font-size:14px;text-decoration:none;box-shadow:0 8px 18px rgba(0,0,0,.12);margin-bottom:18px}
.page-card{background:#fff;border-radius:18px;padding:22px;box-shadow:0 14px 35px rgba(0,0,0,.08)}
.head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;border-bottom:1px solid #eef2f7;padding-bottom:14px;margin-bottom:14px}
.title{margin:0;font-size:30px;font-weight:1000}
.sub{margin:6px 0 0;color:#6b7280;font-weight:700}
.btn-ghost{border:none;cursor:pointer;padding:10px 14px;border-radius:12px;background:#6b7075;color:#fff;font-weight:1000;box-shadow:0 10px 18px rgba(0,0,0,.12)}
.btn-danger{border:none;cursor:pointer;padding:12px 16px;border-radius:14px;background:#dc3545;color:#fff;font-weight:1000;box-shadow:0 14px 24px rgba(220,53,69,.18)}
.list{display:flex;flex-direction:column;gap:12px}
.item{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;border:1px solid #eef2f7;border-radius:16px;padding:14px;background:#fbfdff}
.item.unread{border-left:6px solid #0D6EFD;background:#f7fbff}
.item.read{opacity:.78}
.item-title{font-weight:1000;font-size:16px}
.item-msg{margin-top:6px;color:#111827;font-weight:700}
.item-meta{margin-top:8px;color:#6b7280;font-weight:700;font-size:13px}
.item-link{color:#0D6EFD;font-weight:900;text-decoration:none}
.empty{padding:16px;color:#6b7280;font-weight:800}
@media (max-width:700px){.head{flex-direction:column}.btn-danger{width:100%}}
</style>
@endsection
