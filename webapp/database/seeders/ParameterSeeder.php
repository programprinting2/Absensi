<?php

namespace Database\Seeders;

use App\Models\Parameter;
use Illuminate\Database\Seeder;

class ParameterSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'name' => 'STATUS PTKP',
                'description' => 'Status pajak penghasilan tidak kena pajak',
                'details' => [
                    ['name' => 'TK/0', 'description' => 'Tidak kawin, tanpa tanggungan'],
                    ['name' => 'TK/1', 'description' => 'Tidak kawin, 1 tanggungan'],
                    ['name' => 'TK/2', 'description' => 'Tidak kawin, 2 tanggungan'],
                    ['name' => 'TK/3', 'description' => 'Tidak kawin, 3 tanggungan'],
                    ['name' => 'K/0', 'description' => 'Kawin, tanpa tanggungan'],
                    ['name' => 'K/1', 'description' => 'Kawin, 1 tanggungan'],
                    ['name' => 'K/2', 'description' => 'Kawin, 2 tanggungan'],
                    ['name' => 'K/3', 'description' => 'Kawin, 3 tanggungan'],
                ],
            ],
            [
                'name' => 'DEPARTEMEN',
                'description' => 'Daftar departemen perusahaan',
                'details' => [
                    ['name' => 'Umum'],
                    ['name' => 'HRD'],
                    ['name' => 'Keuangan'],
                    ['name' => 'Operasional'],
                    ['name' => 'IT'],
                ],
            ],
            [
                'name' => 'JABATAN',
                'description' => 'Daftar jabatan karyawan',
                'details' => [
                    ['name' => 'Staff'],
                    ['name' => 'Supervisor'],
                    ['name' => 'Manager'],
                    ['name' => 'Direktur'],
                ],
            ],
            [
                'name' => 'BANK',
                'description' => 'Daftar bank untuk rekening gaji',
                'details' => [
                    ['name' => 'BCA'],
                    ['name' => 'BRI'],
                    ['name' => 'Mandiri'],
                    ['name' => 'BNI'],
                    ['name' => 'BTN'],
                    ['name' => 'CIMB Niaga'],
                ],
            ],
        ];

        foreach ($groups as $index => $group) {
            $parameter = Parameter::firstOrCreate(
                ['name' => $group['name']],
                [
                    'description' => $group['description'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );

            foreach ($group['details'] as $detailIndex => $detail) {
                $parameter->details()->firstOrCreate(
                    ['name' => $detail['name']],
                    [
                        'value' => $detail['value'] ?? $detail['name'],
                        'description' => $detail['description'] ?? null,
                        'is_active' => true,
                        'sort_order' => $detailIndex + 1,
                    ]
                );
            }
        }
    }
}
