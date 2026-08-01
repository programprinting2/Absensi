#pragma once

#include <Arduino.h>

namespace sync_manager {

void begin(const String &deviceId);

// Panggil berkala dari loop(). Non-blocking di sisi caller (fungsi ini sendiri
// melakukan HTTP request blocking singkat saat online, tapi hanya dipanggil
// tiap SYNC_INTERVAL_MS).
void loop();

// Paksa sync antrian segera (mis. setelah absen baru).
void requestNow();

int pendingCount();

} // namespace sync_manager
