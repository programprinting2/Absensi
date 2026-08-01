#include "device_config.h"
#include "supabase_client.h"
#include <ArduinoJson.h>
#include <SPIFFS.h>

namespace device_config {

namespace {

const char *CONFIG_PATH = "/device_config.json";

String deviceNameCache = "Absensi";
ModeTexts clockInTexts;
ModeTexts breakStartTexts;
ModeTexts breakEndTexts;
ModeTexts clockOutTexts;
WorkSchedule scheduleCache;
bool loadedFlag = false;

ModeTexts &modeSlot(AttendanceType type) {
    switch (type) {
        case AttendanceType::ClockIn: return clockInTexts;
        case AttendanceType::BreakStart: return breakStartTexts;
        case AttendanceType::BreakEnd: return breakEndTexts;
        case AttendanceType::ClockOut: return clockOutTexts;
    }
    return clockInTexts;
}

void applyDefaults() {
    deviceNameCache = "Absensi";
    clockInTexts = {"Selamat Datang", "MANTAB ON TIME !", "TERLAMBAT", ""};
    breakStartTexts = {"Jangan lupa makan ^_^", "MANTAB ON TIME !", "", "KEMBALI SEBELUM"};
    breakEndTexts = {"Yuk semangat kerja lagi !", "MANTAB ON TIME !", "OVER BREAK", ""};
    clockOutTexts = {"Terima Kasih", "SAMPAI JUMPA LAGI", "PULANG AWAL", ""};
    scheduleCache.clockInMinutes = 8 * 60;
    scheduleCache.clockOutMinutes = 17 * 60;
    scheduleCache.breakDurationMinutes = 60;
    scheduleCache.loaded = true;
}

void parseMode(JsonObject modes, const char *key, ModeTexts &out) {
    JsonObject obj = modes[key];
    if (obj.isNull()) {
        return;
    }
    if (!obj["header"].isNull()) {
        out.header = obj["header"].as<String>();
    }
    if (!obj["indicator_ok"].isNull()) {
        out.indicatorOk = obj["indicator_ok"].as<String>();
    }
    if (!obj["indicator_warn_prefix"].isNull()) {
        out.indicatorWarnPrefix = obj["indicator_warn_prefix"].as<String>();
    }
    if (!obj["indicator_info_prefix"].isNull()) {
        out.indicatorInfoPrefix = obj["indicator_info_prefix"].as<String>();
    }
}

int parseTimeToMinutes(const String &timeStr) {
    if (timeStr.length() < 4) {
        return 0;
    }
    int colon = timeStr.indexOf(':');
    if (colon < 0) {
        return 0;
    }
    return timeStr.substring(0, colon).toInt() * 60 + timeStr.substring(colon + 1, colon + 3).toInt();
}

void parseFromDoc(JsonDocument &doc, bool resetToDefaults) {
    if (resetToDefaults) {
        applyDefaults();
    }

    if (!doc["name"].isNull()) {
        deviceNameCache = doc["name"].as<String>();
    }

    JsonObject modes = doc["lcd_config"]["modes"];
    if (!modes.isNull()) {
        parseMode(modes, "clock_in", clockInTexts);
        parseMode(modes, "break_start", breakStartTexts);
        parseMode(modes, "break_end", breakEndTexts);
        parseMode(modes, "clock_out", clockOutTexts);
    }

    JsonObject sched = doc["schedule"];
    if (!sched.isNull()) {
        scheduleCache.clockInMinutes = parseTimeToMinutes(sched["clock_in_time"] | "08:00");
        scheduleCache.clockOutMinutes = parseTimeToMinutes(sched["clock_out_time"] | "17:00");
        scheduleCache.breakDurationMinutes = sched["break_duration_minutes"] | 60;
        scheduleCache.loaded = true;
    }
}

void saveToSpiffs(const String &json) {
    File file = SPIFFS.open(CONFIG_PATH, FILE_WRITE);
    if (!file) {
        return;
    }
    file.print(json);
    file.close();
}

} // namespace

void begin() {
    applyDefaults();
    loadFromSpiffs();
}

void loadFromSpiffs() {
    if (!SPIFFS.exists(CONFIG_PATH)) {
        return;
    }

    File file = SPIFFS.open(CONFIG_PATH, FILE_READ);
    if (!file) {
        return;
    }

    JsonDocument doc;
    if (deserializeJson(doc, file) == DeserializationError::Ok) {
        parseFromDoc(doc, true);
        loadedFlag = true;
    }
    file.close();
}

bool refresh(const String &deviceId) {
    String deviceJson;
    String scheduleJson;

    bool gotDevice = supabase_client::fetchDeviceSettings(deviceId, deviceJson);
    bool gotSchedule = supabase_client::fetchActiveWorkSchedule(scheduleJson);

    if (!gotDevice && !gotSchedule) {
        return false;
    }

    JsonDocument doc;
    if (gotDevice) {
        JsonDocument deviceDoc;
        if (deserializeJson(deviceDoc, deviceJson) == DeserializationError::Ok) {
            JsonArray arr = deviceDoc.as<JsonArray>();
            if (arr.size() > 0) {
                doc["name"] = arr[0]["name"];
                doc["lcd_config"] = arr[0]["lcd_config"];
            }
        }
    } else {
        doc["name"] = deviceNameCache;
    }

    if (gotSchedule) {
        JsonDocument scheduleDoc;
        if (deserializeJson(scheduleDoc, scheduleJson) == DeserializationError::Ok) {
            JsonArray arr = scheduleDoc.as<JsonArray>();
            if (arr.size() > 0) {
                doc["schedule"] = arr[0];
            }
        }
    }

    parseFromDoc(doc, false);

    String serialized;
    serializeJson(doc, serialized);
    saveToSpiffs(serialized);
    loadedFlag = true;
    return true;
}

String deviceName() {
    return deviceNameCache;
}

const ModeTexts &modeTexts(AttendanceType type) {
    return modeSlot(type);
}

const WorkSchedule &schedule() {
    return scheduleCache;
}

bool isLoaded() {
    return loadedFlag;
}

} // namespace device_config
