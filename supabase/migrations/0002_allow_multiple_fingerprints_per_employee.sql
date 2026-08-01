-- Satu karyawan boleh punya lebih dari satu sidik jari di device yang sama
-- (mis. jari telunjuk + jempol). Constraint lama unique(device_id, employee_id)
-- membuat enroll fisik sukses di sensor tapi INSERT ke Supabase gagal (409).

alter table fingerprint_templates
  drop constraint if exists fingerprint_templates_device_id_employee_id_key;

-- Index non-unique supaya query "semua sidik jari karyawan X di device Y" tetap cepat.
create index if not exists idx_fingerprint_templates_device_employee
  on fingerprint_templates (device_id, employee_id);
