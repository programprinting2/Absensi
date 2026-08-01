-- Konfigurasi teks LCD per device (header, indikator per mode absen).
-- devices.name tetap dipakai untuk header LCD dan nama di dashboard Laravel.

alter table devices add column if not exists lcd_config jsonb not null default '{
  "modes": {
    "clock_in": {
      "header": "Selamat Datang",
      "indicator_ok": "MANTAB ON TIME !",
      "indicator_warn_prefix": "TERLAMBAT"
    },
    "break_start": {
      "header": "Jangan lupa makan ^_^",
      "indicator_ok": "MANTAB ON TIME !",
      "indicator_info_prefix": "KEMBALI SEBELUM"
    },
    "break_end": {
      "header": "Yuk semangat kerja lagi !",
      "indicator_ok": "MANTAB ON TIME !",
      "indicator_warn_prefix": "OVER BREAK"
    },
    "clock_out": {
      "header": "Terima Kasih",
      "indicator_ok": "SAMPAI JUMPA LAGI",
      "indicator_warn_prefix": "PULANG AWAL"
    }
  }
}'::jsonb;

-- Backfill device yang sudah ada sebelum kolom default diterapkan.
update devices
set lcd_config = '{
  "modes": {
    "clock_in": {
      "header": "Selamat Datang",
      "indicator_ok": "MANTAB ON TIME !",
      "indicator_warn_prefix": "TERLAMBAT"
    },
    "break_start": {
      "header": "Jangan lupa makan ^_^",
      "indicator_ok": "MANTAB ON TIME !",
      "indicator_info_prefix": "KEMBALI SEBELUM"
    },
    "break_end": {
      "header": "Yuk semangat kerja lagi !",
      "indicator_ok": "MANTAB ON TIME !",
      "indicator_warn_prefix": "OVER BREAK"
    },
    "clock_out": {
      "header": "Terima Kasih",
      "indicator_ok": "SAMPAI JUMPA LAGI",
      "indicator_warn_prefix": "PULANG AWAL"
    }
  }
}'::jsonb
where lcd_config is null;
