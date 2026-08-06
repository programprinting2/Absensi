-- Kolom acuan perhitungan yang dipakai webapp + firmware LCD.
-- late_after_time: ambang terlambat (nullable → fallback clock_in_time di app/device).
-- work_duration_minutes: target jam kerja efektif untuk laporan/payroll.

alter table work_schedules
  add column if not exists work_duration_minutes integer not null default 480;

alter table work_schedules
  add column if not exists late_after_time time;

update work_schedules
set late_after_time = clock_in_time
where late_after_time is null;
