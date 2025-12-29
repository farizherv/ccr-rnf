<!DOCTYPE html>
<html>
<head>
    <title>CCR RNF</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Alpine JS --}}
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
            font-family: Arial, sans-serif;
        }

        /* ===== BOX GLOBAL ===== */
        .box {
            background: white;
            padding: 18px;
            border-radius: 14px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.07);
        }

        /* ===== INPUT FIELD ===== */
        .input {
            width: 100%;
            padding: 10px 12px;
            margin-top: 5px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 14px;
        }

        .input:focus {
            outline: none;
            border-color: #E40505;
            box-shadow: 0 0 6px rgba(228,5,5,0.25);
        }

        /* ===== BUTTON MODERN ===== */
        .btn-modern {
            display: inline-block;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            color: white;
            transition: 0.2s ease;
            box-shadow: 0 3px 7px rgba(0,0,0,0.15);
        }

        .btn-primary { background:#0d6efd; }
        .btn-success { background:#198754; }
        .btn-danger  { background:#dc3545; }
        .btn-back    { background:#6c757d; }

        .btn-modern:hover { transform: translateY(-2px); }

        /* ===== DROPZONE ===== */
        .dropzone {
            border: 2px dashed #999;
            padding: 20px;
            border-radius: 10px;
            cursor: pointer;
            text-align: center;
            background: #fafafa;
            margin-bottom: 10px;
        }
        .dropzone:hover { background:#f0f0f0; }

        .preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .thumb {
            width: 110px;
            height: 110px;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            border: 1px solid #ddd;
        }

        .thumb img { width:100%; height:100%; object-fit: cover; }

        .remove-btn {
            position:absolute;
            top:4px;
            right:4px;
            background:#ff4444;
            color:white;
            padding: 2px 6px;
            border-radius:50%;
            font-size:12px;
            cursor:pointer;
        }

        /* ===== TOAST NOTIFICATION PREMIUM ===== */
        .toast-wrapper {
            position: fixed;
            top: 18px;
            right: 18px;
            z-index: 9999;
        }

        .toast-success {
            min-width: 260px;
            max-width: 360px;
            background: #ffffff;
            border-radius: 14px;
            padding: 12px 14px 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border-left: 4px solid #198754;
        }

        .toast-icon {
            width: 26px;
            height: 26px;
            border-radius: 999px;
            background: #1987541a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #198754;
            flex-shrink: 0;
        }

        .toast-content { flex: 1; }

        .toast-title {
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 2px;
            color: #1f2933;
        }

        .toast-message { font-size: 13px; color: #4a5563; }

        .toast-close {
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 16px;
            line-height: 1;
            color: #9ca3af;
            padding: 0 2px;
        }
        .toast-close:hover { color: #4b5563; }

        .toast-progress {
            height: 3px;
            border-radius: 999px;
            background: #d1fae5;
            overflow: hidden;
            margin-top: 8px;
        }

        .toast-progress-bar {
            height: 100%;
            background: #10b981;
            width: 100%;
            animation: toast-progress 3.5s linear forwards;
        }

        @keyframes toast-progress { from { width: 100%; } to { width: 0%; } }

        /* ===================== TOPBAR ACTIONS (INBOX + SETTINGS) ===================== */
        .top-actions { display:flex; align-items:center; gap:10px; position:relative; }

        .icon-btn {
            position:relative;
            width:42px;
            height:42px;
            border-radius:12px;
            border:1px solid #e5e7eb;
            background:#f3f4f6;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            cursor:pointer;
            text-decoration:none;
            box-shadow: 0 3px 7px rgba(0,0,0,0.08);
            font-size:18px;
            transition:.18s;
        }
        .icon-btn:hover { transform: translateY(-1px); filter: brightness(.98); }

        .inbox-badge{
            position:absolute;
            top:-6px;
            right:-6px;
            min-width:20px;
            height:20px;
            padding:0 6px;
            border-radius:999px;
            background:#E40505;
            color:#fff;
            font-weight:900;
            font-size:12px;
            display:flex;
            align-items:center;
            justify-content:center;
            border:2px solid #fff;
        }

        /* ===== SETTINGS DROPDOWN ===== */
        .drop {
            position:absolute;
            right:0;
            top:52px;
            width:220px;
            background:#fff;
            border:1px solid #e5e7eb;
            border-radius:14px;
            box-shadow:0 18px 40px rgba(0,0,0,.16);
            overflow:hidden;
            z-index:9999;
        }

        .drop-head { padding:12px 14px; border-bottom:1px solid #eef2f7; }
        .drop-head .name { font-weight:900; color:#111827; }
        .drop-head .meta { margin-top:3px; font-weight:800; font-size:12px; color:#6b7280; }

        .drop-item {
            width:100%;
            text-align:left;
            border:none;
            background:#fff;
            padding:12px 14px;
            cursor:pointer;
            font-weight:900;
        }
        .drop-item:hover { background:#f8fafc; }

        [x-cloak]{ display:none !important; }

        /* ===== INBOX DROPDOWN PANEL (LIKE SCREENSHOT #5) ===== */
        .notif-wrap{ position:relative; }
        .notif-panel{
            position:absolute;
            right:0;
            top:52px;
            width:360px;
            max-width:92vw;
            background:#fff;
            border-radius:18px;
            box-shadow:0 26px 70px rgba(0,0,0,.18);
            border:1px solid #eef2f7;
            overflow:hidden;
            z-index:9999;
        }
        .notif-head{
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding:12px 14px;
            border-bottom:1px solid #eef2f7;
        }
        .notif-title{ font-weight:1000; color:#111827; }
        .notif-markall{
            border:0;background:transparent;color:#0D6EFD;
            font-weight:900;cursor:pointer;
        }
        .notif-body{ max-height:360px; overflow:auto; }
        .notif-item{ display:flex; gap:10px; padding:12px 14px; border-bottom:1px solid #f1f5f9; }
        .notif-item.unread{ background:#f7fbff; }
        .notif-dot{ width:10px; height:10px; border-radius:999px; background:#0D6EFD; margin-top:7px; flex:0 0 auto; }
        .notif-item.read .notif-dot{ background:#cbd5e1; }
        .notif-main{ flex:1; min-width:0; }
        .notif-h{ font-weight:1000; }
        .notif-m{ margin-top:3px; color:#111827; font-weight:700; font-size:13px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .notif-meta{ margin-top:6px; color:#6b7280; font-weight:700; font-size:12px; display:flex; gap:8px; align-items:center; }
        .notif-open{ color:#0D6EFD; font-weight:900; text-decoration:none; }
        .notif-readbtn{ border:0; background:#6b7075; color:#fff; font-weight:900; border-radius:12px; padding:8px 10px; cursor:pointer; }
        .notif-empty{ padding:14px; color:#6b7280; font-weight:800; }
        .notif-foot{ padding:10px 14px; border-top:1px solid #eef2f7; background:#fbfdff; }
        .notif-viewall{ color:#111827; font-weight:900; text-decoration:none; }

        @media (max-width: 520px){
            .icon-btn{ width:40px; height:40px; border-radius:12px; }
        }
    </style>
</head>

<body>

    {{-- TOAST NOTIFICATION (PREMIUM) --}}
    @if(session('success'))
        <div class="toast-wrapper"
             x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 3500)"
             x-show="show"
             x-transition.opacity.duration.300ms>

            <div class="toast-success">
                <div class="toast-icon">✓</div>

                <div class="toast-content">
                    <div class="toast-title">Berhasil disimpan</div>
                    <div class="toast-message">{{ session('success') }}</div>
                    <div class="toast-progress">
                        <div class="toast-progress-bar"></div>
                    </div>
                </div>

                <button class="toast-close" @click="show = false">×</button>
            </div>
        </div>
    @endif

    {{-- ======================= TOPBAR QUICK MENU ======================= --}}
    @auth
        @php
            $unread = 0;

            if (class_exists(\App\Support\Inbox::class)) {
                try { $unread = \App\Support\Inbox::unreadCount(auth()->user()); }
                catch (\Throwable $e) { $unread = 0; }
            }

            $hasInboxRoute = \Illuminate\Support\Facades\Route::has('inbox.index');
            $hasInboxPanelRoute = \Illuminate\Support\Facades\Route::has('inbox.panel');
        @endphp

        <div class="box" style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
            {{-- LEFT BUTTONS --}}
            @php
                $role = strtolower(trim((string) auth()->user()->role));
            @endphp

            {{-- LEFT BUTTONS --}}
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                @if($role === 'director')
                    <a href="{{ route('director.monitoring') }}" class="btn-modern btn-success">🟢 Monitoring Direktur</a>
                @endif

                @if(in_array($role, ['admin','director'], true))
                    <a href="{{ route('admin.users.index') }}" class="btn-modern btn-primary">👥 User Management</a>
                @endif
            </div>


            {{-- RIGHT ACTIONS (INBOX PANEL + SETTINGS DROPDOWN) --}}
            <div class="top-actions">

                {{-- INBOX DROPDOWN (AJAX PANEL) --}}
                @if($hasInboxPanelRoute)
                    <div class="notif-wrap" id="notifWrap">
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
                    <a href="{{ route('inbox.index') }}" class="icon-btn" title="Inbox">
                        🔔
                        @if($unread > 0)
                            <span class="inbox-badge">{{ $unread }}</span>
                        @endif
                    </a>
                @endif

                {{-- SETTINGS (ALPINE) --}}
                <div x-data="{ open:false }" style="position:relative;">
                    <button type="button" class="icon-btn" title="Settings" @click="open = !open">⚙️</button>

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
    @endauth

    {{-- PAGE CONTENT --}}
    <div id="ccr-root">
        @yield('content')
    </div>

    {{-- INBOX PANEL SCRIPT --}}
    @auth
        @if(\Illuminate\Support\Facades\Route::has('inbox.panel'))
            <script>
            (function(){
                const btn = document.getElementById('notifBtn');
                const panel = document.getElementById('notifPanel');
                const body = document.getElementById('notifBody');
                const badge = document.getElementById('notifBadge');
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                async function fetchPanel(){
                    try{
                        const res = await fetch("{{ route('inbox.panel') }}", { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
                        if(!res.ok) return;
                        const data = await res.json();

                        const unread = Number(data.unread || 0);
                        if(unread > 0){
                            badge.style.display = 'flex';
                            badge.textContent = String(unread);
                        } else {
                            badge.style.display = 'none';
                            badge.textContent = '';
                        }

                        const items = data.items || [];
                        if(items.length === 0){
                            body.innerHTML = `<div class="notif-empty">Belum ada notifikasi.</div>`;
                            return;
                        }

                        body.innerHTML = items.map(n => `
                            <div class="notif-item ${n.is_read ? 'read' : 'unread'}" data-id="${n.id}">
                                <div class="notif-dot"></div>
                                <div class="notif-main">
                                    <div class="notif-h">${escapeHtml(n.title || '-')}</div>
                                    <div class="notif-m">${escapeHtml(n.message || '')}</div>
                                    <div class="notif-meta">
                                        <span>${escapeHtml(n.created_at)}</span>
                                        ${n.url ? `<a class="notif-open" href="${n.url}">Open</a>` : ``}
                                    </div>
                                </div>
                                ${n.is_read ? `` : `<button class="notif-readbtn" data-read="${n.id}">Read</button>`}
                            </div>
                        `).join('');
                    } catch(e) {}
                }

                async function markRead(id){
                    try{
                        await fetch("{{ url('/inbox') }}/" + id + "/read-json", {
                            method: "POST",
                            headers: {
                                "X-CSRF-TOKEN": token,
                                "X-Requested-With": "XMLHttpRequest"
                            }
                        });
                        await fetchPanel();
                    } catch(e) {}
                }

                function togglePanel(){
                    const open = panel.style.display !== 'none';
                    panel.style.display = open ? 'none' : 'block';
                    if(!open) fetchPanel();
                }

                document.addEventListener('click', (e) => {
                    if(btn && btn.contains(e.target)) return;
                    if(panel && panel.contains(e.target)) return;
                    if(panel) panel.style.display = 'none';
                });

                if(btn){
                    btn.addEventListener('click', togglePanel);
                }

                if(body){
                    body.addEventListener('click', (e) => {
                        const id = e.target.getAttribute('data-read');
                        if(id) markRead(id);
                    });
                }

                // auto refresh badge
                setInterval(fetchPanel, 15000);

                function escapeHtml(str){
                    return String(str).replace(/[&<>"']/g, (m)=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]));
                }
            })();
            </script>
        @endif
    @endauth

</body>
</html>
