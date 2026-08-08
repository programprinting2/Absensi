#include "ntp_time.h"
#include "config.h"
#include "wifi_manager.h"
#include <ArduinoJson.h>
#include <SPIFFS.h>

namespace ntp_time {

namespace {
const char *RTC_SYNC_FILE = "/rtc_sync.json";
bool syncedOnce = false;
time_t lastSyncEpoch = 0;
unsigned long lastSyncMillis = 0;

void persistSync(time_t epoch) {
    JsonDocument doc;
    doc["last_sync_epoch"] = (long)epoch;

    File file = SPIFFS.open(RTC_SYNC_FILE, FILE_WRITE);
    if (!file) {
        return;
    }
    serializeJson(doc, file);
    file.close();
}

void loadPersistedSync() {
    if (!SPIFFS.exists(RTC_SYNC_FILE)) {
        return;
    }

    File file = SPIFFS.open(RTC_SYNC_FILE, FILE_READ);
    if (!file) {
        return;
    }

    JsonDocument doc;
    if (deserializeJson(doc, file) == DeserializationError::Ok) {
        lastSyncEpoch = (time_t)doc["last_sync_epoch"].as<long>();
        lastSyncMillis = 0; // dianggap baru saja boot, offset dihitung dari millis() sejak boot
        syncedOnce = true;
    }
    file.close();
}
} // namespace

void trySync() {
    if (!wifi_manager::isConnected()) {
        return;
    }

    configTime(GMT_OFFSET_SEC, DAYLIGHT_OFFSET_SEC, NTP_SERVER);

    struct tm timeinfo;
    if (getLocalTime(&timeinfo, 5000)) {
        // Pakai time() — epoch UTC sejati dari NTP. Jangan mktime() dari
        // getLocalTime(): struct tm itu wall-clock WIB, mktime() salah
        // interpretasi → event_time +7 jam saat ditampilkan di Laravel.
        time_t epoch = 0;
        time(&epoch);
        if (epoch > 0) {
            lastSyncEpoch = epoch;
            lastSyncMillis = millis();
            syncedOnce = true;
            persistSync(lastSyncEpoch);
        }
    }
}

time_t now() {
    if (!syncedOnce) {
        loadPersistedSync();
    }

    if (!syncedOnce) {
        return 0;
    }

    unsigned long elapsedSec = (millis() - lastSyncMillis) / 1000;
    return lastSyncEpoch + (time_t)elapsedSec;
}

bool hasSyncedOnce() {
    if (!syncedOnce) {
        loadPersistedSync();
    }
    return syncedOnce;
}

} // namespace ntp_time
