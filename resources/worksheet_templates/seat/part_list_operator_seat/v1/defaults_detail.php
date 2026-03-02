<?php

// =========================================================
// Seat Detail Template (v1)
// Key  : seat_part_list_operator_seat
// Title: Part List Operator Seat
// Notes:
// - Consumable Supplies = 5% of Parts cost (company excel)
// - Default tax = 11%, discount = 0%
// =========================================================

$mainRows = [];
for ($i = 0; $i < 2; $i++) {
  $mainRows[] = [
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

$paintingRows = [
  ['item' => 'COLOUR PAINT',     'qty' => '0', 'uom' => 'LTRS', 'unit_price' => '130000', 'total' => '' ],
  ['item' => 'BLACK PAINT',      'qty' => '1', 'uom' => 'LTRS', 'unit_price' => '130000', 'total' => '' ],
  ['item' => 'THINNER',          'qty' => '1', 'uom' => 'LTRS', 'unit_price' => '22000',  'total' => '' ],
  ['item' => 'MASKING TAPE 2"', 'qty' => '1', 'uom' => 'EA',   'unit_price' => '15000',  'total' => '' ],
  ['item' => 'ENGINE OIL',       'qty' => '0', 'uom' => 'LTRS', 'unit_price' => '45000',  'total' => '' ],
  ['item' => 'FUEL',             'qty' => '0', 'uom' => 'LTRS', 'unit_price' => '10000',  'total' => '' ],
];

$externalRows = [];
for ($i = 0; $i < 2; $i++) {
  $externalRows[] = ['service' => '', 'remark' => '', 'amount' => '' ];
}

return [
  'meta' => [
    'template_key' => 'seat_part_list_operator_seat',
    'template_version' => 'v1',
    'customer' => '',
    'quo_est_number' => '',
    'wo_number' => '',
    'model' => 'SEAT',
    'sn' => 'N/A',
    'equipt_no' => '',
    'attention' => '',
    'date' => '',
    'smu' => 'N/A',
    'job_outline' => 'REPAIR OPERATOR SEAT',

    // synced / calculated baselines
    'sub_total_hours' => '0,00',
    'sub_total_labour' => '0',
    'sub_total_parts' => '6550000',
    'sub_total_labour_default' => '0',
    'sub_total_labour_base' => '0',
  ],

  'main_rows' => $mainRows,

  'misc' => [
    'consumable_percent' => '5',
    'consumable_charge' => '',
    'painting_total' => '',
    'external_total' => '',
  ],

  'painting_rows' => $paintingRows,
  'external_rows' => $externalRows,

  'totals' => [
    'discount_percent' => '0',
    'tax_percent' => '11',
    'sales_tax_percent' => '11',

    // amounts will be auto-calculated by UI (recalcAll)
    'total_labour' => '',
    'total_parts' => '',
    'total_misc' => '',
    'total_before_disc' => '',
    'discount_amount' => '',
    'total_before_tax' => '',
    'sales_tax' => '',
    'total_repair_charge' => '',
  ],
];
