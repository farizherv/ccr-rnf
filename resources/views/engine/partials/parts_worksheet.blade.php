{{-- =========================================================
TAB: PARTS & LABOUR WORKSHEET (EXCEL-LIKE)
File: resources/views/engine/partials/parts_worksheet.blade.php
========================================================= --}}

@php
  // aman untuk create page (kalau $report belum ada)
  $payload = ($report->parts_payload ?? []);
  if (!is_array($payload)) $payload = [];

  $parts   = $payload['rows'] ?? [];
  $styles  = $payload['styles'] ?? [];
  $notes   = $payload['notes'] ?? [];
  $noUnit  = $payload['meta']['no_unit'] ?? '';

  // footer (bisa manual / auto)
  $footerTotal        = $payload['meta']['footer_total'] ?? '';
  $footerExtended     = $payload['meta']['footer_extended'] ?? '';
  $footerTotalMode    = $payload['meta']['footer_total_mode'] ?? '';     // 'manual' | 'auto' | ''
  $footerExtendedMode = $payload['meta']['footer_extended_mode'] ?? '';  // 'manual' | 'auto' | ''

  // ===== TEMPLATE META (Opsi A) =====
  $initialTemplateKey = $payload['meta']['template_key'] ?? '';
  $initialTemplateVersion = $payload['meta']['template_version'] ?? '';

  $storageKey = 'ccr_parts_ws_' . md5(url()->current());

  // ===== DROPDOWN LISTS =====
  $partDescList = [];
  $partDescListPath = resource_path('data/part_description_list.php');
  if (file_exists($partDescListPath)) {
      $tmp = include $partDescListPath;
      if (is_array($tmp)) $partDescList = $tmp;
  } else {
      // fallback kalau file kamu kebetulan namanya part_description.php
      $fallback = resource_path('data/part_description.php');
      if (file_exists($fallback)) {
          $tmp = include $fallback;
          if (is_array($tmp)) $partDescList = $tmp;
      }
  }

  // Part Section list dari resources/data/part_section.php
  $partSectionList = [];
  $partSectionPath = resource_path('data/part_section.php');
  if (file_exists($partSectionPath)) {
      $tmp = include $partSectionPath;
      if (is_array($tmp)) $partSectionList = $tmp;
  } else {
      $partSectionList = ['Update Harga Beli','Belum ada harga jual','Harga beli lebih tinggi','Stock Office','Other'];
  }

  // UOM list dari file resources/data/uom_list.php
  $uomList = [];
  $uomListPath = resource_path('data/uom_list.php');
  if (file_exists($uomListPath)) {
      $tmp = include $uomListPath;
      if (is_array($tmp)) $uomList = $tmp;
  }
  if (!is_array($uomList) || !count($uomList)) {
      $uomList = ['ea', 'set', 'ea/set', 'pcs', 'unit'];
  }

  // rapihin list (trim + unique + sort natural)
  $normalize = function($arr){
    if (!is_array($arr)) return [];
    $arr = array_map(function($x){ return trim((string)$x); }, $arr);
    $arr = array_filter($arr, function($x){ return $x !== ''; });
    $arr = array_values(array_unique($arr));
    sort($arr, SORT_NATURAL | SORT_FLAG_CASE);
    return $arr;
  };

  $partDescList    = $normalize($partDescList);
  $partSectionList = $normalize($partSectionList);
  $uomList         = $normalize($uomList);

  // ===== TEMPLATE LIST (Opsi A) =====
  $templates = [];
  $templateDefaultsMap = []; // key => defaults for parts
  try {
    if (class_exists(\App\Support\WorksheetTemplates\EngineTemplateRepo::class)) {
      $templates = \App\Support\WorksheetTemplates\EngineTemplateRepo::list();
      if (!is_array($templates)) $templates = [];
    }
  } catch (\Throwable $e) {
    $templates = [];
  }

  // normalisasi templates minimal (biar aman di JS)
  $templates = array_values(array_filter(array_map(function($t){
    if (!is_array($t)) return null;
    $key = $t['key'] ?? '';
    if (!$key) return null;
    return [
      'key' => (string)$key,
      'name' => (string)($t['name'] ?? $key),
      'version' => (string)($t['version'] ?? ($t['latest'] ?? '')),
      'notes' => (string)($t['notes'] ?? ''),
    ];
  }, $templates)));

  // pilih default template kalau meta kosong
  if (!$initialTemplateKey && count($templates)) {
    $initialTemplateKey = $templates[0]['key'];
    $initialTemplateVersion = $templates[0]['version'] ?? '';
  }

  // load defaults untuk semua templates (biar popup bisa apply tanpa ajax)
  // defaults map hanya untuk "parts" (worksheet ini)
  if (count($templates) && class_exists(\App\Support\WorksheetTemplates\EngineTemplateRepo::class)) {
    foreach ($templates as $t) {
      $k = $t['key'];
      try {
        $defs = \App\Support\WorksheetTemplates\EngineTemplateRepo::defaults($k);

        // dukung beberapa bentuk return:
        // 1) ['parts' => [...], 'detail' => [...]]
        // 2) ['defaults' => ['parts' => [...]]]
        // 3) langsung payload parts
        $partsDef = null;
        $detailDef = null;

        if (is_array($defs)) {
          if (isset($defs['parts']) && is_array($defs['parts'])) $partsDef = $defs['parts'];
          elseif (isset($defs['defaults']['parts']) && is_array($defs['defaults']['parts'])) $partsDef = $defs['defaults']['parts'];

          if (isset($defs['detail']) && is_array($defs['detail'])) $detailDef = $defs['detail'];
          elseif (isset($defs['defaults']['detail']) && is_array($defs['defaults']['detail'])) $detailDef = $defs['defaults']['detail'];

          // fallback: kalau struktur belum pakai keys parts/detail
          if ($partsDef === null && $detailDef === null) $partsDef = $defs;
          if ($partsDef === null) $partsDef = [];
          if ($detailDef === null) $detailDef = [];
        }
        // pastikan bentuk payload parts minimal
        if (!is_array($partsDef)) $partsDef = [];
        if (!isset($partsDef['rows']) && !isset($partsDef['meta'])) {
          // kalau default berbentuk list rows langsung
          if (array_is_list($partsDef)) {
            $partsDef = ['rows' => $partsDef, 'styles' => [], 'notes' => [], 'meta' => []];
          } else {
            $partsDef = ['rows' => [], 'styles' => [], 'notes' => [], 'meta' => []];
          }
        }
        $templateDefaultsMap[$k] = array_merge($partsDef, ['_detail' => $detailDef ?? []]);
      } catch (\Throwable $e) {
        // kalau default tidak ada, kasih kosong (tetap aman)
        $templateDefaultsMap[$k] = ['rows' => [], 'styles' => [], 'notes' => [], 'meta' => [], '_detail' => []];
      }
    }
  }
@endphp

<div x-show="tab==='parts'" x-cloak
     x-data="partsWS({
        initialNoUnit: @js($noUnit),
        initialRows: @js($parts),
        initialStyles: @js($styles),
        initialNotes: @js($notes),

        initialFooterTotal: @js($footerTotal),
        initialFooterExtended: @js($footerExtended),
        initialFooterTotalMode: @js($footerTotalMode),
        initialFooterExtendedMode: @js($footerExtendedMode),

        partDescList: @js($partDescList),
        partSectionList: @js($partSectionList),

        // templates (Opsi A)
        templates: @js($templates),
        templateDefaultsMap: @js($templateDefaultsMap),
        initialTemplateKey: @js($initialTemplateKey),
        initialTemplateVersion: @js($initialTemplateVersion),

        storageKey: @js($storageKey),
     })"
     class="box ws-shell"
     :class="isFs ? 'ws-shell--fs' : ''"
     @keydown.capture="onKey($event)">

  <h3 style="margin-bottom:6px;">Parts &amp; Labour Worksheet</h3>
  <p style="font-size:13px; color:#64748b; margin-bottom:14px;">
    Input manual seperti Excel → export ke template Excel (Accounting Rp + style).
  </p>

  {{-- TOP BAR: autosave + zoom + template --}}
  <div class="ws-topbar">
    <div class="ws-topbar__left">
      <span class="ws-badge" x-text="saveStatus"></span>
      <span class="ws-small">AutoSave ON</span>

      <span class="ws-divider ws-divider--top">|</span>

      <button type="button" class="ws-chip"
              @click="openTemplateModal()"
              :title="templateNotes()">
        <span class="ws-chip__k">Template</span>
        <span class="ws-chip__v" x-text="templateName()"></span>
        <span class="ws-chip__caret">▾</span>
      </button>
    </div>

    <div class="ws-topbar__right">
      <button type="button" class="ws-icbtn" @click="toggleFullscreen()">⛶</button>

      <button type="button" class="ws-icbtn" @click="zoomOut()">−</button>
      <input type="range" min="70" max="150" step="5" class="ws-zoom"
             x-model.number="zoom" @input="applyZoom()">
      <button type="button" class="ws-icbtn" @click="zoomIn()">+</button>

      <button type="button" class="ws-pct" @click="zoomReset()">
        <span x-text="zoom + '%'"></span>
      </button>
    </div>
  </div>

  {{-- ACTION BUTTONS --}}
  <div class="ws-actions">
    <button type="button" class="ws-btn ws-btn--primary" @click="addRow()">
      + Tambah Baris
    </button>

    <button type="button" class="ws-btn ws-btn--danger" @click="deleteLast()">
      Hapus Terakhir
    </button>

    <button type="button" class="ws-btn ws-btn--danger2"
            @click="deleteSelectedRow()">
      Hapus Terpilih
    </button>

    <div class="ws-tip">
      Enter → kanan • Tab/Shift+Tab → kanan/kiri • Arrow ↑↓←→ → pindah cell
    </div>
  </div>

  {{-- SUB BAR --}}
  <div class="ws-subbar">
    <div class="ws-subbar__left">
      <div class="ws-field">
        <label>No. Unit</label>
        <input class="ws-input"
               x-model="noUnit"
               @input="onChanged()"
               placeholder="Contoh: 4D34T-S64029">
      </div>

      <div class="ws-field ws-field--rows">
        <label>Rows</label>
        <input class="ws-input ws-input--rows"
               inputmode="numeric"
               x-model="rowsTarget"
               @input="rowsTarget = onlyDigits(rowsTarget)"
               @change="applyRowsTarget()"
               @keydown.enter.prevent="applyRowsTarget()"
               placeholder="20">
      </div>

      <div class="ws-rowsinfo">
        Current: <b x-text="rows.length"></b>
      </div>

      <div class="ws-divider">|</div>

      <div class="ws-cellinfo">
        Cell: <b x-text="cellLabel()"></b>
      </div>
    </div>

    <div class="ws-subbar__right">
      <div class="ws-tools" role="toolbar" aria-label="Excel tools">
        {{-- Bold --}}
        <button type="button" class="ws-tool"
                :class="toolOn('bold')?'is-on':''"
                @click="toggleFmt('bold')">
          <span class="ws-ico ws-ico-text"><b>B</b></span>
        </button>

        {{-- Italic --}}
        <button type="button" class="ws-tool"
                :class="toolOn('italic')?'is-on':''"
                @click="toggleFmt('italic')">
          <span class="ws-ico ws-ico-text"><i>I</i></span>
        </button>

        {{-- Underline --}}
        <button type="button" class="ws-tool"
                :class="toolOn('underline')?'is-on':''"
                @click="toggleFmt('underline')">
          <span class="ws-ico ws-ico-text"><u>U</u></span>
        </button>

        <span class="ws-sep"></span>

        {{-- Align Left --}}
        <button type="button" class="ws-tool"
                :class="toolOnAlign('left')?'is-on':''"
                @click="setAlign('left')">
          <svg class="ws-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M4 6h14M4 10h10M4 14h14M4 18h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>

        {{-- Align Center --}}
        <button type="button" class="ws-tool"
                :class="toolOnAlign('center')?'is-on':''"
                @click="setAlign('center')">
          <svg class="ws-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M5 6h14M7 10h10M5 14h14M7 18h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>

        {{-- Align Right --}}
        <button type="button" class="ws-tool"
                :class="toolOnAlign('right')?'is-on':''"
                @click="setAlign('right')">
          <svg class="ws-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M6 6h14M10 10h10M6 14h14M10 18h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>

        <span class="ws-sep"></span>

        {{-- Font Color --}}
        <div class="ws-pop" @click.outside="fontOpen=false">
          <button type="button" class="ws-tool"
                  :class="toolHas('color')?'is-on':''"
                  @click="fontOpen=!fontOpen; fillOpen=false; noteOpen=false">
            <svg class="ws-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M7 19h10M9 17l3-10 3 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span class="ws-swatch" :style="{backgroundColor: activeStyle().color || '#111827'}"></span>
          </button>

          <div class="ws-popover" x-show="fontOpen" x-transition>
            <div class="ws-pop-head">Font Color</div>
            <div class="ws-pop-actions">
              <button type="button" class="ws-pop-action" @click="setFontColor('')">Automatic</button>
            </div>

            <div class="ws-colors">
              <template x-for="c in themeColors" :key="'f_'+c">
                <button type="button" class="ws-color"
                        :style="{backgroundColor:c}"
                        @click="setFontColor(c); fontOpen=false"></button>
              </template>
            </div>

            <div class="ws-pop-sub">Standard</div>
            <div class="ws-colors">
              <template x-for="c in standardColors" :key="'fs_'+c">
                <button type="button" class="ws-color"
                        :style="{backgroundColor:c}"
                        @click="setFontColor(c); fontOpen=false"></button>
              </template>
            </div>

            <div class="ws-pop-sub">More…</div>
            <input type="color" class="ws-colorinput"
                   @input="setFontColor($event.target.value)">
          </div>
        </div>

        {{-- Fill Color --}}
        <div class="ws-pop" @click.outside="fillOpen=false">
          <button type="button" class="ws-tool"
                  :class="toolHas('bg')?'is-on':''"
                  @click="fillOpen=!fillOpen; fontOpen=false; noteOpen=false">
            <svg class="ws-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M7 7l5-5 5 5-5 5-5-5z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
              <path d="M4 20h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M12 12v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span class="ws-swatch" :style="{backgroundColor: activeStyle().bg || 'transparent', borderColor: activeStyle().bg ? '#cbd5e1' : '#111827'}"></span>
          </button>

          <div class="ws-popover" x-show="fillOpen" x-transition>
            <div class="ws-pop-head">Fill Color</div>
            <div class="ws-pop-actions">
              <button type="button" class="ws-pop-action" @click="setFill('')">No Fill</button>
            </div>

            <div class="ws-colors">
              <template x-for="c in themeColors" :key="'b_'+c">
                <button type="button" class="ws-color"
                        :style="{backgroundColor:c}"
                        @click="setFill(c); fillOpen=false"></button>
              </template>
            </div>

            <div class="ws-pop-sub">Standard</div>
            <div class="ws-colors">
              <template x-for="c in standardColors" :key="'bs_'+c">
                <button type="button" class="ws-color"
                        :style="{backgroundColor:c}"
                        @click="setFill(c); fillOpen=false"></button>
              </template>
            </div>

            <div class="ws-pop-sub">More…</div>
            <input type="color" class="ws-colorinput"
                   @input="setFill($event.target.value)">
          </div>
        </div>

        {{-- NOTE --}}
        <div class="ws-pop" @click.outside="noteOpen=false">
          <button type="button" class="ws-tool"
                  :class="hasNote(activeKey()) ? 'is-on' : ''"
                  @click="toggleNotePopover(); fontOpen=false; fillOpen=false">
            🗒️
          </button>

          <div class="ws-popover" x-show="noteOpen" x-transition style="width:320px;">
            <div class="ws-pop-head">Note — <span x-text="cellLabel()"></span></div>
            <textarea class="ws-note-text"
                      x-model="noteText"
                      placeholder="Tulis catatan seperti Excel…"></textarea>
            <div class="ws-note-actions">
              <button type="button" class="ws-pop-action" @click="saveNote()">Save</button>
              <button type="button" class="ws-pop-action" @click="removeNote()">Remove</button>
              <button type="button" class="ws-pop-action" @click="noteOpen=false">Close</button>
            </div>
            <div class="ws-pop-sub" style="margin-top:8px;">
              Tip: Ctrl+M untuk buka note di cell aktif.
            </div>
          </div>
        </div>

        <span class="ws-sep"></span>

        {{-- Clear format --}}
        <button type="button" class="ws-tool ws-tool--ghost"
                @click="clearFormat()">
          Tx
        </button>
      </div>
    </div>
  </div>

  {{-- SHEET --}}
  <div class="ws-wrap">
    {{-- datalist UOM (tetap native) --}}
    <datalist id="ws_uom_list">
      @foreach($uomList as $opt)
        <option value="{{ $opt }}"></option>
      @endforeach
    </datalist>

    {{-- datalist Part Description (native seperti UOM) --}}
    <datalist id="ws_part_desc_list">
      @foreach($partDescList as $opt)
        <option value="{{ $opt }}"></option>
      @endforeach
    </datalist>

    {{-- datalist Part Section (native seperti UOM) --}}
    <datalist id="ws_part_section_list">
      @foreach($partSectionList as $opt)
        <option value="{{ $opt }}"></option>
      @endforeach
    </datalist>

    <div class="ws-tablewrap"
         x-ref="tablewrap"
         @mousedown="onMouseDownOutside($event)"
         @mouseover="onHoverNote($event)"
         @mouseout="onLeaveNote($event)">

      <div class="ws-zoomTarget" x-ref="zoomTarget">
        <table class="ws-table">
          <thead>
            <tr>
              <th style="width:70px;">Items<br>No</th>
              <th style="width:80px;">Qty</th>
              <th style="width:100px;">UOM</th>
              <th style="width:180px;">Part Number</th>
              <th style="width:240px;">Part Description</th>
              <th style="width:190px;">Part Section</th>
              <th style="width:180px;">Purchase Price</th>
              <th style="width:190px;">Total</th>
              <th style="width:180px;">Sales Price</th>
              <th style="width:180px;">Extended Price</th>
            </tr>
          </thead>

          <tbody>
            <template x-for="(r, i) in rows" :key="r._id">
              <tr>
                {{-- A --}}
                <td>
                  <div class="ws-box ws-box--no ws-cell"
                       tabindex="0"
                       :class="cellClass(i,0)"
                       :style="cellStyle(i,0)"
                       data-cell="1" :data-row="i" data-col="0"
                       @focus="setActive(i,0,true)"
                       @mousedown="onCellMouseDown($event,i,0)"
                       @mouseenter="onCellMouseEnter($event,i,0)"
                       @click="$event.currentTarget.focus()">
                    <span x-text="i+1"></span>
                  </div>
                </td>

                {{-- B --}}
                <td>
                  <input type="text" class="ws-inp ws-inp--center ws-cell"
                         :class="cellClass(i,1)"
                         :style="cellStyle(i,1)"
                         data-cell="1" :data-row="i" data-col="1"
                         @focus="setActive(i,1,true)"
                         @mousedown="onCellMouseDown($event,i,1)"
                         @mouseenter="onCellMouseEnter($event,i,1)"
                         x-model="r.qty"
                         @input="
                           r.qty = onlyDigits(r.qty);
                           recalcRow(i,'qty');
                           onChanged();
                         "
                         inputmode="numeric" placeholder="0">
                </td>

                {{-- C (UOM dropdown + bisa ketik) --}}
                <td>
                  <input type="text" class="ws-inp ws-inp--center ws-cell"
                         list="ws_uom_list"
                         autocomplete="off"
                         :class="cellClass(i,2)"
                         :style="cellStyle(i,2)"
                         data-cell="1" :data-row="i" data-col="2"
                         @focus="setActive(i,2,true)"
                         @mousedown="onCellMouseDown($event,i,2)"
                         @mouseenter="onCellMouseEnter($event,i,2)"
                         x-model="r.uom"
                         @input="r.uom = cleanText(r.uom); onChanged()"
                         placeholder="ea/set">
                </td>

                {{-- D --}}
                <td>
                  <input type="text" class="ws-inp ws-cell"
                         autocomplete="off"
                         :class="cellClass(i,3)"
                         :style="cellStyle(i,3)"
                         data-cell="1" :data-row="i" data-col="3"
                         @focus="setActive(i,3,true)"
                         @mousedown="onCellMouseDown($event,i,3)"
                         @mouseenter="onCellMouseEnter($event,i,3)"
                         x-model="r.part_number"
                         @input="r.part_number = cleanText(r.part_number); onChanged()">
                </td>

                {{-- E (Part Description dropdown ketik: datalist seperti UOM) --}}
                <td>
                  <input type="text" class="ws-inp ws-cell"
                         list="ws_part_desc_list"
                         autocomplete="off"
                         :class="cellClass(i,4)"
                         :style="cellStyle(i,4)"
                         data-cell="1" :data-row="i" data-col="4"
                         @focus="setActive(i,4,true)"
                         @mousedown="onCellMouseDown($event,i,4)"
                         @mouseenter="onCellMouseEnter($event,i,4)"
                         x-model="r.part_description"
                         @input="r.part_description = cleanText(r.part_description); onChanged()">
                </td>

                {{-- F (Part Section dropdown ketik: datalist seperti UOM) --}}
                <td>
                  <input type="text" class="ws-inp ws-cell"
                         list="ws_part_section_list"
                         autocomplete="off"
                         :class="cellClass(i,5)"
                         :style="cellStyle(i,5)"
                         data-cell="1" :data-row="i" data-col="5"
                         @focus="setActive(i,5,true)"
                         @mousedown="onCellMouseDown($event,i,5)"
                         @mouseenter="onCellMouseEnter($event,i,5)"
                         x-model="r.part_section"
                         @input="r.part_section = cleanText(r.part_section); onChanged()">
                </td>

                {{-- G (Purchase Price) --}}
                <td>
                  <div class="money">
                    <span class="rp" :style="rpStyle(i,6)">Rp</span>
                    <input type="text" class="ws-inp moneyinput ws-cell"
                           :class="cellClass(i,6)"
                           :style="cellStyle(i,6)"
                           data-cell="1" :data-row="i" data-col="6"
                           @focus="setActive(i,6,true)"
                           @mousedown="onCellMouseDown($event,i,6)"
                           @mouseenter="onCellMouseEnter($event,i,6)"
                           :value="formatDots(r.purchase_price)"
                           @input="
                             r.purchase_price = onlyDigits($event.target.value);
                             $event.target.value = formatDots(r.purchase_price);
                             recalcRow(i,'purchase');
                             onChanged();
                           "
                           inputmode="numeric"
                           placeholder="0">
                  </div>
                </td>

                {{-- H (Total = Qty * Purchase, auto tapi bisa override) --}}
                <td>
                  <div class="money">
                    <span class="rp" :style="rpStyle(i,7)">Rp</span>
                    <input type="text" class="ws-inp moneyinput ws-cell"
                           :class="cellClass(i,7)"
                           :style="cellStyle(i,7)"
                           data-cell="1" :data-row="i" data-col="7"
                           @focus="setActive(i,7,true)"
                           @mousedown="onCellMouseDown($event,i,7)"
                           @mouseenter="onCellMouseEnter($event,i,7)"
                           :value="formatDots(r.total)"
                           @input="
                             r.total = onlyDigits($event.target.value);
                             $event.target.value = formatDots(r.total);
                             setManual(i,'total', r.total);
                             updateAutoFooters();
                             onChanged();
                           "
                           inputmode="numeric"
                           placeholder="0">
                  </div>
                </td>

                {{-- I (Sales Price) --}}
                <td>
                  <div class="money">
                    <span class="rp" :style="rpStyle(i,8)">Rp</span>
                    <input type="text" class="ws-inp moneyinput ws-cell"
                           :class="cellClass(i,8)"
                           :style="cellStyle(i,8)"
                           data-cell="1" :data-row="i" data-col="8"
                           @focus="setActive(i,8,true)"
                           @mousedown="onCellMouseDown($event,i,8)"
                           @mouseenter="onCellMouseEnter($event,i,8)"
                           :value="formatDots(r.sales_price)"
                           @input="
                             r.sales_price = onlyDigits($event.target.value);
                             $event.target.value = formatDots(r.sales_price);
                             recalcRow(i,'sales');
                             onChanged();
                           "
                           inputmode="numeric"
                           placeholder="0">
                  </div>
                </td>

                {{-- J (Extended = Qty * Sales, auto tapi bisa override) --}}
                <td>
                  <div class="money">
                    <span class="rp" :style="rpStyle(i,9)">Rp</span>
                    <input type="text" class="ws-inp moneyinput ws-cell"
                           :class="cellClass(i,9)"
                           :style="cellStyle(i,9)"
                           data-cell="1" :data-row="i" data-col="9"
                           @focus="setActive(i,9,true)"
                           @mousedown="onCellMouseDown($event,i,9)"
                           @mouseenter="onCellMouseEnter($event,i,9)"
                           :value="formatDots(r.extended_price)"
                           @input="
                             r.extended_price = onlyDigits($event.target.value);
                             $event.target.value = formatDots(r.extended_price);
                             setManual(i,'extended', r.extended_price);
                             updateAutoFooters();
                             onChanged();
                           "
                           inputmode="numeric"
                           placeholder="0">
                  </div>
                </td>
              </tr>
            </template>
          </tbody>

          {{-- FOOTER: otomatis sum (tapi tetap bisa override: isi angka => manual, kosongkan => balik auto) --}}
          <tfoot>
            <tr>
              <td colspan="7" class="ws-tfoot-pad"></td>

              {{-- H footer total --}}
              <td class="ws-tfoot-money">
                <div class="money">
                  <span class="rp">Rp</span>
                  <input type="text" class="ws-inp moneyinput ws-inp--tfoot"
                         :value="formatDots(displayFooterTotal())"
                         @input="
                           footerTotal = onlyDigits($event.target.value);
                           $event.target.value = formatDots(footerTotal);
                           footerTotalManual = (footerTotal !== '');
                           if (!footerTotalManual) footerTotal = '';
                           onChanged();
                         "
                         inputmode="numeric"
                         placeholder="0">
                </div>
              </td>

              {{-- I blank --}}
              <td class="ws-tfoot-pad"></td>

              {{-- J footer extended --}}
              <td class="ws-tfoot-money">
                <div class="money">
                  <span class="rp">Rp</span>
                  <input type="text" class="ws-inp moneyinput ws-inp--tfoot"
                         :value="formatDots(displayFooterExtended())"
                         @input="
                           footerExtended = onlyDigits($event.target.value);
                           $event.target.value = formatDots(footerExtended);
                           footerExtendedManual = (footerExtended !== '');
                           if (!footerExtendedManual) footerExtended = '';
                           onChanged();
                         "
                         inputmode="numeric"
                         placeholder="0">
                </div>
              </td>
            </tr>
          </tfoot>

        </table>
      </div>
    </div>

    {{-- NOTE hover popup --}}
    <div class="ws-notehover" x-show="noteHover.show" x-transition
         :style="{ left: noteHover.x + 'px', top: noteHover.y + 'px' }">
      <div class="ws-notehover__title">Note</div>
      <div class="ws-notehover__text" x-text="noteHover.text"></div>
    </div>
  </div>

  {{-- TEMPLATE MODAL (Opsi A) --}}
  <div class="ws-modal" x-show="tpl.open" x-transition @keydown.escape.window="closeTemplateModal()">
    <div class="ws-modal__backdrop" @click="closeTemplateModal()"></div>
    <div class="ws-modal__card" @click.stop>
      <div class="ws-modal__head">
        <div>
          <div class="ws-modal__title">Pilih Template</div>
          <div class="ws-modal__sub">Template ini akan mengisi data default worksheet (aman & konsisten untuk jangka panjang).</div>
        </div>
        <button type="button" class="ws-modal__x" @click="closeTemplateModal()">✕</button>
      </div>

      <div class="ws-modal__body">
        <input type="text" class="ws-modal__search"
               x-model="tpl.q"
               @input="filterTemplates()"
               placeholder="Cari template… (contoh: 4D34T / Canter)">

        <div class="ws-modal__list">
          <template x-for="t in tpl.filtered" :key="t.key">
            <button type="button" class="ws-modal__item"
                    :class="tpl.selectedKey === t.key ? 'is-active' : ''"
                    @click="selectTemplate(t.key)">
              <div class="ws-modal__itemname" x-text="t.name"></div>
              <div class="ws-modal__itemmeta">
                <span class="ws-pill" x-text="'Key: ' + t.key"></span>
                <span class="ws-pill" x-text="'Ver: ' + (t.version || '-')"></span>
              </div>
              <div class="ws-modal__itemnote" x-show="t.notes" x-text="t.notes"></div>
            </button>
          </template>

          <div class="ws-modal__empty" x-show="tpl.filtered.length === 0">
            Tidak ada template yang cocok.
          </div>
        </div>

        <div class="ws-modal__warn" x-show="tpl.needConfirm">
          <b>Perhatian:</b> Worksheet sudah berisi data. Jika kamu <b>Apply Template</b>,
          data sekarang akan <b>ditimpa</b>. Klik Apply sekali lagi untuk konfirmasi.
        </div>
      </div>

      <div class="ws-modal__foot">
        <button type="button" class="ws-btn" @click="closeTemplateModal()">Batal</button>
        <button type="button" class="ws-btn ws-btn--primary" @click="applySelectedTemplate()">
          Apply Template
        </button>
      </div>
    </div>
  </div>

  {{-- hidden JSON payload --}}
  <input type="hidden" name="parts_payload" :value="jsonPayload()">
</div>

<style>
  /* ===== SHELL + FULLSCREEN ===== */
  .ws-shell{position:relative;}

  .ws-shell--fs{
    position:fixed; inset:0; z-index:9999;
    width:100vw; height:100dvh;
    background:#f1f5f9;
    padding:14px;
    display:flex; flex-direction:column;
    gap:12px;
    overflow:hidden !important;
  }

  /* ===== TOP BAR ===== */
  .ws-topbar{
    display:flex; align-items:center; justify-content:space-between;
    gap:12px; flex-wrap:wrap;
    background:#f8fafc; border:1px solid #e5e7eb;
    padding:10px 12px; border-radius:14px;
    margin-top:2px;
  }
  .ws-topbar__left{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
  .ws-badge{display:inline-flex;align-items:center;font-weight:900;font-size:12px;color:#0f172a;padding:7px 12px;border-radius:999px;background:#e2e8f0;}
  .ws-small{font-size:12px;color:#64748b;font-weight:800;}
  .ws-topbar__right{display:flex;align-items:center;gap:8px;}
  .ws-icbtn{height:36px;width:40px;border-radius:12px;border:1px solid #cbd5e1;background:#fff;font-weight:900;cursor:pointer;box-shadow:0 1px 0 rgba(15,23,42,.05);}
  .ws-zoom{width:180px;}
  .ws-pct{height:36px;padding:0 12px;border-radius:12px;border:1px solid #cbd5e1;background:#fff;font-weight:900;cursor:pointer;}
  .ws-divider--top{padding:0 2px;}

  .ws-chip{
    height:36px;
    border-radius:999px;
    border:1px solid #cbd5e1;
    background:#fff;
    cursor:pointer;
    padding:0 12px;
    display:inline-flex;
    align-items:center;
    gap:10px;
    box-shadow:0 1px 0 rgba(15,23,42,.05);
    font-weight:900;
  }
  .ws-chip__k{font-size:12px;color:#64748b;}
  .ws-chip__v{font-size:12px;color:#0f172a;max-width:320px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .ws-chip__caret{color:#94a3b8;font-weight:900;}

  /* ===== ACTIONS ===== */
  .ws-actions{
    display:flex;gap:10px;flex-wrap:wrap;align-items:center;
    margin:2px 0 4px 0;
  }
  .ws-btn{
    height:42px; padding:0 16px; border-radius:14px;
    border:1px solid #cbd5e1; background:#fff;
    font-weight:900; cursor:pointer; box-shadow:0 1px 0 rgba(15,23,42,.06);
  }
  .ws-btn--primary{background:#2563eb;border-color:#2563eb;color:#fff;}
  .ws-btn--danger{background:#ef4444;border-color:#ef4444;color:#fff;}
  .ws-btn--danger2{background:#dc2626;border-color:#dc2626;color:#fff;}
  .ws-tip{font-size:12px;color:#64748b;font-weight:800;}

  /* ===== SUB BAR ===== */
  .ws-subbar{
    display:flex; align-items:center; justify-content:space-between;
    gap:12px; flex-wrap:wrap;
    background:#fff; border:1px solid #e5e7eb;
    padding:10px 12px; border-radius:14px;
    margin-bottom:6px;
  }
  .ws-subbar__left{display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;}
  .ws-field{display:flex;align-items:center;gap:10px;}
  .ws-field label{font-weight:900;font-size:13px;color:#334155;}
  .ws-input{height:40px;border:1px solid #cbd5e1;border-radius:12px;padding:0 12px;min-width:260px;background:#fff;}
  .ws-field--rows .ws-input{min-width:120px;}
  .ws-input--rows{text-align:center;font-weight:900;}
  .ws-rowsinfo,.ws-cellinfo{font-size:12px;color:#334155;font-weight:800; padding-bottom:4px;}
  .ws-divider{font-weight:900;color:#94a3b8; padding-bottom:4px;}

  /* ===== TOOLS ===== */
  .ws-tools{
    display:flex;align-items:center;gap:6px;
    background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;
    padding:6px;
  }
  .ws-tool{
    height:34px; min-width:34px; padding:0 10px;
    border-radius:12px; border:1px solid transparent;
    background:#fff; font-weight:900; cursor:pointer;
    box-shadow:0 1px 0 rgba(15,23,42,.05);
    display:inline-flex; align-items:center; justify-content:center; gap:8px;
  }
  .ws-tool.is-on{outline:2px solid rgba(37,99,235,.25); border-color:#93c5fd;}
  .ws-tool--ghost{background:transparent;border-color:transparent;box-shadow:none;color:#334155;}
  .ws-sep{width:1px;height:20px;background:#e2e8f0;margin:0 2px;}
  .ws-ico{width:18px;height:18px;color:#111827;}
  .ws-ico-text{font-size:14px; line-height:1; color:#111827;}
  .ws-swatch{width:14px;height:14px;border-radius:4px;border:1px solid #cbd5e1;display:inline-block;}

  .ws-pop{position:relative;}
  .ws-popover{
    position:absolute; top:44px; right:0;
    width:260px;
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:14px;
    box-shadow:0 18px 40px rgba(2,6,23,.18);
    padding:10px;
    z-index:50;
  }
  .ws-pop-head{font-weight:900;color:#0f172a;font-size:13px;margin-bottom:8px;}
  .ws-pop-actions{display:flex;gap:8px;margin-bottom:10px;}
  .ws-pop-action{
    height:32px;padding:0 10px;border-radius:10px;border:1px solid #cbd5e1;background:#f8fafc;
    font-weight:800;cursor:pointer;color:#0f172a;
  }
  .ws-pop-sub{margin-top:10px;margin-bottom:6px;font-size:12px;color:#64748b;font-weight:800;}
  .ws-colors{display:grid;grid-template-columns:repeat(8, 1fr);gap:6px;}
  .ws-color{height:22px;border-radius:6px;border:1px solid #e5e7eb;cursor:pointer;}
  .ws-colorinput{width:100%;height:34px;border-radius:10px;border:1px solid #cbd5e1;background:#fff;padding:0 6px;}

  .ws-note-text{
    width:100%;
    min-height:90px;
    border-radius:12px;
    border:1px solid #cbd5e1;
    padding:10px;
    resize:vertical;
    font-size:13px;
    outline:none;
  }
  .ws-note-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;}

  /* ===== SHEET ===== */
  .ws-wrap{
    border:1px solid #e5e7eb;border-radius:14px;background:#fff;
    overflow:hidden;
    display:flex; flex-direction:column;
  }

  .ws-tablewrap{
    overflow:auto;
    max-height:560px;
  }

  .ws-shell--fs .ws-wrap{flex:1 1 auto;min-height:0;}
  .ws-shell--fs .ws-tablewrap{
    flex:1 1 auto;
    min-height:0;
    max-height:none;
    height:100%;
    overflow:auto;
    -webkit-overflow-scrolling: touch;
  }

  .ws-zoomTarget{transform-origin:0 0; display:inline-block;}

  .ws-table{
    border-collapse:collapse;
    font-size:13px;
    table-layout:fixed;
    width:max-content;
    min-width:100%;
  }

  /* ===== KUNCI WIDTH KOLOM ===== */
  .ws-table thead th:nth-child(1),
  .ws-table tbody td:nth-child(1),
  .ws-table tfoot td:nth-child(1){ width:70px; min-width:70px; }
  .ws-table thead th:nth-child(2),
  .ws-table tbody td:nth-child(2),
  .ws-table tfoot td:nth-child(2){ width:80px; min-width:80px; }
  .ws-table thead th:nth-child(3),
  .ws-table tbody td:nth-child(3),
  .ws-table tfoot td:nth-child(3){ width:100px; min-width:100px; }
  .ws-table thead th:nth-child(4),
  .ws-table tbody td:nth-child(4),
  .ws-table tfoot td:nth-child(4){ width:180px; min-width:180px; }
  .ws-table thead th:nth-child(5),
  .ws-table tbody td:nth-child(5),
  .ws-table tfoot td:nth-child(5){ width:240px; min-width:240px; }
  .ws-table thead th:nth-child(6),
  .ws-table tbody td:nth-child(6),
  .ws-table tfoot td:nth-child(6){ width:190px; min-width:190px; }
  .ws-table thead th:nth-child(7),
  .ws-table tbody td:nth-child(7),
  .ws-table tfoot td:nth-child(7){ width:180px; min-width:180px; }
  .ws-table thead th:nth-child(8),
  .ws-table tbody td:nth-child(8),
  .ws-table tfoot td:nth-child(8){ width:190px; min-width:190px; }
  .ws-table thead th:nth-child(9),
  .ws-table tbody td:nth-child(9),
  .ws-table tfoot td:nth-child(9){ width:180px; min-width:180px; }
  .ws-table thead th:nth-child(10),
  .ws-table tbody td:nth-child(10),
  .ws-table tfoot td:nth-child(10){ width:180px; min-width:180px; }

  /* sticky header */
  .ws-table thead th{
    position:sticky; top:0; z-index:2;
    background:#0b0b0b;color:#fff;
    padding:10px;border-right:1px solid #111;
    font-weight:900;white-space:nowrap;
  }
  .ws-table thead th::after{content:" ▾";opacity:.85;font-weight:900;}
  .ws-table td{border-top:1px solid #eef2f7;border-right:1px solid #f1f5f9;padding:8px;background:#fff;}
  .ws-table tbody tr:focus-within td{background:#eff6ff;}

  /* footer row sticky bottom */
  .ws-table tfoot td{
    background:#fff;
    border-top:2px solid #e5e7eb;
    padding:8px;
    position:sticky;
    bottom:0;
    z-index:3;
  }
  .ws-tfoot-pad{ background:#fff; }
  .ws-tfoot-money{ background:#f8fafc; }

  /* inputs */
  .ws-inp{
    width:100%;
    height:40px;
    border:1px solid #dbe5f1;
    border-radius:12px;
    padding:0 10px;
    background:#fff;
    outline:none;
    color:inherit;
    font-weight:inherit;
    font-style:inherit;
    text-decoration:inherit;
    text-align:inherit;
  }
  .ws-inp--center{text-align:center;}
  .ws-inp:focus{
    border-color:#22c55e;
    box-shadow:0 0 0 3px rgba(34,197,94,.18);
  }
  .ws-inp--tfoot{
    background:#f8fafc;
    border-color:#e2e8f0;
    font-weight:900;
  }

  /* focusable box */
  .ws-box{
    min-height:40px;
    border:1px solid #dbe5f1;
    border-radius:12px;
    padding:8px 10px;
    background:#fff;
    outline:none;
    display:flex; align-items:center;
  }
  .ws-box:focus{
    border-color:#22c55e;
    box-shadow:0 0 0 3px rgba(34,197,94,.18);
  }
  .ws-box--no{justify-content:center;font-weight:900;}

  /* money */
  .money{position:relative;display:flex;align-items:center;}
  .money .rp{
    position:absolute;left:10px;font-weight:900;pointer-events:none;color:inherit;
  }
  .moneyinput{padding-left:34px !important;text-align:right !important;}

  /* selection */
  .ws-cell.is-sel{
    box-shadow: inset 0 0 0 2px rgba(37,99,235,.9) !important;
    border-color: rgba(37,99,235,.55) !important;
  }
  .ws-cell.is-active{
    box-shadow: inset 0 0 0 2px rgba(34,197,94,.95) !important;
    border-color: rgba(34,197,94,.55) !important;
  }

  /* NOTE indicator corner (BLUE khusus A-F, RED untuk lainnya) */
  .ws-cell.has-note-blue,
  .ws-cell.has-note-red{
    background-repeat:no-repeat !important;
    background-position: right 2px top 2px !important;
    background-size: 12px 12px !important;
  }
  .ws-cell.has-note-blue{
    background-image: linear-gradient(135deg, #2563eb 0 50%, transparent 50%) !important;
  }
  .ws-cell.has-note-red{
    background-image: linear-gradient(135deg, #ef4444 0 50%, transparent 50%) !important;
  }

  /* note hover */
  .ws-notehover{
    position:fixed;
    z-index:99999;
    max-width:320px;
    background:#fff7d6;
    border:1px solid #e5d28a;
    box-shadow:0 16px 30px rgba(2,6,23,.18);
    border-radius:10px;
    padding:10px 12px;
    pointer-events:none;
  }
  .ws-notehover__title{font-weight:900;color:#6b4e00;font-size:12px;margin-bottom:6px;}
  .ws-notehover__text{font-weight:700;color:#1f2937;font-size:12px;white-space:pre-wrap;}

  /* ===== TEMPLATE MODAL ===== */
  .ws-modal{position:fixed;inset:0;z-index:100000;}
  .ws-modal__backdrop{position:absolute;inset:0;background:rgba(2,6,23,.45);}
  .ws-modal__card{
    position:relative;
    width:min(820px, calc(100vw - 28px));
    max-height:calc(100dvh - 28px);
    overflow:hidden;
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:18px;
    box-shadow:0 30px 80px rgba(2,6,23,.35);
    margin:14px auto;
    display:flex;
    flex-direction:column;
  }
  .ws-modal__head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;padding:14px 16px;background:#f8fafc;border-bottom:1px solid #eef2f7;}
  .ws-modal__title{font-weight:900;color:#0f172a;font-size:16px;}
  .ws-modal__sub{margin-top:3px;font-weight:700;color:#64748b;font-size:12px;}
  .ws-modal__x{height:36px;width:40px;border-radius:12px;border:1px solid #cbd5e1;background:#fff;font-weight:900;cursor:pointer;}
  .ws-modal__body{padding:14px 16px;overflow:auto;}
  .ws-modal__search{
    width:100%;
    height:42px;
    border-radius:14px;
    border:1px solid #cbd5e1;
    padding:0 14px;
    outline:none;
    font-weight:800;
  }
  .ws-modal__list{margin-top:12px;display:grid;gap:10px;}
  .ws-modal__item{
    border:1px solid #e5e7eb;
    background:#fff;
    border-radius:16px;
    padding:12px 12px;
    cursor:pointer;
    text-align:left;
    box-shadow:0 1px 0 rgba(15,23,42,.04);
  }
  .ws-modal__item.is-active{outline:3px solid rgba(37,99,235,.20);border-color:#93c5fd;}
  .ws-modal__itemname{font-weight:900;color:#0f172a;font-size:14px;}
  .ws-modal__itemmeta{margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;}
  .ws-pill{display:inline-flex;align-items:center;border:1px solid #e5e7eb;background:#f8fafc;border-radius:999px;padding:6px 10px;font-size:11px;font-weight:900;color:#334155;}
  .ws-modal__itemnote{margin-top:8px;font-size:12px;color:#64748b;font-weight:700;white-space:pre-wrap;}
  .ws-modal__empty{padding:14px;border:1px dashed #cbd5e1;border-radius:14px;color:#64748b;font-weight:800;}
  .ws-modal__warn{
    margin-top:12px;
    padding:12px 12px;
    border-radius:14px;
    border:1px solid #fecaca;
    background:#fff1f2;
    color:#991b1b;
    font-weight:800;
    font-size:12px;
  }
  .ws-modal__foot{padding:12px 16px;border-top:1px solid #eef2f7;background:#fff;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;}
</style>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('partsWS', (cfg) => ({
    storageKey: cfg.storageKey || ('ccr_parts_ws_' + window.location.pathname),

    // meta
    noUnit: cfg.initialNoUnit || '',
    rowsTarget: '',

    // footer (manual/auto)
    footerTotal: cfg.initialFooterTotal || '',
    footerExtended: cfg.initialFooterExtended || '',
    footerTotalManual: false,
    footerExtendedManual: false,

    footerAutoTotal: '',
    footerAutoExtended: '',

    // dropdown lists
    partDescList: Array.isArray(cfg.partDescList) ? cfg.partDescList : [],
    partSectionList: Array.isArray(cfg.partSectionList) ? cfg.partSectionList : [],

    // templates (Opsi A)
    templates: Array.isArray(cfg.templates) ? cfg.templates : [],
    templateDefaultsMap: (cfg.templateDefaultsMap && typeof cfg.templateDefaultsMap === 'object') ? cfg.templateDefaultsMap : {},
    templateKey: cfg.initialTemplateKey || '',
    templateVersion: cfg.initialTemplateVersion || '',

    // template modal state
    tpl: { open:false, q:'', filtered:[], selectedKey:'', needConfirm:false },

    // data
    rows: [],
    styles: {},
    notes: {},

    // selection
    sel: null,
    anchor: null,
    dragging: false,

    // view
    zoom: 100,
    isFs: false,
    _bodyOverflow: null,

    // autosave
    saveStatus: 'Auto-saved --:--:--',
    _tSave: null,

    // active cell
    activeRow: 0,
    activeCol: 1,

    // popovers
    fillOpen: false,
    fontOpen: false,

    // note
    noteOpen: false,
    noteText: '',

    // note hover
    noteHover: { show:false, text:'', x:0, y:0 },

    // palettes
    themeColors: ['#ffffff','#000000','#e5e7eb','#94a3b8','#1e3a8a','#2563eb','#dc2626','#f59e0b','#16a34a','#7c3aed','#0ea5e9','#fb7185','#22c55e','#f97316','#334155','#111827'],
    standardColors: ['#c00000','#ff0000','#ffc000','#ffff00','#92d050','#00b050','#00b0f0','#0070c0','#002060','#7030a0'],

    init() {
      // footer mode: kalau dulu sudah ada isi -> dianggap manual (agar data lama tidak ketimpa)
      const ftMode = (cfg.initialFooterTotalMode || '').toLowerCase();
      const feMode = (cfg.initialFooterExtendedMode || '').toLowerCase();

      this.footerTotalManual = (ftMode === 'manual') || (ftMode === '' && String(this.footerTotal||'').trim() !== '');
      this.footerExtendedManual = (feMode === 'manual') || (feMode === '' && String(this.footerExtended||'').trim() !== '');

      // 1) DB rows
      if (Array.isArray(cfg.initialRows) && cfg.initialRows.length) {
        this.rows = cfg.initialRows.map(r => this.makeRow(r));
        this.styles = (cfg.initialStyles && typeof cfg.initialStyles === 'object') ? cfg.initialStyles : {};
        this.notes  = (cfg.initialNotes && typeof cfg.initialNotes === 'object') ? cfg.initialNotes : {};
        this.saveStatus = 'Auto-saved ' + this.formatTime(new Date());
      } else {
        // 2) draft localStorage
        const d = this.loadDraft();
        if (d && Array.isArray(d.rows) && d.rows.length) {
          this.noUnit  = d.meta?.no_unit || this.noUnit;

          // restore footer manual state if exists
          if (d.meta?.footer_total_mode) this.footerTotalManual = (String(d.meta.footer_total_mode).toLowerCase() === 'manual');
          if (d.meta?.footer_extended_mode) this.footerExtendedManual = (String(d.meta.footer_extended_mode).toLowerCase() === 'manual');

          // restore template meta
          if (d.meta?.template_key) this.templateKey = String(d.meta.template_key);
          if (d.meta?.template_version) this.templateVersion = String(d.meta.template_version);

          this.footerTotal = d.meta?.footer_total || this.footerTotal;
          this.footerExtended = d.meta?.footer_extended || this.footerExtended;

          this.rows    = d.rows.map(r => this.makeRow(r));
          this.styles  = (d.styles && typeof d.styles === 'object') ? d.styles : {};
          this.notes   = (d.notes  && typeof d.notes  === 'object') ? d.notes  : {};
          this.saveStatus = d.ts ? ('Auto-saved ' + this.formatTime(new Date(d.ts))) : ('Auto-saved ' + this.formatTime(new Date()));
        } else {
          this.rows = Array.from({length:100}).map(() => this.makeRow({}));
          this.styles = {};
          this.notes  = {};
          this.saveStatus = 'Auto-saved ' + this.formatTime(new Date());
        }
      }

      // kalau templateKey belum ada tapi templates ada: set default (tidak menimpa data)
      if (!this.templateKey && this.templates.length) {
        this.templateKey = this.templates[0].key;
        this.templateVersion = this.templates[0].version || '';
      } else if (this.templateKey && !this.templateVersion) {
        const t = this.templates.find(x => x.key === this.templateKey);
        if (t) this.templateVersion = t.version || '';
      }

      // hitung formula awal
      this.rows.forEach((_, i) => this.recalcRow(i, 'init', true));
      this.updateAutoFooters();

      this.rowsTarget = String(this.rows.length);

      this.$nextTick(() => {
        this.applyZoom();
        this.focusCell(0,1);
      });

      window.addEventListener('beforeunload', () => this.saveDraft(true));

      this._onMouseUp = () => { this.dragging = false; };
      window.addEventListener('mouseup', this._onMouseUp);

      // clear draft when submit
      this.bindClearOnSubmit();
    },

    bindClearOnSubmit() {
      const form = this.$el.closest('form');
      if (!form) return;
      if (form.__partsWsClearBound) return;
      form.__partsWsClearBound = true;

      form.addEventListener('submit', () => {
        try { localStorage.removeItem(this.storageKey); } catch(e) {}
      });
    },

    /* ===== TEMPLATE (Opsi A) ===== */
    templateName(){
      const t = this.templates.find(x => x.key === this.templateKey);
      return t ? (t.name || t.key) : (this.templateKey || '—');
    },
    templateNotes(){
      const t = this.templates.find(x => x.key === this.templateKey);
      return t ? (t.notes || '') : '';
    },
    openTemplateModal(){
      this.tpl.open = true;
      this.tpl.q = '';
      this.tpl.needConfirm = false;
      this.tpl.selectedKey = this.templateKey || (this.templates[0]?.key || '');
      this.tpl.filtered = this.templates.slice();
    },
    closeTemplateModal(){
      this.tpl.open = false;
      this.tpl.needConfirm = false;
    },
    filterTemplates(){
      const q = String(this.tpl.q || '').toLowerCase().trim();
      if (!q) return this.tpl.filtered = this.templates.slice();
      const out = [];
      for (const t of this.templates) {
        const hay = (String(t.name||'') + ' ' + String(t.key||'') + ' ' + String(t.notes||'')).toLowerCase();
        if (hay.includes(q)) out.push(t);
      }
      this.tpl.filtered = out;
    },
    selectTemplate(key){
      this.tpl.selectedKey = key;
      this.tpl.needConfirm = false;
    },
    isWorksheetEmpty(){
      const last = this.lastNonEmptyIndex();
      const hasAny = last >= 0;
      const hasStyles = this.styles && Object.keys(this.styles).length > 0;
      const hasNotes  = this.notes  && Object.keys(this.notes).length > 0;
      const hasFooter = String(this.footerTotal||'').trim() || String(this.footerExtended||'').trim();
      return !(hasAny || hasStyles || hasNotes || hasFooter);
    },
    normalizeTemplatePayload(raw){
      // expected raw: {meta, rows, styles, notes}
      if (!raw || typeof raw !== 'object') raw = {};
      const meta = (raw.meta && typeof raw.meta === 'object') ? raw.meta : {};
      let rows = [];
      if (Array.isArray(raw.rows)) rows = raw.rows;
      else if (Array.isArray(raw)) rows = raw; // fallback kalau raw list langsung

      const styles = (raw.styles && typeof raw.styles === 'object') ? raw.styles : {};
      const notes  = (raw.notes  && typeof raw.notes  === 'object') ? raw.notes  : {};

      return { meta, rows, styles, notes };
    },
    applySelectedTemplate(){
      const key = this.tpl.selectedKey;
      if (!key) return;

      // jika worksheet tidak kosong, butuh confirm 2x
      if (!this.isWorksheetEmpty() && !this.tpl.needConfirm) {
        this.tpl.needConfirm = true;
        return;
      }

      const raw = this.templateDefaultsMap[key] || {};
      const def = this.normalizeTemplatePayload(raw);

      // update template meta
      this.templateKey = key;
      const t = this.templates.find(x => x.key === key);
      this.templateVersion = t ? (t.version || '') : (this.templateVersion || '');

      // apply defaults ke worksheet
      const keepNoUnit = String(this.noUnit||'').trim();
      const defNoUnit = String(def.meta?.no_unit || '').trim();
      if (!keepNoUnit && defNoUnit) this.noUnit = defNoUnit;

      // footer defaults (optional)
      const dFt = String(def.meta?.footer_total ?? '').trim();
      const dFe = String(def.meta?.footer_extended ?? '').trim();
      const dFtMode = String(def.meta?.footer_total_mode ?? '').toLowerCase();
      const dFeMode = String(def.meta?.footer_extended_mode ?? '').toLowerCase();

      this.footerTotal = dFt;
      this.footerExtended = dFe;

      this.footerTotalManual = (dFtMode === 'manual') ? true : (dFtMode === 'auto' ? false : (dFt !== ''));
      this.footerExtendedManual = (dFeMode === 'manual') ? true : (dFeMode === 'auto' ? false : (dFe !== ''));

      // rows
      if (Array.isArray(def.rows) && def.rows.length) {
        this.rows = def.rows.map(r => this.makeRow(r));
      } else {
        this.rows = Array.from({length:96}).map(() => this.makeRow({}));
      }

      this.styles = def.styles || {};
      this.notes  = def.notes || {};

      this.rowsTarget = String(this.rows.length);
      this.tpl.needConfirm = false;
      this.closeTemplateModal();

      // recalc + footer
      this.rows.forEach((_, i) => this.recalcRow(i, 'tpl_apply', true));
      this.updateAutoFooters();
      this.onChanged();

      // broadcast defaults_detail ke Detail Worksheet (langsung nyambung)
      const detailPayload = (raw && typeof raw === 'object' && raw._detail && typeof raw._detail === 'object') ? raw._detail : null;
      if (detailPayload) {
        try {
          window.dispatchEvent(new CustomEvent('ccr:engineTemplateApplied', {
            detail: { templateKey: this.templateKey, templateVersion: this.templateVersion, detailPayload }
          }));
        } catch(e) {}
      }

      this.$nextTick(() => this.focusCell(0,1));
    },

    makeRow(r) {
      const hasTotal = String(r.total ?? '').trim() !== '';
      const hasExt   = String(r.extended_price ?? '').trim() !== '';

      return {
        _id: this.uid(),
        qty: (r.qty ?? ''),
        uom: (r.uom ?? ''),
        part_number: (r.part_number ?? ''),
        part_description: (r.part_description ?? ''),
        part_section: (r.part_section ?? ''),
        purchase_price: (r.purchase_price ?? ''),
        total: (r.total ?? ''),
        sales_price: (r.sales_price ?? ''),
        extended_price: (r.extended_price ?? ''),

        total_manual: (typeof r.total_manual === 'boolean') ? r.total_manual : hasTotal,
        extended_manual: (typeof r.extended_manual === 'boolean') ? r.extended_manual : hasExt,
      };
    },

    uid() {
      return 'r_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2,8);
    },

    /* ===== FORMULA (BigInt aman jangka panjang) ===== */
    toBigInt(v){
      const d = this.onlyDigits(v);
      if (!d) return 0n;
      try { return BigInt(d); } catch(e) { return 0n; }
    },
    mulMoney(qty, price){
      const q = this.toBigInt(qty);
      const p = this.toBigInt(price);
      if (q === 0n || p === 0n) return '';
      return (q * p).toString();
    },

    setManual(i, which, digits){
      digits = this.onlyDigits(digits);
      if (which === 'total') {
        if (digits === '') { this.rows[i].total_manual = false; this.recalcRow(i, 'total_clear'); }
        else this.rows[i].total_manual = true;
      } else if (which === 'extended') {
        if (digits === '') { this.rows[i].extended_manual = false; this.recalcRow(i, 'ext_clear'); }
        else this.rows[i].extended_manual = true;
      }
    },

    recalcRow(i, src, silent=false){
      const r = this.rows[i];
      if (!r) return;

      if (!r.total_manual) {
        const next = this.mulMoney(r.qty, r.purchase_price);
        r.total = next;
      }
      if (!r.extended_manual) {
        const next = this.mulMoney(r.qty, r.sales_price);
        r.extended_price = next;
      }

      this.updateAutoFooters();
      if (!silent) this.onChanged();
    },

    updateAutoFooters(){
      let sumT = 0n, sumE = 0n;
      let hasT = false, hasE = false;

      for (const r of this.rows) {
        const t = this.onlyDigits(r.total);
        const e = this.onlyDigits(r.extended_price);
        if (t !== '') { hasT = true; sumT += this.toBigInt(t); }
        if (e !== '') { hasE = true; sumE += this.toBigInt(e); }
      }

      this.footerAutoTotal = hasT ? sumT.toString() : '';
      this.footerAutoExtended = hasE ? sumE.toString() : '';
    },

    displayFooterTotal(){
      return this.footerTotalManual ? this.onlyDigits(this.footerTotal) : this.onlyDigits(this.footerAutoTotal);
    },
    displayFooterExtended(){
      return this.footerExtendedManual ? this.onlyDigits(this.footerExtended) : this.onlyDigits(this.footerAutoExtended);
    },

    /* ===== UI ===== */
    addRow() {
      this.rows.push(this.makeRow({}));
      this.rowsTarget = String(this.rows.length);
      this.updateAutoFooters();
      this.onChanged();
      this.$nextTick(() => this.focusCell(this.rows.length-1, 1));
    },

    deleteLast() {
      if (this.rows.length <= 1) return;

      const lastNE = this.lastNonEmptyIndex();
      if (this.rows.length - 1 <= lastNE) return;

      const lastIndex = this.rows.length - 1;
      this.deleteRowStyles(lastIndex);
      this.deleteRowNotes(lastIndex);

      this.rows.pop();
      this.rowsTarget = String(this.rows.length);
      this.updateAutoFooters();
      this.onChanged();
      this.sel = null;
    },

    deleteSelectedRow() {
      const idx = this.activeRow;
      if (idx === null || idx === undefined) return;
      if (this.rows.length <= 1) return;

      this.rows.splice(idx, 1);
      this.shiftStylesAfterDelete(idx);
      this.shiftNotesAfterDelete(idx);

      if (this.rows.length === 0) this.rows.push(this.makeRow({}));
      this.rowsTarget = String(this.rows.length);

      this.updateAutoFooters();
      this.onChanged();
      this.sel = null;

      this.$nextTick(() => this.focusCell(Math.min(this.activeRow, this.rows.length-1), Math.min(this.activeCol, 9)));
    },

    toggleFullscreen() {
      this.isFs = !this.isFs;

      if (this.isFs) {
        this._bodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
      } else {
        document.body.style.overflow = this._bodyOverflow ?? '';
      }

      this.$nextTick(() => {
        this.applyZoom();
        this.focusCell(this.activeRow, this.activeCol);
      });
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

    /* ===== rows target ===== */
    applyRowsTarget() {
      let n = parseInt(this.onlyDigits(this.rowsTarget), 10) || 1;

      // Opsi A: allow lebih besar (tetap aman UI)
      n = Math.max(1, Math.min(500, n));

      const minNeeded = this.lastNonEmptyIndex() + 1;
      if (n < minNeeded) n = minNeeded;

      const cur = this.rows.length;
      if (n > cur) {
        for (let i=0;i<(n-cur);i++) this.rows.push(this.makeRow({}));
      } else if (n < cur) {
        for (let ri = n; ri < cur; ri++) {
          this.deleteRowStyles(ri);
          this.deleteRowNotes(ri);
        }
        this.rows.splice(n);
      }

      this.rowsTarget = String(this.rows.length);
      this.updateAutoFooters();
      this.onChanged();
      this.sel = null;
      this.$nextTick(() => this.focusCell(Math.min(this.activeRow, this.rows.length-1), Math.min(this.activeCol, 9)));
    },

    lastNonEmptyIndex() {
      for (let i=this.rows.length-1; i>=0; i--) {
        const r = this.rows[i];
        if (
          String(r.qty||'').trim() ||
          String(r.uom||'').trim() ||
          String(r.part_number||'').trim() ||
          String(r.part_description||'').trim() ||
          String(r.part_section||'').trim() ||
          String(r.purchase_price||'').trim() ||
          String(r.total||'').trim() ||
          String(r.sales_price||'').trim() ||
          String(r.extended_price||'').trim()
        ) return i;
      }
      return -1;
    },

    /* ===== Styles ===== */
    skey(ri,ci){ return `${ri}:${ci}`; },
    activeKey(){ return this.skey(this.activeRow, this.activeCol); },

    activeStyle(){
      const k = this.activeKey();
      return (this.styles[k] && typeof this.styles[k] === 'object') ? this.styles[k] : {};
    },

    ensureStyleKey(k){
      if (!this.styles[k] || typeof this.styles[k] !== 'object') this.styles[k] = {};
      return this.styles[k];
    },

    cleanupStyle(k){
      const s = this.styles[k];
      if (!s || typeof s !== 'object') return;
      const empty = !s.bold && !s.italic && !s.underline && !s.align && !s.color && !s.bg;
      if (empty) delete this.styles[k];
    },

    selectedRange() {
      if (!this.sel) return null;
      const {ar,ac,br,bc} = this.sel;
      const r1 = Math.min(ar, br), r2 = Math.max(ar, br);
      const c1 = Math.min(ac, bc), c2 = Math.max(ac, bc);
      return {r1,r2,c1,c2};
    },

    iterSelectedKeys(){
      const s = this.selectedRange();
      if (!s) return [this.activeKey()];
      const keys = [];
      for (let r=s.r1; r<=s.r2; r++){
        for (let c=s.c1; c<=s.c2; c++){
          keys.push(`${r}:${c}`);
        }
      }
      return keys;
    },

    toggleFmt(prop){
      const keys = this.iterSelectedKeys();
      const allOn = keys.every(k => !!(this.styles[k] && this.styles[k][prop]));
      const next = !allOn;

      keys.forEach(k => {
        const s = this.ensureStyleKey(k);
        s[prop] = next;
        this.cleanupStyle(k);
      });

      this.onChanged();
    },

    setAlign(val){
      const keys = this.iterSelectedKeys();
      keys.forEach(k => {
        const s = this.ensureStyleKey(k);
        s.align = val;
        this.cleanupStyle(k);
      });
      this.onChanged();
    },

    setFontColor(hex){
      const keys = this.iterSelectedKeys();
      keys.forEach(k => {
        const s = this.ensureStyleKey(k);
        s.color = (hex || '');
        this.cleanupStyle(k);
      });
      this.onChanged();
    },

    setFill(hex){
      const keys = this.iterSelectedKeys();
      keys.forEach(k => {
        const s = this.ensureStyleKey(k);
        s.bg = (hex || '');
        this.cleanupStyle(k);
      });
      this.onChanged();
    },

    clearFormat(){
      const keys = this.iterSelectedKeys();
      keys.forEach(k => { delete this.styles[k]; });
      this.onChanged();
    },

    toolOn(name){
      const keys = this.iterSelectedKeys();
      return keys.every(k => !!(this.styles[k] && this.styles[k][name]));
    },
    toolOnAlign(val){
      const keys = this.iterSelectedKeys();
      return keys.every(k => ((this.styles[k]?.align || '') === val));
    },
    toolHas(prop){
      const keys = this.iterSelectedKeys();
      return keys.some(k => !!(this.styles[k] && this.styles[k][prop]));
    },

    cellStyle(ri,ci){
      const s = (this.styles[this.skey(ri,ci)] || {});
      const st = {};
      if (s.bg) st.backgroundColor = s.bg;
      if (s.color) st.color = s.color;
      if (s.bold) st.fontWeight = '900';
      if (s.italic) st.fontStyle = 'italic';
      if (s.underline) st.textDecoration = 'underline';
      if (s.align) st.textAlign = s.align;
      return st;
    },

    rpStyle(ri,ci){
      const s = (this.styles[this.skey(ri,ci)] || {});
      if (s.color) return { color: s.color };
      return {};
    },

    /* ===== Notes ===== */
    hasNote(k){ return !!(this.notes && this.notes[k] && String(this.notes[k]).trim()); },

    toggleNotePopover(){
      this.noteOpen = !this.noteOpen;
      if (this.noteOpen) {
        const k = this.activeKey();
        this.noteText = this.notes[k] || '';
      }
    },

    saveNote(){
      const k = this.activeKey();
      const t = String(this.noteText || '').trim();
      if (!t) delete this.notes[k];
      else this.notes[k] = t;
      this.onChanged();
    },

    removeNote(){
      const k = this.activeKey();
      delete this.notes[k];
      this.noteText = '';
      this.onChanged();
    },

    onHoverNote(e){
      const el = e.target?.closest?.('[data-cell="1"]');
      if (!el || !this.$refs.tablewrap.contains(el)) return;

      const r = parseInt(el.getAttribute('data-row'),10);
      const c = parseInt(el.getAttribute('data-col'),10);
      if (Number.isNaN(r) || Number.isNaN(c)) return;

      const k = this.skey(r,c);
      if (!this.hasNote(k)) return;

      const rect = el.getBoundingClientRect();
      this.noteHover.text = this.notes[k];
      this.noteHover.x = Math.min(window.innerWidth - 340, rect.left + 18);
      this.noteHover.y = Math.min(window.innerHeight - 140, rect.top + 18);
      this.noteHover.show = true;
    },

    onLeaveNote(){
      this.noteHover.show = false;
    },

    /* ===== Active & label ===== */
    setActive(r,c,resetSelection){
      this.activeRow = r;
      this.activeCol = c;
      if (resetSelection) {
        this.anchor = {r,c};
        this.sel = {ar:r, ac:c, br:r, bc:c};
      }
    },

    cellLabel(){
      const letters = ['A','B','C','D','E','F','G','H','I','J'];
      const col = letters[this.activeCol] || '?';
      return `${col}${this.activeRow + 1}`;
    },

    /* ===== Selection mouse ===== */
    onCellMouseDown(e, r, c){
      if (e && e.shiftKey && this.anchor) {
        this.sel = { ar:this.anchor.r, ac:this.anchor.c, br:r, bc:c };
        this.setActive(r,c,false);
      } else {
        this.anchor = {r,c};
        this.sel = { ar:r, ac:c, br:r, bc:c };
        this.setActive(r,c,false);
      }
      this.dragging = true;
    },

    onCellMouseEnter(e, r, c){
      if (!this.dragging || !this.anchor) return;
      this.sel = { ar:this.anchor.r, ac:this.anchor.c, br:r, bc:c };
    },

    onMouseDownOutside(e){
      if (!e.target.closest('[data-cell="1"]')) this.dragging = false;
    },

    cellClass(ri,ci){
      const k = this.skey(ri,ci);
      const s = this.selectedRange();
      const inSel = s ? (ri>=s.r1 && ri<=s.r2 && ci>=s.c1 && ci<=s.c2) : false;
      const isActive = (ri===this.activeRow && ci===this.activeCol);
      const noteCls = this.hasNote(k) ? ((ci <= 5) ? 'has-note-blue' : 'has-note-red') : '';

      return [
        inSel ? 'is-sel' : '',
        isActive ? 'is-active' : '',
        noteCls
      ].join(' ');
    },

    /* ===== Navigation ===== */
    focusCell(r,c){
      r = Math.max(0, Math.min(this.rows.length-1, r));
      c = Math.max(0, Math.min(9, c));

      const sel = `[data-cell="1"][data-row="${r}"][data-col="${c}"]`;
      const el = this.$el.querySelector(sel);
      if (!el) return;

      this.setActive(r,c,true);
      el.focus({preventScroll:true});

      const wrap = this.$refs.tablewrap;
      if (wrap && el.scrollIntoView) {
        el.scrollIntoView({block:'nearest', inline:'nearest'});
      }
    },

    moveRight(){
      if (this.activeCol < 9) return this.focusCell(this.activeRow, this.activeCol+1);
      if (this.activeRow < this.rows.length-1) return this.focusCell(this.activeRow+1, 1);
      this.rows.push(this.makeRow({}));
      this.rowsTarget = String(this.rows.length);
      this.updateAutoFooters();
      this.onChanged();
      this.$nextTick(() => this.focusCell(this.rows.length-1, 1));
    },
    moveLeft(){
      if (this.activeCol > 0) return this.focusCell(this.activeRow, this.activeCol-1);
      if (this.activeRow > 0) return this.focusCell(this.activeRow-1, 9);
    },
    moveDown(){
      if (this.activeRow < this.rows.length-1) return this.focusCell(this.activeRow+1, this.activeCol);
      this.rows.push(this.makeRow({}));
      this.rowsTarget = String(this.rows.length);
      this.updateAutoFooters();
      this.onChanged();
      this.$nextTick(() => this.focusCell(this.rows.length-1, this.activeCol));
    },
    moveUp(){
      if (this.activeRow > 0) return this.focusCell(this.activeRow-1, this.activeCol);
    },

    onKey(e){
      const ae = document.activeElement;
      if (!ae || !this.$el.contains(ae)) return;

      if (e.key === 'Escape') {
        if (this.tpl.open) { e.preventDefault(); return this.closeTemplateModal(); }
        if (this.isFs) { e.preventDefault(); return this.toggleFullscreen(); }
      }

      // jangan ganggu textarea note
      if (ae.classList && ae.classList.contains('ws-note-text')) return;

      // navigation hanya ketika fokus ada di cell worksheet
      if (!(ae.classList && ae.classList.contains('ws-cell'))) return;

      if ((e.ctrlKey || e.metaKey) && (e.key === 'b' || e.key === 'B')) { e.preventDefault(); return this.toggleFmt('bold'); }
      if ((e.ctrlKey || e.metaKey) && (e.key === 'i' || e.key === 'I')) { e.preventDefault(); return this.toggleFmt('italic'); }
      if ((e.ctrlKey || e.metaKey) && (e.key === 'u' || e.key === 'U')) { e.preventDefault(); return this.toggleFmt('underline'); }
      if ((e.ctrlKey || e.metaKey) && (e.key === 'm' || e.key === 'M')) { e.preventDefault(); this.noteOpen = true; this.noteText = this.notes[this.activeKey()] || ''; return; }

      if (e.key === 'Enter') { e.preventDefault(); return this.moveRight(); }
      if (e.key === 'Tab') { e.preventDefault(); return e.shiftKey ? this.moveLeft() : this.moveRight(); }

      if (e.key === 'ArrowDown') { e.preventDefault(); return this.moveDown(); }
      if (e.key === 'ArrowUp') { e.preventDefault(); return this.moveUp(); }
      if (e.key === 'ArrowLeft') { e.preventDefault(); return this.moveLeft(); }
      if (e.key === 'ArrowRight') { e.preventDefault(); return this.moveRight(); }
    },

    /* ===== Style housekeeping ===== */
    deleteRowStyles(ri){
      for (let ci=0; ci<=9; ci++){
        const k = this.skey(ri,ci);
        if (this.styles[k]) delete this.styles[k];
      }
    },
    shiftStylesAfterDelete(deletedRi){
      const newStyles = {};
      for (const k in this.styles){
        const parts = k.split(':');
        if (parts.length !== 2) continue;
        const ri = parseInt(parts[0], 10);
        const ci = parseInt(parts[1], 10);
        if (Number.isNaN(ri) || Number.isNaN(ci)) continue;

        if (ri < deletedRi) newStyles[`${ri}:${ci}`] = this.styles[k];
        else if (ri > deletedRi) newStyles[`${ri-1}:${ci}`] = this.styles[k];
      }
      this.styles = newStyles;
      if (this.activeRow > deletedRi) this.activeRow = Math.max(0, this.activeRow - 1);
    },

    deleteRowNotes(ri){
      for (let ci=0; ci<=9; ci++){
        const k = this.skey(ri,ci);
        if (this.notes[k]) delete this.notes[k];
      }
    },
    shiftNotesAfterDelete(deletedRi){
      const newNotes = {};
      for (const k in this.notes){
        const parts = k.split(':');
        if (parts.length !== 2) continue;
        const ri = parseInt(parts[0], 10);
        const ci = parseInt(parts[1], 10);
        if (Number.isNaN(ri) || Number.isNaN(ci)) continue;

        if (ri < deletedRi) newNotes[`${ri}:${ci}`] = this.notes[k];
        else if (ri > deletedRi) newNotes[`${ri-1}:${ci}`] = this.notes[k];
      }
      this.notes = newNotes;
    },

    /* ===== autosave (local draft) ===== */
    onChanged() {
      clearTimeout(this._tSave);
      this._tSave = setTimeout(() => this.saveDraft(true), 600);
    },
    saveDraft(isAuto) {
      const payload = this.payloadObject();
      payload.ts = Date.now();

      // guard: kalau payload besar dan localStorage penuh, jangan bikin crash
      try {
        localStorage.setItem(this.storageKey, JSON.stringify(payload));
        this.saveStatus = (isAuto ? 'Auto-saved ' : 'Saved ') + this.formatTime(new Date(payload.ts));
      } catch(e) {
        // quota error -> tetap lanjut, source of truth = DB saat submit
        this.saveStatus = 'AutoSave limited (data besar) ' + this.formatTime(new Date(payload.ts));
      }
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

    payloadObject() {
      const rowsAll = this.rows.map(r => ({
        qty: String(r.qty||'').trim(),
        uom: String(r.uom||'').trim(),
        part_number: String(r.part_number||'').trim(),
        part_description: String(r.part_description||'').trim(),
        part_section: String(r.part_section||'').trim(),
        purchase_price: this.onlyDigits(r.purchase_price),
        total: this.onlyDigits(r.total),
        sales_price: this.onlyDigits(r.sales_price),
        extended_price: this.onlyDigits(r.extended_price),

        total_manual: !!r.total_manual,
        extended_manual: !!r.extended_manual,
      }));

      const finalFooterTotal = this.footerTotalManual ? this.onlyDigits(this.footerTotal) : this.onlyDigits(this.footerAutoTotal);
      const finalFooterExtended = this.footerExtendedManual ? this.onlyDigits(this.footerExtended) : this.onlyDigits(this.footerAutoExtended);

      return {
        meta: {
          no_unit: String(this.noUnit||'').trim(),

          // template meta (Opsi A)
          template_key: String(this.templateKey||'').trim(),
          template_version: String(this.templateVersion||'').trim(),

          footer_total: finalFooterTotal,
          footer_extended: finalFooterExtended,

          footer_total_mode: this.footerTotalManual ? 'manual' : 'auto',
          footer_extended_mode: this.footerExtendedManual ? 'manual' : 'auto',
        },
        rows: rowsAll,
        styles: this.styles || {},
        notes: this.notes || {},
      };
    },

    jsonPayload() {
      return JSON.stringify(this.payloadObject());
    },
  }));
});
</script>
