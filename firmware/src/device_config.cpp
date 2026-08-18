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

    if (!supabase_client::fetchDeviceSettings(deviceId, deviceJson)) {
        return false;
    }

    JsonDocument doc;
    JsonDocument deviceDoc;
    if (deserializeJson(deviceDoc, deviceJson) != DeserializationError::Ok) {
        return false;
    }

    JsonArray arr = deviceDoc.as<JsonArray>();
    if (arr.size() == 0) {
        return false;
    }

    doc["name"] = arr[0]["name"];
    doc["lcd_config"] = arr[0]["lcd_config"];

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

bool isLoaded() {
    return loadedFlag;
}

} // namespace device_config
