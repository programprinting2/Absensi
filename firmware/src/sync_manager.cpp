#include "sync_manager.h"
#include "boot_session.h"
#include "config.h"
#include "ntp_time.h"
#include "storage_queue.h"
#include "supabase_client.h"
#include "wifi_manager.h"

namespace sync_manager {

namespace {

String deviceId;
unsigned long lastRunMs = 0;
const int MAX_BATCH = 5;
volatile bool syncRequested = false;

void logSyncFailure(const storage_queue::PendingEvent &event, int httpCode) {
    Serial.print(F("[sync] Gagal upload antrian: "));
    Serial.print(event.path);
    Serial.print(F(" employee="));
    Serial.print(event.employeeId);
    Serial.print(F(" type="));
    Serial.print(event.attendanceType);
    Serial.print(F(" uuid="));
    Serial.print(event.clientUuid);
    Serial.print(F(" retry="));
    Serial.print(event.retryCount);
    Serial.print(F(" http="));
    Serial.println(httpCode);
}

void handleFailedUpload(storage_queue::PendingEvent &event, int httpCode) {
    logSyncFailure(event, httpCode);

    int retryCount = 0;
    if (!storage_queue::incrementRetry(event.path, retryCount)) {
        Serial.print(F("[sync] Tidak bisa update retry, coba lagi nanti: "));
        Serial.println(event.path);
        return;
    }

    if (retryCount >= storage_queue::maxRetryCount()) {
        Serial.print(F("[sync] Batas retry tercapai, buang antrian: "));
        Serial.println(event.path);
        storage_queue::remove(event.path);
    }
}

int collectPending(storage_queue::PendingEvent *events, int maxCount, bool urgent) {
    int count = storage_queue::listPending(events, maxCount);
    if (count > 0 || !urgent) {
        return count;
    }

    for (int attempt = 0; attempt < 5 && count == 0; attempt++) {
        vTaskDelay(pdMS_TO_TICKS(100));
        count = storage_queue::listPending(events, maxCount);
    }
    return count;
}

} // namespace

void begin(const String &deviceIdIn) {
    deviceId = deviceIdIn;
    storage_queue::begin();
    int pending = storage_queue::countPending();
    if (pending > 0) {
        Serial.print(F("[sync] Antrian tersisa saat boot: "));
        Serial.println(pending);
        syncRequested = true;
    }
}

void loop() {
    unsigned long now = millis();
    bool urgent = syncRequested;
    if (!urgent && now - lastRunMs < SYNC_INTERVAL_MS) {
        return;
    }
    syncRequested = false;
    lastRunMs = now;

    if (!wifi_manager::isConnected()) {
        return;
    }

    if (!ntp_time::hasValidClockThisBoot()) {
        ntp_time::trySync();
    }

    if (deviceId.length() == 0) {
        Serial.println(F("[sync] Lewati sync: device_id belum resolved"));
        return;
    }

    storage_queue::PendingEvent events[MAX_BATCH];
    int count = collectPending(events, MAX_BATCH, urgent);
    if (count == 0) {
        return;
    }

    Serial.print(F("[sync] Memproses "));
    Serial.print(count);
    Serial.println(F(" item antrian..."));

    for (int i = 0; i < count; i++) {
        time_t eventTimeForUpload = events[i].eventTime;
        if (events[i].needsTimeCorrection &&
            events[i].bootId == boot_session::id() &&
            ntp_time::correctionAnchorReady()) {
            time_t corrected = ntp_time::correctQueuedTime(events[i].eventTime);
            Serial.print(F("[sync] Koreksi waktu antrian: "));
            Serial.print((long)events[i].eventTime);
            Serial.print(F(" -> "));
            Serial.println((long)corrected);
            eventTimeForUpload = corrected;
        }

        int httpCode = -1;
        auto result = supabase_client::insertAttendanceLog(
            deviceId, events[i].employeeId, events[i].attendanceType,
            events[i].method, eventTimeForUpload, events[i].isOfflineCapture,
            events[i].clientUuid, &httpCode);

        if (result == supabase_client::InsertResult::Success ||
            result == supabase_client::InsertResult::DuplicateIgnored) {
            storage_queue::remove(events[i].path);
            continue;
        }

        if (result == supabase_client::InsertResult::Rejected) {
            Serial.print(F("[sync] Ditolak server, buang antrian: "));
            Serial.println(events[i].path);
            storage_queue::remove(events[i].path);
            continue;
        }

        handleFailedUpload(events[i], httpCode);
    }
}

int pendingCount() {
    return storage_queue::countPending();
}

void requestNow() {
    syncRequested = true;
}

} // namespace sync_manager
