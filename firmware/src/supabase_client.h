#pragma once

#include <Arduino.h>

namespace supabase_client {

enum class InsertResult {
    Success,
    DuplicateIgnored, // unique violation client_uuid -> dianggap sukses idempotent
    Rejected,         // server menolak (mis. tidak ada jadwal)
    Failed,
};

InsertResult insertAttendanceLog(const String &deviceId, const String &employeeId,
                                  const String &attendanceType, const String &method,
                                  time_t eventTime, bool isOfflineCapture,
                                  const String &clientUuid, int *httpCodeOut = nullptr);

// Ambil employees aktif (untuk cache PIN & fingerprint mapping).
bool fetchActiveEmployees(String &outJson);

// Ambil mapping slot sidik jari -> employee_id milik device ini.
bool fetchFingerprintTemplates(const String &deviceId, String &outJson);

bool fetchDeviceSettings(const String &deviceId, String &outJson);

// Ambil device_commands pending untuk device ini.
bool fetchPendingCommands(const String &deviceId, String &outJson);

bool updateCommandStatus(const String &commandId, const String &status, const String &resultJson);

bool insertFingerprintTemplate(const String &employeeId, const String &deviceId, int slotId);

bool getDeviceIdByCode(const String &deviceCode, String &outDeviceId);

// Heartbeat: last_seen_at=now() + kapasitas sensor asli (dibaca dari modul).
// Dipanggil berkala supaya dashboard tahu device online/offline.
bool updateDeviceStatus(const String &deviceId, int fingerprintCapacity);

} // namespace supabase_client
