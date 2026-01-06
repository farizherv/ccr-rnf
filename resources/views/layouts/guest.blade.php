<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root{
            --rnf-red:#E40505;
            --rnf-black:#111827;
        }

        /* Background */
        .rnf-auth-bg{
            background: radial-gradient(1200px 600px at 50% 25%, #ffffff 0%, #f3f4f6 60%, #e9edf3 100%);
        }

        /* Wrapper */
        .rnf-auth-wrap{
            width: 100%;
        }

        /* Logo */
        .rnf-brand-logo{
            width: 140px;
            height: 140px;
            object-fit: contain;
            filter: drop-shadow(0 18px 30px rgba(0,0,0,.12));
        }

        /* Card utama (slot/login form) */
        .rnf-auth-card{
            background:#fff !important;
            border-radius: 18px;
            box-shadow: 0 18px 45px rgba(0,0,0,.12);
            border-bottom: 8px solid var(--rnf-red);
        }

        /* Footer card (sejajar card utama karena pakai sm:max-w-md yang sama) */
        .rnf-footer-card{
            margin-top: 14px;
            background:#fff;
            border-radius: 16px;
            padding: 12px 14px;
            box-shadow: 0 16px 40px rgba(0,0,0,.10);
            font-weight: 800;
            font-size: 12px;
            color:#6b7280;
            text-align:center;
        }

        /* ===== Mobile tuning (<= 640px) ===== */
        @media (max-width: 640px){
            /* jarak atas/bawah agar tidak “kepanjangan” */
            .rnf-auth-wrap{
                padding-top: 18px !important;
                padding-bottom: 18px !important;
            }

            /* logo lebih pas di mobile */
            .rnf-brand-logo{
                width: 125px !important;
                height: 125px !important;
                margin-bottom: 10px !important;
            }

            /* card login: full width + padding lebih kecil */
            .rnf-auth-card{
                width: calc(100vw - 28px) !important;
                max-width: 100% !important;
                padding: 18px !important;
                border-radius: 18px !important;
            }

            /* input lebih ramping */
            .rnf-auth-card input[type="text"],
            .rnf-auth-card input[type="password"]{
                height: 44px !important;
                border-radius: 12px !important;
            }

            /* action area: link + tombol jadi rapi */
            .rnf-login-actions{
                display: flex !important;
                flex-wrap: wrap !important;
                gap: 10px !important;
                justify-content: space-between !important;
                align-items: center !important;
            }

            /* tombol login jangan kepanjangan */
            .rnf-login-btn{
                min-width: 120px !important;
                padding: 10px 16px !important;
            }

            /* footer card: ikut full width dan rapi */
            .rnf-footer-card{
                width: calc(100vw - 28px) !important;
                max-width: 100% !important;
                padding: 12px 14px !important;
                border-radius: 16px !important;
                margin-top: 12px !important;
            }
        }
    </style>
</head>

<body class="font-sans text-gray-900 antialiased">
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-10 rnf-auth-bg">
        <div class="rnf-auth-wrap flex flex-col items-center">
            <!-- Logo -->
            <div class="flex flex-col items-center mb-4">
                <img src="{{ asset('rnf-logo.png') }}" alt="RNF Logo" class="rnf-brand-logo">
            </div>

            <!-- Card utama -->
            <div class="w-full sm:max-w-md rnf-auth-card px-6 py-6">
                {{ $slot }}
            </div>

            <!-- Footer card -->
            <div class="w-full sm:max-w-md rnf-footer-card">
                Copyright © {{ date('Y') }} PT Rezeki Nadh Fathan. All Rights Reserved
            </div>
        </div>
    </div>
</body>
</html>
