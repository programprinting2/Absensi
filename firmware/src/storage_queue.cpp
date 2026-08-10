#include "storage_queue.h"
#include <ArduinoJson.h>
#include <SPIFFS.h>
#include <WiFi.h>

namespace storage_queue {

namespace {

const char *QUEUE_DIR = "/q";
const char *LEGACY_QUEUE_DIR = "/queue";
const int MAX_RETRY_COUNT = 20;

enum class ReadStatus {
    Ok,
    OpenFailed,
    ParseFailed,
    InvalidFields,
};

String resolveQueuePath(const String &pathOrName) {
    if (pathOrName.length() == 0) {
        return pathOrName;
    }
    if (pathOrName.startsWith("/q/") || pathOrName.startsWith("/queue/")) {
        return pathOrName;
    }
    if (pathOrName.startsWith("q/")) {
        return String("/") + pathOrName;
    }
    if (pathOrName.startsWith("queue/")) {
        return String("/") + pathOrName;
    }
    return String(QUEUE_DIR) + "/" + pathOrName;
}

String generateClientUuid(time_t eventTime) {
    char buf[48];
    uint64_t mac = ESP.getEfuseMac();
    snprintf(buf, sizeof(buf), "%08lX-%04X-%08lX-%04X",
             (unsigned long)(mac & 0xFFFFFFFF),
             (unsigned)((mac >> 32) & 0xFFFF),
             (unsigned long)eventTime,
             (unsigned)random(0, 0xFFFF));
    return String(buf);
}

String queueFilePath(time_t eventTime) {
    // Path SPIFFS max ~31 char. "/q/" + 8 hex + "_" + 4 hex + ".json" = 21 char.
    char buf[32];
    snprintf(buf, sizeof(buf), "/q/%08lX_%04X.json",
             (unsigned long)eventTime, (unsigned)random(0, 0xFFFF));
    return String(buf);
}

bool isQueueFileName(const String &path) {
    // ".js" = file lama terpotong saat buf terlalu kecil (bug sebelumnya).
    return path.endsWith(".json") || path.endsWith(".js");
}

String entryPath(File &entry) {
    String path = resolveQueuePath(entry.path());
    if (isQueueFileName(path)) {
        return path;
    }
    String name = entry.name();
    path = resolveQueuePath(name);
    return path;
}

bool fillEventFromDoc(const String &path, JsonDocument &doc, PendingEvent &out) {
    if (doc["client_uuid"].isNull() || doc["employee_id"].isNull() ||
        doc["attendance_type"].isNull() || doc["method"].isNull() ||
        doc["event_time"].isNull()) {
        return false;
    }

    String employeeId = doc["employee_id"].as<String>();
    if (employeeId.length() == 0) {
        return false;
    }

    out.path = path;
    out.clientUuid = doc["client_uuid"].as<String>();
    out.employeeId = employeeId;
    out.attendanceType = doc["attendance_type"].as<String>();
    out.method = doc["method"].as<String>();
    out.eventTime = (time_t)doc["event_time"].as<long>();
    out.isOfflineCapture = doc["is_offline_capture"] | false;
    out.needsTimeCorrection = doc["needs_time_correction"] | false;
    out.bootId = doc["boot_id"] | 0;
    out.retryCount = doc["retry_count"] | 0;
    return true;
}

ReadStatus readEventFileDetailed(const String &rawPath, PendingEvent &out) {
    String path = resolveQueuePath(rawPath);

    File file = SPIFFS.open(path, FILE_READ);
    if (!file) {
        Serial.print(F("[queue] Tidak bisa buka (skip dulu): "));
        Serial.println(path);
        return ReadStatus::OpenFailed;
    }

    JsonDocument doc;
    DeserializationError err = deserializeJson(doc, file);
    file.close();

    if (err != DeserializationError::Ok) {
        Serial.print(F("[queue] JSON rusak: "));
        Serial.println(path);
        return ReadStatus::ParseFailed;
    }

    if (!fillEventFromDoc(path, doc, out)) {
        Serial.print(F("[queue] Field tidak lengkap: "));
        Serial.println(path);
        return ReadStatus::InvalidFields;
    }

    return ReadStatus::Ok;
}

bool readEventFile(const String &rawPath, PendingEvent &out) {
    return readEventFileDetailed(rawPath, out) == ReadStatus::Ok;
}

void logRemoveInvalid(const String &path, const char *reason) {
    Serial.print(F("[queue] Hapus file antrian tidak valid ("));
    Serial.print(reason);
    Serial.print(F("): "));
    Serial.println(path);
}

int scanDirectory(const char *dirPath, PendingEvent *outEvents, int maxCount, bool collectEvents) {
    File dir = SPIFFS.open(dirPath);
    if (!dir || !dir.isDirectory()) {
        if (dir) {
            dir.close();
        }
        return 0;
    }

    int count = 0;
    File entry = dir.openNextFile();
    while (entry && (!collectEvents || count < maxCount)) {
        String path = entryPath(entry);
        entry.close();

        if (!isQueueFileName(path)) {
            entry = dir.openNextFile();
            continue;
        }

        if (collectEvents) {
            PendingEvent event;
            ReadStatus status = readEventFileDetailed(path, event);
            if (status == ReadStatus::Ok) {
                outEvents[count] = event;
                count++;
            } else if (status == ReadStatus::ParseFailed || status == ReadStatus::InvalidFields) {
                logRemoveInvalid(path, "data rusak");
                SPIFFS.remove(resolveQueuePath(path));
            }
        } else {
            count++;
        }

        entry = dir.openNextFile();
    }

    dir.close();
    return count;
}

} // namespace

void begin() {
    if (!SPIFFS.exists(QUEUE_DIR)) {
        SPIFFS.mkdir(QUEUE_DIR);
    }
}

bool enqueue(const String &employeeId, const String &attendanceType,
             const String &method, time_t eventTime, bool isOfflineCapture,
             bool needsTimeCorrection, uint32_t bootId) {
    if (employeeId.length() == 0) {
        Serial.println(F("[queue] Gagal enqueue: employee_id kosong"));
        return false;
    }

    String clientUuid = generateClientUuid(eventTime);
    String path = queueFilePath(eventTime);

    JsonDocument doc;
    doc["client_uuid"] = clientUuid;
    doc["employee_id"] = employeeId;
    doc["attendance_type"] = attendanceType;
    doc["method"] = method;
    doc["event_time"] = (long)eventTime;
    doc["is_offline_capture"] = isOfflineCapture;
    doc["needs_time_correction"] = needsTimeCorrection;
    doc["boot_id"] = bootId;
    doc["retry_count"] = 0;

    File file = SPIFFS.open(path, FILE_WRITE);
    if (!file) {
        Serial.print(F("[queue] Gagal buka file untuk ditulis: "));
        Serial.println(path);
        return false;
    }
    serializeJson(doc, file);
    file.close();

    Serial.print(F("[queue] Disimpan ke antrian: "));
    Serial.print(path);
    Serial.print(F(" employee="));
    Serial.println(employeeId);
    return true;
}

int listPending(PendingEvent *outEvents, int maxCount) {
    int count = scanDirectory(QUEUE_DIR, outEvents, maxCount, true);
    if (count < maxCount) {
        count += scanDirectory(LEGACY_QUEUE_DIR, outEvents + count, maxCount - count, true);
    }
    return count;
}

int countPending() {
    return scanDirectory(QUEUE_DIR, nullptr, 0, false) +
           scanDirectory(LEGACY_QUEUE_DIR, nullptr, 0, false);
}

bool incrementRetry(const String &rawPath, int &outRetryCount) {
    String path = resolveQueuePath(rawPath);
    File file = SPIFFS.open(path, FILE_READ);
    if (!file) {
        return false;
    }

    JsonDocument doc;
    if (deserializeJson(doc, file) != DeserializationError::Ok) {
        file.close();
        return false;
    }
    file.close();

    outRetryCount = (doc["retry_count"] | 0) + 1;
    doc["retry_count"] = outRetryCount;

    file = SPIFFS.open(path, FILE_WRITE);
    if (!file) {
        return false;
    }
    serializeJson(doc, file);
    file.close();
    return true;
}

void remove(const String &rawPath) {
    String path = resolveQueuePath(rawPath);
    if (!SPIFFS.remove(path)) {
        Serial.print(F("[queue] Gagal hapus file antrian: "));
        Serial.println(path);
        return;
    }
    Serial.print(F("[queue] Berhasil sync, dihapus: "));
    Serial.println(path);
}

int maxRetryCount() {
    return MAX_RETRY_COUNT;
}

} // namespace storage_queue
