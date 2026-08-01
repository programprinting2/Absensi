-- Kolom buat halaman Settings: kapasitas sensor sidik jari (dibaca langsung
-- dari modul saat boot, bukan ditebak) dan heartbeat online/offline.
-- last_seen_at sudah ada dari 0001_init_schema.sql, cuma belum pernah diisi.

alter table devices add column if not exists fingerprint_capacity integer;

-- Device (anon key) perlu bisa update last_seen_at & fingerprint_capacity
-- miliknya sendiri buat heartbeat status online/kapasitas sensor.
create policy device_update_own on devices
  for update
  using (true);
