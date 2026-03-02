<?php

// Detail Worksheet payload kosong.
// Return shape harus konsisten:
// { meta, main_rows, painting_rows, external_rows, misc, totals }

$main = [];
for ($i = 0; $i < 5; $i++) {
    $main[] = [
        'seg' => '',
        'code' => '',
        'component_desc' => '',
        'work_desc' => '',
        'work_order' => '',
        'hours' => '',
        'labour_charge' => '',
        'parts_charge' => '',
    ];
}

$painting = [];
for ($i = 0; $i < 5; $i++) {
    $painting[] = [
        'item' => '',
        'qty' => '',
        'uom' => '',
        'unit_price' => '',
        'total' => '',
    ];
}

$external = [];
for ($i = 0; $i < 5; $i++) {
    $external[] = [
        'service' => '',
        'amount' => '',
    ];
}


return [
  'meta' => [
    'smu' => '',
    'sub_total_hours' => '0',

    // tambahan header display di UI (opsional)
    'work_order' => '',
    'unit_model' => '',
    'unit_sn' => '',
  ],

  // UI: SECTION COMPONENT (min 5 rows)
  'main_rows' => array_fill(0, 5, [
    'seg' => '',
    'code' => '',
    'component_desc' => '',
    'work_desc' => '',
    'work_order' => '',
    'hours' => '',
    'labour_charge' => '',
    'parts_charge' => '',
  ]),

  // UI: SECTION PAINTING (min 5 rows)
  'painting_rows' => array_fill(0, 5, [
    'item' => '',
    'qty' => '',
    'uom' => '',
    'unit_price' => '',
    'total' => '',
  ]),

  // UI: SECTION EXTERNAL SERVICES (min 5 rows)
  'external_rows' => array_fill(0, 5, [
    'service' => '',
    'amount' => '',
  ]),

  'misc' => [
    'consumable_percent' => '0',
    'consumable_charge' => '0',
    'special_tools_percent' => '0',
    'special_tools_charge' => '0',
    'handling_percent' => '0',
    'handling_charge' => '0',
  ],

  // totals (string angka, tanpa format)
  'totals' => [
    'total_labour' => '0',
    'total_parts' => '0',
    'consumables' => '0',
    'special_tools' => '0',
    'handling' => '0',

    'sub_total' => '0',

    // ✅ konsisten dengan template lain + UI: SALES TAX
    'sales_tax_percent' => '0',
    'sales_tax' => '0',

    // discount
    'discount_percent' => '0',
    'discount' => '0',

    // total after discount + tax
    'total_amount' => '0',
  ],
];

