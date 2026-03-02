@extends('layout')

@section('content')
<style>
    .word-preview-page{
        max-width:1200px;
        margin:20px auto 36px;
        padding:0 14px;
    }
    .wp-back{
        display:inline-block;
        color:#fff;
        padding:8px 16px;
        border-radius:8px;
        background:#5f656a;
        font-weight:700;
        font-size:14px;
        text-decoration:none;
        transition:.2s ease;
        box-shadow:0 3px 7px rgba(0,0,0,.15);
    }
    .wp-back:hover{
        background:#2f3439;
        color:#fff;
        text-decoration:none;
    }
    .wp-head{
        margin-top:14px;
        background:#fff;
        border:1px solid #d7e1ef;
        border-radius:14px;
        padding:18px 20px;
        box-shadow:0 3px 10px rgba(0,0,0,.07);
    }
    .wp-title{
        margin:0;
        font-size:28px;
        font-weight:900;
        color:#0f172a;
    }
    .wp-subtitle{
        margin:6px 0 0;
        color:#475569;
        font-size:14px;
        font-weight:600;
    }
    .wp-actions{
        margin-top:14px;
        display:flex;
        align-items:center;
        flex-wrap:wrap;
        gap:10px;
    }
    .wp-action-btn{
        display:flex;
        align-items:center;
        gap:8px;
        width:auto;
        max-width:100%;
        flex:0 0 auto;
        min-height:40px;
        padding:8px 14px;
        border-radius:11px;
        text-decoration:none;
        border:1px solid #b5c1d2;
        background:#f8fafc;
        color:#111827;
        font-size:13px;
        font-weight:700;
        line-height:1.1;
        justify-content:flex-start;
        transition:background .18s ease, border-color .18s ease, color .18s ease;
    }
    .wp-action-btn:hover{
        text-decoration:none;
        color:#111827;
        background:#f1f5f9;
        border-color:#aebccd;
    }
    .wp-action-btn:focus{
        outline:none;
        box-shadow:0 0 0 3px rgba(37,99,235,.2);
    }
    .wp-action-icon{
        width:20px;
        height:20px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        flex:0 0 20px;
    }
    .wp-action-icon img{
        width:20px;
        height:20px;
        object-fit:contain;
        display:block;
    }
    .wp-action-icon--open{
        border-radius:4px;
        border:1px solid #ccd7e5;
        background:#eef2f7;
        font-size:13px;
        font-weight:900;
        color:#1e293b;
        line-height:1;
    }
    .wp-action-label{
        display:inline-block;
        white-space:nowrap;
    }
    .wp-note{
        margin-top:10px;
        font-size:12px;
        color:#64748b;
        font-weight:600;
    }
    .wp-error{
        margin-top:16px;
        padding:12px 14px;
        border-radius:10px;
        border:1px solid #fecaca;
        background:#fff1f2;
        color:#b91c1c;
        font-size:13px;
        font-weight:700;
    }
    .wp-frame-wrap{
        margin-top:16px;
        background:#fff;
        border:1px solid #d7e1ef;
        border-radius:14px;
        box-shadow:0 3px 10px rgba(0,0,0,.07);
        overflow:hidden;
    }
    .wp-frame{
        width:100%;
        height:calc(100vh - 220px);
        min-height:820px;
        border:none;
        background:#f8fafc;
    }
    @media (max-width: 900px){
        .wp-title{
            font-size:22px;
        }
        .wp-action-btn{
            min-height:38px;
            padding:7px 12px;
            border-radius:10px;
            font-size:12px;
        }
        .wp-action-icon{
            width:18px;
            height:18px;
            flex-basis:18px;
        }
        .wp-action-icon img{
            width:18px;
            height:18px;
        }
        .wp-action-icon--open{
            font-size:12px;
        }
        .wp-frame{
            min-height:680px;
        }
    }
    @media (max-width: 640px){
        .wp-actions{
            gap:8px;
        }
        .wp-action-btn{
            font-size:12px;
            min-height:36px;
            border-radius:9px;
            width:auto;
            padding:7px 10px;
            gap:6px;
            border-width:1px;
        }
        .wp-action-icon{
            width:16px;
            height:16px;
            flex-basis:16px;
        }
        .wp-action-icon img{
            width:16px;
            height:16px;
        }
        .wp-action-icon--open{
            font-size:11px;
        }
    }
</style>

<div class="word-preview-page">
    <a href="{{ $backUrl }}" class="wp-back">← Kembali ke menu Edit CCR</a>

    <div class="wp-head">
        <h1 class="wp-title">{{ $title }}</h1>
        <p class="wp-subtitle">{{ $subtitle }}</p>

        <div class="wp-actions">
            <a href="{{ $downloadWordUrl }}" class="wp-action-btn">
                <span class="wp-action-icon">
                    <img src="{{ asset('icons/word.svg') }}" alt="Word">
                </span>
                <span class="wp-action-label">Download Word CCR</span>
            </a>
            <a href="{{ $pdfPreviewUrl }}" target="_blank" rel="noopener" class="wp-action-btn">
                <span class="wp-action-icon wp-action-icon--open">↗</span>
                <span class="wp-action-label">Buka Preview di Tab Baru</span>
            </a>
        </div>

        <div class="wp-note">Preview ini dihasilkan dari file DOCX yang sama dengan file Word yang akan diunduh.</div>
    </div>

    @if($previewError)
        <div class="wp-error">{{ $previewError }}</div>
    @else
        <div class="wp-frame-wrap">
            <iframe class="wp-frame" src="{{ $pdfPreviewUrl }}#toolbar=1&page=1&zoom=80"></iframe>
        </div>
    @endif
</div>
@endsection
