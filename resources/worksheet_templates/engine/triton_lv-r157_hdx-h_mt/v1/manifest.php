<?php

return [
  'key'     => 'engine_triton_lv_r157_hdxh_mt',
  'version' => 'v1',
  'name'    => 'Engine Triton LV-R157 (All New Triton HDX-H MT)',

  // defaults files (absolute path)
  'defaults' => [
    'parts'  => __DIR__ . '/defaults_parts.php',
    'detail' => __DIR__ . '/defaults_detail.php',
  ],

  // optional export maps (safe placeholders)
  'export_maps' => [
    'excel' => __DIR__ . '/excel_map.php',
    'word'  => __DIR__ . '/word_map.php',
  ],
];
