<!DOCTYPE html>
<html>
<head>
    <title>CCR RNF</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

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

        .btn-modern:hover {
            transform: translateY(-2px);
        }

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

        .thumb img {
            width:100%;
            height:100%;
            object-fit: cover;
        }

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

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 2px;
            color: #1f2933;
        }

        .toast-message {
            font-size: 13px;
            color: #4a5563;
        }

        .toast-close {
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 16px;
            line-height: 1;
            color: #9ca3af;
            padding: 0 2px;
        }

        .toast-close:hover {
            color: #4b5563;
        }

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

        @keyframes toast-progress {
            from { width: 100%; }
            to   { width: 0%; }
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
                <div class="toast-icon">
                    ✓
                </div>

                <div class="toast-content">
                    <div class="toast-title">
                        Berhasil disimpan
                    </div>
                    <div class="toast-message">
                        {{ session('success') }}
                    </div>
                    <div class="toast-progress">
                        <div class="toast-progress-bar"></div>
                    </div>
                </div>

                <button class="toast-close" @click="show = false">
                    ×
                </button>
            </div>
        </div>
    @endif

    {{-- PAGE CONTENT --}}
    @yield('content')

</body>
</html>
