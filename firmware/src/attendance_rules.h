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

AttendanceIndicator evaluate(const String &employeeId, AttendanceType type, time_t eventTime);

} // namespace attendance_rules
