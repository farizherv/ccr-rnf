{{-- =========================================================
TAB: PARTS & LABOUR WORKSHEET (EXCEL-LIKE)
File: resources/views/seat/partials/parts_worksheet.blade.php
========================================================= --}}

@php
  // =========================================================
  // 1) REPORT + PAYLOAD (aman untuk create / edit)
  // =========================================================
  $reportObj = $report ?? null;
  $reportId  = $reportObj?->id;
  $initialPayloadRev = $reportObj ? max(0, (int) ($reportObj->parts_payload_rev ?? 0)) : 0;

  $payload = $reportObj ? ($reportObj->parts_payload ?? []) : [];

  // kalau payload tersimpan sebagai JSON string
  if (is_string($payload)) {
    $decoded = json_decode($payload, true);
    if (is_array($decoded)) $payload = $decoded;
  }
  $draftSeedPartsPayload = (isset($draftSeedPartsPayload) && is_array($draftSeedPartsPayload))
    ? $draftSeedPartsPayload
    : [];
  $skipLocalDraftLoad = isset($skipLocalDraftLoad) ? (bool) $skipLocalDraftLoad : false;
  if (!$reportObj && empty($payload) && !empty($draftSeedPartsPayload)) {
    $payload = $draftSeedPartsPayload;
  }
  if (!is_array($payload)) $payload = [];

  // data utama
  $rows   = $payload['rows'] ?? [];
  $styles = $payload['styles'] ?? [];
  $notes  = $payload['notes'] ?? [];
  $tools  = $payload['tools'] ?? [];
  $itemsMasterRows = $seatItemsRows ?? ($payload['items_master_rows'] ?? []);

  if (!is_array($rows))   $rows = [];
  if (!is_array($styles)) $styles = [];
  if (!is_array($notes))  $notes = [];
  if (!is_array($tools))  $tools = [];
  if (!is_array($itemsMasterRows)) $itemsMasterRows = [];

  // kompatibilitas nama lama
  $parts = $rows;

  // meta
  $meta = (isset($payload['meta']) && is_array($payload['meta'])) ? $payload['meta'] : [];

  // default rows: khusus blank = 22, selain itu tetap 100 (atau mengikuti meta/rows template)
  $initialTemplateKeyForRows = $meta['template_key'] ?? 'seat_blank';
  $defaultRowsCount = ($initialTemplateKeyForRows === 'seat_blank') ? 22 : 100;

  $meta = array_merge([
    'no_unit' => '',
    'rows_count' => $defaultRowsCount,
    'footer_total_mode' => 'auto',
    'footer_extended_mode' => 'auto',
  ], $meta);

  $noUnit = $meta['no_unit'] ?? '';
  $footerTotal = $meta['footer_total'] ?? '';
  $footerExtended = $meta['footer_extended'] ?? '';

  $footerTotalMode    = $meta['footer_total_mode'] ?? '';     // 'manual' | 'auto' | ''
  $footerExtendedMode = $meta['footer_extended_mode'] ?? '';  // 'manual' | 'auto' | ''
  $initialPayloadTs = 0;
  foreach ([$payload['ts'] ?? null, $meta['ts'] ?? null, $meta['saved_at_ts'] ?? null] as $candidateTs) {
    if ($candidateTs === null) continue;
    if (is_numeric($candidateTs)) {
      $ts = (int) $candidateTs;
      if ($ts > 0) {
        $initialPayloadTs = $ts;
        break;
      }
    }
  }

  // template meta (Opsi A)
  $initialTemplateKey     = $meta['template_key'] ?? 'seat_blank';
  $initialTemplateVersion = $meta['template_version'] ?? 'v1';

  // =========================================================
  // 2) DROPDOWN LISTS (aman walau controller tidak kirim)
  // =========================================================
  // Prefer template-scoped datalists (engine templates). No more resources/data/* include.
  $datalists = $datalists ?? \App\Support\WorksheetTemplates\SeatTemplateRepo::datalists($initialTemplateKey);
  if (!is_array($datalists)) $datalists = [];

  $uomList = $uomList ?? ($datalists['uom'] ?? []);
  $partDescList = $partDescList ?? ($datalists['part_description'] ?? []);
  $partSectionList = $partSectionList ?? ($datalists['part_section'] ?? []);

  if (!is_array($uomList)) $uomList = [];
  if (!is_array($partDescList)) $partDescList = [];
  if (!is_array($partSectionList)) $partSectionList = [];

  // templates list (kalau controller tidak kirim) + fallback dari registry
  $templates = $templates ?? null;

  // kalau controller ngirim associative array (key => meta), normalisasi jadi list
  $normalizeTemplates = function ($raw) {
    $out = [];
    if (!is_array($raw)) return $out;

    $isAssoc = array_keys($raw) !== range(0, count($raw) - 1);

    if ($isAssoc) {
      foreach ($raw as $k => $v) {
        if (is_array($v)) {
          $key = $v['key'] ?? (is_string($k) ? $k : '');
          if (!$key) continue;
          $out[] = [
            'key'     => $key,
            'name'    => $v['name'] ?? $v['title'] ?? $key,
            'version' => $v['version'] ?? $v['ver'] ?? 'v1',
            'notes'   => $v['notes'] ?? '',
          ];
        } elseif (is_string($v) && $v !== '') {
          $out[] = ['key' => $v, 'name' => $v, 'version' => 'v1', 'notes' => ''];
        }
      }
      return $out;
    }

    foreach ($raw as $v) {
      if (is_array($v)) {
        $key = $v['key'] ?? '';
        if (!$key) continue;
        $out[] = [
          'key'     => $key,
          'name'    => $v['name'] ?? $v['title'] ?? $key,
          'version' => $v['version'] ?? $v['ver'] ?? 'v1',
          'notes'   => $v['notes'] ?? '',
        ];
      }
    }
    return $out;
  };

  // 1) normalisasi kalau ada dari controller
  $templates = $normalizeTemplates($templates);

  // 2) fallback: load dari resources/worksheet_templates/seat/registry.php
  if (!is_array($templates) || count($templates) === 0) {
    $registryPath = resource_path('worksheet_templates/seat/registry.php');
    if (file_exists($registryPath)) {
      $raw = include $registryPath;
      $templates = $normalizeTemplates($raw);
    } else {
      $templates = [];
    }
  }

  // 3) guard: minimal harus ada seat_blank
  $hasBlank = false;
  foreach ($templates as $t) {
    if (is_array($t) && ($t['key'] ?? '') === 'seat_blank') { $hasBlank = true; break; }
  }
  if (!$hasBlank) {
    $templates[] = ['key' => 'seat_blank', 'name' => 'Seat Blank', 'version' => 'v1', 'notes' => 'Template kosong'];
  }

  // =========================================================
  // 4) URL + STORAGE KEY (unik per report + per user agar tidak ketuker antar akun)
  // =========================================================
  $userId = auth()->check() ? (int) auth()->id() : 0;

  // local autosave key (per user + per report/create + per halaman)
  $storageKey = 'ccr_parts_ws_' . ($userId ? ('u'.$userId.'_') : 'guest_')
              . ($reportId ? ('r'.$reportId) : 'create')
              . '_' . md5(url()->current());

  // remember template per user
  $templateRememberKey = $userId
    ? ('ccr_seat_last_template_u' . $userId)
    : 'ccr_seat_last_template_guest';

  $autosaveUrl = $reportId ? route('seat.worksheet.autosave', ['id' => $reportId]) : null;

@endphp

@php
  $templateDefaultsUrl = \Illuminate\Support\Facades\Route::has('seat.worksheet.template.defaults')
    ? route('seat.worksheet.template.defaults')
    : null;
@endphp


<div x-show="tab==='parts'" x-cloak
     x-data="partsWS({
        initialRows: @js($rows),
        initialStyles: @js($styles),
        initialNotes: @js($notes),
        initialTools: @js($tools),
        initialPayloadTs: @js($initialPayloadTs),
        initialNoUnit: @js($noUnit),

        initialFooterTotal: @js($footerTotal),
        initialFooterExtended: @js($footerExtended),
        initialFooterTotalMode: @js($footerTotalMode),
        initialFooterExtendedMode: @js($footerExtendedMode),

        initialMeta: @js($meta),  

        partDescList: @js($partDescList),
        partSectionList: @js($partSectionList),
        initialItemsMasterRows: @js($itemsMasterRows),

        templates: @js($templates),
        templateDefaultsUrl: @js($templateDefaultsUrl),

        initialTemplateKey: @js($initialTemplateKey),
        initialTemplateVersion: @js($initialTemplateVersion),

        storageKey: @js($storageKey),
        userId: @js($userId),
        templateRememberKey: @js($templateRememberKey),
        skipLocalDraftLoad: @js($skipLocalDraftLoad),

        reportId: @js($reportId),
        initialPayloadRev: @js($initialPayloadRev),
        autosaveUrl: @js($autosaveUrl),
        csrf: @js(csrf_token()),
     })"
     class="box ws-shell"
     :class="isFs ? 'ws-shell--fs' : ''"
     @keydown.capture="onKey($event)"
     @copy.capture="onClipCopy($event)"
     @cut.capture="onClipCut($event)"
     @paste.capture="onClipPaste($event)">

  <h3 class="ws-title" style="margin-bottom:6px;">Parts &amp; Labour Worksheet</h3>
  <p class="ws-desc" style="font-size:13px; color:#64748b; margin-bottom:14px;">
    Template “Sheet Parts & Labour Worksheet” (Autosave). Formula dasar aktif;
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

      <div class="ws-rowsinfo">Current: <b x-text="rows.length"></b></div>

      <span class="ws-divider ws-divider--top">|</span>

      <div class="ws-cellinfo">Cell: <b x-text="cellLabel()"></b></div>
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

  {{-- SUB BAR --}}
  <div class="ws-subbar">
    <div class="ws-subbar__left">
      <div class="ws-field ws-field--unit">
        <label>No. Unit</label>
        <input class="ws-input"
               x-model="noUnit"
               @input="onChanged()"
               placeholder="Contoh: LT-S019">
      </div>

      <div class="ws-actions ws-actions--inline">
        <button type="button" class="ws-btn ws-btn--primary" @click="addRow()">
          + Tambah Baris
        </button>

        <button type="button"
                class="ws-btn ws-btn--danger"
                :disabled="!canDeleteLastRow()"
                @click="deleteLast()">
          Hapus Terakhir
        </button>

        <button type="button"
                class="ws-btn"
                :class="canDeleteSelectedRows() ? 'ws-btn--danger2' : 'ws-btn--muted'"
                :disabled="!canDeleteSelectedRows()"
                @click="deleteSelectedRow()">
          Hapus Terpilih
        </button>
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
        <div class="ws-pop" @click.outside="commitOpenNote({ close: true })">
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
              <button type="button" class="ws-pop-action" @click="commitOpenNote({ close: true })">Close</button>
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
    {{-- datalist UOM (native) --}}
    <datalist id="ws_uom_list">
      @foreach((is_array($uomList ?? null) ? $uomList : []) as $opt)
        <option value="{{ $opt }}"></option>
      @endforeach
    </datalist>

    {{-- datalist Part Description: gabungan template + Items tab --}}
    <datalist id="ws_part_desc_list">
      <template x-for="opt in partDescriptionOptions()" :key="'part-desc-' + opt">
        <option :value="opt"></option>
      </template>
    </datalist>

    {{-- datalist Part Section (native seperti UOM) --}}
    <datalist id="ws_part_section_list">
      @foreach((is_array($partSectionList ?? null) ? $partSectionList : []) as $opt)
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
              <th style="width:180px;">Sales Price</th>
              <th style="width:180px;">Extended Price</th>
            </tr>
          </thead>

          <tbody>
            <template x-for="(r, i) in rows" :key="r._id">
              <tr :class="isRowSelected(i) ? 'ws-row-selected' : ''">
                {{-- A --}}
                <td>
                  <div class="ws-box ws-box--no ws-cell"
                       tabindex="0"
                       :class="[cellClass(i,0), isRowSelected(i) ? 'ws-box--no-selected' : '']"
                       :style="cellStyle(i,0)"
                       data-cell="1" :data-row="i" data-col="0"
                       @focus="setActive(i,0,true)"
                       @mousedown.stop
                       @click.prevent.stop="toggleRowSelection(i)">
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

                {{-- E (Part Description: datalist items + template) --}}
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
                         @input="onPartDescriptionInput(i)"
                         @change="onPartDescriptionInput(i)">
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

                {{-- G (Sales Price) --}}
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
                          :value="formatDots(r.sales_price)"
                          @input="
                            r.sales_price_raw = normalizeMoneyRaw($event.target.value);
                            r.sales_price = rawToRupiahDigits(r.sales_price_raw);     // untuk tampilan (0 desimal)
                            $event.target.value = formatDots(r.sales_price);
                            recalcRow(i,'sales');
                            onChanged();
                          "
                          inputmode="numeric"
                          placeholder="0">
                  </div>
                </td>

                {{-- H (Extended = Qty * Sales, auto tapi bisa override) --}}
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
              <td colspan="6" class="ws-tfoot-pad"></td>

              {{-- G blank --}}
              <td class="ws-tfoot-pad"></td>

              {{-- H footer extended --}}
              <td class="ws-tfoot-money">
                <div class="money">
                  <span class="rp">Rp</span>
                  <input type="text" class="ws-inp moneyinput ws-inp--tfoot"
                        :value="formatDots(displayFooterExtended())"
                        @input="
                          footerExtended = onlyDigits($event.target.value);          // ✅ FIX: extended
                          $event.target.value = formatDots(footerExtended);          // ✅ FIX: extended
                          footerExtendedManual = (footerExtended !== '');            // ✅ FIX: extended
                          if (!footerExtendedManual) footerExtended = '';            // ✅ FIX: extended

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
          <div class="ws-modal__sub">Template ini akan mengisi data default worksheet (NOTE: Jika sudah isi data dan mau merubah untuk apply template yang lain maka data yang sudah terisi akan HILANG / RESET).</div>
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
  <input type="hidden" name="parts_payload" x-ref="partsPayloadInput" :value="jsonPayload()">
  <input type="hidden" name="parts_payload_rev" x-ref="partsPayloadRevInput" :value="payloadRev">
</div>

<style>
  /* ===== SHELL + FULLSCREEN ===== */
  .ws-shell{position:relative;}

.ws-shell--fs{
  position:fixed; inset:0;
  z-index:90000;               /* di atas layout */
  width:100vw; height:100dvh;

  /* lawan style .box / container yang suka nahan lebar */
  max-width:none !important;
  margin:0 !important;
  border-radius:0 !important;

  background:#f1f5f9;
  padding:10px;                /* boleh 0 kalau mau lebih “Excel” */
  display:flex; flex-direction:column;
  gap:10px;
  overflow:hidden !important;
}

/* ✅ screenshot 1: judul + deskripsi tidak ikut fullscreen */
.ws-shell--fs .ws-title,
.ws-shell--fs .ws-desc{
  display:none !important;
}

/* ✅ sheet benar-benar ambil sisa tinggi layar */
.ws-shell--fs .ws-wrap{
  flex:1 1 auto;
  min-height:0;
  border-radius:0;             /* biar berasa Excel */
  border:0;
}

/* ✅ scroll area tabel full tinggi, tidak kepotong max-height 560 */
.ws-shell--fs .ws-tablewrap{
  flex:1 1 auto;
  min-height:0;
  max-height:none !important;
  height:auto !important;
  overflow:auto;
  -webkit-overflow-scrolling: touch;
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
  .ws-topmeta{display:flex;align-items:center;gap:8px;}
  .ws-topmeta__label{font-size:12px;color:#334155;font-weight:900;}
  .ws-input--rows-top{min-width:110px !important;}

  /* ===== ACTIONS ===== */
  .ws-actions{
    display:flex;gap:10px;flex-wrap:wrap;align-items:center;
    margin:2px 0 4px 0;
  }
  .ws-actions--inline{margin-left:6px;}
  .ws-btn{
    height:42px; padding:0 16px; border-radius:14px;
    border:1px solid #cbd5e1; background:#fff;
    font-weight:900; cursor:pointer; box-shadow:0 1px 0 rgba(15,23,42,.06);
  }
  .ws-btn--primary{background:#2563eb;border-color:#2563eb;color:#fff;}
  .ws-btn--danger{background:#ef4444;border-color:#ef4444;color:#fff;}
  .ws-btn--danger2{background:#dc2626;border-color:#dc2626;color:#fff;}
  .ws-btn--muted{background:#f8fafc;border-color:#cbd5e1;color:#9ca3af;}
  .ws-btn--muted:disabled{opacity:1;cursor:not-allowed;}
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
  .ws-field--unit .ws-input{min-width:360px;}
  .ws-input--rows{text-align:center;font-weight:900;}
  .ws-rowsinfo,.ws-cellinfo{font-size:12px;color:#334155;font-weight:800; padding-bottom:4px;}
  .ws-divider{font-weight:900;color:#94a3b8; padding-bottom:4px;}
  .ws-subbar .ws-actions{margin:0;}

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

  .ws-zoomTarget{transform-origin:0 0; display:block; min-width:100%;}

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
  .ws-table tfoot td:nth-child(8){ width:180px; min-width:180px; }

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
  .ws-table tbody tr.ws-row-selected td{background:#eaf2ff;}

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
  .ws-box--no-selected{
    background:#2563eb !important;
    border-color:#2563eb !important;
    color:#fff !important;
  }

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
    reportId: cfg.reportId || null,
    payloadRev: Number(cfg.initialPayloadRev || 0),
    payloadTs: Number(cfg.initialPayloadTs || 0),
    autosaveUrl: cfg.autosaveUrl || '',
    csrf: cfg.csrf || (document.querySelector('meta[name=csrf-token]')?.content || ''),
    _saveSeq: 0,

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

    // sync subtotal ke Detail Worksheet
    _lastEmitFooters: { t:'', e:'' },
    _emitFootersTimer: null,

    // dropdown lists
    partDescList: Array.isArray(cfg.partDescList) ? cfg.partDescList : [],
    partSectionList: Array.isArray(cfg.partSectionList) ? cfg.partSectionList : [],

    // templates
    templates: Array.isArray(cfg.templates) ? cfg.templates : [],
    templateDefaultsUrl: cfg.templateDefaultsUrl || '',
    _tplCache: {},
    templateKey: cfg.initialTemplateKey || '',
    templateVersion: cfg.initialTemplateVersion || '',

    // remember template
    userId: cfg.userId || 0,
    templateRememberKey: cfg.templateRememberKey || ('ccr_seat_last_template_u' + (cfg.userId || 0)),
    skipLocalDraftLoad: !!cfg.skipLocalDraftLoad,

    // template modal state
    tpl: { open:false, q:'', filtered:[], selectedKey:'', needConfirm:false },

    // data
    rows: [],
    styles: {},
    notes: {},
    tools: {},
    itemsMasterRows: Array.isArray(cfg.initialItemsMasterRows) ? cfg.initialItemsMasterRows : [],

    // selection
    sel: null,
    anchor: null,
    dragging: false,
    rowSelected: {},
    _clipboard: null,
    _colFields: ['_no','qty','uom','part_number','part_description','part_section','sales_price','extended_price'],
    _skipFocusReset: false,
    _undoStack: [],
    _maxUndo: 50,

    // view
    zoom: 90,
    isFs: false,
    _bodyOverflow: null,

    // autosave
    saveStatus: 'Auto-saved --:--:--',
    _tSave: null,
    _fmtSyncTimer: null,

    // active cell
    activeRow: 0,
    activeCol: 1,
    maxCol: 7,

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

    /* =========================================================
     * HELPERS
     * ========================================================= */
    isUsableDraft(d){
      return !!(
        d && (
          (d.meta && typeof d.meta === 'object') ||
          (Array.isArray(d.rows) && d.rows.length) ||
          (d.styles && typeof d.styles === 'object' && Object.keys(d.styles).length) ||
          (d.notes  && typeof d.notes  === 'object' && Object.keys(d.notes).length) ||
          (d.tools  && typeof d.tools  === 'object' && Object.keys(d.tools).length)
        )
      );
    },
    readRowsCount(meta, fallback){
      const m = (meta && typeof meta === 'object') ? meta : {};
      const raw = (m.rows_count ?? m.rowsCount ?? m.rows ?? m.row_count ?? '');
      let n = parseInt(String(raw || ''), 10);
      if (!Number.isFinite(n) || n <= 0) n = parseInt(String(fallback || ''), 10);
      if (!Number.isFinite(n) || n <= 0) n = 100;
      n = Math.max(1, Math.min(500, n));
      return n;
    },
    readTs(raw){
      const n = Number(raw);
      if (!Number.isFinite(n) || n <= 0) return 0;
      return Math.floor(n);
    },
    serverPayloadWatermark(){
      const ts = this.readTs(this.payloadTs);
      const rev = this.readTs(this.payloadRev);
      return Math.max(ts, rev);
    },
    isDraftNewerThanServer(draft){
      if (!draft || typeof draft !== 'object') return false;
      const draftTs = this.readTs(draft.ts ?? draft?.meta?.ts ?? draft?.meta?.saved_at_ts);
      const serverTs = this.serverPayloadWatermark();
      if (draftTs <= 0) return false;
      if (serverTs <= 0) return true;
      return draftTs > serverTs;
    },
    touchTools(kind, extra = {}){
      const base = (this.tools && typeof this.tools === 'object') ? this.tools : {};
      const patch = (extra && typeof extra === 'object') ? extra : {};
      const range = this.selectedRange();
      const selectedRange = range
        ? { r1: range.r1, r2: range.r2, c1: range.c1, c2: range.c2 }
        : null;
      this.tools = {
        ...base,
        ...patch,
        last_action: String(kind || ''),
        last_action_at: Date.now(),
        last_cell: this.activeKey(),
        selected_range: Object.prototype.hasOwnProperty.call(patch, 'selected_range')
          ? patch.selected_range
          : selectedRange,
      };
    },
    queueFormattingSync(){
      clearTimeout(this._fmtSyncTimer);
      this._fmtSyncTimer = setTimeout(() => {
        this.flushPendingSave(true);
      }, 140);
    },
    padRowsTo(want){
      want = Math.max(1, Math.min(500, parseInt(String(want || ''), 10) || 100));
      if (!Array.isArray(this.rows)) this.rows = [];
      while (this.rows.length < want) this.rows.push(this.makeRow({}));
      if (!this.rows.length) this.rows = Array.from({ length: want }).map(() => this.makeRow({}));
    },
    applyMetaFrom(meta){
      if (!meta || typeof meta !== 'object') return;

      if ('no_unit' in meta) this.noUnit = String(meta.no_unit || '');

      if ('footer_total_mode' in meta) {
        this.footerTotalManual = (String(meta.footer_total_mode || '').toLowerCase() === 'manual');
      }
      if ('footer_extended_mode' in meta) {
        this.footerExtendedManual = (String(meta.footer_extended_mode || '').toLowerCase() === 'manual');
      }

      if ('template_key' in meta) this.templateKey = String(meta.template_key || this.templateKey || '');
      if ('template_version' in meta) this.templateVersion = String(meta.template_version || this.templateVersion || '');

      if (this.footerTotalManual && ('footer_total' in meta)) this.footerTotal = String(meta.footer_total || '');
      if (!this.footerTotalManual) this.footerTotal = this.footerTotal || '';

      if (this.footerExtendedManual && ('footer_extended' in meta)) this.footerExtended = String(meta.footer_extended || '');
      if (!this.footerExtendedManual) this.footerExtended = this.footerExtended || '';
    },

    /* =========================================================
     * INIT (FIXED)
     * ========================================================= */
    init() {
      // footer mode (jaga data lama)
      const ftMode = (cfg.initialFooterTotalMode || '').toLowerCase();
      const feMode = (cfg.initialFooterExtendedMode || '').toLowerCase();
      this.footerTotalManual = (ftMode === 'manual');
      this.footerExtendedManual = (feMode === 'manual');

      const d = this.skipLocalDraftLoad ? null : this.loadDraft();
      const hasUsableDraft = this.isUsableDraft(d);

      const dbHasAny = !!(
        (Array.isArray(cfg.initialRows) && cfg.initialRows.length) ||
        (cfg.initialStyles && typeof cfg.initialStyles === 'object' && Object.keys(cfg.initialStyles).length) ||
        (cfg.initialNotes  && typeof cfg.initialNotes  === 'object' && Object.keys(cfg.initialNotes).length) ||
        (cfg.initialTools  && typeof cfg.initialTools  === 'object' && Object.keys(cfg.initialTools).length) ||
        String(cfg.initialNoUnit||'').trim() ||
        String(cfg.initialFooterTotal||'').trim() ||
        String(cfg.initialFooterExtended||'').trim()
      );

      const cfgMeta = (cfg.initialMeta && typeof cfg.initialMeta === 'object') ? cfg.initialMeta : {};

      const applyFromCfg = () => {
        this.applyMetaFrom(cfgMeta);

        this.noUnit = cfg.initialNoUnit || this.noUnit;
        this.footerTotal = cfg.initialFooterTotal || this.footerTotal;
        this.footerExtended = cfg.initialFooterExtended || this.footerExtended;

        this.rows   = (Array.isArray(cfg.initialRows) ? cfg.initialRows : []).map(r => this.makeRow(r));
        this.styles = (cfg.initialStyles && typeof cfg.initialStyles === 'object') ? cfg.initialStyles : {};
        this.notes  = (cfg.initialNotes  && typeof cfg.initialNotes  === 'object') ? cfg.initialNotes  : {};
        this.tools  = (cfg.initialTools  && typeof cfg.initialTools  === 'object') ? cfg.initialTools  : {};

        // ✅ rows_count dari META SERVER (bukan draft)
        const want = this.readRowsCount(cfgMeta, this.rows.length || 100);
        this.padRowsTo(Math.max(this.rows.length || 0, want));
      };

      const applyFromDraft = () => {
        if (!hasUsableDraft) return;

        this.applyMetaFrom(d.meta || {});
        this.rows   = (Array.isArray(d.rows) ? d.rows : []).map(r => this.makeRow(r));
        this.styles = (d.styles && typeof d.styles === 'object') ? d.styles : {};
        this.notes  = (d.notes  && typeof d.notes  === 'object') ? d.notes  : {};
        this.tools  = (d.tools  && typeof d.tools  === 'object') ? d.tools  : this.tools;

        const want = this.readRowsCount(d.meta || {}, this.rows.length || 100);
        this.padRowsTo(Math.max(this.rows.length || 0, want));

        this.saveStatus = d.ts
          ? ('Auto-saved ' + this.formatTime(new Date(d.ts)))
          : ('Auto-saved ' + this.formatTime(new Date()));
      };

      let recoveredDraftToServer = false;
      if (this.reportId) {
        // EDIT: selalu prioritaskan payload DB agar style/note yang sudah tersimpan
        // tidak tertimpa draft lokal yang timestamp-nya lebih baru tapi kontennya stale.
        applyFromCfg();

        // fallback draft hanya jika DB benar-benar kosong.
        if (!dbHasAny && hasUsableDraft) {
          applyFromDraft();
          recoveredDraftToServer = true;
        }
      } else {
        // CREATE: prefer draft
        if (hasUsableDraft) {
          applyFromDraft();
        } else {
          applyFromCfg();

          const remembered = this.loadRememberedTemplate();
          const stillDefault = !this.templateKey || this.templateKey === 'seat_blank';
          if (remembered && remembered.key && stillDefault) {
            this.templateKey = remembered.key;
            this.templateVersion = remembered.version || 'v1';
          }
        }
      }

      // default template safety
      if (!this.templateKey && this.templates.length) {
        this.templateKey = this.templates[0].key;
        this.templateVersion = this.templates[0].version || '';
      } else if (this.templateKey && !this.templateVersion) {
        const t = this.templates.find(x => x.key === this.templateKey);
        if (t) this.templateVersion = t.version || '';
      }

      // recalc awal
      this.rows.forEach((_, i) => this.recalcRow(i, 'init', true));
      this.updateAutoFooters();
      this.rowsTarget = String(this.rows.length);

      const globalItemsMasterRows = Array.isArray(window.__ccrSeatItemsMasterRows)
        ? window.__ccrSeatItemsMasterRows
        : [];
      if (!this.itemsMasterRows.length && globalItemsMasterRows.length) {
        this.itemsMasterRows = globalItemsMasterRows;
      }
      this.bindItemsMasterEvents();

      this.$nextTick(() => {
        this.applyZoom();
        this.focusCell(0,1);
        this.writePayloadInput();
        if (recoveredDraftToServer && this.reportId) {
          this.saveStatus = 'Recovered local draft, syncing...';
          this.flushPendingSave(true);
        }
      });

      // autosave draft on leaving page (with sendBeacon fallback for remote)
      this._onBeforeUnload = () => this.flushOnLeave(true);
      window.addEventListener('beforeunload', this._onBeforeUnload);
      this._onPageHide = () => this.flushOnLeave(true);
      window.addEventListener('pagehide', this._onPageHide);
      this._onVisibilityChange = () => {
        if (document.visibilityState !== 'hidden') return;
        this.flushOnLeave(true);
      };
      document.addEventListener('visibilitychange', this._onVisibilityChange);

      // mouse drag selection safety
      this._onMouseUp = () => { this.dragging = false; };
      window.addEventListener('mouseup', this._onMouseUp);

      this._onMouseLeave = () => { this.dragging = false; };
      window.addEventListener('mouseleave', this._onMouseLeave);

      this._onForceSave = () => {
        this.flushPendingSave(true);
      };
      window.addEventListener('ccr:seat-force-save', this._onForceSave);

      this.bindClearOnSubmit();

      if (!this.reportId) {
        window.__seatCreateCollectPartsPayload = () => this.flushPendingSave(true);
      }
    },

    bindClearOnSubmit(){
      const form = this.$el.closest('form');
      if (!form || form.__ccrPartsWsBound) return;
      form.__ccrPartsWsBound = true;

      form.addEventListener('submit', () => {
        this.flushPendingSave(true);
        try { localStorage.removeItem(this.storageKey); } catch(e) {}
      });
    },

    bindItemsMasterEvents(){
      if (this._onItemsMasterChanged) return;
      this._onItemsMasterChanged = (ev) => {
        const d = (ev && ev.detail && typeof ev.detail === 'object') ? ev.detail : {};
        this.itemsMasterRows = Array.isArray(d.rows) ? d.rows : [];
        this.onChanged();
      };
      window.addEventListener('ccr:seatItemsMasterChanged', this._onItemsMasterChanged);
    },

    /* =========================================================
     * TEMPLATE
     * ========================================================= */
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
      if (!raw || typeof raw !== 'object') raw = {};
      const meta = (raw.meta && typeof raw.meta === 'object') ? raw.meta : {};
      let rows = [];
      if (Array.isArray(raw.rows)) rows = raw.rows;
      else if (Array.isArray(raw)) rows = raw;

      const styles = (raw.styles && typeof raw.styles === 'object') ? raw.styles : {};
      const notes  = (raw.notes  && typeof raw.notes  === 'object') ? raw.notes  : {};
      const tools  = (raw.tools  && typeof raw.tools  === 'object') ? raw.tools  : {};
      return { meta, rows, styles, notes, tools };
    },

    // ✅ JSON key bisa beda-beda: parts / parts_defaults / parts_payload ...
    extractTemplateDefaults(json){
      const o = (json && typeof json === 'object') ? json : {};

      const parts =
        o.parts ??
        o.parts_defaults ??
        o.partsDefault ??
        o.parts_payload ??
        o.partsPayload ??
        (o.data && (o.data.parts ?? o.data.parts_defaults ?? o.data.parts_payload)) ??
        {};

      const detail =
        o.detail ??
        o.detail_defaults ??
        o.detailDefault ??
        o.detail_payload ??
        o.detailPayload ??
        (o.data && (o.data.detail ?? o.data.detail_defaults ?? o.data.detail_payload)) ??
        {};

      return { parts, detail };
    },

    async getTemplateDefaults(key) {
      if (this._tplCache && this._tplCache[key]) return this._tplCache[key];
      if (!this.templateDefaultsUrl) throw new Error('templateDefaultsUrl belum diset');

      const url = new URL(this.templateDefaultsUrl, window.location.origin);
      url.searchParams.set('template_key', key);

      const res = await fetch(url.toString(), {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin'
      });

      if (!res.ok) {
        const msg = await res.text().catch(() => '');
        throw new Error('Gagal load template (' + res.status + '): ' + msg);
      }

      const json = await res.json();
      if (!json || json.ok === false) throw new Error(json && json.message ? json.message : 'Template tidak valid');

      this._tplCache[key] = json;
      return json;
    },

    async applySelectedTemplate() {
      const key = String(this.tpl?.selectedKey || '').trim();
      if (!key) return;

      const isEmpty = this.isWorksheetEmpty();
      if (!isEmpty && !this.tpl.needConfirm) {
        this.tpl.needConfirm = true;
        return;
      }

      try {
        const t = this.templates.find(x => x.key === key);

        const payload = await this.getTemplateDefaults(key);
        const extracted = this.extractTemplateDefaults(payload);
        const rawParts  = extracted.parts || {};
        const rawDetail = extracted.detail || {};

        const def = this.normalizeTemplatePayload(rawParts);

        // meta
        if (def.meta && typeof def.meta === 'object') {
          const meta = def.meta;

          if ('no_unit' in meta) this.noUnit = String(meta.no_unit || '');

          const ftMode = String(meta.footer_total_mode || '').toLowerCase();
          if (ftMode === 'manual') {
            this.footerTotalManual = true;
            this.footerTotal = ('footer_total' in meta) ? String(meta.footer_total || '') : '';
          } else {
            this.footerTotalManual = false;
            this.footerTotal = '';
          }

          const feMode = String(meta.footer_extended_mode || '').toLowerCase();
          if (feMode === 'manual') {
            this.footerExtendedManual = true;
            this.footerExtended = ('footer_extended' in meta) ? String(meta.footer_extended || '') : '';
          } else {
            this.footerExtendedManual = false;
            this.footerExtended = '';
          }
        }

        // rows/styles/notes
        this.rows = (Array.isArray(def.rows) ? def.rows : []).map(r => this.makeRow(r));
        if (!this.rows.length) this.rows = Array.from({ length: 100 }).map(() => this.makeRow({}));
        this.rowsTarget = String(this.rows.length);

        this.styles = (def.styles && typeof def.styles === 'object') ? def.styles : {};
        this.notes  = (def.notes  && typeof def.notes  === 'object') ? def.notes  : {};
        this.tools  = (def.tools  && typeof def.tools  === 'object') ? def.tools  : {};

        this.templateKey = key;
        this.templateVersion = t ? (t.version || '') : '';

        this.saveRememberedTemplate();

        this.rows.forEach((_, i) => this.recalcRow(i, 'tpl', true));
        this.updateAutoFooters();

        this.dispatchPartsTotals(this.displayFooterExtended());
        this.onChanged();

        window.dispatchEvent(new CustomEvent('ccr:seatTemplateApplied', {
          detail: { key, version: this.templateVersion, detailPayload: rawDetail, replace: true }
        }));

        this.tpl.needConfirm = false;
        this.closeTemplateModal();
        this.$nextTick(() => { this.focusCell(0, 1); });
      } catch (e) {
        console.error(e);
        alert('Gagal apply template. Cek console/log.');
      }
    },

    loadRememberedTemplate() {
      const key = String(this.templateRememberKey || '').trim();
      if (!key) return null;
      const raw = localStorage.getItem(key);
      if (!raw) return null;
      try {
        const v = JSON.parse(raw);
        if (v && v.key) return v;
      } catch (e) {}
      return null;
    },
    saveRememberedTemplate() {
      const key = String(this.templateRememberKey || '').trim();
      if (!key) return;
      try {
        localStorage.setItem(key, JSON.stringify({
          key: String(this.templateKey || '').trim(),
          version: String(this.templateVersion || 'v1').trim(),
          savedAt: new Date().toISOString()
        }));
      } catch (e) {}
    },

    /* =========================================================
     * ROW + MONEY
     * ========================================================= */
    makeRow(r) {
      const spRaw  = this.normalizeMoneyRaw(r.sales_price_raw ?? r.sales_price ?? '');
      const spDisp = this.rawToRupiahDigits(spRaw);

      const extDigits   = this.onlyDigits(r.extended_price ?? '');

      let extManual;
      if (typeof r.extended_manual === 'boolean') extManual = r.extended_manual;
      else if (extDigits === '') extManual = false;
      else {
        const expC = this.mulMoneyCents(r.qty ?? '', (spRaw || spDisp || ''));
        const expD = this.onlyDigits(this.centsToRupiahDigits(expC, true));
        extManual = (expD !== extDigits);
      }

      return {
        _id: this.uid(),
        qty: (r.qty ?? ''),
        uom: (r.uom ?? ''),
        part_number: (r.part_number ?? ''),
        part_description: (r.part_description ?? ''),
        part_section: (r.part_section ?? ''),
        purchase_price: '',
        total: '',
        sales_price: spDisp,
        sales_price_raw: spRaw,
        extended_price: this.onlyDigits(r.extended_price ?? ''),
        total_manual: false,
        extended_manual: extManual,
      };
    },

    uid() { return 'r_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2,8); },

    onlyDigits(v){ return String(v||'').replace(/[^\d]/g,''); },
    cleanText(v){
      return String(v||'')
        .replace(/\u00A0/g,' ')
        .replace(/[ \t]{2,}/g,' ');
    },
    normTextKey(v){
      return this.cleanText(v).replace(/\s+/g, ' ').trim().toLowerCase();
    },
    partDescriptionOptions(){
      const out = [];
      const seen = new Set();
      const push = (v) => {
        const txt = this.cleanText(v).trim();
        if (!txt) return;
        const key = txt.toLowerCase();
        if (seen.has(key)) return;
        seen.add(key);
        out.push(txt);
      };

      const rows = Array.isArray(this.itemsMasterRows) ? this.itemsMasterRows : [];
      rows.forEach((row) => {
        if (!row || typeof row !== 'object') return;
        push(row.item ?? row.part_description ?? '');
      });

      (Array.isArray(this.partDescList) ? this.partDescList : []).forEach(push);
      return out;
    },
    findItemsMasterByDescription(desc){
      const key = this.normTextKey(desc);
      if (!key) return null;
      const rows = Array.isArray(this.itemsMasterRows) ? this.itemsMasterRows : [];
      for (const row of rows) {
        if (!row || typeof row !== 'object') continue;
        const candidate = this.normTextKey(row.item ?? row.part_description ?? '');
        if (candidate && candidate === key) return row;
      }
      return null;
    },
    applyItemsMasterSalesPrice(i, itemRow){
      const r = this.rows[i];
      if (!r || !itemRow || typeof itemRow !== 'object') return false;
      const salesDigits = this.onlyDigits(itemRow.sales_price ?? '');
      if (salesDigits === '') return false;

      r.sales_price_raw = this.normalizeMoneyRaw(salesDigits);
      r.sales_price = this.rawToRupiahDigits(r.sales_price_raw);
      this.recalcRow(i, 'items_master', true);
      return true;
    },
    onPartDescriptionInput(i){
      const r = this.rows[i];
      if (!r) return;
      r.part_description = this.cleanText(r.part_description);
      const matched = this.findItemsMasterByDescription(r.part_description);
      if (matched) this.applyItemsMasterSalesPrice(i, matched);
      this.updateAutoFooters();
      this.onChanged();
    },
    formatDots(v){
      v = this.onlyDigits(v);
      if(v === '') return '';
      return v.replace(/\B(?=(\d{3})+(?!\d))/g,'.');
    },
    formatTime(dt) {
      const pad = n => String(n).padStart(2,'0');
      return pad(dt.getHours()) + ':' + pad(dt.getMinutes()) + ':' + pad(dt.getSeconds());
    },

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

    normalizeMoneyRaw(v){
      if (v === null || v === undefined) return '';
      if (typeof v === 'number') {
        if (!Number.isFinite(v)) return '';
        return String(v);
      }

      let s = String(v || '').trim();
      if (!s) return '';

      s = s.replace(/\u00A0/g,' ').replace(/\s+/g,'');
      s = s.replace(/[^\d,.\-]/g,'');

      if (s.includes(',')) {
        s = s.replace(/\./g,'').replace(',', '.');
      } else if (s.includes('.')) {
        const parts = s.split('.');
        if (parts.length === 2) {
          const dec = parts[1] || '';
          if (dec.length > 2) s = s.replace(/\./g,'');
        } else {
          s = s.replace(/\./g,'');
        }
      }

      s = s.replace(/[^\d.\-]/g,'');

      const neg = s.startsWith('-');
      s = s.replace(/-/g,'');
      if (!s) return '';

      const firstDot = s.indexOf('.');
      if (firstDot !== -1) {
        s = s.slice(0, firstDot+1) + s.slice(firstDot+1).replace(/\./g,'');
      }
      if (s.startsWith('.')) s = '0' + s;

      const p = s.split('.');
      let intp = (p[0] || '0').replace(/^0+(?=\d)/,'');
      if (intp === '') intp = '0';

      if (p.length === 1) return (neg ? '-' : '') + intp;

      let dec = (p[1] || '').slice(0,2);
      return (neg ? '-' : '') + (dec ? (intp + '.' + dec) : intp);
    },

    moneyNormToCents(norm){
      if (!norm) return 0n;
      const parts = norm.split('.');
      const intp = parts[0] || '0';
      const dec  = (parts[1] || '');
      const d2   = (dec + '00').slice(0,2);

      let i = 0n, f = 0n;
      try { i = BigInt(intp); } catch(e) { i = 0n; }
      try { f = BigInt(d2); } catch(e) { f = 0n; }

      return (i * 100n) + f;
    },
    moneyRawToCents(raw){
      const norm = this.normalizeMoneyRaw(raw);
      return this.moneyNormToCents(norm);
    },
    centsToRupiahDigits(cents, emptyWhenZero=true){
      let c = 0n;
      try { c = (typeof cents === 'bigint') ? cents : BigInt(String(cents)); } catch(e) { c = 0n; }
      if (c === 0n) return emptyWhenZero ? '' : '0';
      return ((c + 50n) / 100n).toString(); // half-up
    },
    rawToRupiahDigits(raw){
      const norm = this.normalizeMoneyRaw(raw);
      if (norm === '') return '';
      return this.centsToRupiahDigits(this.moneyNormToCents(norm), false);
    },
    mulMoneyCents(qty, priceRaw){
      const q = this.toBigInt(qty);
      if (q === 0n) return 0n;
      const p = this.moneyRawToCents(priceRaw);
      if (p === 0n) return 0n;
      return q * p;
    },

    /* =========================================================
     * FOOTERS + RECALC
     * ========================================================= */
    setManual(i, which, digits){
      digits = this.onlyDigits(digits);

      if (which === 'total') {
        if (digits === '') {
          this.rows[i].total_manual = false;
          this.recalcRow(i, 'total_clear', true);
        } else {
          this.rows[i].total_manual = true;
        }
      } else if (which === 'extended') {
        if (digits === '') {
          this.rows[i].extended_manual = false;
          this.recalcRow(i, 'ext_clear', true);
        } else {
          this.rows[i].extended_manual = true;
        }
      }
      this.updateAutoFooters();
    },

    recalcRow(i, reason = '', silent = false) {
      const r = this.rows[i];
      if (!r) return;

      r.qty = this.onlyDigits(r.qty);
      r.purchase_price = '';
      r.total = '';
      r.total_manual = false;

      if (!r.extended_manual) {
        const cents = this.mulMoneyCents(r.qty || '', (r.sales_price_raw || r.sales_price || ''));
        r.extended_price = this.centsToRupiahDigits(cents, true);
      }

      if (!silent) this.updateAutoFooters();
    },

    updateAutoFooters(){
      let sumE_cents = 0n, hasE = false;

      for (const r of this.rows) {
        if (r.extended_manual) {
          const e = this.onlyDigits(r.extended_price);
          if (e !== '') { hasE = true; sumE_cents += (this.toBigInt(e) * 100n); }
        } else {
          const cents = this.mulMoneyCents(r.qty ?? '', (r.sales_price_raw || r.sales_price || ''));
          if (cents !== 0n) { hasE = true; sumE_cents += cents; }
        }
      }

      this.footerAutoTotal = '';
      this.footerAutoExtended = hasE ? this.centsToRupiahDigits(sumE_cents, true) : '';

      this.emitFootersChangedDebounced();
    },

    displayFooterTotal(){
      return '';
    },
    displayFooterExtended(){
      return this.footerExtendedManual ? this.onlyDigits(this.footerExtended) : this.onlyDigits(this.footerAutoExtended);
    },

    emitFootersChanged(force=false){
      const t = String(this.displayFooterTotal() || '');
      const e = String(this.displayFooterExtended() || '');

      if (!force && this._lastEmitFooters && t === this._lastEmitFooters.t && e === this._lastEmitFooters.e) return;
      this._lastEmitFooters = { t, e };

      this.dispatchPartsTotals(e);

      window.__ccrPartsFooters = {
        total: t,
        extended: e,
        total_fmt: this.formatDots(t),
        extended_fmt: this.formatDots(e),
        reportId: this.reportId || null
      };

      window.dispatchEvent(new CustomEvent('ccr:partsFootersChanged', { detail: window.__ccrPartsFooters }));
      window.dispatchEvent(new CustomEvent('ccr:partsFooterTotalChanged',   { detail: { value: t, value_fmt: this.formatDots(t), reportId: this.reportId||null } }));
      window.dispatchEvent(new CustomEvent('ccr:partsFooterExtendedChanged',{ detail: { value: e, value_fmt: this.formatDots(e), reportId: this.reportId||null } }));
      window.dispatchEvent(new CustomEvent('ccr:totalSubtotalChanged',    { detail: { value: t, value_fmt: this.formatDots(t) } }));
      window.dispatchEvent(new CustomEvent('ccr:extendedSubtotalChanged', { detail: { value: e, value_fmt: this.formatDots(e) } }));
    },

    emitFootersChangedDebounced(){
      if (this._emitFootersTimer) clearTimeout(this._emitFootersTimer);
      this._emitFootersTimer = setTimeout(() => this.emitFootersChanged(false), 80);
    },

    emitExtendedSubtotal(){
      this.emitFootersChangedDebounced();
    },

    dispatchPartsTotals(totalExtendedDigits) {
      const v = String(totalExtendedDigits ?? '').replace(/[^\d]/g,'');
      if (v === '') return;

      window.dispatchEvent(new CustomEvent('ccr:seatPartsTotalsChanged', {
        detail: { total_extended_price: v, sub_total_parts: v, total_parts: v, ts: Date.now() }
      }));

      window.dispatchEvent(new CustomEvent('ccr:seatPartsTotals', {
        detail: { total_extended_price: v, sub_total_parts: v, total_parts: v }
      }));
    },

    /* =========================================================
     * ROW OPS
     * ========================================================= */
    addRow() {
      this.rows.push(this.makeRow({}));
      this.rowsTarget = String(this.rows.length);
      this.updateAutoFooters();
      this.emitFootersChanged(true);
      this.onChanged();
      this.$nextTick(() => this.focusCell(this.rows.length-1, 1));
    },
    clearRowSelections() {
      this.rowSelected = {};
    },
    isRowSelected(idx) {
      return !!(this.rowSelected && this.rowSelected[idx]);
    },
    toggleRowSelection(idx) {
      const i = parseInt(idx, 10);
      if (!Number.isFinite(i) || i < 0 || i >= this.rows.length) return;
      if (this.rowSelected[i]) delete this.rowSelected[i];
      else this.rowSelected[i] = true;
      this.setActive(i, 0, false);
    },
    selectedRowIndexes() {
      const out = [];
      for (const k in (this.rowSelected || {})) {
        const idx = parseInt(k, 10);
        if (!Number.isFinite(idx)) continue;
        if (!this.rowSelected[k]) continue;
        if (idx < 0 || idx >= this.rows.length) continue;
        out.push(idx);
      }
      out.sort((a, b) => a - b);
      return out;
    },
    selectedRowsCount() {
      return this.selectedRowIndexes().length;
    },
    canDeleteSelectedRows() {
      const count = this.selectedRowsCount();
      return this.rows.length > 1 && count > 0 && (this.rows.length - count) >= 1;
    },
    canDeleteLastRow() {
      if (this.rows.length <= 1) return false;
      const lastNE = this.lastNonEmptyIndex();
      return (this.rows.length - 1) > lastNE;
    },
    deleteLast() {
      if (!this.canDeleteLastRow()) return;

      const lastIndex = this.rows.length - 1;
      this.deleteRowStyles(lastIndex);
      this.deleteRowNotes(lastIndex);

      this.rows.pop();
      this.rowsTarget = String(this.rows.length);
      this.updateAutoFooters();
      this.onChanged();
      this.sel = null;
      this.anchor = null;
      this.clearRowSelections();
    },
    deleteSelectedRow() {
      if (!this.canDeleteSelectedRows()) return;
      const idxs = this.selectedRowIndexes();
      if (!idxs.length) return;

      const deleteCount = idxs.length;
      if (this.rows.length - deleteCount < 1) return;

      const desc = idxs.slice().sort((a, b) => b - a);
      for (const ri of desc) {
        this.rows.splice(ri, 1);
        this.shiftStylesAfterDelete(ri);
        this.shiftNotesAfterDelete(ri);
      }

      if (this.rows.length === 0) this.rows.push(this.makeRow({}));
      this.rowsTarget = String(this.rows.length);

      this.updateAutoFooters();
      this.onChanged();
      this.sel = null;
      this.anchor = null;
      this.clearRowSelections();

      const next = Math.max(0, Math.min((idxs[0] ?? 0), this.rows.length - 1));
      this.$nextTick(() => this.focusCell(next, 0));
    },

    applyRowsTarget() {
      let n = parseInt(this.onlyDigits(this.rowsTarget), 10) || 1;
      n = Math.max(1, Math.min(500, n));
      const minNeeded = this.lastNonEmptyIndex() + 1;
      if (n < minNeeded) n = minNeeded;

      const cur = this.rows.length;
      if (n > cur) {
        for (let i=0;i<(n-cur);i++) this.rows.push(this.makeRow({}));
      } else if (n < cur) {
        for (let ri = n; ri < cur; ri++) { this.deleteRowStyles(ri); this.deleteRowNotes(ri); }
        this.rows.splice(n);
      }

      this.rowsTarget = String(this.rows.length);
      this.updateAutoFooters();
      this.onChanged();
      this.sel = null;
      this.anchor = null;
      this.clearRowSelections();
      this.$nextTick(() => this.focusCell(Math.min(this.activeRow, this.rows.length-1), Math.min(this.activeCol, this.maxCol)));
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
          String(r.sales_price||'').trim() ||
          String(r.extended_price||'').trim()
        ) return i;
      }
      return -1;
    },

    /* =========================================================
     * STYLE / SELECTION
     * ========================================================= */
    skey(ri,ci){ return `${ri}:${ci}`; },
    activeKey(){ return this.skey(this.activeRow, this.activeCol); },

    activeStyle(){
      const k = this.activeKey();
      const s = (this.styles && this.styles[k] && typeof this.styles[k] === 'object') ? this.styles[k] : {};
      return s;
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
      for (let r=s.r1; r<=s.r2; r++) for (let c=s.c1; c<=s.c2; c++) keys.push(`${r}:${c}`);
      return keys;
    },
    toggleFmt(prop){
      const keys = this.iterSelectedKeys();
      const allOn = keys.every(k => !!(this.styles[k] && this.styles[k][prop]));
      const next = !allOn;
      keys.forEach(k => { const s = this.ensureStyleKey(k); s[prop] = next; this.cleanupStyle(k); });
      this.touchTools('format_toggle', { format_prop: prop, format_value: !!next });
      this.onChanged();
      this.queueFormattingSync();
    },
    setAlign(val){
      const keys = this.iterSelectedKeys();
      keys.forEach(k => { const s = this.ensureStyleKey(k); s.align = val; this.cleanupStyle(k); });
      this.touchTools('align', { align: String(val || '') });
      this.onChanged();
      this.queueFormattingSync();
    },
    setFontColor(hex){
      const keys = this.iterSelectedKeys();
      keys.forEach(k => { const s = this.ensureStyleKey(k); s.color = (hex || ''); this.cleanupStyle(k); });
      this.touchTools('font_color', { font_color: String(hex || '') });
      this.onChanged();
      this.queueFormattingSync();
    },
    setFill(hex){
      const keys = this.iterSelectedKeys();
      keys.forEach(k => { const s = this.ensureStyleKey(k); s.bg = (hex || ''); this.cleanupStyle(k); });
      this.touchTools('fill_color', { fill_color: String(hex || '') });
      this.onChanged();
      this.queueFormattingSync();
    },
    clearFormat(){
      const keys = this.iterSelectedKeys();
      keys.forEach(k => { delete this.styles[k]; });
      this.touchTools('clear_format');
      this.onChanged();
      this.queueFormattingSync();
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

    /* =========================================================
     * NOTES
     * ========================================================= */
    hasNote(k){ return !!(this.notes && this.notes[k] && String(this.notes[k]).trim()); },
    commitOpenNote(opts = {}){
      const close = !!opts.close;
      const silent = !!opts.silent;
      const force = !!opts.force;
      if (!force && !this.noteOpen) {
        if (close) this.noteOpen = false;
        return false;
      }

      const k = this.activeKey();
      const t = String(this.noteText || '').trim();
      const hasExisting = Object.prototype.hasOwnProperty.call(this.notes || {}, k);
      const prev = hasExisting ? String(this.notes[k] || '').trim() : '';
      let changed = false;

      if (!t) {
        if (hasExisting) {
          delete this.notes[k];
          changed = true;
        }
      } else if (!hasExisting || prev !== t) {
        this.notes[k] = t;
        changed = true;
      }

      if (close) this.noteOpen = false;
      if (changed && !silent) this.onChanged();
      return changed;
    },
    toggleNotePopover(){
      if (this.noteOpen) return this.commitOpenNote({ close: true });
      this.noteOpen = true;
      const k = this.activeKey();
      this.noteText = this.notes[k] || '';
    },
    saveNote(){
      const noteValue = String(this.noteText || '').trim();
      this.touchTools(noteValue ? 'save_note' : 'remove_note', { note_text: noteValue });
      const changed = this.commitOpenNote({ close: true });
      if (changed) this.queueFormattingSync();
    },
    removeNote(){
      const k = this.activeKey();
      delete this.notes[k];
      this.noteText = '';
      this.touchTools('remove_note', { note_text: '' });
      this.onChanged();
      this.queueFormattingSync();
    },
    onHoverNote(e){
      const el = e.target?.closest?.('[data-cell="1"]');
      if (!el || !this.$refs.tablewrap || !this.$refs.tablewrap.contains(el)) return;
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
    onLeaveNote(){ this.noteHover.show = false; },

    /* =========================================================
     * ACTIVE / SELECTION
     * ========================================================= */
    setActive(r,c,resetSelection){
      if (resetSelection && this._skipFocusReset) {
        this._skipFocusReset = false;
        resetSelection = false;
      }
      const changedCell = (r !== this.activeRow || c !== this.activeCol);
      if (this.noteOpen && changedCell) {
        this.commitOpenNote({ silent: true });
      }
      this.activeRow = r; this.activeCol = c;
      if (resetSelection) { this.anchor = {r,c}; this.sel = {ar:r, ac:c, br:r, bc:c}; }
      if (this.noteOpen) {
        const k = this.activeKey();
        this.noteText = this.notes[k] || '';
      }
    },
    cellLabel(){
      const letters = ['A','B','C','D','E','F','G','H'];
      const col = letters[this.activeCol] || '?';
      return `${col}${this.activeRow + 1}`;
    },
    cellClass(ri,ci){
      const k = this.skey(ri,ci);
      const s = this.selectedRange();
      const inSel = s ? (ri>=s.r1 && ri<=s.r2 && ci>=s.c1 && ci<=s.c2) : false;
      const isActive = (ri===this.activeRow && ci===this.activeCol);
      const noteCls = this.hasNote(k) ? ((ci <= 5) ? 'has-note-blue' : 'has-note-red') : '';
      return [ inSel ? 'is-sel' : '', isActive ? 'is-active' : '', noteCls ].join(' ');
    },

    focusCell(r,c){
      r = Math.max(0, Math.min(this.rows.length-1, r));
      c = Math.max(0, Math.min(this.maxCol, c));
      const sel = `[data-cell="1"][data-row="${r}"][data-col="${c}"]`;
      const el = this.$el.querySelector(sel);
      if (!el) return;
      this.setActive(r,c,true);
      el.focus({preventScroll:true});
      const wrap = this.$refs.tablewrap;
      if (wrap && el.scrollIntoView) el.scrollIntoView({block:'nearest', inline:'nearest'});
    },

    onCellMouseDown(e, r, c){
      this._skipFocusReset = true;
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
      if (e && typeof e.buttons === 'number' && e.buttons !== 1) {
        this.dragging = false;
        return;
      }
      if (!this.dragging || !this.anchor) return;
      this.sel = { ar:this.anchor.r, ac:this.anchor.c, br:r, bc:c };
    },
    onMouseDownOutside(e){
      if (!e.target.closest('[data-cell="1"]')) this.dragging = false;
    },

    /* =========================================================
     * KEYBOARD NAV
     * ========================================================= */
    moveRight(){
      if (this.activeCol < this.maxCol) return this.focusCell(this.activeRow, this.activeCol+1);
      if (this.activeRow < this.rows.length-1) return this.focusCell(this.activeRow+1, 1);
      this.rows.push(this.makeRow({}));
      this.rowsTarget = String(this.rows.length);
      this.updateAutoFooters();
      this.onChanged();
      this.$nextTick(() => this.focusCell(this.rows.length-1, 1));
    },
    moveLeft(){
      if (this.activeCol > 0) return this.focusCell(this.activeRow, this.activeCol-1);
      if (this.activeRow > 0) return this.focusCell(this.activeRow-1, this.maxCol);
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
      if (ae.classList && ae.classList.contains('ws-note-text')) return;
      if (!(ae.classList && ae.classList.contains('ws-cell'))) return;

      if ((e.ctrlKey || e.metaKey) && (e.key === 'b' || e.key === 'B')) { e.preventDefault(); return this.toggleFmt('bold'); }
      if ((e.ctrlKey || e.metaKey) && (e.key === 'i' || e.key === 'I')) { e.preventDefault(); return this.toggleFmt('italic'); }
      if ((e.ctrlKey || e.metaKey) && (e.key === 'u' || e.key === 'U')) { e.preventDefault(); return this.toggleFmt('underline'); }
      if ((e.ctrlKey || e.metaKey) && (e.key === 'm' || e.key === 'M')) { e.preventDefault(); this.noteOpen = true; this.noteText = this.notes[this.activeKey()] || ''; return; }

      if ((e.ctrlKey || e.metaKey) && (e.key === 'a' || e.key === 'A')) {
        e.preventDefault();
        if (this.rows.length) {
          this.anchor = {r:0, c:0};
          this.sel = {ar:0, ac:0, br:this.rows.length-1, bc:this.maxCol};
        }
        return;
      }
      if ((e.ctrlKey || e.metaKey) && (e.key === 'z' || e.key === 'Z')) { e.preventDefault(); return this.undo(); }

      if (e.key === 'Delete' || e.key === 'Backspace') {
        if (this.isMultiSel()) {
          e.preventDefault();
          return this.clearSelectedCells();
        }
      }

      if (e.key === 'Enter') { e.preventDefault(); return this.moveRight(); }
      if (e.key === 'Tab') { e.preventDefault(); return e.shiftKey ? this.moveLeft() : this.moveRight(); }

      if (e.key === 'ArrowDown') { e.preventDefault(); return this.moveDown(); }
      if (e.key === 'ArrowUp') { e.preventDefault(); return this.moveUp(); }
      if (e.key === 'ArrowLeft') { e.preventDefault(); return this.moveLeft(); }
      if (e.key === 'ArrowRight') { e.preventDefault(); return this.moveRight(); }
    },

    /* =========================================================
     * CLIPBOARD (EXCEL-LIKE COPY / CUT / PASTE)
     * ========================================================= */
    getCellValue(ri, ci) {
      try {
        if (ci === 0) return String(ri + 1);
        if (ri < 0 || ri >= this.rows.length || ci < 0 || ci > this.maxCol) return '';
        const field = this._colFields[ci];
        if (!field) return '';
        const row = this.rows[ri];
        if (!row) return '';
        if (ci === 6) return row.sales_price || '';
        if (ci === 7) return this.formatDots(row.extended_price || '');
        return String(row[field] ?? '');
      } catch(_) { return ''; }
    },

    setCellValue(ri, ci, val) {
      try {
        if (ci === 0) return;
        if (ri < 0 || ri >= this.rows.length || ci < 0 || ci > this.maxCol) return;
        const row = this.rows[ri];
        if (!row) return;
        val = this.cleanText(String(val ?? ''));
        const field = this._colFields[ci];
        if (!field) return;

        if (ci === 1) {
          row.qty = this.onlyDigits(val);
          this.recalcRow(ri, 'qty', true);
          return;
        }
        if (ci === 6) {
          const raw = this.normalizeMoneyRaw(val);
          row.sales_price_raw = raw;
          row.sales_price = this.rawToRupiahDigits(raw);
          this.recalcRow(ri, 'sales', true);
          return;
        }
        if (ci === 7) {
          const d = this.onlyDigits(val);
          if (d) { row.extended_price = d; row.extended_manual = true; }
          else   { row.extended_manual = false; this.recalcRow(ri, 'ext_clear', true); }
          return;
        }
        row[field] = val;
      } catch(_) {}
    },

    /* --- DELETE / CLEAR --- */
    clearSelectedCells() {
      this.pushUndo();
      const s = this.selectedRange();
      if (!s) return;
      for (let r = s.r1; r <= s.r2; r++) {
        for (let c = s.c1; c <= s.c2; c++) {
          this.setCellValue(r, c, '');
        }
      }
      this.updateAutoFooters();
      this.onChanged();
    },

    /* --- UNDO --- */
    pushUndo() {
      try {
        const snap = {
          rows: JSON.parse(JSON.stringify(this.rows)),
          styles: JSON.parse(JSON.stringify(this.styles)),
          notes: JSON.parse(JSON.stringify(this.notes)),
        };
        this._undoStack.push(snap);
        if (this._undoStack.length > this._maxUndo) this._undoStack.shift();
      } catch(_) {}
    },
    undo() {
      if (!this._undoStack.length) return;
      const snap = this._undoStack.pop();
      if (!snap) return;
      this.rows = (Array.isArray(snap.rows) ? snap.rows : []).map(r => this.makeRow(r));
      this.styles = (snap.styles && typeof snap.styles === 'object') ? snap.styles : {};
      this.notes = (snap.notes && typeof snap.notes === 'object') ? snap.notes : {};
      this.rows.forEach((_, i) => this.recalcRow(i, 'undo', true));
      this.updateAutoFooters();
      this.onChanged();
    },

    isMultiSel() {
      if (!this.sel) return false;
      const s = this.selectedRange();
      if (!s) return false;
      return (s.r1 !== s.r2 || s.c1 !== s.c2);
    },

    onClipCopy(e) {
      try {
        const ae = document.activeElement;
        if (!ae || !this.$el.contains(ae)) return;
        if (!this.isMultiSel()) return;
        e.preventDefault();

        const s = this.selectedRange();
        const lines = [];
        const clipData = [];
        const clipStyles = [];

        for (let r = s.r1; r <= s.r2; r++) {
          const rv = [], rs = [];
          for (let c = s.c1; c <= s.c2; c++) {
            rv.push(this.getCellValue(r, c));
            const sk = this.skey(r, c);
            rs.push(this.styles[sk] ? {...this.styles[sk]} : null);
          }
          lines.push(rv.join('\t'));
          clipData.push(rv);
          clipStyles.push(rs);
        }

        e.clipboardData.setData('text/plain', lines.join('\n'));
        this._clipboard = { data: clipData, styles: clipStyles, startCol: s.c1 };
      } catch(_) {}
    },

    onClipCut(e) {
      try {
        const ae = document.activeElement;
        if (!ae || !this.$el.contains(ae)) return;
        if (!this.isMultiSel()) return;

        this.pushUndo();
        this.onClipCopy(e);

        const s = this.selectedRange();
        for (let r = s.r1; r <= s.r2; r++) {
          for (let c = s.c1; c <= s.c2; c++) {
            this.setCellValue(r, c, '');
            delete this.styles[this.skey(r, c)];
          }
        }
        this.updateAutoFooters();
        this.onChanged();
      } catch(_) {}
    },

    onClipPaste(e) {
      try {
        const ae = document.activeElement;
        if (!ae || !this.$el.contains(ae)) return;
        if (!(ae.classList && (ae.classList.contains('ws-cell') || ae.closest('[data-cell="1"]')))) return;

        const text = (e.clipboardData || window.clipboardData).getData('text/plain');
        if (!text) return;

        const hasTab = text.includes('\t');
        const hasNewline = text.includes('\n');
        if (!hasTab && !hasNewline) return;

        e.preventDefault();
        this.pushUndo();

        let rawLines = text.split('\n');
        if (rawLines.length > 0 && rawLines[rawLines.length - 1].trim() === '') rawLines.pop();
        if (rawLines.length > 500) rawLines = rawLines.slice(0, 500);

        const startRow = this.activeRow;
        const startCol = this.activeCol;
        const mc = this.maxCol;

        const neededRows = Math.min(startRow + rawLines.length, 500);
        while (this.rows.length < neededRows) this.rows.push(this.makeRow({}));

        const maxLines = Math.min(rawLines.length, 500 - startRow);
        for (let ri = 0; ri < maxLines; ri++) {
          const cols = rawLines[ri].split('\t');
          for (let ci = 0; ci < cols.length; ci++) {
            const tc = startCol + ci;
            if (tc > mc) break;
            this.setCellValue(startRow + ri, tc, cols[ci]);
          }
        }

        if (this._clipboard && this._clipboard.styles) {
          const cs = this._clipboard.styles;
          for (let ri = 0; ri < cs.length; ri++) {
            for (let ci = 0; ci < cs[ri].length; ci++) {
              const tr = startRow + ri;
              const tc = startCol + ci;
              if (tc > mc || tr >= this.rows.length) continue;
              if (cs[ri][ci]) this.styles[this.skey(tr, tc)] = {...cs[ri][ci]};
            }
          }
        }

        this.rowsTarget = String(this.rows.length);
        this.updateAutoFooters();
        this.onChanged();

        const endRow = Math.min(startRow + maxLines - 1, this.rows.length - 1);
        const maxC = Math.max(...rawLines.slice(0, maxLines).map(l => l.split('\t').length));
        const endCol = Math.min(startCol + maxC - 1, mc);
        this.sel = { ar: startRow, ac: startCol, br: endRow, bc: endCol };
        this.anchor = { r: startRow, c: startCol };
      } catch(_) {}
    },

    /* =========================================================
     * STYLES/NOTES SHIFT ON DELETE
     * ========================================================= */
    deleteRowStyles(ri){ for (let ci=0; ci<=this.maxCol; ci++){ const k = this.skey(ri,ci); if (this.styles[k]) delete this.styles[k]; } },
    shiftStylesAfterDelete(deletedRi){
      const newStyles = {};
      for (const k in this.styles){
        const parts = k.split(':'); if (parts.length !== 2) continue;
        const ri = parseInt(parts[0], 10); const ci = parseInt(parts[1], 10);
        if (Number.isNaN(ri) || Number.isNaN(ci)) continue;
        if (ri < deletedRi) newStyles[`${ri}:${ci}`] = this.styles[k];
        else if (ri > deletedRi) newStyles[`${ri-1}:${ci}`] = this.styles[k];
      }
      this.styles = newStyles;
      if (this.activeRow > deletedRi) this.activeRow = Math.max(0, this.activeRow - 1);
    },

    deleteRowNotes(ri){ for (let ci=0; ci<=this.maxCol; ci++){ const k = this.skey(ri,ci); if (this.notes[k]) delete this.notes[k]; } },
    shiftNotesAfterDelete(deletedRi){
      const newNotes = {};
      for (const k in this.notes){
        const parts = k.split(':'); if (parts.length !== 2) continue;
        const ri = parseInt(parts[0], 10); const ci = parseInt(parts[1], 10);
        if (Number.isNaN(ri) || Number.isNaN(ci)) continue;
        if (ri < deletedRi) newNotes[`${ri}:${ci}`] = this.notes[k];
        else if (ri > deletedRi) newNotes[`${ri-1}:${ci}`] = this.notes[k];
      }
      this.notes = newNotes;
    },

    /* =========================================================
     * FULLSCREEN + ZOOM
     * ========================================================= */
    toggleFullscreen() {
      // ENTER
      if (!this.isFs) {
        this._bodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        this.isFs = true;

        this.$nextTick(() => {
          this.applyZoom();
          this.focusCell(this.activeRow, this.activeCol);
        });
        return;
      }

      // EXIT
      this.isFs = false;
      document.body.style.overflow = this._bodyOverflow ?? '';

      this.$nextTick(() => {
        this.applyZoom();
        this.focusCell(this.activeRow, this.activeCol);
      });
    },

    applyZoom() {
      const z = Math.max(70, Math.min(150, Number(this.zoom || 90)));
      this.zoom = z;
      const target = this.$refs.zoomTarget;
      if (!target) return;
      // lebih stabil daripada 0.9
      target.style.zoom = `${z}%`;    },
    zoomIn(){ this.zoom = Math.min(150, this.zoom + 5); this.applyZoom(); },
    zoomOut(){ this.zoom = Math.max(70, this.zoom - 5); this.applyZoom(); },
    zoomReset(){ this.zoom = 90; this.applyZoom(); },

    /* =========================================================
     * SAVE (DRAFT + DB)
     * ========================================================= */
    flushPendingSave(force = false) {
      clearTimeout(this._tSave);
      const payload = this.payloadObject();
      payload.ts = Date.now();
      this.writePayloadInput(payload);
      this.saveDraft(true, payload);
      this.emitCreateDraft(payload);
      if (force || this.reportId) {
        this.saveRemote(true, payload);
      }
      return payload;
    },

    onChanged() {
      this.updateAutoFooters();

      // Keep hidden input in sync immediately so form submit never misses latest formatting.
      this.writePayloadInput();

      clearTimeout(this._tSave);
      this.saveStatus = 'Saving...';
      this._tSave = setTimeout(() => {
        const payload = this.payloadObject();
        payload.ts = Date.now();
        this.writePayloadInput(payload);
        this.saveDraft(true, payload);
        this.saveRemote(true, payload);
        this.emitCreateDraft(payload);
      }, 900);
    },

    emitCreateDraft(payload = null) {
      if (this.reportId) return;
      const p = payload && typeof payload === 'object' ? payload : this.payloadObject();
      try {
        window.dispatchEvent(new CustomEvent('ccr:create-draft-section', {
          detail: {
            type: 'engine',
            section: 'parts',
            payload: p,
          },
        }));
      } catch (e) {}
    },

    loadDraft() {
      try {
        const raw = localStorage.getItem(this.storageKey);
        if (!raw) return null;
        return JSON.parse(raw);
      } catch(e) { return null; }
    },

    saveDraft(isAuto = false, payload = null) {
      try {
        const p = payload || this.payloadObject();
        if (!p) return;
        p.ts = p.ts || Date.now();
        localStorage.setItem(this.storageKey, JSON.stringify(p));
        this.saveStatus = (isAuto ? 'Auto-saved' : 'Saved') + ' ' + new Date(p.ts).toLocaleTimeString();
      } catch (e) {
        console.warn('saveDraft failed', e);
      }
    },

    sendBeaconRemote(payload = null) {
      if (!this.reportId || !this.autosaveUrl) return false;
      if (!navigator.sendBeacon) return false;

      const p = payload || this.payloadObject();
      if (!p || typeof p !== 'object') return false;
      p.ts = p.ts || Date.now();

      try {
        const form = new FormData();
        form.append('_token', String(this.csrf || ''));
        form.append('parts_payload', JSON.stringify(p));
        form.append('parts_payload_rev', String(Number(this.payloadRev || 0)));

        const ok = navigator.sendBeacon(this.autosaveUrl, form);
        if (ok) {
          const payloadTs = this.readTs(p.ts);
          if (payloadTs > 0) this.payloadTs = Math.max(this.readTs(this.payloadTs), payloadTs);
        }
        return !!ok;
      } catch (e) {
        return false;
      }
    },

    flushOnLeave(tryRemote = true) {
      const payload = this.payloadObject();
      payload.ts = Date.now();
      this.writePayloadInput(payload);
      this.saveDraft(true, payload);
      this.emitCreateDraft(payload);

      if (tryRemote) {
        this.sendBeaconRemote(payload);
      }
    },

    async saveRemote(isAuto = true, payload = null) {
      if (!this.reportId || !this.autosaveUrl) return;
      const seq = ++this._saveSeq;

      try {
        const p = payload || this.payloadObject();
        if (!p) return;

        const res = await fetch(this.autosaveUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': this.csrf,
          },
          body: JSON.stringify({
            parts_payload: p,
            parts_payload_rev: Number(this.payloadRev || 0),
          }),
        });

        if (seq !== this._saveSeq) return;
        if (!res.ok) throw new Error('autosave http ' + res.status);

        const json = await res.json().catch(() => ({}));
        if (json && typeof json === 'object' && Number.isFinite(Number(json.parts_payload_rev))) {
          this.payloadRev = Number(json.parts_payload_rev || 0);
        }
        if (json && json.stale && json.stale.parts) {
          this.saveStatus = 'AutoSave stale skipped (Parts)';
          return;
        }
        const payloadTs = this.readTs(p.ts);
        if (payloadTs > 0) this.payloadTs = Math.max(this.readTs(this.payloadTs), payloadTs);

        this.saveStatus = (isAuto ? 'Auto-saved (DB)' : 'Saved (DB)') + ' ' + new Date().toLocaleTimeString();
      } catch (e) {
        console.warn('saveRemote failed', e);
        if (seq === this._saveSeq) this.saveStatus = 'AutoSave failed (DB)';
      }
    },

    payloadObject() {
      if (this.noteOpen) this.commitOpenNote({ silent: true, force: true });
      const last = this.lastNonEmptyIndex();
      const keep = Math.max(1, last + 1);
      const rowsSlice = this.rows.slice(0, keep);

      const rowsAll = rowsSlice.map(r => ({
        qty: String(r.qty||'').trim(),
        uom: String(r.uom||'').trim(),
        part_number: String(r.part_number||'').trim(),
        part_description: String(r.part_description||'').trim(),
        part_section: String(r.part_section||'').trim(),
        purchase_price: this.onlyDigits(r.purchase_price),
        total: this.onlyDigits(r.total),
        sales_price: this.onlyDigits(r.sales_price),
        sales_price_raw: this.normalizeMoneyRaw(r.sales_price_raw),
        extended_price: this.onlyDigits(r.extended_price),
        total_manual: !!r.total_manual,
        extended_manual: !!r.extended_manual,
      }));

      const finalFooterTotal = '';
      const finalFooterExtended = this.footerExtendedManual ? this.onlyDigits(this.footerExtended) : this.onlyDigits(this.footerAutoExtended);

      const stylesPlain = JSON.parse(JSON.stringify(this.styles || {}));
      const notesPlain = JSON.parse(JSON.stringify(this.notes || {}));
      const toolsPlain = JSON.parse(JSON.stringify(this.tools || {}));

      return {
        meta: {
          no_unit: String(this.noUnit||'').trim(),
          template_key: String(this.templateKey||'').trim(),
          template_version: String(this.templateVersion||'').trim(),
          footer_total: finalFooterTotal,
          footer_extended: finalFooterExtended,
          footer_total_mode: 'auto',
          footer_extended_mode: this.footerExtendedManual ? 'manual' : 'auto',
          rows_count: this.rows.length,
        },
        rows: rowsAll,
        styles: stylesPlain,
        notes: notesPlain,
        tools: toolsPlain,
        items_master_rows: Array.isArray(this.itemsMasterRows) ? this.itemsMasterRows : [],
      };
    },

    writePayloadInput(payload = null) {
      const input = this.$refs.partsPayloadInput;
      if (!input) return;
      const p = (payload && typeof payload === 'object') ? payload : this.payloadObject();
      input.value = JSON.stringify(p);
    },

    jsonPayload() {
      return JSON.stringify(this.payloadObject());
    },
  }));
});
</script>
