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

void begin();

// Muat cache SPIFFS (kalau ada) supaya layar bisa pakai config sebelum WiFi.
void loadFromSpiffs();

// Ambil name + lcd_config dari server, simpan ke SPIFFS.
bool refresh(const String &deviceId);

String deviceName();
const ModeTexts &modeTexts(AttendanceType type);
bool isLoaded();

} // namespace device_config
