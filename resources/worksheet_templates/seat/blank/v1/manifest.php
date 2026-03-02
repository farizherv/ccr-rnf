<?php

/**
 * Template: Seat - Blank (kosong)
 *
 * Apply template ini harus:
 * - mengosongkan Parts & Labour Worksheet (Seat)
 * - mengosongkan Detail Worksheet (Seat)
 */

return [
    'key'     => 'seat_blank',
    'version' => 'v1',
    'name'    => 'Template Kosong (Blank) — Seat',
    'notes'   => 'Template Kosong Parts & Labour + Detail untuk Seat "No Data"',

    'sheets' => [
        'parts'  => 'Parts & Labour Worksheet',
        'detail' => 'Detail Worksheet',
    ],

    'defaults' => [
        'parts'  => __DIR__ . '/defaults_parts.php',
        'detail' => __DIR__ . '/defaults_detail.php',
    ],

    // optional export maps (placeholder)
    'export_maps' => [
        'excel' => null,
        'word'  => null,
    ],
];
