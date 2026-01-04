<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Forgot your password? No problem. Just talk to admin and we will help you to reset username or password to a new one.') }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->

        <div class="flex items-center justify-end mt-4">

        </div>
    </form>

    <style>
        :root{ --rnf-red:#E40505; --rnf-black:#111827; }

        /* fokus input: biru -> merah */
        .rnf-input{
            border-radius: 12px !important;
        }
        .rnf-input:focus{
            border-color: var(--rnf-red) !important;
            box-shadow: 0 0 0 4px rgba(228,5,5,.14) !important;
            outline: none !important;
        }

        /* tombol (opsional) hitam -> hover merah, biar konsisten */
        .rnf-btn{
            background: var(--rnf-black) !important;
            border-color: var(--rnf-black) !important;
        }
        .rnf-btn:hover{
            background: var(--rnf-red) !important;
            border-color: var(--rnf-red) !important;
        }
    </style>
</x-guest-layout>
