<?php

// Datalists untuk Seat template.
// Kalau nanti mau beda per template (misal Euro/Model Seat), taruh list spesifik di sini.

return [
    'uom' => [
        'EA',
        'SET',
        'PCS',
        'PACK',
        'UNIT',
        'LTR',
        'LTRS',
        'MTR',
        'KG',
        'BOX',
        'CAN',
        'ROLL',
        'BOT',
        'PAIR',
    ],

    // Untuk Seat, part_description biasanya diambil dari Items Master (typeahead).
    // Tapi datalists ini tetap disediakan sebagai fallback.
    'part_description' => [],
    'part_section' => [
        'Update Harga Beli',
        'Belum ada harga jual',
        'Harga beli lebih tinggi',
        'Stock Office',
        'Other',
    ],];
