#pragma once

#include "attendance_rules.h"
#include <Arduino.h>

namespace dashboard_client {

// Kirim heartbeat ke Laravel dashboard (indikator online/offline).
bool sendHeartbeat(int fingerprintCapacity);

// Evaluasi indikator LCD dari Laravel (ShiftResolver + aturan absen).
bool evaluateAttendance(const String &employeeId, const String &attendanceType,
                          time_t eventTime, time_t breakStartTime,
                          attendance_rules::AttendanceIndicator &out,
                          bool &allowedOut, String &scheduleNameOut);

} // namespace dashboard_client
