<?php

return [
  'key'     => 'engine_4d34t_s64029_cantereuro2',
  'version' => 'v1',
  'name'    => 'Parts & Labour Engine 4D34T-S64029 (Canter Euro 2)',
  'notes'   => 'Template default 2 sheet: Parts & Labour Worksheet + Detail Worksheet (editable per report; template file tetap aman).',

  'sheets' => [
    'parts'  => 'Parts & Labour Worksheet',
    'detail' => 'Detail Worksheet',
  ],

  'defaults' => [
    'parts'  => __DIR__ . '/defaults_parts.php',
    'detail' => __DIR__ . '/defaults_detail.php',
  ],

  // optional untuk mapping export (kalau belum ada, boleh kosongin/skip file-nya dulu)
  'export_maps' => [
    'excel' => __DIR__ . '/excel_map.php',
    'word'  => __DIR__ . '/word_map.php',
  ],
];
