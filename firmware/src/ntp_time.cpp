#include "ntp_time.h"
#include "config.h"
#include "wifi_manager.h"
#include <ArduinoJson.h>
#include <SPIFFS.h>

namespace ntp_time {

namespace {
const char *RTC_SYNC_FILE = "/rtc_sync.json";
bool syncedOnce = false;
bool validClockThisBoot = false;
bool correctionAnchorReadyFlag = false;
time_t lastSyncEpoch = 0;
unsigned long lastSyncMillis = 0;
time_t preNtpDeviceAnchor = 0;
time_t postNtpRealAnchor = 0;

time_t readNowInternal() {
    if (!syncedOnce) {
        return 0;
    }
    unsigned long elapsedSec = (millis() - lastSyncMillis) / 1000;
    return lastSyncEpoch + (time_t)elapsedSec;
}

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
        lastSyncMillis = 0;
        syncedOnce = true;
    }
    file.close();
}
} // namespace

void begin() {
    loadPersistedSync();
}

void trySync() {
    if (!wifi_manager::isConnected()) {
        return;
    }

    if (validClockThisBoot) {
        return;
    }

    // Snapshot jam device (mungkin ngaco) sebelum NTP menimpa baseline.
    if (!syncedOnce) {
        loadPersistedSync();
    }
    if (syncedOnce) {
        preNtpDeviceAnchor = readNowInternal();
    } else {
        preNtpDeviceAnchor = 0;
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
            postNtpRealAnchor = epoch;
            lastSyncEpoch = epoch;
            lastSyncMillis = millis();
            syncedOnce = true;
            validClockThisBoot = true;
            persistSync(lastSyncEpoch);

            if (preNtpDeviceAnchor > 0) {
                correctionAnchorReadyFlag = true;
                Serial.print(F("[ntp] anchor pre="));
                Serial.print((long)preNtpDeviceAnchor);
                Serial.print(F(" post="));
                Serial.println((long)postNtpRealAnchor);
            } else {
                correctionAnchorReadyFlag = false;
            }

            Serial.println(F("[ntp] sync OK — jam valid boot ini"));
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

    return readNowInternal();
}

bool hasSyncedOnce() {
    if (!syncedOnce) {
        loadPersistedSync();
    }
    return syncedOnce;
}

bool hasValidClockThisBoot() {
    return validClockThisBoot;
}

bool correctionAnchorReady() {
    return correctionAnchorReadyFlag && preNtpDeviceAnchor > 0 && postNtpRealAnchor > 0;
}

time_t correctQueuedTime(time_t scanDeviceTime) {
    if (!correctionAnchorReady()) {
        return scanDeviceTime;
    }

    long deltaSec = (long)(preNtpDeviceAnchor - scanDeviceTime);
    time_t corrected = postNtpRealAnchor - deltaSec;
    if (corrected <= 0) {
        return scanDeviceTime;
    }
    return corrected;
}

} // namespace ntp_time
