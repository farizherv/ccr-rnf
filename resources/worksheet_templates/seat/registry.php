<?php

/**
 * Seat worksheet templates registry (source-of-truth).
 *
 * Struktur:
 *  key => [
 *    'name'   => 'Nama tampil di UI',
 *    'path'   => 'folder relatif di resources/worksheet_templates/seat',
 *    'latest' => 'v1',
 *  ]
 */
return [
    // ===== BLANK TEMPLATE (kosong) =====
    'seat_blank' => [
        'name'   => 'Template Kosong (Blank)',
        'path'   => 'blank',
        'latest' => 'v1',
        'notes'  => 'Template kosong Parts & Labour + Detail untuk Seat.',
    ],

    // ===== PART LIST OPERATOR SEAT =====
    'seat_part_list_operator_seat' => [
        'name'   => 'Part List Operator Seat',
        'path'   => 'part_list_operator_seat',
        'latest' => 'v1',
        'notes'  => 'Template default Parts & Labour + Detail untuk Part List Operator Seat',
    ],

];
