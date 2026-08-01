# Catatan Keamanan

## Status saat ini (fase awal, milestone 1-4)

Device (ESP32) mengakses Supabase langsung menggunakan `anon` key + RLS policy
yang didefinisikan di `supabase/migrations/0001_init_schema.sql`. Policy ini
sengaja longgar (`with check (true)` pada insert absensi & fingerprint) agar
pengembangan awal cepat berjalan.

**Risiko**: siapa pun yang mengetahui `anon` key + endpoint Supabase project ini
bisa insert data absensi palsu atau membaca daftar karyawan aktif. Ini bisa diterima
untuk 1 device di jaringan kantor tertutup, tapi TIDAK layak untuk rollout
multi-device produksi.

## Rencana hardening (milestone 8, sebelum multi-device produksi)

Ganti jalur akses device dari langsung-ke-Postgrest menjadi lewat
**Supabase Edge Function** (`supabase/functions/device-gateway/`):

- Device mengirim `device_code` + `device_secret` (kolom `devices.device_secret`)
  di header setiap request ke Edge Function.
- Edge Function memverifikasi kredensial, lalu menggunakan `service_role` key
  HANYA di sisi server untuk melakukan operasi ke Postgres.
- Firmware tidak pernah menyimpan `service_role` key.
- RLS policy pada tabel-tabel di atas bisa diperketat (mis. `with check` yang
  memvalidasi `device_id` sesuai token), karena akses publik langsung
  dihilangkan setelah migrasi ini.

## Aturan tetap (berlaku di semua fase)

- Device **tidak pernah** memakai `service_role` key secara langsung.
- Laravel dashboard connect ke Supabase Postgres via native driver dengan
  kredensial admin yang hanya disimpan di `.env` server lokal, tidak pernah
  dikirim ke client/browser.
- PIN karyawan disimpan sebagai hash (HMAC-SHA256 + salt per-karyawan), tidak
  pernah plaintext di database maupun di firmware.
