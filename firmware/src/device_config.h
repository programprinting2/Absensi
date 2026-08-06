#pragma once

#include "app_state.h"
#include <Arduino.h>

namespace device_config {

struct ModeTexts {
    String header;
    String indicatorOk;
    String indicatorWarnPrefix;
    String indicatorInfoPrefix;
};

struct WorkSchedule {
    int clockInMinutes = 8 * 60;
    int lateAfterMinutes = 8 * 60; // ambang terlambat; fallback = clockInMinutes
    int clockOutMinutes = 17 * 60;
    int breakDurationMinutes = 60;
    bool loaded = false;
};

void begin();

// Muat cache SPIFFS (kalau ada) supaya layar bisa pakai config sebelum WiFi.
void loadFromSpiffs();

// Ambil name + lcd_config + jadwal aktif dari Supabase, simpan ke SPIFFS.
bool refresh(const String &deviceId);

String deviceName();
const ModeTexts &modeTexts(AttendanceType type);
const WorkSchedule &schedule();
bool isLoaded();

} // namespace device_config
