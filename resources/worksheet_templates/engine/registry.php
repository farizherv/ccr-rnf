<?php

/**
 * Engine worksheet templates registry (source-of-truth).
 *
 * Struktur:
 *  key => [
 *    'name'   => 'Nama tampil di UI',
 *    'path'   => 'folder relatif di resources/worksheet_templates/engine',
 *    'latest' => 'v1',
 *  ]
 */

return [
    // ===== BLANK TEMPLATE (kosong) =====
    'engine_blank' => [
        'name'   => 'Template Kosong (Blank)',
        'path'   => 'blank',
        'latest' => 'v1',
    ],

    // ===== TEMPLATE ENGINE CANTER EURO 2 =====
    'engine_4d34t_s64029_cantereuro2' => [
        'name'   => 'Parts & Labour Engine 4D34T-S64029 (Canter Euro 2)',
        'path'   => '4d34t-s64029_cantereuro2',
        'latest' => 'v1',
    ],

    // ===== TEMPLATE ENGINE CANTER EURO 4 =====
    'engine_4v21y_77263_euro4' => [
    'name'   => 'Engine 4V21Y-77263 Euro 4',
    'path'   => '4v21y-77263_euro4',
    'latest' => 'v1',
    'notes'  => 'Template default Parts & Labour + Detail untuk Engine 4V21Y-77263 Euro 4',
    ],


    // ===== TEMPLATE ENGINE Triton HDX-H MT =====
    'engine_triton_lv_r157_hdxh_mt' => [
        'name'   => 'Engine Triton LV-R157 (All New Triton HDX-H MT)',
        'path'   => 'triton_lv-r157_hdx-h_mt',
        'latest' => 'v1',
        'notes'  => 'Template default Parts & Labour + Detail untuk Engine Triton LV-R157 (All New Triton HDX-H MT)',
    ],


];
