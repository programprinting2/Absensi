@php
    use App\Models\PayrollSetting;
    use App\Support\PaySlipPaper;
    use App\Support\PaySlipPrintConfig;

    $slip = PayrollSetting::active();
    $cfg = PaySlipPrintConfig::fromSettings($slip);
    $paperLabels = PaySlipPaper::options();
    $saveUrl = route('settings.slip.update');
    $previewBase = route('payroll.slip.preview');
@endphp

<div
    x-data="{
        open: false,
        saving: false,
        previewLoading: false,
        previewUrl: '',
        _previewTimer: null,
        _blobUrl: null,
        baseUrl: '',
        requireSelected: false,
        selectionRoot: null,
        paper: @js($cfg->paper),
        font: @js($cfg->font),
        fontScale: {{ $cfg->fontScale }},
        marginTop: {{ $cfg->marginTop }},
        marginRight: {{ $cfg->marginRight }},
        marginBottom: {{ $cfg->marginBottom }},
        marginLeft: {{ $cfg->marginLeft }},
        fitToWidth: @js($cfg->fitToWidth),
        packPerPage: @js($cfg->packPerPage),
        customWidth: @js($cfg->customWidthMm),
        customHeight: @js($cfg->customHeightMm),
        paperDefaults: {
            thermal_15x10: { w: 150, h: 100 },
            thermal_80: { w: 80, h: 120 },
            a5: { w: 148, h: 210 },
            a4: { w: 210, h: 297 }
        },
        get pageW() {
            const n = parseFloat(this.customWidth);
            if (!isNaN(n) && n > 0) return n;
            return (this.paperDefaults[this.paper] || this.paperDefaults.a4).w;
        },
        get pageH() {
            const n = parseFloat(this.customHeight);
            if (!isNaN(n) && n > 0) return n;
            return (this.paperDefaults[this.paper] || this.paperDefaults.a4).h;
        },
        get isShortPaper() { return this.pageH <= 110; },
        get overflowRisk() { return this.isShortPaper && this.fontScale > 100; },
        onPaperChange() {
            this.customWidth = null;
            this.customHeight = null;
            this.packPerPage = !(this.paper === 'thermal_15x10' || this.paper === 'thermal_80');
            this.schedulePreview();
        },
        selectedIds() {
            if (!this.requireSelected) return [];
            if (!this.selectionRoot) return [];
            const root = document.querySelector(this.selectionRoot);
            if (!root) return [];
            return Array.from(root.querySelectorAll('input.js-slip-entry:checked'))
                .map((el) => el.value)
                .filter(Boolean);
        },
        queryParams(extra = {}) {
            const p = {
                paper: this.paper,
                font: this.font,
                font_scale: String(this.fontScale),
                mt: String(this.marginTop),
                mr: String(this.marginRight),
                mb: String(this.marginBottom),
                ml: String(this.marginLeft),
                fit: this.fitToWidth ? '1' : '0',
                pack: this.packPerPage ? '1' : '0',
                width: this.customWidth === null || this.customWidth === '' ? '' : String(this.customWidth),
                height: this.customHeight === null || this.customHeight === '' ? '' : String(this.customHeight),
                ...extra,
            };
            return p;
        },
        buildPrintUrl() {
            const url = new URL(this.baseUrl, window.location.origin);
            Object.entries(this.queryParams()).forEach(([k, v]) => url.searchParams.set(k, v));
            const ids = this.selectedIds();
            if (ids.length) url.searchParams.set('entries', ids.join(','));
            return url.toString();
        },
        buildPreviewUrl() {
            // Kalau sudah centang karyawan → preview = PDF cetak asli (WYSIWYG)
            const ids = this.selectedIds();
            if (this.baseUrl && (!this.requireSelected || ids.length > 0)) {
                const url = new URL(this.baseUrl, window.location.origin);
                Object.entries(this.queryParams()).forEach(([k, v]) => url.searchParams.set(k, v));
                if (ids.length) url.searchParams.set('entries', ids.join(','));
                url.searchParams.set('format', 'pdf');
                url.searchParams.set('inline', '1');
                url.searchParams.set('_', String(Date.now()));
                return url.toString();
            }
            // Belum centang → contoh layout (termasuk pack 2×2 jika A4)
            const url = new URL(@js($previewBase), window.location.origin);
            Object.entries(this.queryParams()).forEach(([k, v]) => url.searchParams.set(k, v));
            url.searchParams.set('_', String(Date.now()));
            return url.toString();
        },
        schedulePreview() {
            clearTimeout(this._previewTimer);
            this.previewLoading = true;
            this._previewTimer = setTimeout(() => this.refreshPreview(), 450);
        },
        refreshPreview() {
            this.previewUrl = this.buildPreviewUrl();
            this.previewLoading = false;
        },
        async saveSettings() {
            const token = document.querySelector('meta[name=csrf-token]')?.content;
            const body = new FormData();
            body.append('_method', 'PUT');
            body.append('slip_paper', this.paper);
            body.append('slip_font', this.font);
            body.append('slip_font_scale', String(this.fontScale));
            body.append('slip_margin_top_mm', String(this.marginTop));
            body.append('slip_margin_right_mm', String(this.marginRight));
            body.append('slip_margin_bottom_mm', String(this.marginBottom));
            body.append('slip_margin_left_mm', String(this.marginLeft));
            if (this.fitToWidth) body.append('slip_fit_to_width', '1');
            if (this.customWidth !== null && this.customWidth !== '') body.append('slip_width_mm', String(this.customWidth));
            if (this.customHeight !== null && this.customHeight !== '') body.append('slip_height_mm', String(this.customHeight));

            const res = await fetch(@js($saveUrl), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token || '',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body,
                credentials: 'same-origin',
                redirect: 'follow',
            });
            return res.ok || res.redirected;
        },
        async doPrint() {
            if (this.requireSelected && this.selectedIds().length === 0) {
                alert('Centang karyawan yang mau dicetak terlebih dahulu.');
                return;
            }
            if (this.overflowRisk && !confirm('Skala font ' + this.fontScale + '% pada kertas pendek berisiko jadi 2 halaman. Tetap cetak?')) {
                return;
            }
            this.saving = true;
            try { await this.saveSettings(); } catch (e) {}

            try {
                const url = new URL(this.buildPrintUrl(), window.location.origin);
                url.searchParams.set('format', 'pdf');
                url.searchParams.set('inline', '1');

                const res = await fetch(url.toString(), { credentials: 'same-origin' });
                if (!res.ok) throw new Error('Gagal menyiapkan PDF (' + res.status + ')');

                const blob = await res.blob();
                if (this._blobUrl) URL.revokeObjectURL(this._blobUrl);
                this._blobUrl = URL.createObjectURL(blob);

                let frame = document.getElementById('slip-silent-print-frame');
                if (!frame) {
                    frame = document.createElement('iframe');
                    frame.id = 'slip-silent-print-frame';
                    frame.setAttribute('title', 'Slip print');
                    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;opacity:0;pointer-events:none;';
                    document.body.appendChild(frame);
                }

                const blobUrl = this._blobUrl;
                await new Promise((resolve, reject) => {
                    const timer = setTimeout(() => reject(new Error('Timeout memuat PDF')), 20000);
                    frame.onload = () => { clearTimeout(timer); resolve(); };
                    frame.onerror = () => { clearTimeout(timer); reject(new Error('Gagal memuat PDF')); };
                    frame.src = blobUrl;
                });

                this.open = false;
                this.saving = false;
                try {
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                } catch (err) {
                    window.open(blobUrl, '_blank', 'noopener');
                }
            } catch (err) {
                this.saving = false;
                alert((err && err.message) ? err.message : 'Gagal cetak. Coba lagi.');
            }
        },
        handleOpen(detail) {
            this.baseUrl = detail.baseUrl || '';
            this.requireSelected = !!detail.requireSelected;
            this.selectionRoot = detail.selectionRoot || null;
            this.open = true;
            this.$nextTick(() => this.refreshPreview());
        }
    }"
    @open-slip-print.window="handleOpen($event.detail)"
>
    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            class="fixed inset-0 z-[80] flex items-center justify-center p-4 bg-gray-900/50"
            @keydown.escape.window="open = false"
        >
            <div
                class="bg-white rounded-xl shadow-xl w-full max-w-5xl max-h-[92vh] overflow-hidden flex flex-col"
                @click.outside="open = false"
            >
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 shrink-0">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Print Slip Gaji</h3>
                        <p class="text-xs text-gray-500">Centang karyawan dulu — preview menampilkan PDF yang sama persis dengan hasil Cetak.</p>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-700 text-xl leading-none px-2" @click="open = false">&times;</button>
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto p-5">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                        <div class="space-y-4">
                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-900">
                                <p class="font-semibold mb-1">Kenapa jadi 1 lembar A4?</p>
                                <p>PDF thermal (15×10) tetap dicetak di <strong>kertas A4</strong> jika di dialog Windows printer-nya HP/laser. Ukuran kertas fisik ditentukan printer, bukan hanya setting di sini.</p>
                                <ul class="mt-1.5 list-disc pl-4 space-y-0.5">
                                    <li>Mau label 15×10 → pilih <strong>printer thermal</strong> + kertas 15×10 di dialog Windows.</li>
                                    <li>Mau cetak di HP/laser → pilih ukuran <strong>A4</strong> di bawah (bukan Thermal).</li>
                                </ul>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Cetak ke perangkat</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button"
                                            @click="paper = 'thermal_15x10'; fontScale = 80; packPerPage = false; customWidth = null; customHeight = null; schedulePreview()"
                                            :class="paper === 'thermal_15x10' || paper === 'thermal_80' ? 'border-[#f7340d] bg-orange-50 text-[#f7340d]' : 'border-gray-200 bg-white text-gray-700'"
                                            class="rounded-lg border px-3 py-2 text-left text-sm font-medium">
                                        Thermal / label
                                        <span class="block text-[11px] font-normal opacity-70 mt-0.5">Bukan HP Laser</span>
                                    </button>
                                    <button type="button"
                                            @click="paper = 'a4'; fontScale = 100; packPerPage = true; customWidth = null; customHeight = null; schedulePreview()"
                                            :class="paper === 'a4' || paper === 'a5' ? 'border-[#f7340d] bg-orange-50 text-[#f7340d]' : 'border-gray-200 bg-white text-gray-700'"
                                            class="rounded-lg border px-3 py-2 text-left text-sm font-medium">
                                        Laser / A4
                                        <span class="block text-[11px] font-normal opacity-70 mt-0.5">HP, Canon, dll</span>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Ukuran kertas</label>
                                <select x-model="paper" @change="onPaperChange()" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-[#f7340d] focus:border-[#f7340d]">
                                    @foreach ($paperLabels as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-[11px] text-red-600" x-show="paper === 'thermal_15x10' || paper === 'thermal_80'" x-cloak>
                                    Jangan pilih printer HP/A4 di dialog Windows — hasilnya selalu 1 lembar A4.
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Font</label>
                                    <select x-model="font" @change="schedulePreview()" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-[#f7340d] focus:border-[#f7340d]">
                                        <option value="helvetica">Helvetica (Arial)</option>
                                        <option value="times">Times New Roman</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Skala font (%)</label>
                                    <input type="number" min="70" max="150" step="5" x-model.number="fontScale" @change="schedulePreview()" @input="schedulePreview()" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-[#f7340d] focus:border-[#f7340d]">
                                    <p class="mt-1 text-[11px] text-gray-500">100 = normal · 15×10 coba <strong>70–80</strong> · 80 mm 100–120</p>
                                </div>
                            </div>

                            <div>
                                <p class="text-sm font-medium text-gray-700 mb-2">Margin (mm)</p>
                                <div class="grid grid-cols-4 gap-2">
                                    <div>
                                        <label class="text-[11px] text-gray-500">Atas</label>
                                        <input type="number" min="0" max="50" step="0.5" x-model.number="marginTop" @change="schedulePreview()" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    </div>
                                    <div>
                                        <label class="text-[11px] text-gray-500">Kanan</label>
                                        <input type="number" min="0" max="50" step="0.5" x-model.number="marginRight" @change="schedulePreview()" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    </div>
                                    <div>
                                        <label class="text-[11px] text-gray-500">Bawah</label>
                                        <input type="number" min="0" max="50" step="0.5" x-model.number="marginBottom" @change="schedulePreview()" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    </div>
                                    <div>
                                        <label class="text-[11px] text-gray-500">Kiri</label>
                                        <input type="number" min="0" max="50" step="0.5" x-model.number="marginLeft" @change="schedulePreview()" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    </div>
                                </div>
                            </div>

                            <label class="flex items-start gap-2">
                                <input type="checkbox" x-model="fitToWidth" @change="schedulePreview()" class="mt-1 rounded border-gray-300 text-[#f7340d] focus:ring-[#f7340d]">
                                <span>
                                    <span class="text-sm font-medium text-gray-800">Fit to width</span>
                                    <span class="block text-[11px] text-gray-500">Tabel memenuhi lebar area cetak.</span>
                                </span>
                            </label>

                            <label class="flex items-start gap-2" x-show="paper === 'a4' || paper === 'a5'" x-cloak>
                                <input type="checkbox" x-model="packPerPage" @change="schedulePreview()" class="mt-1 rounded border-gray-300 text-[#f7340d] focus:ring-[#f7340d]">
                                <span>
                                    <span class="text-sm font-medium text-gray-800">Gabung beberapa slip per halaman</span>
                                    <span class="block text-[11px] text-gray-500">A4: 4 slip (2×2). A5: 2 slip. Menghindari halaman kosong.</span>
                                </span>
                            </label>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Lebar kustom (mm)</label>
                                    <input type="number" min="40" max="300" step="0.5"
                                           :value="customWidth ?? ''"
                                           @input="customWidth = ($event.target.value === '' ? null : $event.target.value); schedulePreview()"
                                           placeholder="Kosong = preset"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Tinggi kustom (mm)</label>
                                    <input type="number" min="50" max="400" step="0.5"
                                           :value="customHeight ?? ''"
                                           @input="customHeight = ($event.target.value === '' ? null : $event.target.value); schedulePreview()"
                                           placeholder="Kosong = preset"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                </div>
                            </div>
                            <button type="button" class="text-xs text-[#f7340d] hover:underline" @click="customWidth = null; customHeight = null; schedulePreview()">
                                Pakai ukuran preset kertas (<span x-text="(paperDefaults[paper] || {}).w + '×' + (paperDefaults[paper] || {}).h + ' mm'"></span>)
                            </button>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Preview PDF</p>
                                    <p class="text-xs text-gray-500" x-text="Math.round(pageW) + ' × ' + Math.round(pageH) + ' mm · skala ' + fontScale + '%'"></p>
                                </div>
                                <span class="text-[10px] uppercase text-gray-400 font-medium" x-text="previewLoading ? 'Memuat…' : 'PDF'"></span>
                            </div>
                            <div class="rounded-lg bg-gray-200 border border-gray-300 overflow-hidden relative" style="height: 420px;">
                                <iframe
                                    x-show="previewUrl"
                                    :src="previewUrl"
                                    class="w-full h-full bg-white"
                                    title="Preview slip PDF"
                                ></iframe>
                                <div x-show="!previewUrl || previewLoading" class="absolute inset-0 flex items-center justify-center text-sm text-gray-500 bg-gray-100/80">
                                    Menyiapkan preview PDF…
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-amber-700" x-show="overflowRisk" x-cloak>
                                Skala <span x-text="fontScale"></span>% pada tinggi <span x-text="Math.round(pageH)"></span> mm sering membuat <strong>2 halaman</strong> (seperti di print dialog). Turunkan ke 70–80%.
                            </p>
                            <p class="mt-2 text-xs text-gray-500" x-show="!overflowRisk">
                                <span x-show="requireSelected && selectedIds().length === 0" x-cloak>Centang karyawan untuk preview data asli. Sekarang menampilkan contoh layout.</span>
                                <span x-show="!requireSelected || selectedIds().length > 0">Preview = file PDF yang sama dengan tombol Cetak.</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 px-5 py-3 border-t border-gray-100 bg-gray-50 shrink-0">
                    <p class="text-[11px] text-gray-500 max-w-lg">
                        <span x-show="paper === 'a4' || paper === 'a5'">Setelah Cetak → pilih printer laser → kertas A4 → Scale: Default / 100%.</span>
                        <span x-show="paper === 'thermal_15x10' || paper === 'thermal_80'" x-cloak>Setelah Cetak → pilih printer <strong>thermal</strong> (bukan HP) → ukuran kertas 15×10 / 80 mm → Scale: Actual size.</span>
                    </p>
                    <div class="flex justify-end gap-2 shrink-0">
                        <button type="button" class="px-4 py-2 text-sm text-gray-700 hover:text-gray-900" @click="open = false">Batal</button>
                        <button type="button"
                                class="inline-flex items-center px-4 py-2 rounded-md text-sm font-semibold text-white bg-[#f7340d] hover:bg-[#d42c0a] disabled:opacity-60"
                                :disabled="saving"
                                @click="doPrint()">
                            <span x-show="!saving">Cetak</span>
                            <span x-show="saving" x-cloak>Menyiapkan...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
