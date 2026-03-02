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

    if (!function_exists('inboxRenderMessage')) {
        function inboxRenderMessage($text) {
            $raw = trim((string) $text);
            if ($raw === '') {
                return '<div class="inbox-msg-note">-</div>';
            }

            if (preg_match('/^\s*(Approved|Rejected)\s+oleh\s+(.+?)\s*(?:\.?\s*Catatan:\s*(.*))?\s*$/iu', $raw, $m)) {
                $status = ucfirst(strtolower((string) ($m[1] ?? '')));
                $actor  = trim((string) ($m[2] ?? ''));

                $pillClass = strtolower($status) === 'approved'
                    ? 'notif-pill notif-pill-approved'
                    : 'notif-pill notif-pill-rejected';

                $row = '<div class="inbox-msg-row">';
                $row .= '<span class="' . $pillClass . '">' . e($status) . '</span>';
                $row .= '<span class="inbox-msg-actor">By ' . e($actor) . '</span>';
                $row .= '</div>';
                return $row;
            }

            $sanitized = trim((string) (preg_replace('/\.?\s*Catatan:\s*.*$/iu', '', $raw) ?? $raw));

            if (preg_match('/^\s*(?:di)?submit(?:ted)?\s+(?:by|oleh)\s+(.+?)\.?\s*$/iu', $sanitized, $submit)) {
                $actor = trim((string) ($submit[1] ?? ''));
                return '<div class="inbox-msg-row"><span class="inbox-msg-actor">Submitted by ' . e($actor) . '</span></div>';
            }

            $sanitized = preg_replace('/^\s*disubmit\s+oleh\s+/iu', 'Submitted by ', $sanitized) ?? $sanitized;
            $sanitized = preg_replace('/^\s*submit(?:ted)?\s+oleh\s+/iu', 'Submitted by ', $sanitized) ?? $sanitized;
            $sanitized = preg_replace('/^\s*disubmit\s+by\s+/iu', 'Submitted by ', $sanitized) ?? $sanitized;
            $sanitized = preg_replace('/^\s*submit(?:ted)?\s+by\s+/iu', 'Submitted by ', $sanitized) ?? $sanitized;
            $sanitized = preg_replace('/\boleh\b/iu', 'By', $sanitized, 1) ?? $sanitized;
            return '<div class="inbox-msg-note">' . inboxStatusPill($sanitized) . '</div>';
        }
    }
@endphp

<a href="{{ $backUrl }}" class="inbox-back-btn">← Kembali</a>

<div class="inbox-page-card">
    <div class="inbox-head">
        <div>
            <h1 class="inbox-title">Inbox</h1>
            <p class="inbox-sub">Notifikasi untuk {{ $roleLabel }}</p>
        </div>

        <form method="POST" action="{{ route('inbox.clearAll') }}"
              onsubmit="return confirm('Hapus semua notifikasi?');">
            @csrf
            <button class="inbox-btn-clearall" type="submit">Clear all</button>
        </form>
    </div>

    <div class="inbox-list">
        @forelse($items as $n)
            <article class="inbox-item {{ $n->is_read ? 'read' : 'unread' }}">
                <div class="inbox-item-main">
                    <div class="inbox-item-title">{!! inboxStatusPill($n->title) !!}</div>

                    {{-- ✅ Approved/Rejected jadi pill seperti contoh --}}
                    <div class="inbox-item-msg">{!! inboxRenderMessage($n->message) !!}</div>

                </div>
                <div class="inbox-item-side">
                    <span class="inbox-item-time">{{ $n->created_at->format('d M Y H:i') }}</span>
                    @if($n->url)
                        {{-- ✅ klik Open => otomatis mark read via JS (tanpa klik tombol Read) --}}
                        <a class="inbox-item-link inbox-open" data-id="{{ $n->id }}" href="{{ $n->url }}">Open</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="inbox-empty">Belum ada notifikasi.</div>
        @endforelse
    </div>
</div>

<style>
/* ================= BACK BUTTON ================= */
.inbox-back-btn{
    display:inline-block;
    color:#fff;
    padding:10px 18px;
    border-radius:12px;
    background:#5f656a;
    font-weight:900;
    font-size:14px;
    text-decoration:none;
    box-shadow:0 8px 18px rgba(0,0,0,.12);
    margin-bottom:18px;
    transition:.18s;
}
.inbox-back-btn:hover{
    background:#111827;
    transform:translateY(-1px);
}

/* ================= PAGE CARD ================= */
.inbox-page-card{
    background:#fff;
    border:1px solid #dbe5f3;
    border-radius:18px;
    padding:22px;
    box-shadow:0 14px 35px rgba(15,23,42,.06);
    max-width:1040px;
    margin:0 auto;
}

.inbox-head{
    display:flex;
    justify-content:space-between;
    gap:12px;
    align-items:flex-start;
    border-bottom:1px solid #eef2f7;
    padding-bottom:14px;
    margin-bottom:14px
}
.inbox-title{margin:0;font-size:30px;font-weight:1000}
.inbox-sub{margin:6px 0 0;color:#6b7280;font-weight:700}

/* buttons */
.inbox-btn-clearall{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border:1px solid #dbe5f3;
    cursor:pointer;
    padding:12px 18px;
    border-radius:999px;
    background:#f8fbff;
    color:#0f172a;
    font-weight:1000;
    font-size:14px;
    transition:.18s;
}
.inbox-btn-clearall:hover{
    background:rgba(220,53,69,.10);
    border-color:rgba(220,53,69,.25);
    color:#dc3545;
    transform:translateY(-1px);
}

/* ================= LIST ================= */
.inbox-list{display:flex;flex-direction:column;gap:12px}

/* card item */
.inbox-item{
    position:relative;
    display:grid;
    grid-template-columns:minmax(0,1fr) auto;
    gap:14px;
    align-items:center;
    border:1px solid #dbe5f3;
    border-radius:18px;
    padding:16px 18px 16px 44px; /* space buat dot */
    background:#ffffff;
    box-shadow: 0 8px 18px rgba(15,23,42,0.05);
    transition: .15s ease;
}
.inbox-item:hover{
    transform: translateY(-1px);
    box-shadow: 0 12px 26px rgba(15,23,42,0.08);
}

/* unread dot (ganti border kiri biru) */
.inbox-item::before{
    content:"";
    width:12px;height:12px;border-radius:999px;
    background:#cbd5e1;
    position:absolute;
    left:16px;
    top:22px;
}
.inbox-item.unread{ background:#f7fbff; border-color:#e6f0ff; }
.inbox-item.unread::before{ background:#2563eb; }
.inbox-item.read{ opacity:.92; }

/* left content */
.inbox-item-main{ min-width:0; }
.inbox-item-title{
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
.inbox-item-msg{
    margin-top:8px;
    min-width:0;
}
.inbox-msg-row{
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:8px;
}
.inbox-msg-actor,
.inbox-msg-label{
    font-weight:700;
    font-size:13px;
    letter-spacing:0;
    color:#6b7280;
    line-height:1.25;
}
.inbox-msg-note{
    margin-top:5px;
    color:#111827;
    font-weight:800;
    font-size:15px;
    line-height:1.3;
    overflow-wrap:anywhere;
    word-break:break-word;
    display:-webkit-box;
    -webkit-line-clamp:2;
    -webkit-box-orient:vertical;
    overflow:hidden;
}

/* meta row */
.inbox-item-side{
    min-width:170px;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:flex-end;
    gap:8px;
}
.inbox-item-time{
    display:block;
    color:#6b7280;
    font-weight:700;
    font-size:12px;
    text-align:right;
}
.inbox-item-link{
    color:#0D6EFD;
    font-weight:900;
    text-decoration:none;
    padding:3px 8px;
    border-radius:10px;
}
.inbox-item-link:hover{ background:#eef6ff; }

/* empty */
.inbox-empty{padding:16px;color:#6b7280;font-weight:800}

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
    .inbox-title{ font-size:26px; }
    .inbox-item{ padding:14px 14px 14px 42px; }
    .inbox-item-title{ font-size:17px; }
    .inbox-item-msg{ font-size:14px; }
}

@media (max-width: 700px){
    .inbox-head{flex-direction:column}
    .inbox-btn-clearall{width:100%}

    .inbox-item{
        grid-template-columns:1fr;
        gap:10px;
        padding:14px 14px 14px 42px;
    }
    .inbox-item-side{
        min-width:0;
        align-items:flex-start;
        gap:6px;
    }
    .inbox-item-time{ text-align:left; }
}
</style>

<script>
/**
 * ✅ Klik "Open" => otomatis tandai Read (tanpa klik tombol Read)
 * - Tidak mengganggu navigasi (pakai keepalive)
 * - UI langsung berubah jadi "read" biar terasa cepat
 */
document.addEventListener('click', function(e){
    const a = e.target.closest('a.inbox-open[data-id]');
    if(!a) return;

    const id = Number(a.getAttribute('data-id') || 0);
    if(!id) return;
    window.__inboxReadPending = window.__inboxReadPending || new Set();
    if (window.__inboxReadPending.has(id)) return;
    window.__inboxReadPending.add(id);

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // UI cepat: ubah card jadi read + ganti aksi jadi badge Read
    const item = a.closest('.inbox-item');
    if(item){
        item.classList.remove('unread');
        item.classList.add('read');
    }

    // kirim request tanpa nunggu (tetap pindah halaman)
    fetch(`/inbox/${id}/read-json`, {
        method:'POST',
        headers:{
            'X-CSRF-TOKEN': token,
            'X-Requested-With':'XMLHttpRequest'
        },
        keepalive:true
    }).catch(()=>{}).finally(() => {
        window.__inboxReadPending.delete(id);
    });
});
</script>
@endsection
