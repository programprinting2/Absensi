#include "attendance_rules.h"
#include "device_config.h"
#include <ArduinoJson.h>
#include <SPIFFS.h>

namespace attendance_rules {

namespace {

const char *BREAK_SESSIONS_PATH = "/break_sessions.json";

String formatClockTime(time_t t) {
    struct tm *tmInfo = localtime(&t);
    char buf[6];
    snprintf(buf, sizeof(buf), "%02d:%02d", tmInfo->tm_hour, tmInfo->tm_min);
    return String(buf);
}

bool sameLocalDay(time_t a, time_t b) {
    struct tm taBuf = *localtime(&a);
    struct tm tbBuf = *localtime(&b);
    return taBuf.tm_year == tbBuf.tm_year && taBuf.tm_yday == tbBuf.tm_yday;
}

bool loadSessionsDoc(JsonDocument &doc) {
    if (!SPIFFS.exists(BREAK_SESSIONS_PATH)) {
        return true;
    }

    File file = SPIFFS.open(BREAK_SESSIONS_PATH, FILE_READ);
    if (!file) {
        return false;
    }

    DeserializationError err = deserializeJson(doc, file);
    file.close();
    return err == DeserializationError::Ok;
}

bool saveSessionsDoc(JsonDocument &doc) {
    File file = SPIFFS.open(BREAK_SESSIONS_PATH, FILE_WRITE);
    if (!file) {
        return false;
    }
    serializeJson(doc, file);
    file.close();
    return true;
}

AttendanceIndicator makeOk(AttendanceType type) {
    AttendanceIndicator result;
    result.level = IndicatorLevel::Ok;
    result.barText = device_config::modeTexts(type).indicatorOk;
    return result;
}

} // namespace

void saveBreakStart(const String &employeeId, time_t startedAt) {
    JsonDocument doc;
    if (!loadSessionsDoc(doc)) {
        return;
    }

    JsonObject session = doc[employeeId].to<JsonObject>();
    session["break_start"] = (long)startedAt;
    session["ended"] = false;
    saveSessionsDoc(doc);
}

bool loadBreakStart(const String &employeeId, time_t now, time_t &outStartedAt) {
    JsonDocument doc;
    if (!loadSessionsDoc(doc)) {
        return false;
    }

    JsonObject session = doc[employeeId];
    if (session.isNull()) {
        return false;
    }

    outStartedAt = (time_t)session["break_start"].as<long>();
    return sameLocalDay(outStartedAt, now);
}

void markBreakEnded(const String &employeeId) {
    JsonDocument doc;
    if (!loadSessionsDoc(doc)) {
        return;
    }

    JsonObject session = doc[employeeId];
    if (session.isNull()) {
        return;
    }

    session["ended"] = true;
    saveSessionsDoc(doc);
}

AttendanceIndicator evaluateOffline(AttendanceType type) {
    const device_config::ModeTexts &texts = device_config::modeTexts(type);
    AttendanceIndicator result = makeOk(type);

    if (type == AttendanceType::BreakStart) {
        result.level = IndicatorLevel::Info;
        result.barText = texts.indicatorInfoPrefix.length() > 0
            ? texts.indicatorInfoPrefix
            : texts.indicatorOk;
    }

    return result;
}

AttendanceIndicator evaluate(const String &employeeId, AttendanceType type, time_t eventTime) {
    if (type == AttendanceType::BreakStart) {
        saveBreakStart(employeeId, eventTime);
    }

    if (type == AttendanceType::BreakEnd) {
        markBreakEnded(employeeId);
    }

    return evaluateOffline(type);
}

} // namespace attendance_rules
