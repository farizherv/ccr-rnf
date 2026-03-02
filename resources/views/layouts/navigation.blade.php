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
    $hasInboxClearAll   = \Illuminate\Support\Facades\Route::has('inbox.clearAll');

    $role = strtolower(trim((string) auth()->user()->role));
@endphp

<div class="box ccr-topbar">
    {{-- LEFT BUTTONS --}}
    <div class="ccr-topbar-left">
        @if($role === 'director')
            <a href="{{ route('director.monitoring') }}" class="btn-modern btn-monitoring">Monitoring</a>
        @endif

        @if(in_array($role, ['admin','director'], true))
            <a href="{{ route('admin.users.index') }}" class="btn-modern btn-primary">User Management</a>
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
                    </div>

                    <div class="notif-body" id="notifBody">
                        <div class="notif-empty">Loading…</div>
                    </div>

                    <div class="notif-foot">
                        <div class="notif-foot-row">
                            <a class="notif-viewall notif-foot-btn" href="{{ route('inbox.index') }}">View all</a>
                            @if($hasInboxClearAll)
                                <form method="POST"
                                      action="{{ route('inbox.clearAll') }}"
                                      class="notif-clearform"
                                      onsubmit="return confirm('Hapus semua notifikasi?');">
                                    @csrf
                                    <button type="submit" class="notif-clearread notif-foot-btn">Clear all</button>
                                </form>
                            @endif
                        </div>
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
                    <button type="submit" class="drop-item">LOG OUT</button>
                </form>
            </div>
        </div>

    </div>
</div>

{{-- ===================== TOPBAR BUTTON OVERRIDE (MONITORING) ===================== --}}
<style>
/* tombol topbar Monitoring + User Management */
.ccr-topbar .btn-modern.btn-monitoring{
    background:#ffffff !important;
    color:#0f1b3a !important;
    border:2px solid #bccce2 !important;
    box-shadow: 0 3px 8px rgba(15, 27, 58, .08);
}
.ccr-topbar .btn-modern.btn-primary{
    background:#ffffff !important;
    color:#0f1b3a !important;
    border:2px solid #bccce2 !important;
    box-shadow: 0 3px 8px rgba(15, 27, 58, .08);
}
.ccr-topbar .btn-modern.btn-monitoring:hover,
.ccr-topbar .btn-modern.btn-primary:hover{
    background:#ffffff !important;
    color:#0f1b3a !important;
    border-color:#f2aaaa !important;
    box-shadow: 0 5px 12px rgba(228, 5, 5, .14) !important;
    transform:translateY(-1px);
}
</style>

{{-- ===================== NOTIF CSS (PILL + ITEM) ===================== --}}
<style>
/* panel */
#notifPanel{
    width:min(760px, calc(100vw - 24px)) !important;
    border-radius:24px !important;
    border:1px solid #dbe5f3 !important;
    box-shadow:0 22px 55px rgba(15,23,42,.18) !important;
}

#notifPanel .notif-head{
    padding:18px 22px !important;
    background:#fff !important;
    border-bottom:1px solid #eef2f7 !important;
    display:flex !important;
    align-items:center !important;
    justify-content:flex-start !important;
}

#notifPanel .notif-title{
    font-weight:1000 !important;
    font-size:16px !important;
    color:#111827 !important;
}

#notifPanel .notif-body{
    max-height:min(520px, calc(100vh - 180px)) !important;
}

/* ===================== PILL Approved / Rejected ===================== */
#notifPanel .notif-pill,
#notifBody  .notif-pill{
    display:inline-flex !important;
    align-items:center !important;
    padding:8px 14px !important;
    border-radius:999px !important;
    font-weight:900 !important;
    font-size:13px !important;
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
    gap:12px;
    align-items:flex-start;
    padding:14px 16px;
    border-bottom:1px solid #e6edf6;
    background:#fff;
    justify-content:space-between;
}
.notif-item.unread{
    background:#f8fbff;
}
.notif-item.read{
    opacity:.94;
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
.notif-item.unread .notif-dot{ background:#2563eb; }
.notif-content{ min-width:0; }
.notif-title-text{
    font-weight:1000;
    font-size:17px;
    line-height:1.2;
    display:-webkit-box;
    -webkit-line-clamp:1;
    -webkit-box-orient:vertical;
    overflow:hidden;
}
.notif-msg{
    margin-top:6px;
    min-width:0;
}
.notif-msg-row{
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:8px;
}
.notif-msg-actor{
    color:#6b7280;
    font-weight:700;
    font-size:13px;
    letter-spacing:0;
    line-height:1.25;
}
.notif-msg-label{
    color:#111827;
    font-weight:900;
    font-size:14px;
    line-height:1.25;
}
.notif-msg-note{
    margin-top:4px;
    color:#111827;
    font-weight:800;
    font-size:14px;
    line-height:1.3;
    overflow-wrap:anywhere;
    word-break:break-word;
    display:-webkit-box;
    -webkit-line-clamp:2;
    -webkit-box-orient:vertical;
    overflow:hidden;
}
.notif-side{
    min-width:160px;
    margin-top:2px;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:flex-end;
    gap:8px;
}
.notif-meta{
    font-weight:800;
    color:#6b7280;
    font-size:12px;
    text-align:right;
}
.notif-open{
    font-weight:900;
    color:#2563eb;
    text-decoration:none;
    border-radius:10px;
    padding:6px 10px;
    font-size:13px;
}
.notif-open:hover{
    background:#eef6ff;
}

.notif-foot{
    padding:14px 18px;
    border-top:1px solid #eef2f7;
    background:#fff;
}
.notif-foot-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
}
.notif-clearform{ margin:0; }
.notif-foot-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:14px 22px;
    border-radius:999px;
    border:1px solid #dbe5f3;
    background:#f8fbff;
    color:#0f172a;
    font-weight:1000;
    font-size:14px;
    line-height:1;
    white-space:nowrap;
    cursor:pointer;
    text-decoration:none;
    transition:.18s;
}
.notif-viewall:hover{
    background:rgba(13,110,253,.10);
    border-color:rgba(13,110,253,.25);
    color:#0D6EFD;
    transform:translateY(-1px);
}
.notif-clearread:hover{
    background:rgba(220,53,69,.10);
    border-color:rgba(220,53,69,.25);
    color:#dc3545;
    transform:translateY(-1px);
}

@media (max-width: 520px){
    .notif-side{
        min-width:0;
        align-items:flex-start;
        gap:6px;
    }
    .notif-meta{
        text-align:left;
    }
}
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
    const pendingReadIds = new Set();

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

    function renderMsg(raw) {
        const plain = String(raw ?? '').trim();
        if (!plain) return `<div class="notif-msg-note">-</div>`;

        const parsed = plain.match(/^\s*(Approved|Rejected)\s+oleh\s+(.+?)(?:\.?\s*Catatan:\s*(.*))?\s*$/iu);
        if (parsed) {
            const statusText = /^approved$/i.test(parsed[1]) ? 'Approved' : 'Rejected';
            const cls = statusText === 'Approved'
                ? 'notif-pill notif-pill-approved'
                : 'notif-pill notif-pill-rejected';
            const actor = escapeHtml((parsed[2] || '').trim());

            return `
                <div class="notif-msg-row">
                    <span class="${cls}">${statusText}</span>
                    <span class="notif-msg-actor">By ${actor}</span>
                </div>
            `;
        }

        const noNote = plain.replace(/\.?\s*Catatan:\s*.*$/iu, '').trim();
        const submitBy = noNote.match(/^\s*(?:di)?submit(?:ted)?\s+(?:by|oleh)\s+(.+?)\.?\s*$/iu);
        if (submitBy) {
            const actor = escapeHtml((submitBy[1] || '').trim().replace(/\.$/, ''));
            return `
                <div class="notif-msg-row">
                    <span class="notif-msg-actor">Submitted by ${actor}</span>
                </div>
            `;
        }

        const normalized = noNote
            .replace(/^\s*disubmit\s+oleh\s+/iu, 'Submitted by ')
            .replace(/^\s*submit(?:ted)?\s+oleh\s+/iu, 'Submitted by ')
            .replace(/^\s*disubmit\s+by\s+/iu, 'Submitted by ')
            .replace(/^\s*submit(?:ted)?\s+by\s+/iu, 'Submitted by ')
            .replace(/\boleh\b/iu, 'By');
        return `<div class="notif-msg-note">${pillify(escapeHtml(normalized))}</div>`;
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
            const msg   = renderMsg(n.message || '');
            const time  = escapeHtml(n.created_at || '');
            const link  = safeUrl(n.url);

            return `
                <div class="notif-item ${isRead ? 'read' : 'unread'}" data-id="${id}">
                    <div class="notif-left">
                        <div class="notif-dot"></div>
                        <div class="notif-content">
                            <div class="notif-title-text">${title}</div>
                            <div class="notif-msg">${msg}</div>
                        </div>
                    </div>
                    <div class="notif-side">
                        <span class="notif-meta">${time}</span>
                        ${link ? `<a class="notif-open" data-open-id="${id}" href="${escapeHtml(link)}">Open</a>` : ``}
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
        if (pendingReadIds.has(id)) return;
        pendingReadIds.add(id);

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
                row.classList.remove('unread');
                row.classList.add('read');
                const dot = row.querySelector('.notif-dot');
                if (dot) dot.style.background = '#cbd5e1';
            }

            // badge turun 1
            if (badge) {
                const cur = Number(badge.textContent || 0);
                setBadge(Math.max(0, cur - 1));
            }
        } catch (e) {
        } finally {
            pendingReadIds.delete(id);
        }
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

    body.addEventListener('click', (e) => {
        const openLink = e.target.closest('a.notif-open[data-open-id]');
        if (!openLink) return;
        const id = Number(openLink.getAttribute('data-open-id') || 0);
        if (id) markRead(id);
    });
})();
</script>
@endauth
