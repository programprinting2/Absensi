<?php

/**
 * Registry menu aplikasi.
 *
 * Menambah menu baru = tambah 1 entri di sini.
 * Tab Settings → Hak Akses otomatis menampilkan menu baru;
 * role yang ada di "defaults" otomatis mendapat akses.
 */
return [
    'icons' => [
        'home' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1',
        'users' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        'clipboard' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        'report' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'payroll' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
        'cash' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'leave' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        'settings' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
        'database' => 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4',
    ],

    'items' => [
        [
            'key' => 'dashboard',
            'label' => 'My Dashboard',
            'route' => 'dashboard',
            'patterns' => ['dashboard'],
            'icon' => 'home',
            'sidebar' => true,
            'defaults' => ['admin'],
        ],
        [
            'key' => 'employee.dashboard',
            'label' => 'My Dashboard',
            'route' => 'employee.dashboard',
            'patterns' => ['employee.dashboard'],
            'icon' => 'home',
            'sidebar' => true,
            'defaults' => ['employee'],
        ],
        [
            'key' => 'employees',
            'label' => 'Karyawan',
            'route' => 'employees.index',
            'patterns' => ['employees.*', 'enroll-commands.*'],
            'icon' => 'users',
            'sidebar' => true,
            'defaults' => ['admin'],
        ],
        [
            'key' => 'attendance',
            'label' => 'Absensi',
            'route' => 'attendance.index',
            'patterns' => ['attendance.*'],
            'icon' => 'clipboard',
            'sidebar' => true,
            'defaults' => ['admin'],
        ],
        [
            'key' => 'leaves',
            'label' => 'Cuti',
            'route' => 'leaves.index',
            'patterns' => ['leaves.*'],
            'icon' => 'leave',
            'sidebar' => true,
            'defaults' => ['admin'],
        ],
        [
            'key' => 'employee.leaves',
            'label' => 'Cuti Saya',
            'route' => 'employee.leaves',
            'patterns' => ['employee.leaves'],
            'icon' => 'leave',
            'sidebar' => true,
            'defaults' => ['employee'],
        ],
        [
            'key' => 'reports',
            'label' => 'Laporan',
            'route' => 'reports.attendance',
            'patterns' => ['reports.*'],
            'icon' => 'report',
            'sidebar' => true,
            'defaults' => ['admin'],
        ],
        [
            'key' => 'payroll',
            'label' => 'Penggajian',
            'route' => 'payroll.index',
            'patterns' => ['payroll.*'],
            'icon' => 'payroll',
            'sidebar' => true,
            'defaults' => ['admin'],
        ],
        [
            'key' => 'cash-bons',
            'label' => 'Cash Bon',
            'route' => 'cash-bons.index',
            'patterns' => ['cash-bons.*'],
            'icon' => 'cash',
            'sidebar' => true,
            'defaults' => ['admin'],
        ],
        [
            'key' => 'settings',
            'label' => 'Settings',
            'route' => 'settings.index',
            'patterns' => ['settings.*', 'work-schedule.*'],
            'icon' => 'settings',
            'sidebar' => true,
            'defaults' => ['admin'],
        ],
        [
            'key' => 'tools.database',
            'label' => 'Database & Tools',
            'route' => 'tools.database',
            'patterns' => ['tools.database*', 'tools.google-drive*'],
            'icon' => 'database',
            'sidebar' => false,
            'defaults' => ['admin'],
        ],
    ],
];
