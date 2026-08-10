#pragma once

#include <Arduino.h>
#include <time.h>

namespace ntp_time {

void begin();

// Coba sinkronisasi ke NTP. Panggil setiap kali WiFi baru terhubung.
void trySync();

// Waktu sekarang (RTC internal ESP32, disesuaikan offset NTP terakhir).
// Valid untuk dipakai walau sedang offline, selama sudah pernah sync sekali.
time_t now();

bool hasSyncedOnce();

// True setelah NTP sukses pada boot sesi ini — jam LCD dipercaya untuk absensi baru.
bool hasValidClockThisBoot();

// Anchor koreksi antrian (snapshot sebelum/sesudah NTP boot ini) sudah siap.
bool correctionAnchorReady();

// Koreksi event_time antrian: jam_real_online − (jam_device_online − jam_device_scan).
time_t correctQueuedTime(time_t scanDeviceTime);

} // namespace ntp_time
