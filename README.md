# Absensi Online

Sistem absensi karyawan berbasis ESP32 (sidik jari ZW111 + keypad matrix 4x4 +
LCD TFT ST7735) dengan dashboard manajemen Laravel dan database Supabase.

## Struktur Proyek

- `firmware/` — firmware ESP32 (PlatformIO)
- `webapp/` — dashboard manajemen Laravel
- `supabase/` — skema database & migration (source of truth)
- `docs/` — dokumentasi arsitektur & catatan keamanan

## Alur Singkat

Device ESP32 terhubung langsung ke Supabase (bukan lewat Laravel), sehingga
proses absensi tetap berjalan walau komputer kantor yang menjalankan Laravel
belum menyala. Saat internet kantor mati, device menyimpan absensi secara
lokal dan menyinkronkannya begitu koneksi kembali tersedia. Laravel dashboard
mengakses database Supabase yang sama untuk manajemen karyawan, jadwal kerja,
dan laporan absensi.

Lihat `docs/security-notes.md` untuk detail strategi keamanan akses device.
