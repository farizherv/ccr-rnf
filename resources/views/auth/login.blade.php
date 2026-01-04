{{-- resources/views/auth/login.blade.php --}}

<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    {{-- Judul di atas Username --}}
    <div class="rnf-login-title">
        COMPONENT CONDITION REPORT SYSTEM
    </div>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        {{-- Username --}}
        <div>
            <x-input-label for="username" :value="__('Username')" />
            <x-text-input id="username"
                          class="block mt-1 w-full rnf-input"
                          type="text"
                          name="username"
                          :value="old('username')"
                          required autofocus />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        {{-- Password --}}
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password"
                          class="block mt-1 w-full rnf-input"
                          type="password"
                          name="password"
                          required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        {{-- Remember Me (merah #E40505) --}}
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me"
                       type="checkbox"
                       class="rounded border-gray-300 shadow-sm rnf-check"
                       name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-6 gap-4 rnf-login-actions">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 rnf-link"
                   href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            {{-- tombol hitam hover merah --}}
            <x-primary-button class="rnf-btn">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>

    <style>
        :root{
            --rnf-red:#E40505;
            --rnf-black:#111827;
        }

        .rnf-login-title{
            text-align:center;
            font-weight: 900;
            letter-spacing: .4px;
            text-transform: uppercase;
            color: #0f172a; /* lebih gelap */
            font-size: 16px;
            margin-bottom: 22px; /* jarak lebih jauh ke field Username */
        }

        /* input: hilangkan biru -> merah */
        .rnf-input{
            border-radius: 12px !important;
        }
        .rnf-input:focus{
            border-color: var(--rnf-red) !important;
            box-shadow: 0 0 0 4px rgba(228,5,5,.14) !important;
        }

        /* checkbox RNF (fix: pastikan tidak balik jadi biru) */
        .rnf-check{
            accent-color: var(--rnf-red) !important; /* browser modern */
            color: var(--rnf-red) !important;        /* penting untuk Tailwind forms (currentColor) */
        }
        .rnf-check:checked{
            background-color: var(--rnf-red) !important;
            border-color: var(--rnf-red) !important;
        }
        .rnf-check:focus{
            border-color: var(--rnf-red) !important;
            box-shadow: 0 0 0 4px rgba(228,5,5,.18) !important;
        }

        .rnf-link:hover{
            color: var(--rnf-red) !important;
        }

        /* tombol hitam hover merah (override component) */
        .rnf-btn{
            background: var(--rnf-black) !important;
            border-color: var(--rnf-black) !important;
        }
        .rnf-btn:hover{
            background: var(--rnf-red) !important;
            border-color: var(--rnf-red) !important;
            transform: translateY(-1px);
        }

        @media (max-width: 640px){
            .rnf-login-title{
                font-size: 14px;
                margin-bottom: 12px;
            }
        }
    </style>
</x-guest-layout>
