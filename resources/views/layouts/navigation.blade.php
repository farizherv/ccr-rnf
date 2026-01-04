{{-- resources/views/layouts/navigation.blade.php --}}
@auth
@php
    $unread = 0;

    if (class_exists(\App\Support\Inbox::class)) {
        try { $unread = \App\Support\Inbox::unreadCount(auth()->user()); }
        catch (\Throwable $e) { $unread = 0; }
    }

    $hasInboxRoute      = \Illuminate\Support\Facades\Route::has('inbox.index');
    $hasInboxPanelRoute = \Illuminate\Support\Facades\Route::has('inbox.panel');

    $role = strtolower(trim((string) auth()->user()->role));
@endphp

<div class="box ccr-topbar">
    {{-- LEFT BUTTONS --}}
    <div class="ccr-topbar-left">
        @if($role === 'director')
            {{-- ✅ Monitoring Direktur: warna #9F8170 + icon 📋 --}}
            <a href="{{ route('director.monitoring') }}" class="btn-modern btn-monitoring">📋 Monitoring Direktur</a>
        @endif

        @if(in_array($role, ['admin','director'], true))
            <a href="{{ route('admin.users.index') }}" class="btn-modern btn-primary">👥 User Management</a>
        @endif
    </div>

    {{-- RIGHT ACTIONS (INBOX PANEL + SETTINGS DROPDOWN) --}}
    <div class="top-actions ccr-topbar-right">

        {{-- INBOX DROPDOWN (AJAX PANEL) --}}
        @if($hasInboxPanelRoute)
            <div class="notif-wrap" id="notifWrap" data-panel-url="{{ route('inbox.panel') }}">
                <button type="button" class="icon-btn" id="notifBtn" title="Inbox" aria-label="Inbox">
                    🔔
                    <span class="inbox-badge" id="notifBadge" style="{{ $unread > 0 ? '' : 'display:none;' }}">{{ $unread }}</span>
                </button>

                <div class="notif-panel" id="notifPanel" style="display:none;">
                    <div class="notif-head">
                        <div class="notif-title">Notifications</div>
                        <form method="POST" action="{{ route('inbox.readAll') }}" style="margin:0;">
                            @csrf
                            <button class="notif-markall" type="submit">Mark all as read</button>
                        </form>
                    </div>

                    <div class="notif-body" id="notifBody">
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
                    <span class="inbox-badge">{{ $unread }}</span>
                @endif
            </a>
        @endif

        {{-- SETTINGS (ALPINE) --}}
        <div x-data="{ open:false }" style="position:relative;">
            <button type="button" class="icon-btn" title="Settings" aria-label="Settings" @click="open = !open">⚙️</button>

            <div class="drop" x-show="open" @click.outside="open=false" x-cloak>
                <div class="drop-head">
                    <div class="name">{{ auth()->user()->name }}</div>
                    <div class="meta">
                        {{ strtoupper(auth()->user()->role === 'operator' ? 'PLANNER' : auth()->user()->role) }}
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="drop-item">🚪 Logout</button>
                </form>
            </div>
        </div>

    </div>
</div>

{{-- ===================== TOPBAR BUTTON OVERRIDE (MONITORING) ===================== --}}
<style>
/* ✅ warna tombol Monitoring Direktur */
.ccr-topbar .btn-modern.btn-monitoring{
    background:#9F8170 !important;
    color:#fff !important;
    border:1px solid rgba(255,255,255,.18) !important;
}
.ccr-topbar .btn-modern.btn-monitoring:hover{
    filter:brightness(.95);
    transform:translateY(-1px);
}
</style>

{{-- ===================== NOTIF CSS (PILL + ITEM) ===================== --}}
<style>
/* ===================== PILL Approved / Rejected (khusus dropdown) ===================== */
#notifPanel .notif-pill,
#notifBody  .notif-pill{
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

#notifPanel .notif-pill-approved,
#notifBody  .notif-pill-approved{
    color:#22c55e !important;
    background: rgba(34,197,94,.10) !important;
    border-color: rgba(34,197,94,.28) !important;
}

#notifPanel .notif-pill-rejected,
#notifBody  .notif-pill-rejected{
    color:#ef4444 !important;
    background: rgba(239,68,68,.10) !important;
    border-color: rgba(239,68,68,.28) !important;
}

/* ===================== Dropdown item layout ===================== */
.notif-item{
    display:flex;
    justify-content:space-between;
    gap:14px;
    padding:14px 14px;
    border-bottom:1px solid #eef2f7;
}
.notif-left{
    display:flex;
    gap:12px;
    min-width:0;
}
.notif-dot{
    width:14px;height:14px;border-radius:999px;
    margin-top:6px;
    background:#cbd5e1;
    flex:0 0 auto;
}
.notif-dot.unread{ background:#2563eb; }
.notif-content{ min-width:0; }
.notif-title-text{
    font-weight:1000;
    font-size:20px;
    line-height:1.2;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    max-width:360px;
}
.notif-msg{
    margin-top:4px;
    font-weight:800;
    color:#111827;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    max-width:420px;
}
.notif-meta{
    margin-top:6px;
    font-weight:800;
    color:#6b7280;
    display:flex;
    gap:12px;
    align-items:center;
}
.notif-open{
    font-weight:900;
    color:#2563eb;
    text-decoration:none;
}
.notif-right{ flex:0 0 auto; }
.notif-read-btn,
.notif-read-label{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:86px;
    height:58px;
    border-radius:18px;
    background:#6b7075;
    color:#fff;
    font-weight:1000;
    border:none;
}
.notif-read-btn{ cursor:pointer; }
.notif-read-label{ cursor:default; opacity:.95; }
</style>

{{-- ===================== NOTIF SCRIPT (RENDER JSON + PILL WARNA) ===================== --}}
<script>
(function () {
    const wrap  = document.getElementById('notifWrap');
    const btn   = document.getElementById('notifBtn');
    const panel = document.getElementById('notifPanel');
    const body  = document.getElementById('notifBody');
    const badge = document.getElementById('notifBadge');

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

    function pillify(escapedText) {
        // teks sudah di-escape, jadi aman sisip HTML pill
        return escapedText
            .replace(/\bApproved\b/gi, '<span class="notif-pill notif-pill-approved">Approved</span>')
            .replace(/\bRejected\b/gi, '<span class="notif-pill notif-pill-rejected">Rejected</span>');
    }

    function renderItems(items) {
        if (!Array.isArray(items) || items.length === 0) {
            body.innerHTML = '<div class="notif-empty">Belum ada notifikasi.</div>';
            return;
        }

        body.innerHTML = items.map(n => {
            const id     = Number(n.id || 0);
            const isRead = !!n.is_read;

            const title = escapeHtml(n.title || '-');
            const msg   = pillify(escapeHtml(n.message || ''));
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
            const ct  = (res.headers.get('content-type') || '').toLowerCase();

            if (ct.includes('application/json')) {
                const data = await res.json();

                // ✅ panel() kamu: { unread, items: [...] }
                if (typeof data?.unread !== 'undefined') setBadge(data.unread);
                renderItems(data?.items || []);
            } else {
                // fallback kalau suatu saat panel() balikin HTML
                const html = await res.text();
                body.innerHTML = html || '<div class="notif-empty">Belum ada notifikasi.</div>';

                // pillify fallback HTML
                body.innerHTML = (body.innerHTML || '')
                    .replace(/\bApproved\b/gi, '<span class="notif-pill notif-pill-approved">Approved</span>')
                    .replace(/\bRejected\b/gi, '<span class="notif-pill notif-pill-rejected">Rejected</span>');
            }
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

            // update UI cepat
            const row = body.querySelector(`.notif-item[data-id="${id}"]`);
            if (row) {
                row.dataset.read = '1';
                const dot = row.querySelector('.notif-dot');
                if (dot) dot.classList.remove('unread');

                const b = row.querySelector('.notif-read-btn');
                if (b) b.outerHTML = `<span class="notif-read-label">Read</span>`;
            }

            // badge turun 1
            if (badge) {
                const cur = Number(badge.textContent || 0);
                setBadge(Math.max(0, cur - 1));
            }
        } catch (e) {}
    }

    function openPanel() {
        panel.style.display = 'block';
        loadPanel(); // reload tiap buka
    }

    function closePanel() {
        panel.style.display = 'none';
    }

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

    // delegation tombol Read
    body.addEventListener('click', (e) => {
        const t = e.target;
        if (!t) return;
        if (t.matches && t.matches('.notif-read-btn[data-action="read"]')) {
            const id = Number(t.dataset.id || 0);
            markRead(id);
        }
    });
})();
</script>
@endauth
