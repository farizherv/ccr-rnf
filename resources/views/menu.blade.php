@extends('layout')

@section('content')

<div class="menu-wrapper">

    {{-- HEADER --}}
    <div class="header">
        <img src="/img/rnf_logo.png" class="logo">
        <div>
            <h1 class="title">CCR RNF System</h1>
            <p class="subtitle">Condition Component Report Management</p>
        </div>
    </div>

    {{-- MENU BUTTONS --}}
    <div class="menu-buttons">

        <h2 class="section-title">Pilih Jenis CCR</h2>

        <a href="{{ route('engine.create') }}" class="menu-card blue">
            <div class="icon">🔧</div>
            <div>
                <h3>CCR ENGINE</h3>
                <p>Buat laporan kerusakan dan kondisi komponen engine.</p>
            </div>
        </a>

        <a href="{{ route('seat.create') }}" class="menu-card green">
            <div class="icon">💺</div>
            <div>
                <h3>CCR OPERATOR SEAT</h3>
                <p>Buat laporan pemeriksaan dan kondisi kursi operator.</p>
            </div>
        </a>

        <a href="{{ route('ccr.index') }}" class="menu-card dark">
            <div class="icon">📂</div>
            <div>
                <h3>DAFTAR CCR</h3>
                <p>Lihat seluruh laporan CCR yang sudah dibuat.</p>
            </div>
        </a>

    </div>
</div>


{{-- STYLE --}}
<style>

.menu-wrapper {
    max-width: 650px;
    margin: auto;
    padding: 20px;
}

/* Header */
.header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
}

.logo {
    width: 60px;
    height: 60px;
    object-fit: contain;
}

.title {
    margin: 0;
    font-size: 26px;
    font-weight: 800;
}

.subtitle {
    margin-top: -5px;
    font-size: 14px;
    color: #666;
}

/* Cards */
.menu-buttons {
    margin-top: 20px;
}

.section-title {
    font-size: 18px;
    margin-bottom: 15px;
    font-weight: 700;
}

.menu-card {
    display: flex;
    gap: 15px;
    background: white;
    padding: 18px;
    border-radius: 14px;
    margin-bottom: 14px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.07);
    text-decoration: none;
    transition: 0.2s;
}

.menu-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 22px rgba(0,0,0,0.12);
}

.menu-card h3 {
    margin: 0;
    font-size: 17px;
    font-weight: 700;
}

.menu-card p {
    margin: 2px 0 0 0;
    font-size: 13px;
    color: #666;
}

.menu-card .icon {
    font-size: 30px;
}

/* Custom colors */
.menu-card.blue { border-left: 6px solid #0d6efd; }
.menu-card.green { border-left: 6px solid #198754; }
.menu-card.dark { border-left: 6px solid #343a40; }

/* MOBILE OPTIMIZATION */
@media(max-width: 600px) {
    .title { font-size: 22px; }
    .menu-card { padding: 16px; }
    .menu-card .icon { font-size: 26px; }
}

</style>

@endsection
