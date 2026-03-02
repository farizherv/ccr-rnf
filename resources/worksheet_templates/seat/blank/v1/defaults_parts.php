<?php

// Parts & Labour Worksheet payload kosong (Seat).
// Return shape harus konsisten: { meta, rows, styles, notes }

$rows = [];

// Seat default rows: 30 (sesuai requirement kamu)
for ($i = 0; $i < 30; $i++) {
    $rows[] = [
        'qty'              => '',
        'uom'              => '',
        'part_number'      => '',
        'part_description' => '',
        'part_section'     => '',
        'purchase_price'   => '',
        'total'            => '',
        'sales_price'      => '',
        'extended_price'   => '',
        'total_manual'     => false,
        'extended_manual'  => false,
    ];
}

return [
    'meta' => [
        'no_unit' => '',
        'rows_count' => 30,
        // footer modes optional: auto/manual
        'footer_total' => '',
        'footer_extended' => '',
        'footer_total_mode' => 'auto',
        'footer_extended_mode' => 'auto',
    ],
    'rows'   => $rows,
    'styles' => [],
    'notes'  => [],
];
