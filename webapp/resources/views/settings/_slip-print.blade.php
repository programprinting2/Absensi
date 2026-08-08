<div x-show="activeTab === 'slip'" x-cloak class="flex-1 overflow-y-auto p-6">
    <div class="mb-5">
        <h3 class="text-base font-semibold text-gray-900">Cetak Slip Gaji</h3>
        <p class="text-sm text-gray-500 mt-0.5">Pengaturan kertas, margin, dan font dipindah ke halaman Penggajian.</p>
    </div>

    <div class="rounded-lg border border-gray-200 bg-gray-50 px-5 py-6 max-w-xl">
        <p class="text-sm text-gray-700">
            Buka <strong>Penggajian</strong>, centang karyawan, lalu klik <strong>Print</strong>.
            Dialog di sana berisi ukuran kertas, margin, fit to width, preview, dan tombol cetak.
        </p>
        <a href="{{ route('payroll.index') }}"
           class="inline-flex mt-4 items-center px-4 py-2 rounded-md text-sm font-semibold text-white bg-[#f7340d] hover:bg-[#d42c0a]">
            Ke halaman Penggajian
        </a>
    </div>
</div>
