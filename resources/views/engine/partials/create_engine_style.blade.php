<style>
    .engine-create-page,
    .engine-create-page * ,
    .engine-create-page *::before,
    .engine-create-page *::after{
        box-sizing: border-box;
    }

    .engine-create-page [x-cloak]{ display:none !important; }
    .engine-create-page{
        --ccr-sidebar-width: 390px;
        --ccr-workspace-gap: 20px;
    }

    .engine-create-page .tabbar{ display:flex; gap:10px; flex-wrap:wrap; margin: 0 0 12px; }
    .engine-create-page .tabbtn{
        border:1px solid #cfd3d7;
        background:#f6f7f8;
        padding:10px 14px;
        border-radius:10px;
        font-weight:800;
        cursor:pointer;
        transition:.2s;
        color:#0f172a;
    }
    .engine-create-page .tabbtn.active{ background:#111827; border-color:#111827; color:#fff; }
    .engine-create-page .tabbtn:hover{ transform: translateY(-1px); }


    .engine-create-page .header-card-master {
        background: #ffffff;
        padding: 28px 38px;
        border-radius: 20px;
        box-shadow: 0 4px 14px rgba(0,0,0,0.07);
        margin-top: 10px;
        margin-bottom: 24px;
        overflow:hidden;
        max-width:100%;
    }

    .engine-create-page .header-content-master {
        display: flex;
        align-items: center;
        gap: 26px;
        min-width:0;
    }
    .engine-create-page .header-logo-master { width: 95px; object-fit:contain; flex:0 0 auto; }
    .engine-create-page .header-title-master { font-size: 28px; font-weight: 800; margin:0; }
    .engine-create-page .header-subtitle-master { font-size: 15px; color: #666; margin-top:6px; }

    .engine-create-page .accent-line {
        height: 4px;
        background: #E40505;
        border-radius: 20px;
        margin-bottom: 20px;
    }

    .engine-create-page .ccr-workspace{
        display:grid;
        grid-template-columns:minmax(0, 820px) auto;
        align-items:start;
        justify-content:center;
        gap:var(--ccr-workspace-gap);
        max-width:calc(820px + var(--ccr-sidebar-width) + var(--ccr-workspace-gap));
        margin:0 auto;
    }
    .engine-create-page .ccr-main-pane{
        min-width:0;
        transition:all .22s ease;
    }
    .engine-create-page .ccr-side-pane{
        position:sticky;
        top:82px;
        z-index:15;
        align-self:start;
    }
    .engine-create-page .ccr-side-toggle{
        width:40px;
        height:40px;
        border:none;
        border-radius:999px;
        background:#0f172a;
        color:#fff;
        font-size:17px;
        font-weight:900;
        cursor:pointer;
        box-shadow:0 10px 20px rgba(2,6,23,.28);
        margin-left:auto;
        display:flex;
        align-items:center;
        justify-content:center;
    }
    .engine-create-page .ccr-side-toggle.is-open{
        margin-bottom:10px;
    }
    .engine-create-page .ccr-side-content{
        width:var(--ccr-sidebar-width);
        max-height:calc(100vh - 140px);
        overflow:auto;
        background:#fff;
        border:1px solid #d1d5db;
        border-radius:14px;
        padding:12px;
        box-shadow:0 10px 24px rgba(2,6,23,.10);
    }
    .engine-create-page .ccr-workspace.is-sidebar-closed .ccr-side-content{
        display:none !important;
    }
    .engine-create-page .ccr-workspace.is-sidebar-open .doc-a4-wrap{
        justify-content:flex-start;
    }
    .engine-create-page .ccr-side-card{
        border:1px solid #e2e8f0;
        border-radius:12px;
        background:#f8fafc;
        padding:12px;
        margin-bottom:12px;
    }
    .engine-create-page .ccr-autosave-row{
        display:flex;
        align-items:center;
        gap:8px;
        margin-bottom:10px;
    }
    .engine-create-page .ccr-autosave-pill{
        flex:1 1 auto;
        min-width:0;
        font-size:12px;
        font-weight:900;
        border-radius:999px;
        border:1px solid #d0d7e2;
        padding:7px 12px;
        line-height:1.2;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
        color:#334155;
        background:#e2e8f0;
    }
    .engine-create-page .ccr-autosave-pill.is-saving{
        background:#ffedd5;
        border-color:#fdba74;
        color:#9a3412;
    }
    .engine-create-page .ccr-autosave-pill.is-saved{
        background:#dbeafe;
        border-color:#bfdbfe;
        color:#1e3a8a;
    }
    .engine-create-page .ccr-autosave-pill.is-error{
        background:#fee2e2;
        border-color:#fecaca;
        color:#991b1b;
    }
    .engine-create-page .ccr-autosave-label{
        font-size:12px;
        font-weight:900;
        color:#475569;
        white-space:nowrap;
    }
    .engine-create-page .ccr-side-head{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:8px;
    }
    .engine-create-page .ccr-side-card:last-child{
        margin-bottom:0;
    }
    .engine-create-page .ccr-side-title{
        font-size:13px;
        font-weight:900;
        color:#0f172a;
        margin-bottom:4px;
    }
    .engine-create-page .ccr-side-counter{
        font-size:11px;
        font-weight:900;
        color:#334155;
        background:#eef2ff;
        border:1px solid #c7d2fe;
        border-radius:999px;
        padding:3px 8px;
        white-space:nowrap;
    }
    .engine-create-page .ccr-side-subtitle{
        margin:0 0 9px;
        font-size:11px;
        color:#475569;
        line-height:1.3;
        font-weight:700;
    }
    .engine-create-page .ccr-side-actions{
        margin-top:10px;
        margin-bottom:12px;
        display:grid;
        gap:10px;
    }
    .engine-create-page .ccr-side-actions .doc-btn{
        width:100%;
    }
    .engine-create-page .ccr-side-actions .doc-btn--danger{
        height:36px;
        font-size:12px;
    }
    .engine-create-page .ccr-staging-drop{
        border:1px dashed #94a3b8;
        border-radius:10px;
        background:#fff;
        min-height:170px;
        padding:9px;
        cursor:pointer;
    }
    .engine-create-page .ccr-staging-note{
        font-size:11px;
        font-weight:800;
        color:#334155;
        margin-bottom:8px;
    }
    .engine-create-page .ccr-staging-thumb{
        cursor:grab;
    }
    .engine-create-page .ccr-staging-thumb:active{
        cursor:grabbing;
    }
    .engine-create-page .ccr-side-footnote{
        margin:8px 0 0;
        font-size:11px;
        color:#475569;
        line-height:1.32;
        font-weight:700;
    }
    .engine-create-page .ccr-side-clear-btn{
        width:100%;
        margin-top:8px;
    }

    .engine-create-page .ccr-folder-chip{
        display:inline-flex;
        align-items:center;
        gap:8px;
        border:1px solid #d1d5db;
        background:#fff;
        border-radius:12px;
        padding:8px 12px;
        font-size:12px;
        font-weight:800;
        color:#334155;
    }
    .engine-create-page .ccr-folder-chip b{
        color:#111827;
        font-size:13px;
    }
    .engine-create-page .ccr-folder-picker{
        display:flex;
        align-items:center;
        gap:8px;
        border:1px solid #d1d5db;
        background:#fff;
        border-radius:12px;
        padding:8px 12px;
        margin-bottom:2px;
    }
    .engine-create-page .ccr-folder-picker label{
        font-size:12px;
        font-weight:900;
        color:#334155;
        white-space:nowrap;
        margin:0;
        line-height:1;
    }
    .engine-create-page .ccr-folder-picker-input{
        flex:1 1 auto;
        min-width:0;
        height:28px;
        border:1px solid #d1d5db;
        border-radius:8px;
        padding:0 10px;
        font-size:12px;
        font-weight:800;
        color:#111827;
        background:#fff;
    }
    .engine-create-page .ccr-folder-picker-input:focus{
        outline:none;
        border-color:#93c5fd;
        box-shadow:0 0 0 2px rgba(59,130,246,.18);
    }
    .engine-create-page .doc-count{
        font-size:12px;
        font-weight:800;
        color:#334155;
        background:#fff;
        border:1px solid #d1d5db;
        border-radius:999px;
        padding:7px 11px;
        margin-top:2px;
    }

    .engine-create-page .doc-btn{
        height:36px;
        border-radius:10px;
        border:1px solid #d1d5db;
        background:#fff;
        color:#111827;
        font-size:12px;
        font-weight:800;
        padding:0 14px;
        cursor:pointer;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        text-decoration:none;
    }
    .engine-create-page .doc-btn:disabled{
        opacity:.55;
        cursor:not-allowed;
    }
    .engine-create-page .doc-btn--primary{
        background:#2563eb;
        border-color:#2563eb;
        color:#fff;
    }
    .engine-create-page .doc-btn--primary:hover:not(:disabled){ background:#1d4ed8; }
    .engine-create-page .doc-btn--ghost:hover:not(:disabled){ background:#f1f5f9; }
    .engine-create-page .doc-btn--danger{
        background:#dc2626;
        border-color:#dc2626;
        color:#fff;
        height:30px;
        padding:0 10px;
        font-size:11px;
    }
    .engine-create-page .doc-btn--danger:hover:not(:disabled){ background:#b91c1c; }

    .engine-create-page .doc-a4-wrap{
        width:100%;
        overflow-x:auto;
        display:flex;
        justify-content:center;
        padding:0 0 16px;
    }
    .engine-create-page .doc-a4{
        width:793px;
        min-width:793px;
        background:#fff;
        box-shadow:0 0 16px rgba(0,0,0,.16);
        padding:14px 0 26px;
        border:1px solid #d7d7d7;
    }
    .engine-create-page .doc-a4 table{
        border-collapse:collapse;
        width:720px;
        margin:0 auto;
    }

    .engine-create-page .doc-header-rnf{
        width:720px;
        margin:0 auto;
    }
    .engine-create-page .doc-header-rnf__row{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
    }
    .engine-create-page .doc-header-rnf__row img{
        width:2.3cm;
        height:2.9cm;
        object-fit:contain;
    }
    .engine-create-page .doc-header-rnf__center{
        flex:1;
        text-align:center;
        color:#1f2937;
    }
    .engine-create-page .doc-company{
        font-size:15px;
        font-weight:900;
        letter-spacing:.3px;
    }
    .engine-create-page .doc-company-sub{
        font-size:10px;
        margin-top:1px;
        font-weight:700;
    }
    .engine-create-page .doc-company-address{
        font-size:9px;
        margin-top:2px;
        line-height:1.2;
    }
    .engine-create-page .doc-header-rnf__line{
        margin:7px 0 10px;
        border-bottom:1px solid #555;
        box-shadow:0 2px 0 0 #555;
        height:3px;
    }

    .engine-create-page .doc-info-table{
        border:1px solid #111;
        margin-top:4px;
    }
    .engine-create-page .doc-info-table td{
        border:none !important;
        padding:2px 6px;
        font-size:11px;
        color:#111;
        vertical-align:middle;
    }
    .engine-create-page .doc-title{
        text-align:center;
        font-size:16px !important;
        font-weight:900;
        text-decoration:underline;
        padding:8px 4px !important;
    }
    .engine-create-page .doc-info-head{
        font-size:12px !important;
        font-weight:900;
        text-decoration:underline;
        padding:7px 6px !important;
    }
    .engine-create-page .doc-k{
        width:150px;
        font-weight:700;
    }
    .engine-create-page .doc-colon{
        width:14px;
        text-align:center;
        font-weight:700;
    }
    .engine-create-page .doc-input{
        width:100%;
        border:1px solid transparent;
        border-bottom:1px dotted #6b7280;
        border-radius:0;
        height:24px;
        background:transparent;
        padding:0 2px;
        font-size:11px;
        color:#111;
    }
    .engine-create-page .doc-input:focus{
        outline:none;
        border-bottom:1px solid #2563eb;
        box-shadow:none;
        background:#f8fbff;
    }
    .engine-create-page .doc-info-table .ts-wrapper{
        border:none;
        padding:0;
    }
    .engine-create-page .doc-info-table .ts-control{
        min-height:24px;
        height:24px;
        border:1px solid transparent;
        border-bottom:1px dotted #6b7280;
        border-radius:0;
        box-shadow:none;
        padding:0 2px;
        background:transparent;
        font-size:11px;
    }
    .engine-create-page .doc-info-table .ts-control input{
        font-size:11px;
    }

    .engine-create-page .doc-main-head{
        border:1px solid #111;
        border-top:none;
        margin-top:0;
    }
    .engine-create-page .doc-main-head td{
        border:1px solid #111 !important;
        padding:4px 6px;
    }
    .engine-create-page .doc-main-title{
        width:50%;
        font-size:11px;
        font-weight:900;
        text-align:center;
        line-height:1.2;
        vertical-align:middle;
    }

    .engine-create-page .doc-item-table{
        border:1px solid #111;
        border-top:none;
        margin-top:0;
    }
    .engine-create-page .doc-item-table td{
        border:1px solid #111 !important;
        vertical-align:top;
        padding:8px;
    }
    .engine-create-page .doc-item-left,
    .engine-create-page .doc-item-right{
        width:50%;
        min-height:280px;
    }
    .engine-create-page .doc-item-rowhead{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:8px;
        margin-bottom:6px;
        font-size:11px;
        font-weight:700;
    }
    .engine-create-page .doc-textarea{
        width:100%;
        min-height:250px;
        resize:vertical;
        border:none;
        outline:none;
        padding:0;
        font-size:11px;
        line-height:1.3;
        background:transparent;
    }
    .engine-create-page .doc-dropzone{
        border:1px dashed #8a8a8a;
        border-radius:0;
        background:#fff;
        min-height:248px;
        padding:8px;
    }
    .engine-create-page .doc-thumb-open{
        display:block;
        width:100%;
        height:100%;
        border:none;
        background:transparent;
        padding:0;
        cursor:pointer;
    }
    .engine-create-page .doc-dropzone-hint{
        margin:0 0 7px;
        font-size:10px;
        color:#4b5563;
        font-weight:700;
        text-align:left;
    }
    .engine-create-page .doc-photo-help{
        display:block;
        margin-top:6px;
        font-size:10px;
        color:#4b5563;
    }
    .engine-create-page .si-thumbs{
        display:flex;
        flex-wrap:wrap;
        gap:8px;
        align-items:flex-start;
    }
    .engine-create-page .si-thumb-wrap{
        position:relative;
    }
    .engine-create-page .si-thumb{
        width:64px;
        height:64px;
        border:1px solid #dbe2ea;
        border-radius:8px;
        overflow:hidden;
        padding:0;
        background:#fff;
        cursor:pointer;
        display:block;
        flex:0 0 auto;
    }
    .engine-create-page .si-thumb img{
        width:100%;
        height:100%;
        object-fit:cover;
        display:block;
    }
    .engine-create-page .si-thumbs > img{
        width:64px;
        height:64px;
        object-fit:cover;
        border:1px solid #dbe2ea;
        border-radius:8px;
        display:block;
        flex:0 0 auto;
    }
    .engine-create-page .si-thumb-x{
        position:absolute;
        top:-6px;
        right:-6px;
        width:20px;
        height:20px;
        border:none;
        border-radius:999px;
        background:#dc2626;
        color:#fff;
        font-size:14px;
        line-height:20px;
        padding:0;
        cursor:pointer;
        font-weight:900;
        box-shadow:0 6px 14px rgba(2,6,23,.25);
    }
    .engine-create-page .doc-dropzone .si-thumbs{
        display:grid;
        grid-template-columns:repeat(4, minmax(0, 1fr));
        gap:8px;
    }
    .engine-create-page .doc-dropzone .si-thumb-wrap{
        width:100%;
        min-width:0;
    }
    .engine-create-page .doc-dropzone .si-thumb{
        width:100%;
        height:auto;
        aspect-ratio:1 / 1;
    }
    .engine-create-page .doc-dropzone .si-thumbs > img{
        width:100%;
        height:auto;
        aspect-ratio:1 / 1;
    }
    .engine-create-page .si-modal{
        position:fixed;
        inset:0;
        z-index:98000;
        display:flex;
        align-items:center;
        justify-content:center;
        padding:24px;
    }
    .engine-create-page .si-modal__backdrop{
        position:absolute;
        inset:0;
        background:rgba(2,6,23,.72);
    }
    .engine-create-page .si-modal__x{
        position:absolute;
        top:14px;
        right:14px;
        z-index:2;
        width:42px;
        height:42px;
        border:none;
        border-radius:999px;
        background:#0f172a;
        color:#fff;
        font-size:30px;
        line-height:42px;
        padding:0;
        cursor:pointer;
        box-shadow:0 10px 24px rgba(0,0,0,.35);
    }
    .engine-create-page .si-modal__img{
        position:relative;
        z-index:1;
        max-width:min(95vw, 1320px);
        max-height:92vh;
        object-fit:contain;
        border-radius:6px;
        box-shadow:0 16px 48px rgba(0,0,0,.42);
        background:transparent;
    }
    .engine-create-page .doc-photo-modal{
        position:fixed;
        inset:0;
        z-index:98000;
        display:flex;
        align-items:center;
        justify-content:center;
        padding:24px;
    }
    .engine-create-page .doc-photo-modal__backdrop{
        position:absolute;
        inset:0;
        background:rgba(2,6,23,.72);
    }
    .engine-create-page .doc-photo-modal__x{
        position:absolute;
        top:14px;
        right:14px;
        z-index:2;
        width:42px;
        height:42px;
        border:none;
        border-radius:999px;
        background:#0f172a;
        color:#fff;
        font-size:30px;
        line-height:42px;
        padding:0;
        cursor:pointer;
        box-shadow:0 10px 24px rgba(0,0,0,.35);
    }
    .engine-create-page .doc-photo-modal__img{
        position:relative;
        z-index:1;
        max-width:min(95vw, 1320px);
        max-height:92vh;
        object-fit:contain;
        border-radius:6px;
        box-shadow:0 16px 48px rgba(0,0,0,.42);
        background:transparent;
    }
    .engine-create-page .doc-bottom-action{
        width:720px;
        margin:12px auto 0;
        text-align:right;
    }

    .engine-create-page .box {
        background:white;
        padding:18px;
        border-radius:14px;
        margin-bottom:20px;
        box-shadow:0 3px 10px rgba(0,0,0,0.07);
        overflow:hidden;
    }

    .engine-create-page .full-width { width: 100%; }

    .engine-create-page .input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ccc;
        border-radius: 8px;
        max-width:100%;
    }

    .engine-create-page .info-grid-edit{
        display:flex;
        flex-direction:column;
        gap:16px;
    }
    .engine-create-page .info-grid-edit > div{ min-width:0; }

    .engine-create-page .ts-wrapper,
    .engine-create-page .ts-control{ max-width:100%; }

    .engine-create-page .dropzone {
        border: 2px dashed #999;
        padding: 20px;
        background: #f8f8f8;
        border-radius: 10px;
        cursor:pointer;
        text-align:center;
        font-size:14px;
        color:#555;
    }

    .engine-create-page .preview-container {
        margin-top: 10px;
        display: flex;
        flex-wrap: wrap;
        gap:10px;
    }

    .engine-create-page .thumb {
        width: 100px;
        height: 100px;
        border-radius: 6px;
        border:1px solid #ccc;
        overflow:hidden;
        position:relative;
        background:#fff;
    }

    .engine-create-page .thumb img {
        width:100%;
        height:100%;
        object-fit:cover;
    }

    .engine-create-page .btn-modern {
        padding: 10px 18px;
        border-radius: 8px;
        border:none;
        cursor:pointer;
        color:white !important;
        font-weight:600;
        display:flex;
        justify-content:center;
        align-items:center;
        gap:6px;
        box-shadow:0 3px 7px rgba(0,0,0,0.15);
        transition:0.25s ease;
        text-decoration:none;
    }

    .engine-create-page .btn-primary { background:#0d6efd; }
    .engine-create-page .btn-primary:hover { background:#0b5ed7; }

    .engine-create-page .btn-success { background:#198754; }
    .engine-create-page .btn-success:hover { background:#157347; }

    .engine-create-page .btn-delete { background:#dc3545; }
    .engine-create-page .btn-delete:hover { background:#bb2d3b; }
    .engine-create-page .btn-ghost{
        background:#ffffff;
        color:#0f172a !important;
        border:1px solid #d1d5db;
        box-shadow:none;
    }
    .engine-create-page .btn-ghost:hover{
        background:#f1f5f9;
        transform:none;
    }
    .engine-create-page .btn-ghost:disabled{
        opacity:.5;
        cursor:not-allowed;
    }

    .engine-create-page .btn-delete-photo {
        position:absolute;
        top:4px; right:4px;
        background:#dc3545;
        color:white;
        border-radius:50%;
        padding:2px 6px;
        cursor:pointer;
        font-size:12px;
        line-height:1;
        user-select:none;
    }

    .engine-create-page .engine-submit-wrap{
        margin-top:10px;
        display:flex;
        justify-content:center;
        padding:8px 6px 8px;
        border:1px solid #d8e3f2;
        border-radius:16px;
        background:rgba(255,255,255,.95);
        box-shadow:0 10px 24px rgba(0,0,0,.08);
        width:fit-content;
        max-width:100%;
        margin-left:auto;
        margin-right:auto;
    }
    .engine-create-page .engine-submit-btn{
        width:700px;
        max-width:100%;
        min-height:58px;
        border-radius:12px;
        border:1px solid #d5dfef;
        background:#f4f8ff;
        color:#0f172a;
        font-size:16px;
        font-weight:900;
        letter-spacing:.1px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:8px;
        box-shadow:none;
        transition:background-color .18s ease, border-color .18s ease, transform .18s ease, box-shadow .18s ease;
        cursor:pointer;
    }
    .engine-create-page .engine-submit-btn:hover{
        background:#ffffff;
        border-color:#111827;
        color:#111827;
        transform:translateY(-1px);
        box-shadow:
            0 0 0 2px rgba(17,24,39,.15),
            0 0 16px rgba(17,24,39,.22),
            0 8px 16px rgba(0,0,0,.08);
    }
    .engine-create-page .engine-submit-btn:active{
        transform:translateY(0);
    }
    .engine-create-page .engine-submit-btn:focus-visible{
        outline:none;
        border-color:#111827;
        box-shadow:
            0 0 0 2px rgba(17,24,39,.18),
            0 0 14px rgba(17,24,39,.20);
    }
    @media (max-width: 768px){
        .engine-create-page .engine-submit-wrap{
            padding:6px;
            border-radius:18px;
            width:100%;
        }
        .engine-create-page .engine-submit-btn{
            width:100%;
            min-height:50px;
            border-radius:14px;
            font-size:14px;
        }
    }

    .engine-create-page .error-box {
        background:#ffe5e5;
        padding:14px;
        border-radius:10px;
        margin-bottom:20px;
    }

    .engine-create-page .btn-back-enhanced {
        display: inline-flex;
        align-items: center;
        padding: 10px 22px;
        background: #5f656a;
        color: white !important;
        font-weight: 600;
        font-size: 15px;
        border-radius: 10px;
        text-decoration: none;
        box-shadow: 0 3px 8px rgba(0,0,0,0.18);
        transition: 0.25s ease;
        margin-bottom: 14px;
    }
    .engine-create-page .btn-back-enhanced:hover {
        background: #2b2d2f;
        transform: translateY(-2px);
    }

    .engine-create-page .hidden{ display:none; }

    @media (max-width: 600px) {
        .engine-create-page .btn-back-enhanced {
            font-size: 14px;
            padding: 9px 18px;
            margin-bottom: 22px;
        }
        .engine-create-page .thumb{ width:110px; height:110px; }
    }

    @media (max-width: 1200px){
        .engine-create-page .ccr-workspace{
            grid-template-columns:minmax(0, 1fr);
            gap:10px;
            max-width:100%;
        }
        .engine-create-page .ccr-side-pane{
            position:static;
            order:-1;
        }
        .engine-create-page .ccr-side-toggle{
            display:none;
        }
        .engine-create-page .ccr-side-content{
            width:100%;
            max-height:none;
        }
        .engine-create-page .ccr-workspace.is-sidebar-open .doc-a4-wrap,
        .engine-create-page .ccr-workspace.is-sidebar-closed .doc-a4-wrap{
            justify-content:center;
        }
    }

    @media (max-width: 920px){
        .engine-create-page .doc-a4-wrap{
            justify-content:flex-start;
        }
        .engine-create-page .doc-a4{
            transform:scale(.84);
            transform-origin:top left;
            margin-right:-125px;
        }
    }

    @media (max-width: 640px){
        .engine-create-page .doc-a4{
            transform:scale(.62);
            transform-origin:top left;
            margin-right:-290px;
        }
    }
</style>
