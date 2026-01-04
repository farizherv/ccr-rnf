{{-- resources/views/inbox/index.blade.php --}}
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

    /**
     * ✅ Convert kata "Approved"/"Rejected" di text -> pill berwarna
     * Aman: text tetap di-escape dulu, baru kita sisipkan span.
     */
    if (!function_exists('inboxStatusPill')) {
        function inboxStatusPill($text) {
            $safe = e((string) $text);

            $safe = preg_replace(
                '/\bApproved\b/i',
                '<span class="notif-pill notif-pill-approved">Approved</span>',
                $safe
            );

            $safe = preg_replace(
                '/\bRejected\b/i',
                '<span class="notif-pill notif-pill-rejected">Rejected</span>',
                $safe
            );

            return $safe;
        }
    }
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
                    <div class="item-title">{!! inboxStatusPill($n->title) !!}</div>

                    {{-- ✅ Approved/Rejected jadi pill seperti contoh --}}
                    <div class="item-msg">{!! inboxStatusPill($n->message) !!}</div>

                    <div class="item-meta">
                        {{ $n->created_at->format('d M Y H:i') }}
                        @if($n->url)
                            {{-- ✅ klik Buka => otomatis mark read via JS (tanpa klik tombol Read) --}}
                            <a class="item-link inbox-open" data-id="{{ $n->id }}" href="{{ $n->url }}">Buka</a>
                        @endif
                    </div>
                </div>

                @if(!$n->is_read)
                    <form method="POST" action="{{ route('inbox.read', $n->id) }}" class="read-form">
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
/* ================= BACK BUTTON ================= */
.btn-back{
    display:inline-block;
    color:#fff;
    padding:10px 18px;
    border-radius:12px;
    background:#5f656a;
    font-weight:900;
    font-size:14px;
    text-decoration:none;
    box-shadow:0 8px 18px rgba(0,0,0,.12);
    margin-bottom:18px
}

/* ================= PAGE CARD ================= */
.page-card{
    background:#fff;
    border-radius:18px;
    padding:22px;
    box-shadow:0 14px 35px rgba(0,0,0,.08)
}

.head{
    display:flex;
    justify-content:space-between;
    gap:12px;
    align-items:flex-start;
    border-bottom:1px solid #eef2f7;
    padding-bottom:14px;
    margin-bottom:14px
}
.title{margin:0;font-size:30px;font-weight:1000}
.sub{margin:6px 0 0;color:#6b7280;font-weight:700}

/* buttons */
.btn-danger{
    border:none;
    cursor:pointer;
    padding:12px 16px;
    border-radius:14px;
    background:#dc3545;
    color:#fff;
    font-weight:1000;
    box-shadow:0 14px 24px rgba(220,53,69,.18)
}
.btn-danger:hover{ filter:brightness(.96); transform: translateY(-1px); }

.btn-ghost{
    border:none;
    cursor:pointer;
    padding:10px 14px;
    border-radius:14px;
    background:#111827;
    color:#fff;
    font-weight:1000;
    box-shadow:0 10px 22px rgba(17,24,39,.18);
    min-width:86px;
    height:50px;
}
.btn-ghost:hover{ filter:brightness(.96); transform: translateY(-1px); }

/* ================= LIST ================= */
.list{display:flex;flex-direction:column;gap:12px}

/* card item */
.item{
    position:relative;
    display:flex;
    justify-content:space-between;
    gap:14px;
    align-items:flex-start;
    border:1px solid #eef2f7;
    border-radius:18px;
    padding:16px 16px 16px 44px; /* space buat dot */
    background:#ffffff;
    box-shadow: 0 6px 16px rgba(0,0,0,0.04);
    transition: .15s ease;
}
.item:hover{ transform: translateY(-1px); box-shadow: 0 10px 22px rgba(0,0,0,0.06); }

/* unread dot (ganti border kiri biru) */
.item::before{
    content:"";
    width:12px;height:12px;border-radius:999px;
    background:#cbd5e1;
    position:absolute;
    left:16px;
    top:22px;
}
.item.unread{ background:#f7fbff; border-color:#e6f0ff; }
.item.unread::before{ background:#2563eb; }
.item.read{ opacity:.78; }

/* left content */
.item-left{ min-width:0; flex:1; }
.item-title{
    font-weight:1000;
    font-size:18px;
    line-height:1.15;
    color:#0f172a;

    /* clamp 1 baris */
    display:-webkit-box;
    -webkit-line-clamp:1;
    -webkit-box-orient:vertical;
    overflow:hidden;
}
.item-msg{
    margin-top:8px;
    color:#111827;
    font-weight:800;
    font-size:15px;
    line-height:1.25;

    /* clamp 1 baris */
    display:-webkit-box;
    -webkit-line-clamp:1;
    -webkit-box-orient:vertical;
    overflow:hidden;
}

/* meta row */
.item-meta{
    margin-top:10px;
    color:#6b7280;
    font-weight:800;
    font-size:13px;
    display:flex;
    gap:12px;
    align-items:center;
    flex-wrap:wrap;
}
.item-link{
    color:#0D6EFD;
    font-weight:1000;
    text-decoration:none;
    padding:4px 8px;
    border-radius:10px;
}
.item-link:hover{ background:#eef6ff; }

/* empty */
.empty{padding:16px;color:#6b7280;font-weight:800}

/* ===================== STATUS PILL ===================== */
.notif-pill{
    display:inline-flex;
    align-items:center;
    padding:6px 14px;
    border-radius:999px;
    font-weight:900;
    font-size:14px;
    line-height:1;
    border:2px solid transparent;
    background:#fff;
    white-space:nowrap;
    margin-right:8px;
}
.notif-pill-approved{
    color:#22c55e;
    background: rgba(34,197,94,.10);
    border-color: rgba(34,197,94,.28);
}
.notif-pill-rejected{
    color:#ef4444;
    background: rgba(239,68,68,.10);
    border-color: rgba(239,68,68,.28);
}

/* ================= RESPONSIVE ================= */
@media (max-width: 820px){
    .title{ font-size:26px; }
    .item{ padding:14px 14px 14px 42px; }
    .item-title{ font-size:17px; }
    .item-msg{ font-size:14px; }
}

@media (max-width: 700px){
    .head{flex-direction:column}
    .btn-danger{width:100%}

    /* item jadi kolom, tombol Read turun ke bawah biar gak mepet */
    .item{ flex-direction:column; }
    .btn-ghost{ width:100%; min-width:0; height:46px; }
}
</style>

<script>
/**
 * ✅ Klik "Buka" => otomatis tandai Read (tanpa klik tombol Read)
 * - Tidak mengganggu navigasi (pakai keepalive)
 * - UI langsung berubah jadi "read" biar terasa cepat
 */
document.addEventListener('click', function(e){
    const a = e.target.closest('a.inbox-open[data-id]');
    if(!a) return;

    const id = Number(a.getAttribute('data-id') || 0);
    if(!id) return;

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // UI cepat: ubah card jadi read + hilangkan tombol Read jika ada
    const item = a.closest('.item');
    if(item){
        item.classList.remove('unread');
        item.classList.add('read');

        const form = item.querySelector('form.read-form');
        if(form) form.remove();
    }

    // kirim request tanpa nunggu (tetap pindah halaman)
    fetch(`/inbox/${id}/read-json`, {
        method:'POST',
        headers:{
            'X-CSRF-TOKEN': token,
            'X-Requested-With':'XMLHttpRequest'
        },
        keepalive:true
    }).catch(()=>{});
});
</script>
@endsection
