@extends('layout')

@section('content')

<a href="{{ route('ccr.index') }}" class="btn-back">← Kembali</a>

{{-- ======================= HEADER ======================= --}}
<div class="header-card">
    <div class="header-left">
        <img src="{{ asset('rnf-logo.png') }}" class="header-logo" width="110" height="110" alt="RNF Logo">

        <div>
            <h1 class="header-title">TRASH & RESTORE</h1>
            <p class="header-subtitle">Lihat data yang dihapus, restore, atau hapus permanen.</p>
        </div>
    </div>
</div>

{{-- ACCENT LINE --}}
<div class="accent-line"></div>

{{-- ======================= MENU TRASH ======================= --}}
<div class="trash-menu-grid">

    {{-- ENGINE --}}
    <a href="{{ route('trash.engine.index') }}" class="trash-menu-card">
        <span class="trash-menu-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"></path>
                <path d="m13 6 6 6-6 6"></path>
            </svg>
        </span>
        <div class="trash-menu-icon">🛠️</div>
        <h2>ENGINE</h2>
        <p>Data CCR Engine yang ada di sampah.</p>
    </a>

    {{-- SEAT --}}
    <a href="{{ route('trash.seat.index') }}" class="trash-menu-card">
        <span class="trash-menu-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"></path>
                <path d="m13 6 6 6-6 6"></path>
            </svg>
        </span>
        <div class="trash-menu-icon">💺</div>
        <h2>OPERATOR SEAT</h2>
        <p>Data CCR Seat yang ada di sampah.</p>
    </a>

</div>

<style>
/* scope khusus halaman trash menu */
.trash-menu-grid,
.trash-menu-grid * ,
.header-card,
.header-card * {
    box-sizing: border-box;
}

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
.header-logo{width:115px;height:auto;object-fit:contain}
.header-title{font-size:22px;font-weight:900;margin:0;letter-spacing:.2px}
.header-subtitle{font-size:14px;color:#555;margin-top:4px}

/* ================= ACCENT LINE ================= */
.accent-line{
    height:4px;background:#E40505;border-radius:20px;margin-bottom:18px
}

/* ================= MENU CARDS ================= */
.trash-menu-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:22px;
    align-items:start;
}

.trash-menu-card{
    position:relative;
    display:flex;
    flex-direction:column;
    justify-content:flex-start;
    width:auto;
    max-width:100%;
    min-height:170px;
    padding:22px 58px 22px 22px;
    border-radius:18px;
    text-decoration:none;
    color:#0f172a;
    border:1px solid #d9e1ee;
    background:linear-gradient(180deg,#ffffff 0%,#fbfcff 100%);
    box-shadow:0 8px 18px rgba(15,23,42,.06);
    transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease,background-color .2s ease;
    overflow:hidden;
}

.trash-menu-card:hover{
    transform:translateY(-2px);
    border-color:rgba(228,5,5,.30);
    box-shadow:0 12px 24px rgba(15,23,42,.12);
    background:#fff;
}

.trash-menu-icon{
    width:56px;
    height:56px;
    display:grid;
    place-items:center;
    border-radius:14px;
    border:1px solid #d8e5ff;
    background:#eef4ff;
    font-size:31px;
    margin-bottom:14px;
    flex:0 0 auto;
}

.trash-menu-card h2{
    margin:0 0 8px;
    font-size:20px;
    font-size:clamp(18px,1.2vw,22px);
    font-weight:800;
    letter-spacing:.2px;
    line-height:1.2;
    color:#060b18;
    max-width:calc(100% - 30px);
}

.trash-menu-card p{
    margin:0;
    color:#4b5563;
    font-size:14px;
    font-size:clamp(13px,.85vw,15px);
    line-height:1.45;
    max-width:calc(100% - 30px);
}

.trash-menu-arrow{
    position:absolute;
    right:16px;
    top:50%;
    width:34px;
    height:34px;
    border-radius:999px;
    border:1px solid #d8e2f1;
    background:#f8fafd;
    color:#8d9ab0;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    opacity:0;
    transform:translate(6px,-50%);
    transition:opacity .2s ease,transform .2s ease,border-color .2s ease,color .2s ease,background-color .2s ease;
}

.trash-menu-arrow svg{
    width:16px;
    height:16px;
}

.trash-menu-card:hover .trash-menu-arrow{
    opacity:1;
    transform:translate(0,-50%);
    color:#111827;
    border-color:rgba(228,5,5,.32);
    background:#fff;
}

@media(max-width:980px){
    .trash-menu-grid{
        grid-template-columns:1fr;
        gap:18px;
    }
    .trash-menu-card{
        min-height:0;
        padding:20px 52px 20px 20px;
    }
    .trash-menu-icon{
        width:50px;
        height:50px;
        font-size:28px;
    }
    .trash-menu-card h2{
        font-size:18px;
        max-width:100%;
    }
    .trash-menu-card p{
        font-size:13px;
        max-width:100%;
    }
    .header-left{
        flex-direction:column;
        align-items:flex-start;
        gap:12px;
    }
    .header-logo{
        width:90px;
        height:auto;
    }
    .header-title{font-size:18px}
    .header-subtitle{font-size:13px}
}

@media (hover:none), (max-width:980px){
    .trash-menu-card{
        padding-right:20px;
    }
    .trash-menu-arrow{
        display:none;
    }
}
</style>

@endsection
