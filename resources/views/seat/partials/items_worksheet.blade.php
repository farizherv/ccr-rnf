{{-- =========================================================
SEAT — TAB: ITEMS (Sheet Items + Save as Template)
File: resources/views/seat/partials/items_worksheet.blade.php

Goal:
- Items master list khusus SEAT (source typeahead di Parts worksheet)
- UI mirip Engine (toolbar + add/delete row + autosave badge + zoom)
- Optional: "Save as Template" (kalau endpoint disediakan)

Controller should pass (recommended):
- $sheetItems (array)
- $endpoints (array) (optional) => [
      'autosave' => url,
      'save_template' => url,
  ]
========================================================= --}}

@php
    $sheetItems = $sheetItems ?? [];
    $endpoints = $endpoints ?? [];
@endphp

<div
    class="w-full"
    x-data="seatItemsWorksheet({
        initialItems: @json($sheetItems),
        endpoints: @json($endpoints),
        csrf: (document.querySelector('meta[name=&quot;csrf-token&quot;]')?.getAttribute('content') ?? ''),
    })"
    x-init="init()"
>
    {{-- Toolbar --}}
    <div class="flex items-center justify-between gap-3 mb-3">
        <div class="flex items-center gap-2 flex-wrap">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gray-100 text-gray-700 text-xs">
                <span class="font-semibold" x-text="autosaveLabel()"></span>
                <span class="mx-1 text-gray-300">|</span>
                <span class="font-semibold" x-text="autoSaveEnabled ? 'AutoSave ON' : 'AutoSave OFF'"></span>
            </div>

            <button type="button" class="px-3 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold inline-flex items-center gap-2 hover:bg-blue-700"
                @click="addRow()">
                <span>+</span> Tambah Baris
            </button>

            <button type="button" class="px-3 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700"
                @click="removeLastRow()" :disabled="items.length===0">
                Hapus Terakhir
            </button>

            <button type="button" class="px-3 py-2 rounded-lg border bg-white hover:bg-gray-50 text-sm font-semibold"
                @click="deleteSelected()" :disabled="selectedIndexes().length===0">
                Hapus Terpilih
            </button>
        </div>

        {{-- Zoom --}}
        <div class="flex items-center gap-2">
            <button type="button" class="w-9 h-9 rounded-lg border bg-white hover:bg-gray-50" @click="zoomOut()">−</button>
            <input type="range" min="60" max="120" step="5" x-model.number="zoom" class="w-40">
            <button type="button" class="w-9 h-9 rounded-lg border bg-white hover:bg-gray-50" @click="zoomIn()">+</button>
            <div class="w-14 text-right text-sm font-semibold text-gray-700" x-text="zoom + '%'"></div>
        </div>
    </div>

    {{-- Save as template --}}
    <div class="mb-3 p-3 border rounded-xl bg-gray-50">
        <div class="flex items-center gap-2 flex-wrap">
            <div class="text-sm font-semibold text-gray-800">Save as Template</div>
            <input type="text" class="border rounded-lg px-3 py-2 text-sm bg-white" placeholder="Template name (key)" x-model="templateKey">
            <input type="text" class="border rounded-lg px-3 py-2 text-sm bg-white" placeholder="Version (optional)" x-model="templateVersion">
            <button type="button" class="px-3 py-2 rounded-lg bg-gray-900 text-white text-sm font-semibold hover:bg-black"
                @click="saveAsTemplate()" :disabled="!endpoints.save_template || !templateKey">
                Save
            </button>
            <div class="text-xs text-gray-500" x-show="!endpoints.save_template">
                (endpoint save_template belum diset — UI siap, tinggal wiring backend)
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="border rounded-xl overflow-hidden bg-white" :style="`transform-origin: top left; transform: scale(${zoom/100}); width: calc(100% * ${100/zoom});`">
        <div class="overflow-auto">
            <table class="min-w-[1100px] w-full border-collapse text-sm">
                <thead>
                    <tr class="bg-black text-white">
                        <th class="px-2 py-2 border border-gray-700 w-14">NO.</th>
                        <th class="px-2 py-2 border border-gray-700 w-44">PART NO.</th>
                        <th class="px-2 py-2 border border-gray-700 min-w-[360px]">DESCRIPTION</th>
                        <th class="px-2 py-2 border border-gray-700 w-24">UOM</th>
                        <th class="px-2 py-2 border border-gray-700 w-40">SECTION</th>
                        <th class="px-2 py-2 border border-gray-700 w-44 text-right">PURCHASE</th>
                        <th class="px-2 py-2 border border-gray-700 w-44 text-right">SALES</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(it, r) in items" :key="it.__id">
                        <tr :class="it.__selected ? 'bg-blue-50' : ''">
                            <td class="border px-2 py-1 text-center cursor-pointer select-none" @click="toggleSelected(r)">
                                <div class="inline-flex items-center justify-center w-9 h-7 rounded bg-gray-100 text-gray-800 font-semibold"
                                     :class="it.__selected ? 'bg-blue-600 text-white' : ''"
                                     x-text="r+1"></div>
                            </td>
                            <td class="border px-1 py-1">
                                <input class="w-full px-2 py-1 rounded border bg-white"
                                    x-model="it.part_number" @input="touch()">
                            </td>
                            <td class="border px-1 py-1">
                                <input class="w-full px-2 py-1 rounded border bg-white"
                                    x-model="it.part_description" @input="touch()">
                            </td>
                            <td class="border px-1 py-1">
                                <input class="w-full px-2 py-1 rounded border bg-white"
                                    x-model="it.uom" @input="touch()">
                            </td>
                            <td class="border px-1 py-1">
                                <input class="w-full px-2 py-1 rounded border bg-white"
                                    x-model="it.part_section" @input="touch()">
                            </td>
                            <td class="border px-1 py-1">
                                <div class="relative">
                                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-500 text-xs">Rp</span>
                                    <input class="w-full pl-8 pr-2 py-1 rounded border bg-white text-right"
                                        x-model="it.purchase_price" @input="touch()">
                                </div>
                            </td>
                            <td class="border px-1 py-1">
                                <div class="relative">
                                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-500 text-xs">Rp</span>
                                    <input class="w-full pl-8 pr-2 py-1 rounded border bg-white text-right"
                                        x-model="it.sales_price" @input="touch()">
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Hidden textarea payload (optional fallback) --}}
    <textarea name="seat_items_payload" class="hidden" x-text="JSON.stringify(itemsForSave())"></textarea>
</div>

@once
@push('scripts')
<script>
    function seatItemsWorksheet(cfg) {
        return {
            endpoints: cfg.endpoints || {},
            csrf: cfg.csrf || '',

            items: [],
            zoom: 75,
            autoSaveEnabled: true,
            autosaveAt: null,
            autosaveState: 'idle',
            dirty: false,
            saveTimer: null,

            templateKey: '',
            templateVersion: '',

            init() {
                const initial = cfg.initialItems || [];
                this.items = initial.map((it,i)=>({
                    part_number: it.part_number ?? '',
                    part_description: it.part_description ?? '',
                    uom: it.uom ?? '',
                    part_section: it.part_section ?? '',
                    purchase_price: it.purchase_price ?? '',
                    sales_price: it.sales_price ?? '',
                    __selected: false,
                    __id: 'sit_'+Date.now()+'_'+i+'_'+Math.random().toString(16).slice(2),
                }));
                if (this.items.length === 0) this.addRow();
            },

            addRow() {
                this.items.push({
                    part_number:'', part_description:'', uom:'', part_section:'', purchase_price:'', sales_price:'',
                    __selected:false, __id:'sit_'+Date.now()+'_'+Math.random().toString(16).slice(2),
                });
                this.touch();
            },

            removeLastRow() {
                if (this.items.length <= 1) return;
                this.items.pop();
                this.touch();
            },

            toggleSelected(i) { this.items[i].__selected = !this.items[i].__selected; },

            selectedIndexes() {
                const out = [];
                this.items.forEach((it, i) => { if (it.__selected) out.push(i); });
                return out;
            },

            deleteSelected() {
                const keep = this.items.filter(it => !it.__selected);
                this.items = keep.length ? keep : [this.items[0] || {}];
                this.items.forEach(it => it.__selected=false);
                this.touch();
            },

            itemsForSave() {
                return this.items.map(it => ({
                    part_number: it.part_number,
                    part_description: it.part_description,
                    uom: it.uom,
                    part_section: it.part_section,
                    purchase_price: it.purchase_price,
                    sales_price: it.sales_price,
                }));
            },

            autosaveLabel() {
                if (this.autosaveState === 'saving') return 'Auto-saving…';
                if (this.autosaveState === 'error') return 'Auto-save error';
                if (!this.autosaveAt) return 'Auto-saved —';
                return 'Auto-saved ' + this.autosaveAt;
            },

            touch() {
                this.dirty = true;
                if (!this.autoSaveEnabled) return;
                if (!this.endpoints.autosave) return;

                clearTimeout(this.saveTimer);
                this.saveTimer = setTimeout(() => this.autosave(), 450);
            },

            async autosave() {
                if (!this.dirty) return;
                this.autosaveState = 'saving';

                try {
                    const res = await fetch(this.endpoints.autosave, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                        },
                        body: JSON.stringify({
                            seat_items_payload: this.itemsForSave(),
                        }),
                    });

                    if (!res.ok) throw new Error('autosave failed');
                    this.dirty = false;
                    this.autosaveState = 'idle';
                    this.autosaveAt = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit' });
                } catch (e) {
                    console.error(e);
                    this.autosaveState = 'error';
                }
            },

            async saveAsTemplate() {
                if (!this.endpoints.save_template) return;
                if (!this.templateKey) return;

                try {
                    const res = await fetch(this.endpoints.save_template, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                        },
                        body: JSON.stringify({
                            key: this.templateKey,
                            version: this.templateVersion || null,
                            items: this.itemsForSave(),
                        }),
                    });
                    if (!res.ok) throw new Error('save_template failed');
                    // optional: show toast
                } catch (e) {
                    console.error(e);
                }
            },

            zoomIn() { this.zoom = Math.min(120, this.zoom + 5); },
            zoomOut() { this.zoom = Math.max(60, this.zoom - 5); },
        };
    }
</script>
@endpush
@endonce
