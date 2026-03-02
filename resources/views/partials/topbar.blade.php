{{-- resources/views/partials/topbar.blade.php --}}
@php
    use App\Support\Inbox;

    $unread = 0;
    if (auth()->check()) {
        try { $unread = Inbox::unreadCount(auth()->user()); }
        catch (\Throwable $e) { $unread = 0; }
    }

    $hasInboxRoute      = \Illuminate\Support\Facades\Route::has('inbox.index');
    $hasInboxPanelRoute = \Illuminate\Support\Facades\Route::has('inbox.panel');
@endphp

<div class="top-actions" x-data="{ open:false }">

    {{-- INBOX DROPDOWN (AJAX PANEL) --}}
    @if($hasInboxPanelRoute)
        <div class="notif-wrap" id="notifWrapTop" data-panel-url="{{ route('inbox.panel') }}">
            <button type="button" class="icon-btn" id="notifBtnTop" title="Inbox" aria-label="Inbox">
                🔔
                <span class="badge" id="notifBadgeTop" style="{{ $unread > 0 ? '' : 'display:none;' }}">{{ $unread }}</span>
            </button>

            <div class="notif-panel" id="notifPanelTop" style="display:none;">
                <div class="notif-head">
                    <div class="notif-title">Notifications</div>
                    <form method="POST" action="{{ route('inbox.readAll') }}" style="margin:0;">
                        @csrf
                        <button class="notif-markall" type="submit">Mark all as read</button>
                    </form>
                </div>

                <div class="notif-body" id="notifBodyTop">
                    <div class="notif-empty">Loading…</div>
                </div>

                <div class="notif-foot">
                    <a class="notif-viewall" href="{{ route('inbox.index') }}">View all</a>
                </div>
            </div>
        </div>
    @elseif($hasInboxRoute)
        {{-- fallback: kalau panel route belum ada --}}
        <a href="{{ route('inbox.index') }}" class="icon-btn" title="Inbox" aria-label="Inbox">
            🔔
            @if($unread > 0)
                <span class="badge">{{ $unread }}</span>
            @endif
        </a>
    @endif


    {{-- Settings dropdown --}}
    <button type="button" class="icon-btn" @click="open = !open" title="Settings" aria-label="Settings">
        ⚙️
    </button>

    <div class="drop" x-show="open" @click.outside="open=false" x-cloak>
        <div class="drop-head">
            <div class="name">{{ auth()->user()->name ?? '-' }}</div>
            <div class="role">{{ strtoupper((auth()->user()->role ?? '')) }}</div>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="drop-item" type="submit">LOG OUT</button>
        </form>
    </div>
</div>

<style>
.top-actions{display:flex;align-items:center;gap:10px;position:relative}
.icon-btn{position:relative;display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:12px;background:#f3f4f6;border:1px solid #e5e7eb;text-decoration:none;font-size:18px;cursor:pointer}
.badge{position:absolute;top:-6px;right:-6px;min-width:20px;height:20px;padding:0 6px;border-radius:999px;background:#E40505;color:#fff;font-weight:1000;font-size:12px;display:flex;align-items:center;justify-content:center}
.drop{position:absolute;right:0;top:52px;width:210px;background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 18px 40px rgba(0,0,0,.14);overflow:hidden;z-index:9999}
.drop-head{padding:12px 14px;border-bottom:1px solid #eef2f7}
.drop-head .name{font-weight:1000}
.drop-head .role{font-weight:900;color:#6b7280;font-size:12px;margin-top:2px}
.drop-item{
    width:100%;
    text-align:center;
    border:none;
    background:#0b1733;
    color:#fff;
    padding:12px 14px;
    font-weight:1000;
    cursor:pointer;
    transition:background .18s ease,color .18s ease;
}
.drop-item:hover{background:#E40505;color:#fff}
[x-cloak]{display:none!important}

/* ===================== NOTIF PANEL ===================== */
.notif-wrap{position:relative}
.notif-panel{
    position:absolute;
    right:0;
    top:52px;
    width:520px;
    max-width:calc(100vw - 24px);
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:18px;
    box-shadow:0 18px 50px rgba(0,0,0,.18);
    overflow:hidden;
    z-index:9999;
}
.notif-head{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid #eef2f7;background:#fbfdff}
.notif-title{font-weight:1000;font-size:22px}
.notif-markall{border:none;background:transparent;color:#2563eb;font-weight:900;cursor:pointer;font-size:18px}
.notif-body{max-height:420px;overflow:auto}
.notif-foot{padding:12px 16px;border-top:1px solid #eef2f7;background:#fff}
.notif-viewall{font-weight:1000;font-size:22px;text-decoration:none;color:#111827}
.notif-empty{padding:18px 16px;color:#6b7280;font-weight:900}

/* item layout mirip screenshot */
.notif-item{display:flex;justify-content:space-between;gap:14px;padding:14px 14px;border-bottom:1px solid #eef2f7}
.notif-left{display:flex;gap:12px;min-width:0}
.notif-dot{width:14px;height:14px;border-radius:999px;margin-top:6px;background:#cbd5e1;flex:0 0 auto}
.notif-dot.unread{background:#2563eb}
.notif-content{min-width:0}
.notif-title-text{font-weight:1000;font-size:20px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:360px}
.notif-msg{margin-top:4px;font-weight:800;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:420px}
.notif-meta{margin-top:6px;font-weight:800;color:#6b7280;display:flex;gap:12px;align-items:center}
.notif-open{font-weight:900;color:#2563eb;text-decoration:none}
.notif-right{flex:0 0 auto}
.notif-read-btn,.notif-read-label{display:inline-flex;align-items:center;justify-content:center;width:86px;height:58px;border-radius:18px;background:#6b7075;color:#fff;font-weight:1000;border:none;cursor:pointer}
.notif-read-label{cursor:default;opacity:.95}

/* ===================== STATUS PILL ===================== */
#notifPanelTop .notif-pill,
#notifBodyTop  .notif-pill{
    display:inline-flex !important;
    align-items:center !important;
    padding:6px 14px !important;
    border-radius:999px !important;
    font-weight:900 !important;
    font-size:14px !important;
    line-height:1 !important;
    border:2px solid transparent !important;
    background:#fff !important;
    white-space:nowrap !important;
    margin-right:8px !important;
}
#notifPanelTop .notif-pill-approved,
#notifBodyTop  .notif-pill-approved{
    color:#22c55e !important;
    background: rgba(34,197,94,.10) !important;
    border-color: rgba(34,197,94,.28) !important;
}
#notifPanelTop .notif-pill-rejected,
#notifBodyTop  .notif-pill-rejected{
    color:#ef4444 !important;
    background: rgba(239,68,68,.10) !important;
    border-color: rgba(239,68,68,.28) !important;
}
</style>

<script>
(function () {
    const wrap  = document.getElementById('notifWrapTop');
    const btn   = document.getElementById('notifBtnTop');
    const panel = document.getElementById('notifPanelTop');
    const body  = document.getElementById('notifBodyTop');
    const badge = document.getElementById('notifBadgeTop');
    if (!wrap || !btn || !panel || !body) return;

    const url = wrap.dataset.panelUrl;

    function getCsrf() {
        const m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function setBadge(n) {
        if (!badge) return;
        const val = Number(n || 0);
        badge.textContent = val;
        badge.style.display = val > 0 ? '' : 'none';
    }

    function escapeHtml(str) {
        return String(str ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function safeUrl(u) {
        const s = String(u ?? '').trim();
        if (!s) return '';
        if (s.startsWith('/') || /^https?:\/\//i.test(s)) return s;
        return '';
    }

    // ✅ PASTI KENA: ambil Approved/Rejected di awal kalimat
    function buildMsg(raw) {
        const plain = String(raw ?? '').trim();
        if (!plain) return '-';

        const m = plain.match(/^\s*(Approved|Rejected)\s+oleh\s+(.+?)\s*(?:\.?\s*Catatan:\s*(.*))?\s*$/iu);
        if (m) {
            const statusWord = /^approved$/i.test(m[1]) ? 'Approved' : 'Rejected';
            const actor = escapeHtml((m[2] || '').trim());
            const cls = /^approved$/i.test(statusWord)
                ? 'notif-pill notif-pill-approved'
                : 'notif-pill notif-pill-rejected';

            return `<span class="${cls}">${statusWord}</span><span style="color:#6b7280;font-weight:700;font-size:13px;letter-spacing:0;">Submitted by ${actor}</span>`;
        }

        const submitBy = plain.match(/^\s*(?:di)?submit(?:ted)?\s+(?:by|oleh)\s+(.+?)\.?\s*$/iu);
        if (submitBy) {
            const actor = escapeHtml((submitBy[1] || '').trim().replace(/\.$/, ''));
            return `<span style="color:#6b7280;font-weight:700;font-size:13px;letter-spacing:0;">Submitted by ${actor}</span>`;
        }

        const normalized = plain
            .replace(/^\s*disubmit\s+oleh\s+/iu, 'Submitted by ')
            .replace(/^\s*submit(?:ted)?\s+oleh\s+/iu, 'Submitted by ')
            .replace(/^\s*disubmit\s+by\s+/iu, 'Submitted by ')
            .replace(/^\s*submit(?:ted)?\s+by\s+/iu, 'Submitted by ')
            .replace(/\boleh\b/iu, 'By');
        const esc = escapeHtml(normalized).replace(/\bApproved\b/gi, '<span class="notif-pill notif-pill-approved">Approved</span>')
            .replace(/\bRejected\b/gi, '<span class="notif-pill notif-pill-rejected">Rejected</span>');
        return esc;
    }

    function renderItems(items) {
        if (!Array.isArray(items) || items.length === 0) {
            body.innerHTML = '<div class="notif-empty">Belum ada notifikasi.</div>';
            return;
        }

        body.innerHTML = items.map(n => {
            const id = Number(n.id || 0);
            const isRead = !!n.is_read;

            const title = escapeHtml(n.title || '-');
            const msg   = buildMsg(n.message || '');
            const time  = escapeHtml(n.created_at || '');
            const link  = safeUrl(n.url);

            const dotClass = isRead ? '' : 'unread';

            return `
                <div class="notif-item" data-id="${id}" data-read="${isRead ? 1 : 0}">
                    <div class="notif-left">
                        <div class="notif-dot ${dotClass}"></div>
                        <div class="notif-content">
                            <div class="notif-title-text">${title}</div>
                            <div class="notif-msg">${msg}</div>
                            <div class="notif-meta">
                                <span>${time}</span>
                                ${link ? `<a class="notif-open" href="${escapeHtml(link)}">Open</a>` : ``}
                            </div>
                        </div>
                    </div>

                    <div class="notif-right">
                        ${
                            isRead
                                ? `<span class="notif-read-label">Read</span>`
                                : `<button type="button" class="notif-read-btn" data-action="read" data-id="${id}">Read</button>`
                        }
                    </div>
                </div>
            `;
        }).join('');
    }

    async function loadPanel() {
        if (!url) {
            body.innerHTML = '<div class="notif-empty">Panel route belum tersedia.</div>';
            return;
        }

        body.innerHTML = '<div class="notif-empty">Loading…</div>';

        try {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (typeof data?.unread !== 'undefined') setBadge(data.unread);
            renderItems(data?.items || []);
        } catch (e) {
            body.innerHTML = '<div class="notif-empty">Gagal load notifikasi.</div>';
        }
    }

    async function markRead(id) {
        if (!id) return;
        try {
            const token = getCsrf();
            await fetch(`/inbox/${id}/read-json`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({})
            });

            const row = body.querySelector(`.notif-item[data-id="${id}"]`);
            if (row) {
                const dot = row.querySelector('.notif-dot');
                if (dot) dot.classList.remove('unread');

                const b = row.querySelector('.notif-read-btn');
                if (b) b.outerHTML = `<span class="notif-read-label">Read</span>`;
            }

            if (badge) {
                const cur = Number(badge.textContent || 0);
                setBadge(Math.max(0, cur - 1));
            }
        } catch(e) {}
    }

    function openPanel() {
        panel.style.display = 'block';
        loadPanel();
    }
    function closePanel() { panel.style.display = 'none'; }

    btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const isOpen = panel.style.display !== 'none';
        if (isOpen) closePanel();
        else openPanel();
    });

    document.addEventListener('click', (e) => {
        if (!wrap.contains(e.target)) closePanel();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closePanel();
    });

    body.addEventListener('click', (e) => {
        const t = e.target;
        if (t && t.matches && t.matches('.notif-read-btn[data-action="read"]')) {
            const id = Number(t.dataset.id || 0);
            markRead(id);
        }
    });
})();
</script>
