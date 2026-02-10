<?php

return [
  'meta' => [
    'customer'        => '',
    'quo_est_number'  => '',
    'wo_number'       => '',
    'model'           => 'ENGINE',
    'sn'              => 'N/A',
    'equipt_no'       => '',
    'attention'       => '',
    'date'            => '',
    'smu'             => 'N/A',
    'job_outline'     => '',

    'sub_total_hours'  => '53,00',
    'sub_total_labour' => '15105000',   // Rp 15.105.000
    'sub_total_parts'  => '81351131',   // Rp 81.351.131
  ],

  'main_rows' => [
    [
      'component_desc' => 'PRE-ELIMINARY INSPECTION',
      'work_desc'      => 'PERFORM INSPECTION FOR MISSING PARTS',
      'work_order'     => '',
      'hours'          => '2,00',
      'labour_charge'  => '',
      'parts_charge'   => '',
    ],
    [
      'component_desc' => 'DISASSEMBLY TASK',
      'work_desc'      => 'STRIP DOWN ENGINE ASSY',
      'work_order'     => '',
      'hours'          => '17,00',
      'labour_charge'  => '',
      'parts_charge'   => '',
    ],
    [
      'component_desc' => '',
      'work_desc'      => 'CLEAN UP STRIPED COMPONENTS',
      'work_order'     => '',
      'hours'          => '',
      'labour_charge'  => '',
      'parts_charge'   => '',
    ],
    [
      'component_desc' => '',
      'work_desc'      => 'INSPECT AND MEASUREMENT ENGINE COMPONENTS',
      'work_order'     => '',
      'hours'          => '',
      'labour_charge'  => '',
      'parts_charge'   => '',
    ],
    [
      'component_desc' => 'ASSEMBLY TASK',
      'work_desc'      => 'ASSEMBLY ENGINE',
      'work_order'     => '',
      'hours'          => '28,00',
      'labour_charge'  => '',
      'parts_charge'   => '',
    ],
    [
      'component_desc' => 'COMPLETION TASK',
      'work_desc'      => 'START UP AND RUNNING ENGINE, CHECK LEAKS',
      'work_order'     => '',
      'hours'          => '4,00',
      'labour_charge'  => '',
      'parts_charge'   => '',
    ],
    [
      'component_desc' => '',
      'work_desc'      => 'PAINTING ENGINE GP',
      'work_order'     => '',
      'hours'          => '2,00',
      'labour_charge'  => '',
      'parts_charge'   => '',
    ],
  ],

  'misc' => [
    'consumable_percent' => '5',
    'consumable_charge'  => '755250',     // Rp 755.250
    'painting_total'     => '1740000',    // Rp 1.740.000
    'external_total'     => '36745000',   // Rp 36.745.000
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
    ['service'=>'Callibration FIP and Nozzle','remark'=>'','amount'=>'13110000'],
    ['service'=>'Grinding 24 ea Valve','remark'=>'','amount'=>'1824000'],
    ['service'=>'replace 4ea Liner and cutting','remark'=>'','amount'=>'7410000'],
    ['service'=>'Polish Crankshaft','remark'=>'','amount'=>'2451000'],
    ['service'=>'Recondition Turbocharger','remark'=>'','amount'=>'6950000'],
    ['service'=>'Starting Motor','remark'=>'Supplied By Rea','amount'=>''],
    ['service'=>'Repair Alternator','remark'=>'Supplied By Rea','amount'=>''],
    ['service'=>'start up and test','remark'=>'','amount'=>'5000000'],
  ],

  'totals' => [
    'discount_percent'    => '3',
    'discount_amount'     => '4070891',    // Rp 4.070.891
    'tax_percent'         => '11',
    'sales_tax'           => '14478804',   // Rp 14.478.804
    'total_labour'        => '15105000',
    'total_parts'         => '81351131',
    'total_misc'          => '39240250',   // Rp 39.240.250
    'total_before_disc'   => '135696381',  // Rp 135.696.381 (TOTAL BEFORE TAX di screenshot)
    'total_before_tax'    => '131625490',  // Rp 131.625.490 (TOTAL AFTER DISCOUNT di screenshot)
    'total_repair_charge' => '146104293',  // Rp 146.104.293
  ],
];
