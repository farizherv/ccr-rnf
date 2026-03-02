<?php

return [
    'key'     => 'seat_part_list_operator_seat',
    'version' => 'v1',
    'name'    => 'Part List Operator Seat',
    'notes'   => 'Template default Parts & Labour + Detail berdasarkan worksheet operator seat.',

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
        'excel' => __DIR__ . '/excel_map.php',
        'word'  => __DIR__ . '/word_map.php',
    ],
];
