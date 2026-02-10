<?php

return [
  'key'     => 'engine_4v21y_77263_euro4',
  'version' => 'v1',
  'name'    => 'Parts & Labour Engine 4V21Y-77263 (Euro 4)',

  // defaults files (absolute path)
  'defaults' => [
    'parts'  => __DIR__ . '/defaults_parts.php',
    'detail' => __DIR__ . '/defaults_detail.php',
  ],

  // optional export maps (safe to leave empty / placeholder)
  'export_maps' => [
    'excel' => __DIR__ . '/excel_map.php',
    'word'  => __DIR__ . '/word_map.php',
  ],
];
