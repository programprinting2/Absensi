#pragma once

#include <Arduino.h>
#include <time.h>

namespace ntp_time {

// Coba sinkronisasi ke NTP. Panggil setiap kali WiFi baru terhubung.
void trySync();

// Waktu sekarang (RTC internal ESP32, disesuaikan offset NTP terakhir).
// Valid untuk dipakai walau sedang offline, selama sudah pernah sync sekali.
time_t now();

bool hasSyncedOnce();

} // namespace ntp_time
