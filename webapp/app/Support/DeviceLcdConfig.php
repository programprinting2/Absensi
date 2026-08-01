<?php

namespace App\Support;

class DeviceLcdConfig
{
    public const MODES = [
        'clock_in' => 'Masuk',
        'break_start' => 'Istirahat',
        'break_end' => 'Kembali',
        'clock_out' => 'Pulang',
    ];

    public const MAX_TEXT_LENGTH = 28;

    public static function defaults(): array
    {
        return [
            'modes' => [
                'clock_in' => [
                    'header' => 'Selamat Datang',
                    'indicator_ok' => 'MANTAB ON TIME !',
                    'indicator_warn_prefix' => 'TERLAMBAT',
                ],
                'break_start' => [
                    'header' => 'Jangan lupa makan ^_^',
                    'indicator_ok' => 'MANTAB ON TIME !',
                    'indicator_info_prefix' => 'KEMBALI SEBELUM',
                ],
                'break_end' => [
                    'header' => 'Yuk semangat kerja lagi !',
                    'indicator_ok' => 'MANTAB ON TIME !',
                    'indicator_warn_prefix' => 'OVER BREAK',
                ],
                'clock_out' => [
                    'header' => 'Terima Kasih',
                    'indicator_ok' => 'SAMPAI JUMPA LAGI',
                    'indicator_warn_prefix' => 'PULANG AWAL',
                ],
            ],
        ];
    }

    public static function mergeWithDefaults(?array $config): array
    {
        $defaults = self::defaults();
        if (! is_array($config)) {
            return $defaults;
        }

        foreach ($defaults['modes'] as $mode => $fields) {
            foreach ($fields as $key => $value) {
                $config['modes'][$mode][$key] = $config['modes'][$mode][$key] ?? $value;
            }
        }

        return $config;
    }

    public static function validationRules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:28'],
        ];

        foreach (self::MODES as $mode => $label) {
            $rules["lcd_config.modes.{$mode}.header"] = ['required', 'string', 'max:' . self::MAX_TEXT_LENGTH];
            $rules["lcd_config.modes.{$mode}.indicator_ok"] = ['required', 'string', 'max:' . self::MAX_TEXT_LENGTH];

            if ($mode === 'break_start') {
                $rules["lcd_config.modes.{$mode}.indicator_info_prefix"] = ['required', 'string', 'max:' . self::MAX_TEXT_LENGTH];
            } else {
                $rules["lcd_config.modes.{$mode}.indicator_warn_prefix"] = ['required', 'string', 'max:' . self::MAX_TEXT_LENGTH];
            }
        }

        return $rules;
    }
}
