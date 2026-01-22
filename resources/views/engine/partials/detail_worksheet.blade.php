{{-- =========================================================
TAB: DETAIL WORKSHEET (SHEET DETAIL TEMPLATE)
File: resources/views/engine/partials/detail.blade.php
========================================================= --}}

@php
  // aman untuk create page (kalau $report belum ada)
  $payload = $report->detail_payload ?? [];

  // kalau kebetulan tersimpan sebagai JSON string
  if (is_string($payload)) {
    $decoded = json_decode($payload, true);
    if (is_array($decoded)) $payload = $decoded;
  }
  if (!is_array($payload)) $payload = [];

  $meta = $payload['meta'] ?? [];
  if (!is_array($meta)) $meta = [];

  $mainRows     = $payload['main_rows'] ?? [];
  $paintingRows = $payload['painting_rows'] ?? [];
  $extRows      = $payload['external_rows'] ?? [];
  $totals       = $payload['totals'] ?? [];
  $misc         = $payload['misc'] ?? [];

  if (!is_array($mainRows)) $mainRows = [];
  if (!is_array($paintingRows)) $paintingRows = [];
  if (!is_array($extRows)) $extRows = [];
  if (!is_array($totals)) $totals = [];
  if (!is_array($misc)) $misc = [];

  // ==== DEFAULTS (biar tampilan langsung mirip screenshot) ====
  $defaultsMeta = [
    'customer' => '',
    'quo_est_number' => '',
    'wo_number' => '',
    'model' => '',
    'sn' => '',
    'equipt_no' => '',
    'attention' => '',
    'engine' => 'ENGINE',
    'date' => '',
    'smu' => '0',
    'job_outline' => 'ENGINE MITSUBISHI FE SUPER HDX HI DUMP PS 136 ( No. Unit)',
    'sub_total_hours' => '48,00',
    'sub_total_labour' => '13560000',
    'sub_total_parts' => '112075256',
  ];

  $defaultsMisc = [
    'consumable_percent' => '5',
    'consumable_charge' => '678000',
    'painting_total' => '1740000',
    'external_total' => '27250000',
  ];

  $defaultsTotals = [
    'total_labour' => '13560000',
    'total_parts' => '112075256',
    'total_misc' => '29668000',
    'total_before_disc' => '155303256',
    'discount_percent' => '3',
    'discount_amount' => '4659098',
    'total_before_tax' => '150644159',

    'tax_percent' => '11', // SALES TAX % (default 11)

    'sales_tax' => '16570857',
    'total_repair_charge' => '167215016',
  ];

  foreach ($defaultsMeta as $k => $v) {
    if (!array_key_exists($k, $meta)) $meta[$k] = $v;
  }
  foreach ($defaultsMisc as $k => $v) {
    if (!array_key_exists($k, $misc)) $misc[$k] = $v;
  }
  foreach ($defaultsTotals as $k => $v) {
    if (!array_key_exists($k, $totals)) $totals[$k] = $v;
  }

  if (!count($mainRows)) {
    $mainRows = [
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
    ];

    // tambah baris kosong biar panjangnya “sheet-like”
    for ($i = count($mainRows); $i < 6; $i++) {
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
  }

  if (!count($paintingRows)) {
    $paintingRows = [
      ['item'=>'COLOUR PAINT','qty'=>'2','uom'=>'LTRS','unit_price'=>'130000','total'=>'260000'],
      ['item'=>'BLACK PAINT','qty'=>'1','uom'=>'LTRS','unit_price'=>'130000','total'=>'130000'],
      ['item'=>'THINNER','qty'=>'5','uom'=>'LTRS','unit_price'=>'22000','total'=>'110000'],
      ['item'=>'MASKING TAPE 2"','qty'=>'1','uom'=>'EA','unit_price'=>'15000','total'=>'15000'],
      ['item'=>'ENGINE OIL','qty'=>'25','uom'=>'LTRS','unit_price'=>'45000','total'=>'1125000'],
      ['item'=>'FUEL','qty'=>'10','uom'=>'LTRS','unit_price'=>'10000','total'=>'100000'],
    ];
  }

  if (!count($extRows)) {
    $extRows = [
      ['service'=>'Calibration FIP and Nozzle','amount'=>'11500000'],
      ['service'=>'Starting Motor','amount'=>''],
      ['service'=>'Alternator','amount'=>''],
      ['service'=>'polesh and grinding crankshaft','amount'=>'1250000'],
      ['service'=>'Grinding 12 ea Valve','amount'=>'750000'],
      ['service'=>'replace 4ea Liner and cutting','amount'=>'6500000'],
      ['service'=>'Recondition Turbocharger','amount'=>'6500000'],
      ['service'=>'Clutch disc','amount'=>'750000'],
    ];
  }

  $storageKey = 'ccr_detail_ws_' . md5(url()->current());
@endphp

<div x-show="tab==='detail'" x-cloak
     x-data="detailWS({
        initialMeta: @js($meta),
        initialMainRows: @js($mainRows),
        initialPaintingRows: @js($paintingRows),
        initialExternalRows: @js($extRows),
        initialMisc: @js($misc),
        initialTotals: @js($totals),
        storageKey: @js($storageKey),
     })"
     class="box dw-shell"
     :class="isFs ? 'dw-shell--fs' : ''"
     @keydown.capture="onKey($event)">

  <h3 style="margin-bottom:6px;">Detail Worksheet</h3>
  <p style="font-size:13px; color:#64748b; margin-bottom:14px;">
    Template “Sheet Detail” (UI dulu). Formula & logic bisa kita masukin setelah layout persis.
  </p>

  {{-- TOP BAR: autosave + zoom --}}
  <div class="dw-topbar">
    <div class="dw-topbar__left">
      <span class="dw-badge" x-text="saveStatus"></span>
      <span class="dw-small">AutoSave ON</span>
    </div>

    <div class="dw-topbar__right">
      <button type="button" class="dw-icbtn" @click="toggleFullscreen()">⛶</button>

      <button type="button" class="dw-icbtn" @click="zoomOut()">−</button>
      <input type="range" min="70" max="150" step="5" class="dw-zoom"
             x-model.number="zoom" @input="applyZoom()">
      <button type="button" class="dw-icbtn" @click="zoomIn()">+</button>

      <button type="button" class="dw-pct" @click="zoomReset()">
        <span x-text="zoom + '%'"></span>
      </button>
    </div>
  </div>

  {{-- ACTIONS (1 tombol pair, target bisa pilih: COMPONENT / PAINTING / External Services) --}}
  <div class="dw-actions">
    <button type="button" class="dw-btn dw-btn--primary" @click="addRow()">
      + Tambah Baris
    </button>

    <button type="button" class="dw-btn dw-btn--danger" @click="deleteLastRow()">
      Hapus Terakhir
    </button>

    <div class="dw-target">
      <span class="dw-target__label">Target</span>
      <select class="dw-select" x-model="rowTarget">
        <option value="main">COMPONENT</option>
        <option value="painting">PAINTING</option>
        <option value="external">External Services</option>
      </select>
    </div>

    <div class="dw-tip">
      Enter → kanan • Tab/Shift+Tab → kanan/kiri • Arrow ↑↓←→ → pindah cell (khusus area MAIN)
    </div>
  </div>

  <div class="dw-subbar">
    <div class="dw-subbar__left">
      <div class="dw-cellinfo">
        Cell: <b x-text="cellLabel()"></b>
      </div>
    </div>
  </div>

  {{-- SHEET --}}
  <div class="dw-wrap">
    <div class="dw-tablewrap" x-ref="tablewrap">
      <div class="dw-zoomTarget" x-ref="zoomTarget">

        <table class="dw-sheet">
          <colgroup>
            <col style="width:70px">   {{-- A --}}
            <col style="width:70px">   {{-- B --}}
            <col style="width:90px">   {{-- C --}}
            <col style="width:170px">  {{-- D --}}
            <col style="width:190px">  {{-- E --}}
            <col style="width:220px">  {{-- F --}}
            <col style="width:220px">  {{-- G --}}
            <col style="width:220px">  {{-- H --}}
            <col style="width:170px">  {{-- I --}}
            <col style="width:120px">  {{-- J --}}
            <col style="width:170px">  {{-- K --}}
            <col style="width:190px">  {{-- L --}}
          </colgroup>

          <tbody>
            {{-- ===== HEADER BLOCK (atas) ===== --}}
            <tr class="dw-hdr">
              <th class="dw-hdr-empty"></th>
              <th colspan="5">CUSTOMER</th>
              <th colspan="2">QUO/EST NUMBER</th>
              <th>WO NUMBER</th>
              <th>MODEL</th>
              <th>S/N</th>
              <th>EQUIPT NO.</th>
            </tr>

            <tr>
              <td class="dw-empty"></td>

              <td colspan="5" class="dw-yellow">
                <input type="text" class="dw-inp dw-cell"
                       x-model="meta.customer"
                       data-cell="1" data-row="0" data-col="1"
                       @focus="setActive(0,1)"
                       @input="onChanged()">
              </td>

              <td colspan="2">
                <input type="text" class="dw-inp dw-cell"
                       x-model="meta.quo_est_number"
                       data-cell="1" data-row="0" data-col="6"
                       @focus="setActive(0,6)"
                       @input="onChanged()">
              </td>

              <td class="dw-cyan">
                <input type="text" class="dw-inp dw-cell"
                       x-model="meta.wo_number"
                       data-cell="1" data-row="0" data-col="8"
                       @focus="setActive(0,8)"
                       @input="onChanged()">
              </td>

              <td class="dw-cyan">
                <input type="text" class="dw-inp dw-cell dw-center dw-bold dw-engine"
                      x-model="meta.model"
                      x-init="if(!meta.model) meta.model='ENGINE'"
                      @blur="if(!meta.model) meta.model='ENGINE'"
                      data-cell="1" data-row="0" data-col="9"
                      @focus="setActive(0,9)"
                      @input="onChanged()">
              </td>

              <td class="dw-cyan">
                <input type="text" class="dw-inp dw-cell"
                       x-model="meta.sn"
                       data-cell="1" data-row="0" data-col="10"
                       @focus="setActive(0,10)"
                       @input="onChanged()">
              </td>

              <td class="dw-cyan">
                <input type="text" class="dw-inp dw-cell"
                       x-model="meta.equipt_no"
                       data-cell="1" data-row="0" data-col="11"
                       @focus="setActive(0,11)"
                       @input="onChanged()">
              </td>
            </tr>

            <tr>
              <td colspan="2" class="dw-label">FOR THE ATTENTION</td>

              <td colspan="4">
                <input type="text" class="dw-inp dw-cell"
                       x-model="meta.attention"
                       data-cell="1" data-row="1" data-col="1"
                       @focus="setActive(1,1)"
                       @input="onChanged()">
              </td>

              {{-- G-H-I kosong --}}
              <td colspan="3" class="dw-empty"></td>

              {{-- J = DATE (cell kedua di bawah MODEL) --}}
              <td class="dw-cyan dw-center dw-bold">DATE</td>

              {{-- K kosong --}}
              <td class="dw-empty"></td>

              {{-- L = SMU. --}}
              <td class="dw-cyan dw-center dw-bold">SMU.</td>
            </tr>

            <tr>
              <td colspan="9" class="dw-empty"></td>

              {{-- J = DATE (TIDAK MERGE) --}}
              <td class="dw-cyan">
                <input type="text" class="dw-inp dw-cell dw-center"
                       x-model="meta.date"
                       placeholder="DATE"
                       data-cell="1" data-row="2" data-col="9"
                       @focus="setActive(2,9)"
                       @input="onChanged()">
              </td>

              {{-- K kosong --}}
              <td class="dw-empty"></td>

              {{-- L = SMU value --}}
              <td class="dw-cyan">
                <input type="text" class="dw-inp dw-cell dw-center"
                       x-model="meta.smu"
                       inputmode="numeric"
                       data-cell="1" data-row="2" data-col="11"
                       @focus="setActive(2,11)"
                       @input="meta.smu = onlyDigits(meta.smu); onChanged()">
              </td>
            </tr>

            <tr>
              <td colspan="2" class="dw-label">JOB OUTLINE :</td>

              <td colspan="10" class="dw-yellow">
                <input type="text" class="dw-inp dw-cell dw-bold"
                       x-model="meta.job_outline"
                       data-cell="1" data-row="3" data-col="2"
                       @focus="setActive(3,2)"
                       @input="onChanged()">
              </td>
            </tr>

            <tr class="dw-spacer">
              <td colspan="12"></td>
            </tr>

            {{-- ===== MAIN TABLE (persis struktur kolom) ===== --}}
            <tr class="dw-hdr">
              <th>SEG</th>
              <th colspan="4">COMPONENT</th>
              <th colspan="3">DESCRIPTION</th>
              <th>WORK ORDER NO.</th>
              <th colspan="2">LABOUR</th>
              <th>PARTS</th>
            </tr>

            <tr class="dw-subhdr">
              <th></th>
              <th colspan="2">CODE</th>
              <th colspan="2">DESCRIPTION</th>
              <th colspan="3">DESCRIPTION</th>
              <th></th>
              <th>HOURS</th>
              <th>CHARGE (Rp)</th>
              <th>CHARGE (Rp)</th>
            </tr>

            <template x-for="(r, i) in mainRows" :key="r._id">
              <tr>
                {{-- A (SEG) --}}
                <td>
                  <input type="text" class="dw-inp dw-cell dw-center"
                         x-model="r.seg"
                         data-cell="1" :data-row="mainBase + i" data-col="0"
                         @focus="setActive(mainBase + i, 0)"
                         @input="onChanged()">
                </td>

                {{-- B-C (CODE) --}}
                <td colspan="2">
                  <input type="text" class="dw-inp dw-cell"
                         x-model="r.code"
                         data-cell="1" :data-row="mainBase + i" data-col="1"
                         @focus="setActive(mainBase + i, 1)"
                         @input="onChanged()">
                </td>

                {{-- D-E (COMPONENT DESC) --}}
                <td colspan="2">
                  <input type="text" class="dw-inp dw-cell dw-bold"
                         x-model="r.component_desc"
                         data-cell="1" :data-row="mainBase + i" data-col="3"
                         @focus="setActive(mainBase + i, 3)"
                         @input="onChanged()">
                </td>

                {{-- F-H (WORK DESC) --}}
                <td colspan="3">
                  <input type="text" class="dw-inp dw-cell dw-bold"
                         x-model="r.work_desc"
                         data-cell="1" :data-row="mainBase + i" data-col="5"
                         @focus="setActive(mainBase + i, 5)"
                         @input="onChanged()">
                </td>

                {{-- I (WORK ORDER) --}}
                <td>
                  <input type="text" class="dw-inp dw-cell"
                         x-model="r.work_order"
                         data-cell="1" :data-row="mainBase + i" data-col="8"
                         @focus="setActive(mainBase + i, 8)"
                         @input="onChanged()">
                </td>

                {{-- J (HOURS - green) --}}
                <td class="dw-green">
                  <input type="text" class="dw-inp dw-cell dw-center dw-green"
                         x-model="r.hours"
                         placeholder="0,00"
                         data-cell="1" :data-row="mainBase + i" data-col="9"
                         @focus="setActive(mainBase + i, 9)"
                         @input="r.hours = cleanHours(r.hours); onChanged()">
                </td>

                {{-- K (LABOUR CHARGE Rp) --}}
                <td>
                  <div class="dw-money">
                    <span class="dw-rp">Rp</span>
                    <input type="text" class="dw-inp dw-cell dw-moneyinp"
                           :value="formatDots(r.labour_charge)"
                           inputmode="numeric"
                           data-cell="1" :data-row="mainBase + i" data-col="10"
                           @focus="setActive(mainBase + i, 10)"
                           @input="
                             r.labour_charge = onlyDigits($event.target.value);
                             $event.target.value = formatDots(r.labour_charge);
                             onChanged();
                           ">
                  </div>
                </td>

                {{-- L (PARTS CHARGE Rp) --}}
                <td>
                  <div class="dw-money">
                    <span class="dw-rp">Rp</span>
                    <input type="text" class="dw-inp dw-cell dw-moneyinp"
                           :value="formatDots(r.parts_charge)"
                           inputmode="numeric"
                           data-cell="1" :data-row="mainBase + i" data-col="11"
                           @focus="setActive(mainBase + i, 11)"
                           @input="
                             r.parts_charge = onlyDigits($event.target.value);
                             $event.target.value = formatDots(r.parts_charge);
                             onChanged();
                           ">
                  </div>
                </td>
              </tr>
            </template>

            {{-- SUB TOTAL --}}
            <tr class="dw-subtotal">
              <td colspan="8" class="dw-empty"></td>
              <td class="dw-right dw-bold">Sub Total</td>

              <td class="dw-green">
                <input type="text" class="dw-inp dw-cell dw-center dw-green dw-bold"
                       x-model="meta.sub_total_hours"
                       data-cell="1" :data-row="mainBase + mainRows.length" data-col="9"
                       @focus="setActive(mainBase + mainRows.length, 9)"
                       @input="meta.sub_total_hours = cleanHours(meta.sub_total_hours); onChanged()">
              </td>

              <td>
                <div class="dw-money">
                  <span class="dw-rp">Rp</span>
                  <input type="text" class="dw-inp dw-cell dw-moneyinp dw-bold"
                         :value="formatDots(meta.sub_total_labour)"
                         inputmode="numeric"
                         data-cell="1" :data-row="mainBase + mainRows.length" data-col="10"
                         @focus="setActive(mainBase + mainRows.length, 10)"
                         @input="
                           meta.sub_total_labour = onlyDigits($event.target.value);
                           $event.target.value = formatDots(meta.sub_total_labour);
                           onChanged();
                         ">
                </div>
              </td>

              <td>
                <div class="dw-money">
                  <span class="dw-rp">Rp</span>
                  <input type="text" class="dw-inp dw-cell dw-moneyinp dw-bold"
                         :value="formatDots(meta.sub_total_parts)"
                         inputmode="numeric"
                         data-cell="1" :data-row="mainBase + mainRows.length" data-col="11"
                         @focus="setActive(mainBase + mainRows.length, 11)"
                         @input="
                           meta.sub_total_parts = onlyDigits($event.target.value);
                           $event.target.value = formatDots(meta.sub_total_parts);
                           onChanged();
                         ">
                </div>
              </td>
            </tr>

            {{-- BLACK BAND --}}
            <tr class="dw-band">
              <td colspan="12"></td>
            </tr>

            {{-- ===== MISCELLANEOUS ===== --}}
            <tr>
              <td colspan="12" class="dw-section">MISCELLANEOUS</td>
            </tr>

            <tr>
              <td colspan="4" class="dw-right dw-bold">CONSUMABLE SUPPLIES</td>

              {{-- 5% of Labour cost -> angka "5" bisa diketik, % dan teks tetap --}}
              <td colspan="7" class="dw-center dw-bold">
                <div class="dw-consumableline">
                  <input type="text"
                        class="dw-consumableinp"
                        x-model="misc.consumable_percent"
                        inputmode="numeric"
                        @input="misc.consumable_percent = onlyDigits(misc.consumable_percent); onChanged()">
                  <span class="dw-consumablesfx">%</span>
                  <span class="dw-consumabletxt">of Labour cost</span>
                </div>
              </td>

              <td>
                <div class="dw-money">
                  <span class="dw-rp">Rp</span>
                  <input type="text" class="dw-inp dw-moneyinp dw-bold"
                         :value="formatDots(misc.consumable_charge)"
                         inputmode="numeric"
                         @input="
                           misc.consumable_charge = onlyDigits($event.target.value);
                           $event.target.value = formatDots(misc.consumable_charge);
                           onChanged();
                         ">
                </div>
              </td>
            </tr>

            <tr class="dw-spacer">
              <td colspan="12"></td>
            </tr>

            {{-- ===== PAINTING ===== --}}
            <tr>
              <td colspan="12" class="dw-section">PAINTING</td>
            </tr>

            <template x-for="(p, i) in paintingRows" :key="p._id">
              <tr>
                <td colspan="3" class="dw-empty"></td>

                <td colspan="2">
                  <input type="text" class="dw-inp dw-bold"
                         x-model="p.item"
                         @input="onChanged()">
                </td>

                <td class="dw-green">
                  <input type="text" class="dw-inp dw-center dw-green dw-bold"
                         x-model="p.qty"
                         inputmode="numeric"
                         @input="p.qty = onlyDigits(p.qty); onChanged()">
                </td>

                <td class="dw-green">
                  <input type="text" class="dw-inp dw-center dw-green dw-bold"
                         x-model="p.uom"
                         @input="p.uom = cleanText(p.uom); onChanged()">
                </td>

                <td class="dw-green">
                  <div class="dw-money">
                    <span class="dw-rp">Rp</span>
                    <input type="text" class="dw-inp dw-moneyinp dw-green dw-bold"
                           :value="formatDots(p.unit_price)"
                           inputmode="numeric"
                           @input="
                             p.unit_price = onlyDigits($event.target.value);
                             $event.target.value = formatDots(p.unit_price);
                             onChanged();
                           ">
                  </div>
                </td>

                <td class="dw-green">
                  <div class="dw-money">
                    <span class="dw-rp">Rp</span>
                    <input type="text" class="dw-inp dw-moneyinp dw-green dw-bold"
                           :value="formatDots(p.total)"
                           inputmode="numeric"
                           @input="
                             p.total = onlyDigits($event.target.value);
                             $event.target.value = formatDots(p.total);
                             onChanged();
                           ">
                  </div>
                </td>

                <td colspan="2" class="dw-empty"></td>

                <td>
                  {{-- TOTAL PAINTING (hanya tampil sekali, seperti screenshot) --}}
                  <div class="dw-money" x-show="i === 0">
                    <span class="dw-rp">Rp</span>
                    <input type="text" class="dw-inp dw-moneyinp dw-bold"
                           :value="formatDots(misc.painting_total)"
                           inputmode="numeric"
                           @input="
                             misc.painting_total = onlyDigits($event.target.value);
                             $event.target.value = formatDots(misc.painting_total);
                             onChanged();
                           ">
                  </div>
                </td>
              </tr>
            </template>

            <tr class="dw-spacer">
              <td colspan="12"></td>
            </tr>

            {{-- ===== External Services ===== --}}
            <tr>
              <td colspan="12" class="dw-section">External Services</td>
            </tr>

            <template x-for="(e, i) in externalRows" :key="e._id">
              <tr>
                <td colspan="5" class="dw-empty"></td>

                <td colspan="3">
                  <input type="text" class="dw-inp dw-bold"
                         x-model="e.service"
                         @input="onChanged()">
                </td>

                <td>
                  <div class="dw-money">
                    <span class="dw-rp">Rp</span>
                    <input type="text" class="dw-inp dw-moneyinp dw-bold"
                           :value="formatDots(e.amount)"
                           inputmode="numeric"
                           @input="
                             e.amount = onlyDigits($event.target.value);
                             $event.target.value = formatDots(e.amount);
                             onChanged();
                           ">
                  </div>
                </td>

                <td colspan="2" class="dw-empty"></td>

                <td>
                  {{-- TOTAL EXTERNAL (sekali) --}}
                  <div class="dw-money" x-show="i === 0">
                    <span class="dw-rp">Rp</span>
                    <input type="text" class="dw-inp dw-moneyinp dw-bold"
                           :value="formatDots(misc.external_total)"
                           inputmode="numeric"
                           @input="
                             misc.external_total = onlyDigits($event.target.value);
                             $event.target.value = formatDots(misc.external_total);
                             onChanged();
                           ">
                  </div>
                </td>
              </tr>
            </template>

            {{-- ===== TOTALS (kanan bawah) ===== --}}
            <tr class="dw-spacer">
              <td colspan="12"></td>
            </tr>

            <tr>
              <td colspan="9" class="dw-empty"></td>
              <td colspan="2" class="dw-bold">TOTAL LABOUR</td>
              <td>
                <div class="dw-money">
                  <span class="dw-rp">Rp</span>
                  <input type="text" class="dw-inp dw-moneyinp dw-bold"
                         :value="formatDots(totals.total_labour)"
                         inputmode="numeric"
                         @input="
                           totals.total_labour = onlyDigits($event.target.value);
                           $event.target.value = formatDots(totals.total_labour);
                           onChanged();
                         ">
                </div>
              </td>
            </tr>

            <tr>
              <td colspan="9" class="dw-empty"></td>
              <td colspan="2" class="dw-bold">TOTAL PARTS</td>
              <td>
                <div class="dw-money">
                  <span class="dw-rp">Rp</span>
                  <input type="text" class="dw-inp dw-moneyinp dw-bold"
                         :value="formatDots(totals.total_parts)"
                         inputmode="numeric"
                         @input="
                           totals.total_parts = onlyDigits($event.target.value);
                           $event.target.value = formatDots(totals.total_parts);
                           onChanged();
                         ">
                </div>
              </td>
            </tr>

            <tr>
              <td colspan="9" class="dw-empty"></td>
              <td colspan="2" class="dw-bold">TOTAL MISCELLANEOUS</td>
              <td>
                <div class="dw-money">
                  <span class="dw-rp">Rp</span>
                  <input type="text" class="dw-inp dw-moneyinp dw-bold"
                         :value="formatDots(totals.total_misc)"
                         inputmode="numeric"
                         @input="
                           totals.total_misc = onlyDigits($event.target.value);
                           $event.target.value = formatDots(totals.total_misc);
                           onChanged();
                         ">
                </div>
              </td>
            </tr>

            <tr>
              <td colspan="9" class="dw-empty"></td>
              <td colspan="2" class="dw-bold">TOTAL BEFORE DISC</td>
              <td>
                <div class="dw-money">
                  <span class="dw-rp">Rp</span>
                  <input type="text" class="dw-inp dw-moneyinp dw-bold"
                         :value="formatDots(totals.total_before_disc)"
                         inputmode="numeric"
                         @input="
                           totals.total_before_disc = onlyDigits($event.target.value);
                           $event.target.value = formatDots(totals.total_before_disc);
                           onChanged();
                         ">
                </div>
              </td>
            </tr>

            <tr>
              <td colspan="9" class="dw-empty"></td>
              <td class="dw-bold">DISCOUNT</td>

              {{-- angka bisa diubah, % tidak bisa --}}
              <td class="dw-green dw-center dw-bold">
                <div class="dw-percent">
                  <input type="text" class="dw-inp dw-center dw-green dw-bold dw-percentinp"
                         x-model="totals.discount_percent"
                         inputmode="numeric"
                         @input="totals.discount_percent = onlyDigits(totals.discount_percent); onChanged()">
                  <span class="dw-percentsign">%</span>
                </div>
              </td>

              <td>
                <div class="dw-money">
                  <span class="dw-rp">Rp</span>
                  <input type="text" class="dw-inp dw-moneyinp dw-bold"
                         :value="formatDots(totals.discount_amount)"
                         inputmode="numeric"
                         @input="
                           totals.discount_amount = onlyDigits($event.target.value);
                           $event.target.value = formatDots(totals.discount_amount);
                           onChanged();
                         ">
                </div>
              </td>
            </tr>

            <tr>
              <td colspan="9" class="dw-empty"></td>
              <td colspan="2" class="dw-bold">TOTAL BEFORE TAX</td>
              <td>
                <div class="dw-money">
                  <span class="dw-rp">Rp</span>
                  <input type="text" class="dw-inp dw-moneyinp dw-bold"
                         :value="formatDots(totals.total_before_tax)"
                         inputmode="numeric"
                         @input="
                           totals.total_before_tax = onlyDigits($event.target.value);
                           $event.target.value = formatDots(totals.total_before_tax);
                           onChanged();
                         ">
                </div>
              </td>
            </tr>

            {{-- SALES TAX: % bisa diubah seperti DISCOUNT --}}
            <tr>
              <td colspan="9" class="dw-empty"></td>
              <td class="dw-bold">SALES TAX</td>

              <td class="dw-green dw-center dw-bold">
                <div class="dw-percent">
                  <input type="text" class="dw-inp dw-center dw-green dw-bold dw-percentinp"
                         x-model="totals.tax_percent"
                         inputmode="numeric"
                         @input="totals.tax_percent = onlyDigits(totals.tax_percent); onChanged()">
                  <span class="dw-percentsign">%</span>
                </div>
              </td>

              <td>
                <div class="dw-money">
                  <span class="dw-rp">Rp</span>
                  <input type="text" class="dw-inp dw-moneyinp dw-bold"
                         :value="formatDots(totals.sales_tax)"
                         inputmode="numeric"
                         readonly>
                </div>
              </td>
            </tr>

            <tr>
              <td colspan="9" class="dw-empty"></td>
              <td colspan="2" class="dw-bold">TOTAL REPAIR CHARGE</td>
              <td>
                <div class="dw-money">
                  <span class="dw-rp">Rp</span>
                  <input type="text" class="dw-inp dw-moneyinp dw-bold"
                         :value="formatDots(totals.total_repair_charge)"
                         inputmode="numeric"
                         readonly>
                </div>
              </td>
            </tr>

          </tbody>
        </table>

      </div>
    </div>
  </div>

  {{-- hidden JSON payload --}}
  <input type="hidden" name="detail_payload" :value="jsonPayload()">
</div>

<style>
  /* ===== SHELL + FULLSCREEN ===== */
  .dw-shell{position:relative;}
  /* ENGINE text (MODEL row 1) */
  .dw-engine{ color:#0b0b0b !important; }
  .dw-shell--fs{
    position:fixed; inset:0; z-index:9999;
    width:100vw; height:100dvh;
    background:#f1f5f9;
    padding:14px;
    display:flex; flex-direction:column;
    gap:12px;
    overflow:hidden !important;
  }

  /* ===== TOP BAR ===== */
  .dw-topbar{
    display:flex; align-items:center; justify-content:space-between;
    gap:12px; flex-wrap:wrap;
    background:#f8fafc; border:1px solid #e5e7eb;
    padding:10px 12px; border-radius:14px;
    margin-top:2px;
  }
  .dw-topbar__left{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
  .dw-badge{display:inline-flex;align-items:center;font-weight:900;font-size:12px;color:#0f172a;padding:7px 12px;border-radius:999px;background:#e2e8f0;}
  .dw-small{font-size:12px;color:#64748b;font-weight:800;}
  .dw-topbar__right{display:flex;align-items:center;gap:8px;}
  .dw-icbtn{height:36px;width:40px;border-radius:12px;border:1px solid #cbd5e1;background:#fff;font-weight:900;cursor:pointer;box-shadow:0 1px 0 rgba(15,23,42,.05);}
  .dw-zoom{width:180px;}
  .dw-pct{height:36px;padding:0 12px;border-radius:12px;border:1px solid #cbd5e1;background:#fff;font-weight:900;cursor:pointer;}

  /* ===== ACTIONS ===== */
  .dw-actions{
    display:flex;gap:10px;flex-wrap:wrap;align-items:center;
    margin:2px 0 4px 0;
  }
  .dw-btn{
    height:42px; padding:0 16px; border-radius:14px;
    border:1px solid #cbd5e1; background:#fff;
    font-weight:900; cursor:pointer; box-shadow:0 1px 0 rgba(15,23,42,.06);
  }
  .dw-btn--primary{background:#2563eb;border-color:#2563eb;color:#fff;}
  .dw-btn--danger{background:#ef4444;border-color:#ef4444;color:#fff;}
  .dw-tip{font-size:12px;color:#64748b;font-weight:800;}

  .dw-target{display:flex;align-items:center;gap:10px;}
  .dw-target__label{font-size:12px;color:#334155;font-weight:900;}
  .dw-select{
    height:42px;
    border-radius:14px;
    border:1px solid #cbd5e1;
    background:#fff;
    padding:0 12px;
    font-weight:900;
    color:#0f172a;
    cursor:pointer;
  }

  .dw-subbar{
    display:flex; align-items:center; justify-content:space-between;
    gap:12px; flex-wrap:wrap;
    background:#fff; border:1px solid #e5e7eb;
    padding:10px 12px; border-radius:14px;
    margin-bottom:6px;
  }
  .dw-cellinfo{font-size:12px;color:#334155;font-weight:800;}

  /* ===== WRAP ===== */
  .dw-wrap{
    border:1px solid #e5e7eb;border-radius:14px;background:#fff;
    overflow:hidden;
    display:flex; flex-direction:column;
  }
  .dw-tablewrap{overflow:auto; max-height:640px;}
  .dw-shell--fs .dw-wrap{flex:1 1 auto;min-height:0;}
  .dw-shell--fs .dw-tablewrap{
    flex:1 1 auto;
    min-height:0;
    max-height:none;
    height:100%;
    overflow:auto;
    -webkit-overflow-scrolling: touch;
  }
  .dw-zoomTarget{transform-origin:0 0; display:inline-block;}

  /* ===== SHEET TABLE ===== */
  .dw-sheet{
    border-collapse:collapse;
    table-layout:fixed;
    width:max-content;
    min-width:100%;
    font-size:12px;
    background:#fff;
  }
  .dw-sheet th, .dw-sheet td{
    border:1px solid #111;
    padding:0;
    vertical-align:middle;
  }

  .dw-hdr th{
    background:#0b0b0b;
    color:#fff;
    font-weight:900;
    text-align:center;
    padding:8px 6px;
    white-space:nowrap;
  }
  .dw-hdr-empty{background:#0b0b0b;}
  .dw-subhdr th{
    background:#fff;
    color:#0b0b0b;
    font-weight:900;
    text-align:center;
    padding:6px 6px;
  }

  .dw-inp{
    width:100%;
    height:34px;
    border:0;
    outline:none;
    padding:0 8px;
    background:transparent;
    font-weight:700;
    color:#111827;
  }
  .dw-inp:focus{
    box-shadow: inset 0 0 0 2px rgba(34,197,94,.95);
  }

  .dw-label{
    padding:6px 8px !important;
    font-weight:900;
    background:#fff;
    color:#0b0b0b;
    white-space:nowrap;
  }

  .dw-empty{background:#fff;}
  .dw-yellow{background:#ffe94d;}
  .dw-cyan{background:#bff3ff;}
  .dw-green{background:#8ddc6a;}
  .dw-center{text-align:center;}
  .dw-right{text-align:right;padding-right:10px !important;}
  .dw-bold{font-weight:900;}

  .dw-spacer td{height:10px;background:#fff;border-left:0;border-right:0;}
  .dw-band td{height:14px;background:#0b0b0b;border-color:#0b0b0b;}
  .dw-section{
    padding:8px 10px !important;
    font-weight:900;
    background:#fff;
    color:#0b0b0b;
    text-align:left;
  }

  .dw-subtotal td{background:#fff;}
  .dw-subtotal .dw-green{background:#8ddc6a;}

  /* money */
  .dw-money{position:relative;display:flex;align-items:center;}
  .dw-rp{
    position:absolute;left:8px;
    font-weight:900;color:#111827;
    pointer-events:none;
  }
  .dw-moneyinp{
    padding-left:30px !important;
    text-align:right !important;
    font-weight:900 !important;
  }

  /* consumable percent inline */
  .dw-consumableline{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:100%;
    height:34px;
    white-space:nowrap;
  }
  .dw-consumableinp{
    width:22px;
    height:24px;
    padding:0;
    border:0 !important;
    background:transparent !important;
    outline:none !important;
    box-shadow:none !important;
    border-radius:0 !important;
    text-align:right;
    font-weight:900;
    color:#111827;
  }
  .dw-consumableinp:focus{ box-shadow:none !important; }
  .dw-consumablesfx{
    margin-left:2px;
    font-weight:900;
    color:#0b0b0b;
  }
  .dw-consumabletxt{
    margin-left:10px;
    font-weight:900;
    color:#0b0b0b;
  }

  /* DISCOUNT / TAX percent */
  .dw-percent{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:0;
    width:100%;
    height:34px;
    white-space:nowrap;
  }
  .dw-percentinp{
    width:40px;
    height:24px;
    padding:0 !important;
    border:0 !important;
    background:transparent !important;
    outline:none !important;
    box-shadow:none !important;
    text-align:right !important;
    font-weight:900 !important;
    color:#111827 !important;
  }
  .dw-percentsign{
    margin-left:2px;
    font-weight:900;
    color:#0b0b0b;
    pointer-events:none;
  }
</style>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('detailWS', (cfg) => ({
    storageKey: cfg.storageKey || ('ccr_detail_ws_' + window.location.pathname),

    // view
    zoom: 100,
    isFs: false,
    _bodyOverflow: null,

    // autosave
    saveStatus: 'Auto-saved --:--:--',
    _tSave: null,

    // active cell info (untuk MAIN area)
    activeRow: 0,
    activeCol: 0,

    // base row index untuk MAIN (biar nav rapi)
    mainBase: 7,

    // row tools target (single tombol pair)
    rowTarget: 'main', // main | painting | external

    // data
    meta: cfg.initialMeta || {},
    mainRows: [],
    paintingRows: [],
    externalRows: [],
    misc: cfg.initialMisc || {},
    totals: cfg.initialTotals || {},

    // lock recalc (biar gak loop)
    _recalcLock: false,

    init() {
      // load DB / initial
      this.mainRows = (Array.isArray(cfg.initialMainRows) ? cfg.initialMainRows : []).map(r => this.makeMainRow(r));
      this.paintingRows = (Array.isArray(cfg.initialPaintingRows) ? cfg.initialPaintingRows : []).map(r => this.makePaintRow(r));
      this.externalRows = (Array.isArray(cfg.initialExternalRows) ? cfg.initialExternalRows : []).map(r => this.makeExtRow(r));

      // fallback local draft kalau DB kosong (optional)
      const d = this.loadDraft();
      if (d && d.meta && (!cfg.initialMainRows || !cfg.initialMainRows.length)) {
        this.meta = d.meta || this.meta;
        this.mainRows = (Array.isArray(d.main_rows) ? d.main_rows : []).map(r => this.makeMainRow(r));
        this.paintingRows = (Array.isArray(d.painting_rows) ? d.painting_rows : []).map(r => this.makePaintRow(r));
        this.externalRows = (Array.isArray(d.external_rows) ? d.external_rows : []).map(r => this.makeExtRow(r));
        this.misc = d.misc || this.misc;
        this.totals = d.totals || this.totals;
        this.saveStatus = d.ts ? ('Auto-saved ' + this.formatTime(new Date(d.ts))) : ('Auto-saved ' + this.formatTime(new Date()));
      } else {
        this.saveStatus = 'Auto-saved ' + this.formatTime(new Date());
      }

      // === FORMULA HITUNG AWAL ===
      this.recalcAll();

      this.$nextTick(() => this.applyZoom());

      window.addEventListener('beforeunload', () => this.saveDraft(true));
      this.bindClearOnSubmit();

      // listen template apply dari Parts Worksheet (langsung update Detail)
      this._onTemplateApplied = (ev) => {
        const d = (ev && ev.detail && typeof ev.detail === 'object') ? ev.detail : {};
        const payload = d.detailPayload || d.detail || d.payload || null;
        if (!payload || typeof payload !== 'object') return;
        this.applyTemplateDetail(payload);
      };
      window.addEventListener('ccr:engineTemplateApplied', this._onTemplateApplied);
    },

    bindClearOnSubmit() {
      const form = this.$el.closest('form');
      if (!form) return;
      if (form.__detailWsClearBound) return;
      form.__detailWsClearBound = true;

      form.addEventListener('submit', () => {
        try { localStorage.removeItem(this.storageKey); } catch(e) {}
      });
    },

    applyTemplateDetail(p){
      // p: payload dari defaults_detail.php {meta, main_rows, painting_rows, external_rows, misc, totals}
      if (!p || typeof p !== 'object') return;

      const meta   = (p.meta && typeof p.meta === 'object') ? p.meta : {};
      const misc   = (p.misc && typeof p.misc === 'object') ? p.misc : {};
      const totals = (p.totals && typeof p.totals === 'object') ? p.totals : {};

      const main = Array.isArray(p.main_rows) ? p.main_rows : (Array.isArray(p.mainRows) ? p.mainRows : []);
      const paint = Array.isArray(p.painting_rows) ? p.painting_rows : (Array.isArray(p.paintingRows) ? p.paintingRows : []);
      const ext = Array.isArray(p.external_rows) ? p.external_rows : (Array.isArray(p.externalRows) ? p.externalRows : []);

      // merge agar default keys tetap ada
      this.meta = Object.assign({}, this.meta || {}, meta);
      this.misc = Object.assign({}, this.misc || {}, misc);
      this.totals = Object.assign({}, this.totals || {}, totals);

      this.mainRows = main.map(r => this.makeMainRow(r));
      this.paintingRows = paint.map(r => this.makePaintRow(r));
      this.externalRows = ext.map(r => this.makeExtRow(r));

      this.rowTarget = 'main';

      // hitung ulang + autosave
      this.recalcAll();
      this.onChanged();

      this.$nextTick(() => {
        try { this.focusCell(this.mainBase, 0); } catch(e) {}
      });
    },

    makeMainRow(r){
      return {
        _id: this.uid(),
        seg: (r.seg ?? ''),
        code: (r.code ?? ''),
        component_desc: (r.component_desc ?? ''),
        work_desc: (r.work_desc ?? ''),
        work_order: (r.work_order ?? ''),
        hours: (r.hours ?? ''),
        labour_charge: (r.labour_charge ?? ''),
        parts_charge: (r.parts_charge ?? ''),
      };
    },
    makePaintRow(r){
      return {
        _id: this.uid(),
        item: (r.item ?? ''),
        qty: (r.qty ?? ''),
        uom: (r.uom ?? ''),
        unit_price: (r.unit_price ?? ''),
        total: (r.total ?? ''),
      };
    },
    makeExtRow(r){
      return {
        _id: this.uid(),
        service: (r.service ?? ''),
        amount: (r.amount ?? ''),
      };
    },

    uid() {
      return 'r_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2,8);
    },

    /* ===== UI ===== */
    toggleFullscreen() {
      this.isFs = !this.isFs;
      if (this.isFs) {
        this._bodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
      } else {
        document.body.style.overflow = this._bodyOverflow ?? '';
      }
      this.$nextTick(() => this.applyZoom());
    },

    applyZoom() {
      const z = Math.max(70, Math.min(150, Number(this.zoom || 100)));
      this.zoom = z;
      const target = this.$refs.zoomTarget;
      if (!target) return;
      target.style.zoom = (z / 100);
    },
    zoomIn(){ this.zoom = Math.min(150, this.zoom + 5); this.applyZoom(); },
    zoomOut(){ this.zoom = Math.max(70, this.zoom - 5); this.applyZoom(); },
    zoomReset(){ this.zoom = 100; this.applyZoom(); },

    /* ===== Row tools (single tombol pair) ===== */
    addRow(){
      if (this.rowTarget === 'painting') return this.addPaintingRow();
      if (this.rowTarget === 'external') return this.addExternalRow();
      return this.addMainRow();
    },
    deleteLastRow(){
      if (this.rowTarget === 'painting') return this.deleteLastPaintingRow();
      if (this.rowTarget === 'external') return this.deleteLastExternalRow();
      return this.deleteLastMainRow();
    },

    addMainRow(){
      this.mainRows.push(this.makeMainRow({}));
      this.onChanged();
      this.$nextTick(() => {
        const rr = this.mainBase + (this.mainRows.length - 1);
        this.focusCell(rr, 0);
      });
    },
    deleteLastMainRow(){
      if (this.mainRows.length <= 1) return;
      this.mainRows.pop();
      this.onChanged();
    },

    addPaintingRow(){
      this.paintingRows.push(this.makePaintRow({}));
      this.onChanged();
    },
    deleteLastPaintingRow(){
      if (this.paintingRows.length <= 1) return;
      this.paintingRows.pop();
      this.onChanged();
    },

    addExternalRow(){
      this.externalRows.push(this.makeExtRow({}));
      this.onChanged();
    },
    deleteLastExternalRow(){
      if (this.externalRows.length <= 1) return;
      this.externalRows.pop();
      this.onChanged();
    },

    /* ===== Active & label (untuk display info) ===== */
    setActive(r,c){
      this.activeRow = Number(r || 0);
      this.activeCol = Number(c || 0);
    },
    cellLabel(){
      const letters = ['A','B','C','D','E','F','G','H','I','J','K','L'];
      const col = letters[this.activeCol] || '?';
      return `${col}${this.activeRow + 1}`;
    },

    /* ===== Navigation (khusus elemen yang punya dw-cell) ===== */
    findCell(r,c){
      return this.$el.querySelector(`[data-cell="1"][data-row="${r}"][data-col="${c}"]`);
    },

    focusCell(r,c){
      const maxCol = 11;
      let cc = c;
      while (cc <= maxCol) {
        const el = this.findCell(r, cc);
        if (el) {
          this.setActive(r, cc);
          el.focus({preventScroll:true});
          el.scrollIntoView({block:'nearest', inline:'nearest'});
          return true;
        }
        cc++;
      }
      cc = 0;
      while (cc <= maxCol) {
        const el = this.findCell(r, cc);
        if (el) {
          this.setActive(r, cc);
          el.focus({preventScroll:true});
          el.scrollIntoView({block:'nearest', inline:'nearest'});
          return true;
        }
        cc++;
      }
      return false;
    },

    moveRight(){
      const maxCol = 11;
      let r = this.activeRow;
      let c = this.activeCol + 1;

      while (r <= (this.mainBase + this.mainRows.length + 2)) {
        while (c <= maxCol) {
          const el = this.findCell(r, c);
          if (el) return this.focusCell(r, c);
          c++;
        }
        r++;
        c = 0;
      }
    },
    moveLeft(){
      let r = this.activeRow;
      let c = this.activeCol - 1;

      while (r >= 0) {
        while (c >= 0) {
          const el = this.findCell(r, c);
          if (el) return this.focusCell(r, c);
          c--;
        }
        r--;
        c = 11;
      }
    },
    moveDown(){
      let r = this.activeRow + 1;
      let c = this.activeCol;

      while (r <= (this.mainBase + this.mainRows.length + 2)) {
        for (let cc=c; cc<=11; cc++){
          const el = this.findCell(r, cc);
          if (el) return this.focusCell(r, cc);
        }
        for (let cc=0; cc<=11; cc++){
          const el = this.findCell(r, cc);
          if (el) return this.focusCell(r, cc);
        }
        r++;
      }
    },
    moveUp(){
      let r = this.activeRow - 1;
      let c = this.activeCol;

      while (r >= 0) {
        for (let cc=c; cc<=11; cc++){
          const el = this.findCell(r, cc);
          if (el) return this.focusCell(r, cc);
        }
        for (let cc=0; cc<=11; cc++){
          const el = this.findCell(r, cc);
          if (el) return this.focusCell(r, cc);
        }
        r--;
      }
    },

    onKey(e){
      const ae = document.activeElement;
      if (!ae || !this.$el.contains(ae)) return;
      if (!(ae.classList && ae.classList.contains('dw-cell'))) return;

      if (e.key === 'Enter') { e.preventDefault(); return this.moveRight(); }
      if (e.key === 'Tab') { e.preventDefault(); return e.shiftKey ? this.moveLeft() : this.moveRight(); }

      if (e.key === 'ArrowDown') { e.preventDefault(); return this.moveDown(); }
      if (e.key === 'ArrowUp') { e.preventDefault(); return this.moveUp(); }
      if (e.key === 'ArrowLeft') { e.preventDefault(); return this.moveLeft(); }
      if (e.key === 'ArrowRight') { e.preventDefault(); return this.moveRight(); }
    },

    /* ===== autosave ===== */
    onChanged() {
      // FORMULA otomatis setiap ada perubahan
      this.recalcAll();

      clearTimeout(this._tSave);
      this._tSave = setTimeout(() => this.saveDraft(true), 600);
    },
    saveDraft(isAuto) {
      const payload = this.payloadObject();
      payload.ts = Date.now();
      try { localStorage.setItem(this.storageKey, JSON.stringify(payload)); } catch(e) {}
      this.saveStatus = (isAuto ? 'Auto-saved ' : 'Saved ') + this.formatTime(new Date(payload.ts));
    },
    loadDraft() {
      try {
        const raw = localStorage.getItem(this.storageKey);
        if (!raw) return null;
        return JSON.parse(raw);
      } catch(e) { return null; }
    },
    formatTime(dt) {
      const pad = n => String(n).padStart(2,'0');
      return pad(dt.getHours()) + ':' + pad(dt.getMinutes()) + ':' + pad(dt.getSeconds());
    },

    /* ===== helpers ===== */
    onlyDigits(v){ return String(v||'').replace(/[^\d]/g,''); },
    cleanText(v){
      return String(v||'')
        .replace(/\u00A0/g,' ')
        .replace(/[ \t]{2,}/g,' ');
    },
    formatDots(v){
      v = this.onlyDigits(v);
      if(v === '') return '';
      return v.replace(/\B(?=(\d{3})+(?!\d))/g,'.');
    },
    cleanHours(v){
      v = String(v||'').replace(/[^0-9,\.]/g,'');
      return v;
    },

    // ===== FORMULA HELPERS =====
    toInt(v){
      const d = this.onlyDigits(v);
      return d === '' ? 0 : parseInt(d, 10);
    },
    clamp(n, min, max){
      n = Number(n || 0);
      return Math.max(min, Math.min(max, n));
    },
    parseHours(v){
      v = String(v || '').trim();
      if (!v) return 0;
      v = v.replace(/\./g, '').replace(',', '.');
      const x = parseFloat(v);
      return isNaN(x) ? 0 : x;
    },
    formatHours(n){
      const fixed = (Number(n || 0)).toFixed(2);
      return fixed.replace('.', ',');
    },

    // ===== FORMULA UTAMA =====
    recalcAll(){
      if (this._recalcLock) return;
      this._recalcLock = true;

      // 1) MAIN: Sub Total HOURS
      let sumHours = 0;
      for (const r of (this.mainRows || [])) {
        sumHours += this.parseHours(r.hours);
      }
      this.meta.sub_total_hours = this.formatHours(sumHours);

      // 2) MAIN: Sub Total LABOUR
      //    - kalau ada input labour per-row -> pakai SUM rows
      //    - kalau SUM rows = 0 -> jangan overwrite default meta.sub_total_labour (13.560.000)
      let sumLabourRows = 0;
      for (const r of (this.mainRows || [])) {
        sumLabourRows += this.toInt(r.labour_charge);
      }

      const currentMetaLabour = this.toInt(this.meta.sub_total_labour);
      const labourBase = (sumLabourRows > 0) ? sumLabourRows : currentMetaLabour;

      if (sumLabourRows > 0) {
        this.meta.sub_total_labour = String(sumLabourRows);
      } else {
        if (!this.meta.sub_total_labour || currentMetaLabour === 0) {
          this.meta.sub_total_labour = String(labourBase);
        }
      }

      // 3) MAIN: Parts subtotal
      //    - kalau user isi parts_charge di main table -> dijumlahkan
      //    - kalau kosong semua -> biarkan meta.sub_total_parts (biasanya dari Parts Worksheet)
      let sumPartsMain = 0;
      for (const r of (this.mainRows || [])) {
        sumPartsMain += this.toInt(r.parts_charge);
      }
      if (!this.meta.sub_total_parts) this.meta.sub_total_parts = '0';
      if (sumPartsMain > 0) {
        this.meta.sub_total_parts = String(sumPartsMain);
      }

      // 4) PAINTING: total per row = qty * unit_price, dan subtotal painting
      let paintTotal = 0;
      for (const p of (this.paintingRows || [])) {
        const qty = this.toInt(p.qty);
        const unit = this.toInt(p.unit_price);
        const rowTotal = qty * unit;
        p.total = String(rowTotal);
        paintTotal += rowTotal;
      }
      this.misc.painting_total = String(paintTotal);

      // 5) EXTERNAL: subtotal external
      let extTotal = 0;
      for (const e of (this.externalRows || [])) {
        extTotal += this.toInt(e.amount);
      }
      this.misc.external_total = String(extTotal);

      // 6) CONSUMABLE: consumable_charge = percent * (Sub Total Labour)
      const consPct = this.clamp(this.toInt(this.misc.consumable_percent), 0, 100);
      this.misc.consumable_percent = String(consPct);
      const consCharge = Math.round(labourBase * (consPct / 100));
      this.misc.consumable_charge = String(consCharge);

      // 7) TOTALS
      const totalLabour = labourBase;
      const totalParts  = this.toInt(this.meta.sub_total_parts);
      const totalMisc   = consCharge + paintTotal + extTotal;

      this.totals.total_labour = String(totalLabour);
      this.totals.total_parts  = String(totalParts);
      this.totals.total_misc   = String(totalMisc);

      const totalBeforeDisc = totalLabour + totalParts + totalMisc;
      this.totals.total_before_disc = String(totalBeforeDisc);

      // 8) DISCOUNT amount
      const discPct = this.clamp(this.toInt(this.totals.discount_percent), 0, 100);
      this.totals.discount_percent = String(discPct);
      const discAmount = Math.round(totalBeforeDisc * (discPct / 100));
      this.totals.discount_amount = String(discAmount);

      // 9) TOTAL BEFORE TAX
      const beforeTax = Math.max(0, totalBeforeDisc - discAmount);
      this.totals.total_before_tax = String(beforeTax);

      // 10) SALES TAX = tax_percent% dari TOTAL BEFORE TAX
      const taxPct = this.clamp(this.toInt(this.totals.tax_percent), 0, 100);
      this.totals.tax_percent = String(taxPct);
      const salesTax = Math.round(beforeTax * (taxPct / 100));
      this.totals.sales_tax = String(salesTax);

      // 11) TOTAL REPAIR CHARGE
      const totalRepair = beforeTax + salesTax;
      this.totals.total_repair_charge = String(totalRepair);

      this._recalcLock = false;
    },

    payloadObject(){
      return {
        meta: this.meta || {},
        main_rows: (this.mainRows || []).map(r => ({
          seg: String(r.seg||''),
          code: String(r.code||''),
          component_desc: String(r.component_desc||''),
          work_desc: String(r.work_desc||''),
          work_order: String(r.work_order||''),
          hours: String(r.hours||''),
          labour_charge: this.onlyDigits(r.labour_charge),
          parts_charge: this.onlyDigits(r.parts_charge),
        })),
        painting_rows: (this.paintingRows || []).map(p => ({
          item: String(p.item||''),
          qty: this.onlyDigits(p.qty),
          uom: String(p.uom||''),
          unit_price: this.onlyDigits(p.unit_price),
          total: this.onlyDigits(p.total),
        })),
        external_rows: (this.externalRows || []).map(e => ({
          service: String(e.service||''),
          amount: this.onlyDigits(e.amount),
        })),
        misc: this.misc || {},
        totals: this.totals || {},
      };
    },

    jsonPayload(){
      return JSON.stringify(this.payloadObject());
    },
  }));
});
</script>
