@extends('layout')

@section('content')

{{-- ======================= BACK BUTTON (DI LUAR HEADER CARD) ======================= --}}
<a href="{{ route('ccr.index') }}" class="btn-back">← Kembali</a>


{{-- ======================= HEADER EDIT (MATCH WITH MAIN STYLE) ======================= --}}
<div class="header-card edit-header">

    <div class="header-content">

        {{-- LOGO SAMA DENGAN HALAMAN UTAMA (115px + proporsional) --}}
        <div class="logo-wrapper">
            <img src="{{ asset('rnf-logo.png') }}" class="header-logo" width="110" height="110" alt="RNF Logo">
        </div>

        <div class="header-text">
            <h1 class="header-title">EDIT COMPONENT CONDITION REPORT</h1>
            <p class="header-subtitle">Pilih kategori laporan CCR yang ingin diedit.</p>
        </div>

    </div>

</div>

{{-- ACCENT LINE --}}
<div class="accent-line-blue"></div>


{{-- ======================= EDIT MENU ======================= --}}
<div class="menu-container">

    <a href="{{ route('ccr.manage.engine') }}" class="menu-card edit-card">
        <div class="menu-icon">🛠️</div>
        <h2>EDIT CCR ENGINE</h2>
        <p>Lihat & ubah semua data laporan CCR Engine.</p>
    </a>

    <a href="{{ route('ccr.manage.seat') }}" class="menu-card edit-card">
        <div class="menu-icon">💺</div>
        <h2>EDIT CCR OPERATOR SEAT</h2>
        <p>Lihat & ubah semua data laporan CCR Operator Seat.</p>
    </a>

    {{-- ✅ TRASH harus ikut container ini --}}
    <a href="{{ route('trash.menu') }}" class="menu-card edit-card trash-card">
        <div class="menu-icon">🗑️</div>
        <h2>TRASH & RESTORE</h2>
        <p>Lihat data yang dihapus, restore, atau hapus permanen.</p>
    </a>

</div>


{{-- ======================= STYLE FINAL (MATCH WITH MAIN PAGE) ======================= --}}
<style>

    /* ================= BUTTON BACK ================= */
    .btn-back {
        display: inline-block;
        color: white;
        padding: 8px 18px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        text-decoration: none;
        background: #5f656aff;
        transition: .2s;
        margin-bottom: 18px;
        box-shadow: 0 3px 7px rgba(0,0,0,0.15);
    }

    .btn-back:hover {
        background: #2b2d2fff;
        transform: translateY(-2px);
    }


    /* ================= HEADER CARD ================= */
    .header-card {
        background: #ffffff;
        padding: 25px;
        border-radius: 18px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.09);
        margin-bottom: 25px;
    }

    .header-content {
        display: flex;
        align-items: center;
        gap: 18px;
    }

    /* REMOVE BORDER LEFT (SESUAI PERMINTAAN) */
    .logo-wrapper {
        padding-left: 0;
        border-left: none;
    }

    /* LOGO SAMA DENGAN TAMPILAN UTAMA (115px) */
    .header-logo {
        width: 115px;
        height: auto;
        object-fit: contain;
        display: block;
    }

    .header-title {
        font-size: 26px;
        font-weight: 800;
        margin: 0;
        letter-spacing: .2px;
    }

    .header-subtitle {
        font-size: 16px;
        color: #666;
        margin-top: 4px;
    }


    /* ================= ACCENT LINE ================= */
    .accent-line-blue {
        width: 100%;
        height: 4px;
        background: #0D6EFD;
        border-radius: 20px;
        margin-bottom: 20px;
    }


    /* ================= MENU CARDS ================= */
    .menu-container {
        display: flex;
        gap: 22px;
        flex-wrap: wrap;
    }

    .menu-card {
        width: 48%;
        background: white;
        padding: 25px;
        border-radius: 16px;
        text-decoration: none;
        color: black;
        box-shadow: 0 3px 10px rgba(0,0,0,0.06);
        transition: .25s ease;
    }

    .edit-card {
        border-left: 6px solid #0D6EFD;
    }

    /* khusus card trash di edit-menu */
    .trash-card{
        border-left: 6px solid #2b2d2f !important; /* hitam gelap */
    }

    .trash-card:hover{
        box-shadow: 0 6px 20px rgba(0,0,0,0.18);
    }

    .edit-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 6px 20px rgba(13,110,253,0.25);
    }

    .menu-icon {
        font-size: 45px;
        margin-bottom: 12px;
    }

    .menu-card h2 {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
    }

    .menu-card p {
        margin-top: 6px;
        color: #445;
        font-size: 14px;
    }


    /* ================= RESPONSIVE ================= */
    @media(max-width: 700px) {
        .menu-card { width: 100%; }
        .header-logo { width: 90px; }
        .header-title { font-size: 20px; }
    }

</style>

@endsection
