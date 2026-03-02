<!DOCTYPE html>
<html>
<head>
    <title>CCR RNF</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="CCR RNF">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">

        @auth
    <script>
    window.CCR_AUTH_ID = @json(auth()->id());
    </script>
    @else
    <script>
    window.CCR_AUTH_ID = 0;
    </script>
    @endauth

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

        /* Anti-FOUC: body hidden until styles parsed */
        body.fouc-guard {
            opacity: 0 !important;
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
        .topbar .btn-modern.btn-monitoring{
            background:#ffffff !important;
            color:#0f1b3a !important;
            border:2px solid #bccce2 !important;
            box-shadow: 0 3px 8px rgba(15, 27, 58, .08);
        }
        .topbar .btn-modern.btn-primary{
            background:#ffffff !important;
            color:#0f1b3a !important;
            border:2px solid #bccce2 !important;
            box-shadow: 0 3px 8px rgba(15, 27, 58, .08);
        }
        .topbar .btn-modern.btn-monitoring:hover,
        .topbar .btn-modern.btn-primary:hover{
            background:#ffffff !important;
            color:#0f1b3a !important;
            border-color:#f2aaaa !important;
            box-shadow: 0 5px 12px rgba(228, 5, 5, .14) !important;
            transform: translateY(-1px);
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
            text-align:center;
            border:none;
            background:#0b1733;
            color:#fff;
            padding:12px 14px;
            cursor:pointer;
            font-weight:900;
            border-radius:0;
            transition:background .18s ease, color .18s ease;
        }
        .drop-item:hover {
            background:#E40505;
            color:#fff;
        }

        /* =========================================================
           INBOX DROPDOWN PANEL
           ========================================================= */
        .notif-wrap{ position:relative; }

        .notif-panel{
            position:absolute;
            right:0;
            top:52px;
            width: min(760px, calc(100vw - 24px));
            background:#fff;
            border-radius:24px;
            box-shadow:0 22px 55px rgba(15,23,42,.18);
            border:1px solid #dbe5f3;
            overflow:hidden;
            z-index:10000;
        }

        .notif-head{
            display:flex;
            align-items:center;
            justify-content:flex-start;
            gap:12px;
            padding:18px 22px;
            border-bottom:1px solid #eef2f7;
            background:#fff;
        }

        .notif-title{
            font-weight:1000;
            color:#111827;
            font-size:16px;
            letter-spacing:.2px;
            line-height:1;
        }

        .notif-body{
            max-height: min(520px, calc(100vh - 180px));
            overflow:auto;
            -webkit-overflow-scrolling: touch;
        }

        .notif-empty{
            padding:14px 18px;
            color:#6b7280;
            font-weight:800;
            font-size:14px;
        }

        .notif-item{
            display:flex;
            gap:12px;
            align-items:flex-start;
            padding:14px 16px;
            border-bottom:1px solid #e6edf6;
            justify-content:space-between;
        }

        .notif-item.unread{ background:#f8fbff; }
        .notif-item.read{ background:#fff; opacity:.94; }

        .notif-dot{
            width:14px; height:14px; border-radius:999px;
            background:#cbd5e1;
            margin-top:8px;
            flex:0 0 auto;
        }
        .notif-item.unread .notif-dot{ background:#2563eb; }

        .notif-main{ flex:1; min-width:0; }

        .notif-h{
            font-weight:1000;
            font-size:17px;
            line-height:1.2;
            color:#0f172a;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
        }

        .notif-m{
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
            color:#6b7280;
            font-weight:700;
            font-size:12px;
            text-align:right;
        }

        .notif-open{
            color:#0D6EFD;
            font-weight:1000;
            text-decoration:none;
            padding:6px 10px;
            border-radius:10px;
            font-size:13px;
        }
        .notif-open:hover{ background:#eef6ff; }

        .notif-panel .notif-pill{
            display:inline-flex;
            align-items:center;
            padding:8px 14px;
            border-radius:999px;
            font-weight:900;
            font-size:13px;
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

        /* ✅ UPDATED FOOTER: kanan bawah + View all + Clear all */
        .notif-foot{
            padding:14px 18px;
            border-top:1px solid #eef2f7;
            background:#fff;
        }
        .notif-foot-row{
            display:flex;
            align-items:center;
            justify-content:space-between; /* ✅ View all kiri, Clear all kanan */
            gap:10px;
        }
        .notif-clearform{ margin:0; }

        /* View all: default sama seperti Clear all, hover soft blue */
        /* base tombol footer: bikin ukuran & border konsisten */
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

        /* Clear all: hover soft red */
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
            .notif-side{
                min-width:0;
                align-items:flex-start;
                gap:6px;
            }
            .notif-meta{ text-align:left; }
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

<body class="fouc-guard">
<script>document.body.classList.remove('fouc-guard');</script>

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
            $hasInboxClearAll = \Illuminate\Support\Facades\Route::has('inbox.clearAll');
        @endphp

        <div class="box topbar">
            {{-- LEFT BUTTONS --}}
            @php
                $roleRaw = auth()->user()->role;
                $role = $roleRaw instanceof \App\Enums\UserRole ? $roleRaw->value : strtolower(trim((string) $roleRaw));
            @endphp

            <div class="top-left">
                @if($role === 'director')
                    <a href="{{ route('director.monitoring') }}" class="btn-modern btn-monitoring">Monitoring</a>
                @endif

                @if(in_array($role, ['admin','director'], true))
                    <a href="{{ route('admin.users.index') }}" class="btn-modern btn-primary">User Management</a>
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
                                @php
                                    $displayRole = $role === 'operator' ? 'PLANNER' : strtoupper($role);
                                @endphp
                                {{ $displayRole }}
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
        const pendingReadIds = new Set();

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
            const plain = String(raw ?? '').trim();
            if (!plain) return `<div class="notif-msg-note">-</div>`;

            const parsed = plain.match(/^\s*(Approved|Rejected)\s+oleh\s+(.+?)\s*(?:\.?\s*Catatan:\s*(.*))?\s*$/iu);
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

            const esc = escapeHtml(normalized)
                .replace(/\bApproved\b/gi, '<span class="notif-pill notif-pill-approved">Approved</span>')
                .replace(/\bRejected\b/gi, '<span class="notif-pill notif-pill-rejected">Rejected</span>');
            return `<div class="notif-msg-note">${esc}</div>`;
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
            placeUnderButton(btn, panel, 760);
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
                            </div>
                            <div class="notif-side">
                                <span class="notif-meta">${escapeHtml(n.created_at)}</span>
                                ${link ? `<a class="notif-open" data-open-id="${n.id}" href="${escapeHtml(link)}">Open</a>` : ``}
                            </div>
                        </div>
                    `;
                }).join('');

                requestAnimationFrame(positionNotif);
            } catch(e) {}
        }

        async function markRead(id){
            if (!id) return;
            if (pendingReadIds.has(id)) return;
            pendingReadIds.add(id);
            const row = body ? body.querySelector(`.notif-item[data-id="${id}"]`) : null;
            const wasUnread = row ? row.classList.contains('unread') : false;
            if (row && wasUnread) {
                row.classList.remove('unread');
                row.classList.add('read');
                const dot = row.querySelector('.notif-dot');
                if (dot) dot.style.background = '#cbd5e1';
                if (badge) {
                    const cur = Number(badge.textContent || 0);
                    const next = Math.max(0, cur - 1);
                    badge.textContent = String(next);
                    badge.style.display = next > 0 ? 'flex' : 'none';
                }
            }
            try{
                await fetch("{{ url('/inbox') }}/" + id + "/read-json", {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": token,
                        "X-Requested-With": "XMLHttpRequest"
                    },
                    keepalive: true
                });
            } catch(e) {
            } finally {
                pendingReadIds.delete(id);
            }
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
                const openLink = e.target.closest('a.notif-open[data-open-id]');
                if (!openLink) return;
                const id = Number(openLink.getAttribute('data-open-id') || 0);
                if (id) markRead(id);
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


    {{-- PWA: Register service worker for install + offline support --}}
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js', { scope: '/' })
            .catch(function() {});
    }
    </script>

    @if((bool) config('ccr_notifications.web_push_enabled', false))
    <script>
    (function () {
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            return;
        }

        const publicKey = @json((string) config('ccr_notifications.web_push_public_key', ''));
        if (!publicKey) {
            return;
        }

        const subscribeUrl = @json(route('notifications.webpush.subscribe'));
        const unsubscribeUrl = @json(route('notifications.webpush.unsubscribe'));
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const promptKey = 'ccr:webpush:prompted_at:v1';
        const promptIntervalMs = 14 * 24 * 60 * 60 * 1000;

        function b64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const raw = window.atob(base64);
            const out = new Uint8Array(raw.length);
            for (let i = 0; i < raw.length; ++i) out[i] = raw.charCodeAt(i);
            return out;
        }

        async function postJson(url, payload) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload || {}),
            });
        }

        async function subscribe(registration) {
            const existing = await registration.pushManager.getSubscription();
            const subscription = existing || await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: b64ToUint8Array(publicKey),
            });

            await postJson(subscribeUrl, {
                subscription: subscription.toJSON(),
                user_agent: navigator.userAgent || '',
            });
        }

        async function run() {
            try {
                const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });

                if (Notification.permission === 'granted') {
                    await subscribe(registration);
                    return;
                }

                if (Notification.permission === 'denied') {
                    return;
                }

                const now = Date.now();
                const promptedAt = Number(localStorage.getItem(promptKey) || 0);
                if (promptedAt > 0 && (now - promptedAt) < promptIntervalMs) {
                    return;
                }

                localStorage.setItem(promptKey, String(now));
                const permission = await Notification.requestPermission();
                if (permission === 'granted') {
                    await subscribe(registration);
                    return;
                }

                if (permission === 'denied') {
                    const oldSub = await registration.pushManager.getSubscription();
                    if (oldSub) {
                        await postJson(unsubscribeUrl, { endpoint: oldSub.endpoint || '' });
                    }
                }
            } catch (error) {
                console.debug('Web push registration skipped:', error);
            }
        }

        if (document.readyState === 'complete') {
            setTimeout(run, 1200);
        } else {
            window.addEventListener('load', () => setTimeout(run, 1200), { once: true });
        }
    })();
    </script>
    @endif
    @endif
    @endauth

</body>
</html>
