<?php

// Detail Worksheet payload kosong (Seat).
// Return shape harus konsisten:
// { meta, main_rows, painting_rows, external_rows, misc, totals }

return [
    'meta' => [
        'customer' => '',
        'quo_est_number' => '',
        'wo_number' => '',
        'model' => 'SEAT',
        'sn' => 'N/A',
        'equipt_no' => '',
        'attention' => '',
        'date' => '',
        'smu' => 'N/A',
        'job_outline' => '',
        'sub_total_hours' => '0,00',
        'sub_total_labour' => '0',
        'sub_total_parts' => '0',
        'sub_total_labour_default' => '0',
        'sub_total_labour_base' => '0',
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
        'remark' => '',
        'amount' => '',
    ]),

    'misc' => [
        // Seat default: consumable 5%
        'consumable_percent' => '5',
        'consumable_charge' => '0',
        'painting_total' => '0',
        'external_total' => '0',
    ],

    'totals' => [
        // Seat default: discount 0%, sales tax 11%
        'discount_percent' => '0',
        'discount_amount' => '0',
        'tax_percent' => '11',
        'sales_tax_percent' => '11',
        'sales_tax' => '0',

        'total_labour' => '0',
        'total_parts' => '0',
        'total_misc' => '0',
        'total_before_disc' => '0',
        'total_before_tax' => '0',
        'total_repair_charge' => '0',
    ],
];
