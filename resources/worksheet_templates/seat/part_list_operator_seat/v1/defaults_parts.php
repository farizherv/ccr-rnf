<?php

// =========================================================
// Seat Template (v1)
// Key  : seat_part_list_operator_seat
// Title: Part List Operator Seat
// =========================================================

$rows = [
  [
    'qty' => '1',
    'uom' => 'EA',
    'part_number' => '',
    'part_description' => 'Trim seat',
    'part_section' => '',
    'purchase_price' => '',
    'total' => '',
    'sales_price' => '600000',
    'extended_price' => '600000',
    'total_manual' => false,
    'extended_manual' => false,
  ],
  [
    'qty' => '1',
    'uom' => 'EA',
    'part_number' => '',
    'part_description' => 'Trim back',
    'part_section' => '',
    'purchase_price' => '',
    'total' => '',
    'sales_price' => '600000',
    'extended_price' => '600000',
    'total_manual' => false,
    'extended_manual' => false,
  ],
  [
    'qty' => '1',
    'uom' => 'EA',
    'part_number' => '',
    'part_description' => 'Air Regulator ISRI',
    'part_section' => '',
    'purchase_price' => '',
    'total' => '',
    'sales_price' => '4700000',
    'extended_price' => '4700000',
    'total_manual' => false,
    'extended_manual' => false,
  ],
  [
    'qty' => '1',
    'uom' => 'EA',
    'part_number' => '',
    'part_description' => 'Arm Rest',
    'part_section' => '',
    'purchase_price' => '',
    'total' => '',
    'sales_price' => '650000',
    'extended_price' => '650000',
    'total_manual' => false,
    'extended_manual' => false,
  ],
];

for ($i = 0; $i < 26; $i++) {
  $rows[] = [
    'qty' => '',
    'uom' => '',
    'part_number' => '',
    'part_description' => '',
    'part_section' => '',
    'purchase_price' => '',
    'total' => '',
    'sales_price' => '',
    'extended_price' => '',
    'total_manual' => false,
    'extended_manual' => false,
  ];
}

return [
  'meta' => [
    'template_key' => 'seat_part_list_operator_seat',
    'template_version' => 'v1',
    'no_unit' => '',
    'rows_count' => 30,
    'footer_total' => '',
    'footer_extended' => '',
    'footer_total_mode' => 'auto',
    'footer_extended_mode' => 'auto',
  ],
  'rows' => $rows,
  'styles' => [],
  'notes' => [],
];
