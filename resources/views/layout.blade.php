<!DOCTYPE html>
<html>
<head>
    <title>CCR RNF</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Alpine JS --}}
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        /* =============================
           BASE
           ============================= */
        body {
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
            font-family: Arial, sans-serif;
        }

        [x-cloak]{ display:none !important; }

        /* === Anti FOUC header global === */
        img.header-logo,
        img.header-logo-master {
            width: 110px;
            height: 110px;
            object-fit: contain;
            display: block;
            flex: 0 0 auto;
        }

        .header-card,
        .header-card-master {
            background: #fff;
            border-radius: 18px;
            padding: 18px 22px;
            box-shadow: 0 6px 18px rgba(0,0,0,.08);
        }

        .header-left,
        .header-content,
        .header-content-master {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .header-title {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: .3px;
            margin: 0;
        }

        .header-subtitle,
        .header-subtitle-master {
            margin: 4px 0 0;
            color: #6b7280;
            font-weight: 600;
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
            white-space: nowrap;
        }
        .btn-primary { background:#0d6efd; }
        .btn-success { background:#198754; }
        .btn-monitoring{
            background:#9F8170 !important;
        }
        .btn-monitoring:hover{
            filter: brightness(.95);
        }
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

        /* =========================================================
           TOPBAR LAYOUT FIX (BIAR ICON 🔔⚙️ SELALU KANAN)
           ========================================================= */
        .topbar{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            flex-wrap:wrap;
        }
        .top-left{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
            align-items:center;
            flex: 1 1 420px;
            min-width: 240px;
        }
        .top-actions{
            display:flex;
            align-items:center;
            gap:10px;
            position:relative;
            margin-left:auto;
            flex: 1 1 160px;
            justify-content:flex-end;
        }
        @media (max-width: 620px){
            .top-left{ flex-basis: 100%; }
            .top-actions{ flex-basis: 100%; }
        }

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
            z-index:10001;
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

        /* =========================================================
           INBOX DROPDOWN PANEL
           ========================================================= */
        .notif-wrap{ position:relative; }

        .notif-panel{
            position:absolute;
            right:0;
            top:52px;
            width: min(360px, calc(100vw - 24px));
            background:#fff;
            border-radius:16px;
            box-shadow:0 22px 55px rgba(0,0,0,.16);
            border:1px solid #e9eef5;
            overflow:hidden;
            z-index:10000;
        }

        .notif-head{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            padding:12px 14px;
            border-bottom:1px solid #eef2f7;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        }

        .notif-title{
            font-weight:1000;
            color:#111827;
            font-size:16px;
            letter-spacing:.2px;
        }

        .notif-markall{
            border:0;
            background:transparent;
            color:#0D6EFD;
            font-weight:900;
            cursor:pointer;
            padding:6px 8px;
            border-radius:10px;
            font-size:13px;
            white-space:nowrap;
        }
        .notif-markall:hover{ background:#eef6ff; }

        .notif-body{
            max-height: min(360px, calc(100vh - 180px));
            overflow:auto;
            -webkit-overflow-scrolling: touch;
        }

        .notif-empty{
            padding:12px 14px;
            color:#6b7280;
            font-weight:800;
            font-size:13px;
        }

        .notif-item{
            display:flex;
            gap:10px;
            padding:12px 14px;
            border-bottom:1px solid #f1f5f9;
            align-items:flex-start;
        }

        .notif-item.unread{ background:#f7fbff; }
        .notif-item.read{ background:#fff; opacity:.92; }

        .notif-dot{
            width:10px; height:10px; border-radius:999px;
            background:#cbd5e1;
            margin-top:7px;
            flex:0 0 auto;
        }
        .notif-item.unread .notif-dot{ background:#2563eb; }

        .notif-main{ flex:1; min-width:0; }

        .notif-h{
            font-weight:1000;
            font-size:15px;
            line-height:1.15;
            color:#0f172a;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
        }

        .notif-m{
            margin-top:6px;
            color:#111827;
            font-weight:800;
            font-size:13px;
            line-height:1.25;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
        }

        .notif-meta{
            margin-top:8px;
            color:#6b7280;
            font-weight:800;
            font-size:12px;
            display:flex;
            gap:10px;
            align-items:center;
            flex-wrap:wrap;
        }

        .notif-open{
            color:#0D6EFD;
            font-weight:1000;
            text-decoration:none;
            padding:4px 8px;
            border-radius:10px;
            font-size:12px;
        }
        .notif-open:hover{ background:#eef6ff; }

        .notif-panel .notif-pill{
            display:inline-flex;
            align-items:center;
            padding:5px 12px;
            border-radius:999px;
            font-weight:900;
            font-size:12px;
            line-height:1;
            border:2px solid transparent;
            background:#fff;
            white-space:nowrap;
            margin-right:8px;
        }
        .notif-panel .notif-pill-approved{
            color:#22c55e;
            background: rgba(34,197,94,.10);
            border-color: rgba(34,197,94,.25);
        }
        .notif-panel .notif-pill-rejected{
            color:#ef4444;
            background: rgba(239,68,68,.10);
            border-color: rgba(239,68,68,.25);
        }

        .notif-readbtn{
            border:0;
            background:#111827;
            color:#fff;
            font-weight:1000;
            border-radius:999px;
            padding:9px 12px;
            cursor:pointer;
            min-width:76px;
            height:36px;
            font-size:13px;
            box-shadow:0 10px 18px rgba(17,24,39,.14);
            flex:0 0 auto;
        }
        .notif-readbtn:hover{ filter:brightness(.96); transform: translateY(-1px); }

        /* ✅ UPDATED FOOTER: kanan bawah + View all + Clear read */
        .notif-foot{
            padding:10px 14px;
            border-top:1px solid #eef2f7;
            background:#fbfdff;
        }
        .notif-foot-row{
            display:flex;
            align-items:center;
            justify-content:space-between; /* ✅ View all kiri, Clear read kanan */
            gap:10px;
        }
        .notif-clearform{ margin:0; }

        /* View all: default sama seperti Clear read, hover soft blue */
        /* base tombol footer: bikin ukuran & border konsisten */
        .notif-foot-btn{
            display:inline-flex;
            align-items:center;
            justify-content:center;

            padding:10px 16px;          /* samakan padding */
            border-radius:16px;         /* samakan radius */
            border:1px solid #e5e7eb;   /* samakan border */
            background:#f1f5f9;
            color:#0f172a;

            font-weight:1000;
            font-size:14px;
            line-height:1;              /* penting biar tinggi sama */
            white-space:nowrap;

            cursor:pointer;
            text-decoration:none;
            transition:.18s;
        }

        /* View all: hover soft blue */
        .notif-viewall:hover{
            background: rgba(13,110,253,.10);
            border-color: rgba(13,110,253,.25);
            color:#0D6EFD;
            transform: translateY(-1px);
        }
        .notif-viewall:active{ transform: translateY(0px); }

        /* Clear read: hover soft red */
        .notif-clearread:hover{
            background: rgba(220,53,69,.10);
            border-color: rgba(220,53,69,.25);
            color:#dc3545;
            transform: translateY(-1px);
        }
        .notif-clearread:active{ transform: translateY(0px); }

        .notif-clearform{ margin:0; }

        @media (max-width: 520px){
            .notif-body{ max-height: min(52vh, 320px); }
            .drop{ width: min(320px, calc(100vw - 24px)); }
        }

        /* =========================================================
           LOCK MODAL (RESPONSIVE, SMALLER)
           - icon besar di tengah
           - tanpa ring merah
           - tanpa tombol OK
           ========================================================= */
        .lock-overlay{
            position:fixed;
            inset:0;
            z-index:20000;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:18px;
        }
        .lock-backdrop{
            position:absolute;
            inset:0;
            background:rgba(17,24,39,.55);
            backdrop-filter: blur(3px);
        }

        .lock-card{
            position:relative;
            width:min(520px, calc(100vw - 56px)); /* desktop default lebih kecil */
            background:#fff;
            border-radius:24px;
            box-shadow:0 30px 90px rgba(0,0,0,.26);
            border:1px solid #e5e7eb;
            overflow:hidden;
            padding: 34px 28px 28px;
            text-align:center;
        }

        .lock-x{
            position:absolute;
            top:18px;
            right:18px;
            width:54px;
            height:54px;
            border-radius:16px;
            border:1px solid #e5e7eb;
            background:#fff;
            cursor:pointer;
            display:flex;
            align-items:center;
            justify-content:center;
            box-shadow: 0 14px 30px rgba(17,24,39,.10);
        }
        .lock-x span{
            font-size:28px;          /* X lebih besar */
            font-weight:900;
            color:#6b7280;
            line-height:1;
            transform: translateY(-1px);
        }
        .lock-x:hover{ filter:brightness(.98); transform: translateY(-1px); }

        .lock-ico{
            width:104px;
            height:104px;
            border-radius:999px;
            background:#f3f4f6;      /* ring merah dihilangkan */
            display:flex;
            align-items:center;
            justify-content:center;
            margin: 4px auto 18px;
            font-size:52px;          /* icon besar */
        }

        .lock-title{
            font-weight:1000;
            font-size:44px;
            color:#111827;
            letter-spacing:.2px;
            margin: 0;
        }

        .lock-sub{
            margin-top:10px;
            font-size:22px;
            color:#6b7280;
            font-weight:900;
        }

        /* Tablet */
        @media (max-width: 900px){
            .lock-card{
                width:min(480px, calc(100vw - 48px));
                padding: 30px 22px 24px;
                border-radius:22px;
            }
            .lock-ico{ width:96px; height:96px; font-size:48px; margin-bottom:16px; }
            .lock-title{ font-size:40px; }
            .lock-sub{ font-size:20px; }
            .lock-x{ width:52px; height:52px; border-radius:16px; top:16px; right:16px; }
            .lock-x span{ font-size:27px; }
        }

        /* Mobile */
        @media (max-width: 520px){
            .lock-overlay{ padding:14px; }
            .lock-card{
                width:min(360px, calc(100vw - 28px));
                padding: 26px 18px 22px;
                border-radius:22px;
            }
            .lock-ico{ width:88px; height:88px; font-size:44px; margin-bottom:14px; }
            .lock-title{ font-size:34px; }
            .lock-sub{ font-size:18px; }
            .lock-x{ width:50px; height:50px; border-radius:16px; top:14px; right:14px; }
            .lock-x span{ font-size:26px; }
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
            $hasInboxClearRead = \Illuminate\Support\Facades\Route::has('inbox.clearRead'); // ✅ optional safety
        @endphp

        <div class="box topbar">
            {{-- LEFT BUTTONS --}}
            @php
                $role = strtolower(trim((string) auth()->user()->role));
            @endphp

            <div class="top-left">
                @if($role === 'director')
                    <a href="{{ route('director.monitoring') }}" class="btn-modern btn-monitoring">📋 Monitoring Direktur</a>
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
                                <div class="notif-foot-row">
                                        <a class="notif-viewall notif-foot-btn" href="{{ route('inbox.index') }}">View all</a>
                                    @if($hasInboxClearRead)
                                        <form method="POST"
                                            action="{{ route('inbox.clearRead') }}"
                                            class="notif-clearform"
                                            onsubmit="return confirm('Hapus semua notifikasi yang sudah dibaca?');">
                                            @csrf
                                            <button type="submit" class="notif-clearread notif-foot-btn">Clear read</button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                        </div>
                    </div>
                @elseif($hasInboxRoute)
                    <a href="{{ route('inbox.index') }}" class="icon-btn" title="Inbox">
                        🔔
                        @if($unread > 0)
                            <span class="inbox-badge">{{ $unread }}</span>
                        @endif
                    </a>
                @endif

                {{-- SETTINGS (ALPINE) --}}
                <div x-data="{ open:false }"
                     x-on:close-settings.window="open=false"
                     style="position:relative;">

                    <button id="settingsBtn"
                            type="button"
                            class="icon-btn"
                            title="Settings"
                            @click="
                                open = !open;
                                window.dispatchEvent(new Event('close-notif'));
                                if(open){
                                    $nextTick(() => window.dispatchEvent(new Event('open-settings')));
                                }
                            ">
                        ⚙️
                    </button>

                    <div class="drop"
                         id="settingsDrop"
                         x-show="open"
                         @click.outside="open=false"
                         x-cloak>
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

    {{-- GLOBAL LOCK MODAL
         Trigger: window.dispatchEvent(new CustomEvent('locked', {detail:{msg:'you cannot access this'}}))
    --}}
    <div
        x-data="{ open:false, msg:'you cannot access this' }"
        @locked.window="
            msg = ($event.detail && $event.detail.msg) ? $event.detail.msg : 'you cannot access this';
            open = true;
        "
        @keydown.escape.window="open=false"
        x-show="open"
        x-cloak
        class="lock-overlay"
        style="display:none;"
    >
        <div class="lock-backdrop" @click="open=false"></div>

        <div class="lock-card" x-transition.scale>
            <button class="lock-x" type="button" @click="open=false" aria-label="Close">
                <span>×</span>
            </button>

            <div class="lock-ico" aria-hidden="true">🔒</div>
            <h2 class="lock-title">Locked</h2>
            <div class="lock-sub" x-text="msg"></div>
        </div>
    </div>
    @if(session('locked'))
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                window.dispatchEvent(new CustomEvent('locked', {
                    detail: { msg: @json(session('locked')) }
                }));
            });
        </script>
    @endif

    {{-- INBOX PANEL SCRIPT --}}
    @auth
    @if(\Illuminate\Support\Facades\Route::has('inbox.panel'))
    <script>
    (function(){
        const btn   = document.getElementById('notifBtn');
        const panel = document.getElementById('notifPanel');
        const body  = document.getElementById('notifBody');
        const badge = document.getElementById('notifBadge');
        const tokenEl = document.querySelector('meta[name="csrf-token"]');
        const token = tokenEl ? tokenEl.getAttribute('content') : '';

        function isShown(el){
            if(!el) return false;
            return getComputedStyle(el).display !== 'none';
        }

        function escapeHtml(str){
            return String(str ?? '').replace(/[&<>"']/g, (m)=>({
                '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
            }[m]));
        }

        function safeUrl(u){
            const s = String(u ?? '').trim();
            if (!s) return '';
            if (s.startsWith('/') || /^https?:\/\//i.test(s)) return s;
            return '';
        }

        function renderMsg(raw){
            const esc = escapeHtml(raw || '');
            const m = esc.match(/^\s*(Approved|Rejected)\b\s*(.*)$/i);
            if (!m) return esc;

            const status = m[1];
            const rest   = m[2] || '';
            const cls = /^approved$/i.test(status)
                ? 'notif-pill notif-pill-approved'
                : 'notif-pill notif-pill-rejected';

            return `<span class="${cls}">${status}</span> ${rest}`;
        }

        function placeUnderButton(anchorBtn, floatingEl, maxWidthPx){
            if(!anchorBtn || !floatingEl) return;
            if(!isShown(floatingEl)) return;

            floatingEl.style.position = 'fixed';
            floatingEl.style.right = 'auto';

            const mw = Math.min(maxWidthPx, window.innerWidth - 24);
            floatingEl.style.width = mw + 'px';
            floatingEl.style.maxWidth = mw + 'px';

            const r = anchorBtn.getBoundingClientRect();
            const rect = floatingEl.getBoundingClientRect();
            const w = rect.width || mw;
            const h = rect.height || 0;

            const margin = 12;
            const offset = 8;

            let left = r.right - w;
            left = Math.max(margin, Math.min(left, window.innerWidth - w - margin));

            let top = r.bottom + offset;

            const willCutBottom = (top + h) > (window.innerHeight - margin);
            if(willCutBottom){
                const upTop = r.top - offset - h;
                if(upTop >= margin) top = upTop;
            }

            top = Math.max(margin, Math.min(top, window.innerHeight - margin - Math.min(h, window.innerHeight)));

            floatingEl.style.left = Math.round(left) + 'px';
            floatingEl.style.top  = Math.round(top) + 'px';
        }

        function positionNotif(){
            if(!btn || !panel) return;
            if(!isShown(panel)) return;
            placeUnderButton(btn, panel, 360);
        }

        async function fetchPanel(){
            try{
                const res = await fetch("{{ route('inbox.panel') }}", {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if(!res.ok) return;
                const data = await res.json();

                const unread = Number(data.unread || 0);
                if(badge){
                    if(unread > 0){
                        badge.style.display = 'flex';
                        badge.textContent = String(unread);
                    } else {
                        badge.style.display = 'none';
                        badge.textContent = '';
                    }
                }

                const items = data.items || [];
                if(items.length === 0){
                    body.innerHTML = `<div class="notif-empty">Belum ada notifikasi.</div>`;
                    return;
                }

                body.innerHTML = items.map(n => {
                    const link = safeUrl(n.url);
                    return `
                        <div class="notif-item ${n.is_read ? 'read' : 'unread'}" data-id="${n.id}">
                            <div class="notif-dot"></div>
                            <div class="notif-main">
                                <div class="notif-h">${escapeHtml(n.title || '-')}</div>
                                <div class="notif-m">${renderMsg(n.message || '')}</div>
                                <div class="notif-meta">
                                    <span>${escapeHtml(n.created_at)}</span>
                                    ${link ? `<a class="notif-open" href="${escapeHtml(link)}">Open</a>` : ``}
                                </div>
                            </div>
                            ${n.is_read ? `` : `<button class="notif-readbtn" data-read="${n.id}">Read</button>`}
                        </div>
                    `;
                }).join('');

                requestAnimationFrame(positionNotif);
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

        function closePanel(){
            if(panel) panel.style.display = 'none';
        }

        function openPanel(){
            if(!panel) return;
            window.dispatchEvent(new Event('close-settings'));

            panel.style.display = 'block';
            requestAnimationFrame(() => {
                positionNotif();
                fetchPanel();
            });
        }

        function togglePanel(){
            const open = panel && panel.style.display !== 'none';
            if(open) closePanel();
            else openPanel();
        }

        window.addEventListener('close-notif', closePanel);

        document.addEventListener('click', (e) => {
            if(btn && btn.contains(e.target)) return;
            if(panel && panel.contains(e.target)) return;
            closePanel();
        });

        if(btn){
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                togglePanel();
            });
        }

        if(body){
            body.addEventListener('click', (e) => {
                const id = e.target.getAttribute('data-read');
                if(id) markRead(id);
            });
        }

        window.addEventListener('resize', () => requestAnimationFrame(positionNotif));
        window.addEventListener('scroll',  () => requestAnimationFrame(positionNotif), true);

        setInterval(fetchPanel, 15000);
    })();
    </script>

    {{-- SETTINGS POSITION SCRIPT --}}
    <script>
    (function(){
        const sBtn  = document.getElementById('settingsBtn');
        const sDrop = document.getElementById('settingsDrop');
        if(!sBtn || !sDrop) return;

        function isShown(el){
            if(!el) return false;
            return getComputedStyle(el).display !== 'none';
        }

        function placeUnderButton(anchorBtn, floatingEl, maxWidthPx){
            if(!anchorBtn || !floatingEl) return;
            if(!isShown(floatingEl)) return;

            floatingEl.style.position = 'fixed';
            floatingEl.style.right = 'auto';

            const mw = Math.min(maxWidthPx, window.innerWidth - 24);
            floatingEl.style.width = mw + 'px';
            floatingEl.style.maxWidth = mw + 'px';

            const r = anchorBtn.getBoundingClientRect();
            const rect = floatingEl.getBoundingClientRect();
            const w = rect.width || mw;
            const h = rect.height || 0;

            const margin = 12;
            const offset = 8;

            let left = r.right - w;
            left = Math.max(margin, Math.min(left, window.innerWidth - w - margin));

            let top = r.bottom + offset;

            const willCutBottom = (top + h) > (window.innerHeight - margin);
            if(willCutBottom){
                const upTop = r.top - offset - h;
                if(upTop >= margin) top = upTop;
            }

            top = Math.max(margin, Math.min(top, window.innerHeight - margin - Math.min(h, window.innerHeight)));

            floatingEl.style.left = Math.round(left) + 'px';
            floatingEl.style.top  = Math.round(top) + 'px';
        }

        function positionSettings(){
            if(!isShown(sDrop)) return;
            placeUnderButton(sBtn, sDrop, 320);
        }

        window.addEventListener('open-settings', () => {
            requestAnimationFrame(positionSettings);
        });

        window.addEventListener('resize', () => requestAnimationFrame(positionSettings));
        window.addEventListener('scroll',  () => requestAnimationFrame(positionSettings), true);
    })();
    </script>
    @endif
    @endauth

</body>
</html>
