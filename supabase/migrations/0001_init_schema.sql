-- Skema awal sistem absensi (devices, employees, fingerprint templates,
-- jadwal kerja, log absensi, dan command queue untuk enroll sidik jari)

create extension if not exists "pgcrypto";

create table devices (
  id uuid primary key default gen_random_uuid(),
  device_code text unique not null,
  name text not null,
  location text,
  device_secret text not null default encode(gen_random_bytes(16), 'hex'),
  firmware_version text,
  last_seen_at timestamptz,
  is_active boolean not null default true,
  fingerprint_capacity integer,
  lcd_config jsonb not null default '{"modes":{"clock_in":{"header":"Selamat Datang","indicator_ok":"MANTAB ON TIME !","indicator_warn_prefix":"TERLAMBAT"},"break_start":{"header":"Jangan lupa makan ^_^","indicator_ok":"MANTAB ON TIME !","indicator_info_prefix":"KEMBALI SEBELUM"},"break_end":{"header":"Yuk semangat kerja lagi !","indicator_ok":"MANTAB ON TIME !","indicator_warn_prefix":"OVER BREAK"},"clock_out":{"header":"Terima Kasih","indicator_ok":"SAMPAI JUMPA LAGI","indicator_warn_prefix":"PULANG AWAL"}}}'::jsonb,
  created_at timestamptz not null default now()
);

create table employees (
  id uuid primary key default gen_random_uuid(),
  employee_code integer unique not null,
  full_name text not null,
  pin_salt text not null default encode(gen_random_bytes(8), 'hex'),
  pin_hash text,
  is_active boolean not null default true,
  created_at timestamptz not null default now()
);

create table fingerprint_templates (
  id uuid primary key default gen_random_uuid(),
  employee_id uuid not null references employees(id) on delete cascade,
  device_id uuid not null references devices(id) on delete cascade,
  fingerprint_slot_id integer not null,
  enrolled_at timestamptz not null default now(),
  unique (device_id, fingerprint_slot_id)
);

create index idx_fingerprint_templates_device_employee
  on fingerprint_templates (device_id, employee_id);

create table work_schedules (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  clock_in_time time not null,
  clock_out_time time not null,
  break_duration_minutes integer not null default 60,
  is_active boolean not null default true,
  created_at timestamptz not null default now()
);

-- Hanya boleh ada satu jadwal aktif pada satu waktu.
create unique index one_active_schedule
  on work_schedules (is_active)
  where is_active;

create table attendance_logs (
  id uuid primary key default gen_random_uuid(),
  device_id uuid not null references devices(id),
  employee_id uuid not null references employees(id),
  attendance_type text not null check (
    attendance_type in ('clock_in', 'break_start', 'break_end', 'clock_out')
  ),
  method text not null check (method in ('fingerprint', 'pin')),
  event_time timestamptz not null,
  synced_at timestamptz not null default now(),
  is_offline_capture boolean not null default false,
  client_uuid text unique not null,
  raw_notes text
);

create index idx_attendance_employee_time on attendance_logs (employee_id, event_time);
create index idx_attendance_device_time on attendance_logs (device_id, event_time);

create table device_commands (
  id uuid primary key default gen_random_uuid(),
  device_id uuid not null references devices(id),
  command_type text not null check (
    command_type in ('enroll_fingerprint', 'delete_fingerprint', 'sync_schedule', 'reboot', 'start_wifi_portal')
  ),
  payload jsonb,
  status text not null default 'pending' check (
    status in ('pending', 'in_progress', 'completed', 'failed')
  ),
  result jsonb,
  created_by text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create index idx_device_commands_pending
  on device_commands (device_id, status)
  where status = 'pending';

-- Row Level Security: fase awal, device memakai anon key dengan akses sempit.
-- Laravel (dashboard) connect via native Postgres driver dengan role penuh,
-- sehingga tidak tunduk pada RLS di bawah ini.

alter table employees enable row level security;
alter table fingerprint_templates enable row level security;
alter table work_schedules enable row level security;
alter table attendance_logs enable row level security;
alter table device_commands enable row level security;
alter table devices enable row level security;

-- Device (anon key) hanya boleh insert absensi, tidak boleh baca/ubah data karyawan lain.
create policy device_insert_attendance on attendance_logs
  for insert
  with check (true);

-- Device boleh membaca jadwal aktif untuk keperluan cache lokal.
create policy device_read_active_schedule on work_schedules
  for select
  using (is_active = true);

-- Device boleh membaca & mengubah status command miliknya sendiri.
create policy device_read_own_commands on device_commands
  for select
  using (true);

create policy device_update_own_commands on device_commands
  for update
  using (true);

-- Device perlu membaca data karyawan (nama, pin_hash) untuk verifikasi PIN,
-- dan menulis mapping fingerprint saat enroll selesai.
create policy device_read_employees on employees
  for select
  using (is_active = true);

create policy device_write_fingerprint on fingerprint_templates
  for insert
  with check (true);

create policy device_read_fingerprint on fingerprint_templates
  for select
  using (true);
