-- 0001_init_schema.sql mengaktifkan RLS di tabel devices tapi lupa bikin
-- policy SELECT-nya (semua tabel lain dapat, devices kelewatan). Akibatnya
-- device (pakai anon key) tidak pernah bisa resolve device_id miliknya
-- sendiri lewat device_code — query selalu balas 200 OK tapi array kosong.

create policy device_read_own on devices
  for select
  using (true);
