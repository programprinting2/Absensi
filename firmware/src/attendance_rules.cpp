#include "attendance_rules.h"
#include "device_config.h"
#include <ArduinoJson.h>
#include <SPIFFS.h>

namespace attendance_rules {

namespace {

const char *BREAK_SESSIONS_PATH = "/break_sessions.json";

String formatDuration(int totalMinutes) {
    if (totalMinutes <= 0) {
        return "0 menit";
    }

    int hours = totalMinutes / 60;
    int minutes = totalMinutes % 60;

    if (hours == 0) {
        return String(minutes) + " menit";
    }
    if (minutes == 0) {
        return String(hours) + " jam";
    }
    return String(hours) + " jam " + String(minutes) + " menit";
}

String formatClockTime(time_t t) {
    struct tm *tmInfo = localtime(&t);
    char buf[6];
    snprintf(buf, sizeof(buf), "%02d:%02d", tmInfo->tm_hour, tmInfo->tm_min);
    return String(buf);
}

int minutesOfDay(time_t t) {
    struct tm *tmInfo = localtime(&t);
    return tmInfo->tm_hour * 60 + tmInfo->tm_min;
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

AttendanceIndicator makeOk(AttendanceType type) {
    AttendanceIndicator result;
    result.level = IndicatorLevel::Ok;
    result.barText = device_config::modeTexts(type).indicatorOk;
    return result;
}

} // namespace

AttendanceIndicator evaluate(const String &employeeId, AttendanceType type, time_t eventTime) {
    const device_config::ModeTexts &texts = device_config::modeTexts(type);
    const device_config::WorkSchedule &sched = device_config::schedule();
    AttendanceIndicator result = makeOk(type);

    switch (type) {
        case AttendanceType::ClockIn: {
            int nowMin = minutesOfDay(eventTime);
            // Samakan dengan webapp: terlambat setelah late_after_time (fallback jam masuk).
            if (sched.loaded && nowMin > sched.lateAfterMinutes) {
                result.level = IndicatorLevel::Warning;
                result.barText = texts.indicatorWarnPrefix + " " +
                                 formatDuration(nowMin - sched.lateAfterMinutes);
            }
            break;
        }

        case AttendanceType::BreakStart: {
            saveBreakStart(employeeId, eventTime);
            time_t deadline = eventTime + (time_t)sched.breakDurationMinutes * 60;
            result.level = IndicatorLevel::Info;
            result.barText = texts.indicatorInfoPrefix + " " + formatClockTime(deadline);
            break;
        }

        case AttendanceType::BreakEnd: {
            time_t breakStart = 0;
            if (loadBreakStart(employeeId, eventTime, breakStart)) {
                int elapsed = (int)((eventTime - breakStart) / 60);
                if (sched.loaded && elapsed > sched.breakDurationMinutes) {
                    result.level = IndicatorLevel::Warning;
                    result.barText = texts.indicatorWarnPrefix + " " +
                                     formatDuration(elapsed - sched.breakDurationMinutes);
                }
                // Jangan hapus break_start — scan KEMBALI berulang masih pakai
                // waktu ISTIRAHAT yang sama. Reset hanya lewat ISTIRAHAT baru.
                markBreakEnded(employeeId);
            }
            break;
        }

        case AttendanceType::ClockOut: {
            int nowMin = minutesOfDay(eventTime);
            if (sched.loaded && nowMin < sched.clockOutMinutes) {
                result.level = IndicatorLevel::Warning;
                result.barText = texts.indicatorWarnPrefix + " " +
                                 formatDuration(sched.clockOutMinutes - nowMin);
            }
            break;
        }
    }

    return result;
}

} // namespace attendance_rules
