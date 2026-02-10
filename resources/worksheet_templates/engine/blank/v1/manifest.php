<?php

/**
 * Template: Engine - Blank (kosong)
 *
 * Apply template ini harus:
 * - mengosongkan Parts & Labour Worksheet
 * - mengosongkan Detail Worksheet
 *
 * Catatan:
 * - Data template disimpan di folder template (bukan disisipkan di Blade),
 *   supaya aman & awet untuk jangka panjang.
 */

return [
    'key'     => 'engine_blank',
    'version' => 'v1',
    'name'    => 'Template Kosong (Blank)',
    'notes'   => 'Template Kosong Parts & Labour + Detail untuk Engine "No Data" ',

    'defaults' => [
        'parts'  => __DIR__ . '/defaults_parts.php',
        'detail' => __DIR__ . '/defaults_detail.php',
    ],

    // optional maps (kalau belum dipakai, biarkan null)
    'maps' => [
        'excel' => null,
        'word'  => null,
    ],
];
