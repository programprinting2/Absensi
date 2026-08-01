-- Izinkan perintah start_wifi_portal dari Laravel (icon WiFi di Settings).

alter table device_commands
  drop constraint if exists device_commands_command_type_check;

alter table device_commands
  add constraint device_commands_command_type_check
  check (
    command_type in (
      'enroll_fingerprint',
      'delete_fingerprint',
      'sync_schedule',
      'reboot',
      'start_wifi_portal'
    )
  );
