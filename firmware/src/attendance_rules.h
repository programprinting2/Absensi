#pragma once

#include "app_state.h"
#include <Arduino.h>

namespace attendance_rules {

enum class IndicatorLevel {
    Ok,
    Info,
    Warning,
};

struct AttendanceIndicator {
    IndicatorLevel level = IndicatorLevel::Ok;
    String barText;
};

AttendanceIndicator evaluateOffline(AttendanceType type);

void saveBreakStart(const String &employeeId, time_t startedAt);
bool loadBreakStart(const String &employeeId, time_t now, time_t &outStartedAt);
void markBreakEnded(const String &employeeId);

// Deprecated: pakai dashboard_client::evaluateAttendance saat online.
AttendanceIndicator evaluate(const String &employeeId, AttendanceType type, time_t eventTime);

} // namespace attendance_rules
