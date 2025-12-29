@extends('layout')

@section('content')

<a href="{{ route('ccr.manage.menu') }}" class="btn-back">← Kembali</a>

{{-- ======================= HEADER ======================= --}}
<div class="header-card">
    <div class="header-left">
        <img src="{{ asset('rnf-logo.png') }}" class="header-logo" alt="RNF Logo">

        <div>
            <h1 class="header-title">TRASH & RESTORE</h1>
            <p class="header-subtitle">Lihat data yang dihapus, restore, atau hapus permanen.</p>
        </div>
    </div>
</div>

{{-- ACCENT LINE (khusus halaman trash: hitam) --}}
<div class="accent-line-dark"></div>

{{-- ======================= MENU TRASH ======================= --}}
<div class="menu-container">

    {{-- ENGINE --}}
    <a href="{{ route('trash.engine.index') }}" class="menu-card trash-engine">
        <div class="menu-icon">🛠️</div>
        <div class="menu-text">
            <h2>ENGINE</h2>
            <p>Data CCR Engine yang ada di sampah.</p>
        </div>
    </a>

    {{-- SEAT --}}
    <a href="{{ route('trash.seat.index') }}" class="menu-card trash-seat">
        <div class="menu-icon">💺</div>
        <div class="menu-text">
            <h2>OPERATOR SEAT</h2>
            <p>Data CCR Seat yang ada di sampah.</p>
        </div>
    </a>

</div>

<style>
/* ================= BUTTON BACK ================= */
.btn-back{
    display:inline-block;color:white;padding:8px 18px;border-radius:8px;
    background:#5f656a;font-weight:600;font-size:14px;text-decoration:none;
    transition:.2s;box-shadow:0 3px 7px rgba(0,0,0,.15);margin-bottom:18px
}
.btn-back:hover{background:#2b2d2f;transform:translateY(-2px)}

/* ================= HEADER ================= */
.header-card{
    background:#fff;padding:22px;border-radius:14px;margin-bottom:18px;
    box-shadow:0 3px 10px rgba(0,0,0,.07)
}
.header-left{display:flex;align-items:center;gap:18px}
.header-logo{width:80px;height:80px;object-fit:contain}
.header-title{font-size:22px;font-weight:900;margin:0;letter-spacing:.2px}
.header-subtitle{font-size:14px;color:#555;margin-top:4px}

/* ================= ACCENT LINE (DARK - TRASH ONLY) ================= */
.accent-line-dark{
    height:4px;background:#2b2d2f;border-radius:20px;margin-bottom:18px
}

/* ================= MENU CARDS ================= */
.menu-container{
    display:flex;flex-direction:column;gap:18px
}
.menu-card{
    display:flex;gap:18px;align-items:center;
    background:#fff;padding:22px;border-radius:16px;text-decoration:none;color:#000;
    box-shadow:0 3px 10px rgba(0,0,0,.07);
    transition:.2s;
}
.menu-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.12)}

.menu-icon{font-size:44px;min-width:52px}
.menu-text h2{margin:0;font-size:22px;font-weight:900;letter-spacing:.2px}
.menu-text p{margin-top:6px;color:#444;font-size:14px}

/* border-left warna per kategori */
.trash-engine{border-left:6px solid #2b2d2f;}
.trash-seat{border-left:6px solid #2b2d2f;}
</style>

@endsection
