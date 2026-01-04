@extends('layout')

@section('content')

<style>
    body {
        background: #f4f4f4;
        font-family: Calibri, sans-serif;
        overflow-x: hidden;
    }

    /* ===================================================================
       🔵 TOP HEADER PREVIEW (Back Button + Logo + Title)
       =================================================================== */

    .btn-back{
        display:inline-block;
        color:white;
        padding:8px 18px;
        border-radius:8px;
        background:#5f656a;
        font-weight:600;
        font-size:14px;
        text-decoration:none;
        transition:.2s;
        box-shadow:0 3px 7px rgba(0,0,0,.15);
        margin-bottom:20px;
    }
    .btn-back:hover{
        background:#2b2d2f;
        transform:translateY(-2px);
    }

    .preview-top {
        max-width: 1100px;
        margin: 25px auto 20px auto;
        padding: 0 15px;
    }

    .preview-header-box {
        background: white;
        padding: 25px 30px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        gap: 18px;
        box-shadow: 0 4px 14px rgba(0,0,0,0.10);
    }

    .preview-header-box img {
        width: 74px;
    }

    .preview-title {
        font-size: 32px;
        font-weight: 700;
    }

    .preview-subtitle {
        font-size: 16px;
        margin-top: -4px;
        color: #444;
    }

    .preview-accent {
        width: 100%;
        height: 6px;
        background: #2E8B57;
        border-radius: 10px;
        margin: 20px auto 30px auto;
    }

    @media(max-width:900px){
        .preview-header-box img { width: 62px; }
        .preview-title { font-size: 27px; }
    }

    @media(max-width:600px){
        .preview-top { margin-top: 10px; padding: 0 10px; }

        .preview-header-box {
            flex-direction: column;
            text-align: center;
            gap: 10px;
            padding: 18px 12px;
        }
        .preview-header-box img { width: 58px; }
        .preview-title { font-size: 22px; }
        .preview-subtitle { font-size: 13px; }

        .preview-accent { height: 5px; }
    }

    /* ===================================================================
       🔵 WRAPPER UNTUK A4 (CENTER DI MOBILE & TABLET)
       =================================================================== */

    .a4-wrapper {
        width: 100%;
        overflow-x: hidden;
        display: flex;
        justify-content: center;
    }

    /* ===================================================================
       🔵 A4 DESKTOP
       =================================================================== */

    .a4 {
        width: 793px;
        background: white;
        margin: 0 auto 60px auto;
        padding: 0;
        box-shadow: 0 0 20px rgba(0,0,0,0.15);
        transition: .2s;
    }

    table {
        border-collapse: collapse;
        width: 100%;
    }

    .info-outer {
        width: 720px;
        margin: 0 auto;
        border: 1px solid black;
        font-size: 11px;
    }

    .info-outer td {
        border: none !important;
        padding: 6px 8px;
    }

    .title-inside {
        text-align: center;
        font-size: 16px;
        font-weight: bold;
        text-decoration: underline;
        padding: 10px 0;
    }

    .info-header {
        font-size: 12px;
        font-weight: bold;
        text-decoration: underline;
        padding: 5px 0 8px 0;
    }

    .border-2 {
        width: 720px;
        margin: 0 auto;
        border: 1px solid black;
    }

    .border-2 td, .border-2 th {
        border: 1px solid black !important;
        padding: 6px 8px;
        vertical-align: top;
        font-size: 11px;
    }

    .section-header {
        text-align: center;
        font-weight: bold;
        font-size: 12px;
        padding: 5px;
    }

    .sub-header {
        text-align: center;
        font-size: 11px;
        line-height: 1.2;
    }

    /* ============================================================
       🔵 FOTO CENTER FIX (SATU-SATUNYA YANG DIUBAH)
       ============================================================ */
    .photo-box {
        text-align: center !important;
    }

    .photo-box img {
        max-width: 310px; /* mengikuti batas Word */
        height: auto;
        margin: 10px auto !important;
        display: block !important;
        border: 1px solid #555;
    }


    /* ===================================================================
       🔵 A4 RESPONSIVE — TABLET (600–1024px)
       =================================================================== */

    @media (min-width:600px) and (max-width:1024px) {
        .a4 {
            transform: scale(0.88);
            transform-origin: top center;
            width: 793px;
            margin-top: 30px;
            margin-bottom: 200px;
        }
    }

    /* ===================================================================
       🔵 A4 RESPONSIVE — MOBILE (≤ 600px)
       =================================================================== */

    @media (max-width:600px) {
        .a4 {
            transform: scale(0.62);
            transform-origin: top center;
            width: 793px;
            margin-top: 20px;
            margin-bottom: 260px;
        }

        .a4-wrapper {
            width: 100%;
            justify-content: center !important;
            overflow-x: hidden !important;
        }

        .btn-back {
            font-size: 13px;
            padding: 7px 14px;
            margin-bottom: 16px;
        }
    }

</style>



{{-- ===================================================================
     🔵 TOP HEADER PREVIEW
     =================================================================== --}}
<div class="preview-top">

    <a href="{{ route('ccr.manage.engine') }}" class="btn-back">← Kembali ke menu Edit CCR</a>

    <div class="preview-header-box">
        <img src="{{ asset('rnf-logo.png') }}" class="header-logo" width="110" height="110" alt="RNF Logo">
        <div>
            <div class="preview-title">PREVIEW CCR – ENGINE</div>
            <div class="preview-subtitle">Tampilan hasil laporan CCR sebelum diunduh.</div>
        </div>
    </div>

    <div class="preview-accent"></div>
</div>



{{-- ===================================================================
     🔵 A4 PREVIEW DOCUMENT
     =================================================================== --}}
<div class="a4-wrapper">
<div class="a4">

    {{-- HEADER --}}
    <div class="header-rnf" style="width:720px;margin:0 auto;padding-top:14px;">

        <div style="display:flex;justify-content:space-between;align-items:center;width:100%;">
            <div><img src="{{ asset('ccrrnf.png') }}" style="width:2.52cm;height:3.48cm;object-fit:contain;"></div>

            <div style="text-align:center;flex:1;margin:0 20px;">
                <div style="font-family:Broadway;font-size:15px;font-weight:bold;">PT. REZEKI NADH FATHAN</div>
                <div style="font-family:'Times New Roman';font-size:11px;font-weight:bold;margin-top:2px;">
                    COMPONENTS REBUILD AND GENERAL SUPPLIER
                </div>
                <div style="font-family:'Times New Roman';font-size:10px;margin-top:3px;line-height:1.1;">
                    JL. Sangga Buana RT. 35 No 54-B Graha Indah Balikpapan Kalimantan Timur 76126 <br>
                    Telp : 0542-4563163   email : sales@rnadhfathan.com
                </div>
            </div>

            <div><img src="{{ asset('engine.jpg') }}" style="width:2.08cm;height:2.93cm;object-fit:contain;"></div>
        </div>

        <div style="width:100%;margin-top:10px;position:relative;height:4px;margin-bottom:41px;">
            <div style="border-bottom:1px solid #4a4a4a;position:absolute;bottom:46px;width:100%;"></div>
            <div style="border-bottom:1px solid #4a4a4a;position:absolute;bottom:44px;width:100%;"></div>
        </div>
    </div>


    {{-- INFO TABLE --}}
    <table class="info-outer">
        <tr>
            <td colspan="3" class="title-inside">
                COMPONENT CONDITION REPORT
            </td>
        </tr>

        <tr>
            <td colspan="3" class="info-header">
                COMPONENT INFORMATION:
            </td>
        </tr>

        <tr>
            <td style="width:180px;">COMPONENT</td>
            <td style="width:10px;">:</td>
            <td>{{ $report->component }}</td>
        </tr>

        <tr>
            <td>MAKE</td>
            <td>:</td>
            <td>{{ $report->make }}</td>
        </tr>

        <tr>
            <td>MODEL</td>
            <td>:</td>
            <td>{{ $report->model }}</td>
        </tr>

        <tr>
            <td>SN</td>
            <td>:</td>
            <td>{{ $report->sn ?? '-' }}</td>
        </tr>

        <tr>
            <td>SMU</td>
            <td>:</td>
            <td>{{ $report->smu ?? '-' }}</td>
        </tr>

        <tr>
            <td>CUSTOMER</td>
            <td>:</td>
            <td>{{ $report->customer }}</td>
        </tr>

        <tr>
            <td>INSPECTION DATE</td>
            <td>:</td>
            <td>{{ \Carbon\Carbon::parse($report->inspection_date)->format('d M Y') }}</td>
        </tr>
    </table>


    {{-- ITEM LOOP --}}
    @foreach($report->items as $item)
        <table class="border-2" style="margin-top:-1px; min-height:200px;">
            <tr>
                <td style="width:50%;padding:10px;">
                    {!! nl2br(e($item->description)) !!}
                </td>

                <td class="photo-box" style="width:50%;padding:10px;">
                    @foreach($item->photos as $photo)
                        <img src="{{ asset('storage/' . $photo->path) }}">
                    @endforeach
                </td>
            </tr>
        </table>
    @endforeach

</div>
</div>

@endsection
