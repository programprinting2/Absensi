<?php

use App\Models\Employee;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[On('echo:employees,.EmployeeListUpdated')]
    public function refresh(): void
    {
        //
    }

    public function with(): array
    {
        return [
            'employees' => Employee::withCount('fingerprintTemplates')
                ->orderBy('employee_code', 'desc')
                ->paginate(20),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Manajemen Karyawan</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="bg-green-50 text-green-800 text-sm px-4 py-3 rounded-md border border-green-200">
                    {{ session('status') }}
                </div>
            @endif

            <div class="flex justify-end">
                <a href="{{ route('employees.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Tambah Karyawan
                </a>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-3">ID</th>
                                <th class="px-6 py-3">Nama</th>
                                <th class="px-6 py-3">Sidik Jari</th>
                                <th class="px-6 py-3">PIN</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($employees as $employee)
                                <tr wire:key="employee-{{ $employee->id }}" class="hover:bg-gray-50">
                                    <td class="px-6 py-3 whitespace-nowrap text-gray-700">{{ $employee->employee_code }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap font-medium text-gray-900">{{ $employee->full_name }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-gray-500">
                                        {{ $employee->fingerprint_templates_count }} terdaftar
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap">
                                        @if ($employee->pin_hash)
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-600" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                            </svg>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap">
                                        @if ($employee->is_active)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Aktif
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                                Nonaktif
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap text-right">
                                        <a href="{{ route('employees.edit', $employee) }}"
                                           class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-6 text-center text-gray-500">
                                        Belum ada karyawan terdaftar.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $employees->links() }}
        </div>
    </div>
</div>
