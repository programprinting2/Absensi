<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <a href="{{ route('payroll.settings') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Pengaturan</a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Jenis Potongan</h2>
            </div>
            <a href="{{ route('payroll.deduction-types.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-gray-700">+ Tambah</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Metode</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Nilai Default</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($deductionTypes as $type)
                            <tr>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $type->name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $type->calculation_method === 'fixed' ? 'Nominal Tetap' : 'Persentase' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700 text-right tabular-nums">
                                    {{ $type->calculation_method === 'percentage' ? number_format($type->default_value, 1) . '%' : 'Rp ' . number_format($type->default_value, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $type->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $type->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm space-x-2">
                                    <a href="{{ route('payroll.deduction-types.edit', $type) }}" class="text-blue-600 hover:text-blue-800">Edit</a>
                                    <form method="POST" action="{{ route('payroll.deduction-types.destroy', $type) }}" class="inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" onclick="return confirm('Hapus potongan ini?')" class="text-red-600 hover:text-red-800">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">Belum ada jenis potongan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
