<?php

return [
    'meta' => [
        'customer'         => '',
        'quo_est_number'   => '',
        'wo_number'        => '',
        'model'            => 'ENGINE',
        'sn'               => '',
        'equipt_no'        => '',
        'attention'        => '',
        'engine'           => 'ENGINE',
        'date'             => '',
        'smu'              => '0',
        'job_outline'      => 'ENGINE MITSUBISHI FE SUPER HDX HI DUMP PS 136 ( No. Unit)',

        // default sesuai template contoh
        'sub_total_hours'  => '48,00',
        'sub_total_labour' => '13560000',
        'sub_total_parts'  => '112075256',
    ],

    'main_rows' => [
        [
            'seg' => '',
            'code' => '',
            'component_desc' => 'PRE-ELIMINARY INSPECTION',
            'work_desc' => 'PERFORM INSPECTION FOR MISSING PARTS',
            'work_order' => '',
            'hours' => '2,00',
            'labour_charge' => '',
            'parts_charge' => '',
        ],
        [
            'seg' => '',
            'code' => '',
            'component_desc' => 'DISASSEMBLY TASK',
            'work_desc' => 'CLEAN UP STRIPED COMPONENTS',
            'work_order' => '',
            'hours' => '15,00',
            'labour_charge' => '',
            'parts_charge' => '',
        ],
        [
            'seg' => '',
            'code' => '',
            'component_desc' => 'ASSEMBLY TASK',
            'work_desc' => 'ASSEMBLY ENGINE',
            'work_order' => '',
            'hours' => '25,00',
            'labour_charge' => '',
            'parts_charge' => '',
        ],
        [
            'seg' => '',
            'code' => '',
            'component_desc' => 'COMPLETION TASK',
            'work_desc' => 'START UP AND RUNNING ENGINE, CHECK LEAKS',
            'work_order' => '',
            'hours' => '4,00',
            'labour_charge' => '',
            'parts_charge' => '',
        ],
        [
            'seg' => '',
            'code' => '',
            'component_desc' => '',
            'work_desc' => '',
            'work_order' => '',
            'hours' => '2,00',
            'labour_charge' => '',
            'parts_charge' => '',
        ],
        // 1 baris kosong biar rapi (total 6)
        [
            'seg' => '',
            'code' => '',
            'component_desc' => '',
            'work_desc' => '',
            'work_order' => '',
            'hours' => '',
            'labour_charge' => '',
            'parts_charge' => '',
        ],
    ],

    'painting_rows' => [
        ['item'=>'COLOUR PAINT','qty'=>'2','uom'=>'LTRS','unit_price'=>'130000','total'=>'260000'],
        ['item'=>'BLACK PAINT','qty'=>'1','uom'=>'LTRS','unit_price'=>'130000','total'=>'130000'],
        ['item'=>'THINNER','qty'=>'5','uom'=>'LTRS','unit_price'=>'22000','total'=>'110000'],
        ['item'=>'MASKING TAPE 2"','qty'=>'1','uom'=>'EA','unit_price'=>'15000','total'=>'15000'],
        ['item'=>'ENGINE OIL','qty'=>'25','uom'=>'LTRS','unit_price'=>'45000','total'=>'1125000'],
        ['item'=>'FUEL','qty'=>'10','uom'=>'LTRS','unit_price'=>'10000','total'=>'100000'],
    ],

    'external_rows' => [
        ['service'=>'Calibration FIP and Nozzle','amount'=>'11500000'],
        ['service'=>'Starting Motor','amount'=>''],
        ['service'=>'Alternator','amount'=>''],
        ['service'=>'polesh and grinding crankshaft','amount'=>'1250000'],
        ['service'=>'Grinding 12 ea Valve','amount'=>'750000'],
        ['service'=>'replace 4ea Liner and cutting','amount'=>'6500000'],
        ['service'=>'Recondition Turbocharger','amount'=>'6500000'],
        ['service'=>'Clutch disc','amount'=>'750000'],
    ],

    'misc' => [
        'consumable_percent' => '5',
        'consumable_charge'  => '678000',     // 5% x 13.560.000
        'painting_total'     => '1740000',
        'external_total'     => '27250000',
    ],

    'totals' => [
        'total_labour'       => '13560000',
        'total_parts'        => '112075256',
        'total_misc'         => '29668000',
        'total_before_disc'  => '155303256',
        'discount_percent'   => '3',
        'discount_amount'    => '4659098',
        'total_before_tax'   => '150644158', // (155.303.256 - 4.659.098)
        'sales_tax'          => '16570857',  // 11% x total_before_tax (dibulatkan)
        'total_repair_charge'=> '167215015', // before_tax + sales_tax
    ],
];
