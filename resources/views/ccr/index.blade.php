@extends('layout')

@section('content')

{{-- ======================= HEADER ======================= --}}
<div class="header-card">
    <div class="header-left">

        {{-- LOGO FIX PROPORSIONAL --}}
        <div class="logo-wrapper">
            <img src="{{ asset('rnf-logo.png') }}" class="header-logo" width="110" height="110" alt="RNF Logo">
        </div>

        <div>
            <h1 class="header-title">COMPONENT CONDITION REPORT SYSTEM</h1>
            <p class="header-subtitle">PT. Rezeki Nadah Fathan</p>
        </div>

    </div>
</div>

<div class="accent-line"></div>

{{-- ======================= MENU UTAMA (ENGINE, SEAT, EDIT) ======================= --}}
<div class="menu-container">

    {{-- ENGINE --}}
    <a href="{{ route('engine.create') }}" class="menu-card menu-engine">
        <div class="menu-icon">🔧</div>
        <h2>CCR ENGINE</h2>
        <p>Buat laporan CCR Engine secara lengkap.</p>
    </a>

    {{-- OPERATOR SEAT --}}
    <a href="{{ route('seat.create') }}" class="menu-card menu-seat">
        <div class="menu-icon">💺</div>
        <h2>CCR OPERATOR SEAT</h2>
        <p>Buat laporan CCR khusus operator seat.</p>
    </a>

    {{-- EDIT CCR --}}
    <a href="{{ route('ccr.manage.menu') }}" class="menu-card menu-edit">
        <div class="menu-icon">📝</div>
        <h2>EDIT CCR</h2>
        <p>Edit laporan CCR Engine & Operator Seat.</p>
    </a>

</div>



{{-- ======================= STYLE FINAL (LOGO FIX + UI RAPIH) ======================= --}}
<style>

    /* HEADER */
    .header-card {
        background: white;
        padding: 22px;
        border-radius: 14px;
        margin-bottom: 20px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.07);
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 18px;
    }

    .logo-wrapper {
        padding: 0;
    }

    /* LOGO FINAL — tidak gepeng + proporsional */
    .header-logo {
        width: 115px;
        height: auto;
        object-fit: contain;
        display: block;
    }

    .header-title {
        font-size: 20px;
        font-weight: 800;
        margin: 0;
    }

    .header-subtitle {
        margin: 0;
        margin-top: 4px;
        font-size: 14px;
        color: #555;
    }

    .accent-line {
        width: 100%;
        height: 4px;
        background: #E40505;
        border-radius: 20px;
        margin-bottom: 22px;
    }


    /* ======================= MENU CARDS BASE ======================= */
    .menu-container {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
    }

    .menu-card {
        width: 48%;
        background: white;
        padding: 25px;
        border-radius: 16px;
        text-decoration: none;
        color: black;
        box-shadow: 0 3px 10px rgba(0,0,0,0.07);
        transition: .25s ease;
    }

    .menu-icon {
        font-size: 40px;
        margin-bottom: 10px;
    }

    .menu-card h2 {
        font-size: 20px;
        margin: 0 0 6px;
        font-weight: 700;
    }

    .menu-card p {
        color: #555;
        font-size: 14px;
        margin: 0;
    }


    /* ======================= BORDER + SHADOW PER CARD ======================= */

    /* ENGINE → RED */
    .menu-engine {
        border-left: 6px solid #E40505;
    }
    .menu-engine:hover {
        transform: translateY(-4px);
        box-shadow: 0 6px 18px rgba(228,5,5,0.15);
    }

    /* SEAT → RED */
    .menu-seat {
        border-left: 6px solid #E40505;
    }
    .menu-seat:hover {
        transform: translateY(-4px);
        box-shadow: 0 6px 18px rgba(228,5,5,0.15);
    }

    /* EDIT → BLUE */
    .menu-edit {
        border-left: 6px solid #0D6EFD;
    }
    .menu-edit:hover {
        transform: translateY(-4px);
        box-shadow: 0 6px 18px rgba(13,110,253,0.20);
    }


    /* RESPONSIVE */
    @media(max-width:700px){
        .menu-card { width: 100%; }
        .header-logo { 
            width: 90px; 
            height: auto; 
        }
        .header-title { font-size: 18px; }
    }

</style>

@endsection
