@extends('layout')

@section('content')

<div data-page="user-create">
    <div class="top-toolbar">
        <a href="{{ route('admin.users.index') }}" class="btn-back">← Kembali ke User Management</a>
    </div>

    <div class="form-card">
        <div class="card-head">
            <h1 class="card-title">Tambah User</h1>
            <p class="card-subtitle">Buat akun baru untuk Admin / Director / Planner.</p>
        </div>

        @if ($errors->any())
            <div class="alert-error">
                <div class="alert-title">Periksa kembali input:</div>
                <ul class="alert-list">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('admin.users.store') }}" method="POST" class="form-body">
            @csrf

            <div class="form-grid">
                <div class="field">
                    <label class="label" for="nameInput">Nama</label>
                    <input
                        id="nameInput"
                        type="text"
                        name="name"
                        class="input"
                        value="{{ old('name') }}"
                        placeholder="Nama"
                        required
                    >
                </div>

                <div class="field">
                    <label class="label" for="roleInput">Role</label>
                    <select id="roleInput" name="role" class="input" required>
                        <option value="admin" {{ old('role')==='admin' ? 'selected' : '' }}>Admin</option>
                        <option value="director" {{ old('role')==='director' ? 'selected' : '' }}>Director</option>
                        <option value="operator" {{ old('role')==='operator' ? 'selected' : '' }}>Planner</option>
                    </select>
                    <div class="hint">Catatan: "Planner" disimpan sebagai role <b>operator</b> di sistem.</div>
                </div>

                <div class="field field-span-2">
                    <label class="label" for="usernameInput">Username</label>
                    <input
                        id="usernameInput"
                        type="text"
                        name="username"
                        class="input"
                        value="{{ old('username') }}"
                        placeholder="username"
                        required
                    >
                    <div class="hint">Catatan: jangan pakai username yang sama.</div>
                </div>

                <div class="field field-span-2">
                    <label class="label" for="passwordInput">Password</label>
                    <input
                        id="passwordInput"
                        type="text"
                        name="password"
                        class="input"
                        value="{{ old('password') }}"
                        placeholder="password"
                        required
                    >
                    <div class="hint">Min. 8 karakter, wajib ada huruf besar (A-Z) dan angka (0-9).</div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save">Simpan</button>
            </div>
        </form>
    </div>
</div>

<style>
[data-page="user-create"] *,
[data-page="user-create"] *::before,
[data-page="user-create"] *::after{
    box-sizing:border-box;
}

[data-page="user-create"] .top-toolbar{
    display:flex;
    align-items:center;
    gap:12px;
    margin-bottom:18px;
}

[data-page="user-create"] .btn-back{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    color:#fff;
    padding:10px 18px;
    border-radius:12px;
    background:#5f656a;
    font-weight:900;
    font-size:14px;
    text-decoration:none;
    transition:.18s;
    box-shadow:0 8px 16px rgba(0,0,0,.12);
}

[data-page="user-create"] .btn-back:hover{
    background:#2f3336;
    transform:translateY(-1px);
}

[data-page="user-create"] .form-card{
    background:#f8fbff;
    border:1px solid #dbe5f3;
    border-radius:18px;
    padding:20px;
    box-shadow:0 10px 28px rgba(15,23,42,.05);
}

[data-page="user-create"] .card-head{
    margin-bottom:14px;
    padding-bottom:12px;
    border-bottom:1px solid #e2e8f0;
}

[data-page="user-create"] .card-title{
    margin:0;
    font-size:28px;
    line-height:1.15;
    font-weight:1000;
    color:#0f1b3a;
}

[data-page="user-create"] .card-subtitle{
    margin:8px 0 0;
    color:#5f6e8a;
    font-size:15px;
    font-weight:700;
}

[data-page="user-create"] .alert-error{
    background:rgba(228,5,5,.08);
    border:1px solid rgba(228,5,5,.22);
    color:#7a1010;
    border-radius:12px;
    padding:12px 14px;
    margin-bottom:14px;
}

[data-page="user-create"] .alert-title{
    font-weight:900;
    margin-bottom:6px;
    font-size:14px;
}

[data-page="user-create"] .alert-list{
    margin:0;
    padding-left:18px;
    font-weight:700;
}

[data-page="user-create"] .form-body{
    width:100%;
}

[data-page="user-create"] .form-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
}

[data-page="user-create"] .field{
    display:flex;
    flex-direction:column;
    gap:7px;
}

[data-page="user-create"] .field-span-2{
    grid-column:1 / -1;
}

[data-page="user-create"] .label{
    font-size:16px;
    font-weight:900;
    color:#111827;
}

[data-page="user-create"] .input{
    width:100%;
    min-height:50px;
    padding:10px 14px;
    border-radius:12px;
    border:1px solid #cfd9e8;
    background:#fff;
    font-size:16px;
    outline:none;
    color:#0f172a;
    transition:border-color .15s ease, box-shadow .15s ease;
}

[data-page="user-create"] .input:focus{
    border-color:#2f65d8;
    box-shadow:0 0 0 3px rgba(47,101,216,.15);
}

[data-page="user-create"] .hint{
    font-size:13px;
    color:#6b7280;
    font-weight:700;
    line-height:1.35;
}

[data-page="user-create"] .form-actions{
    margin-top:16px;
    display:flex;
    justify-content:flex-end;
}

[data-page="user-create"] .btn-save{
    border:0;
    cursor:pointer;
    min-width:170px;
    height:44px;
    border-radius:12px;
    padding:0 18px;
    font-size:16px;
    font-weight:900;
    color:#fff;
    background:#1f6fe5;
    box-shadow:0 10px 20px rgba(31,111,229,.24);
    transition:.18s;
}

[data-page="user-create"] .btn-save:hover{
    transform:translateY(-1px);
    filter:brightness(.98);
}

@media (max-width:960px){
    [data-page="user-create"] .form-grid{
        grid-template-columns:1fr;
    }

    [data-page="user-create"] .field-span-2{
        grid-column:auto;
    }
}

@media (max-width:700px){
    [data-page="user-create"] .card-title{
        font-size:24px;
    }

    [data-page="user-create"] .form-actions{
        justify-content:stretch;
    }

    [data-page="user-create"] .btn-save{
        width:100%;
        min-width:0;
    }
}
</style>

@endsection
