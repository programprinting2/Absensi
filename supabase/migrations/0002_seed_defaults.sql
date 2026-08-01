-- Seed device pertama dan jadwal kerja default.
-- Ganti device_code sesuai kebutuhan sebelum flashing firmware pertama kali.

insert into devices (device_code, name, location)
values ('DEV-001', 'Pintu Utama', 'Kantor Pusat');

insert into work_schedules (
  name, clock_in_time, clock_out_time, break_duration_minutes, is_active
) values (
  'Jadwal Default', '08:00', '17:00', 60, true
);
