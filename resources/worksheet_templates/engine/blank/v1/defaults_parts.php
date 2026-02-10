<?php

// Parts & Labour Worksheet payload kosong.
// Return shape harus konsisten: { meta, rows, styles, notes }

$rows = [];
// Blank template: default 22 empty rows only (requested)
for ($i = 0; $i < 22; $i++) {
    $rows[] = [
        'qty'            => '',
        'uom'            => '',
        'part_number'    => '',
        'part_description' => '',
        'part_section'   => '',
        'purchase_price' => '',
        'total'          => '',
        'sales_price'    => '',
        'extended_price' => '',
        'total_manual'   => false,
        'extended_manual'=> false,
    ];
}

return [
    'meta' => [
        'no_unit' => '',
        'rows_count' => 22,
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
