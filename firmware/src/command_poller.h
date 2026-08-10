#pragma once

#include <Arduino.h>

namespace command_poller {

struct PendingCommand {
    String id;
    String commandType;
    String employeeId;
    String employeeName;
    int employeeCode = 0;
    int fingerprintSlotId = -1; // dipakai command_type=delete_fingerprint
};

void begin(const String &deviceId);

// Cek command pending dari server. Panggil berkala dari state IDLE saja
// (bukan di tengah proses absensi) agar tidak mengganggu UX.
// Return true jika ada command yang perlu diproses, isi outCommand.
bool poll(PendingCommand &outCommand);

bool markInProgress(const String &commandId);
void markCompleted(const String &commandId, int slotId);
void markCompletedSimple(const String &commandId);
void markFailed(const String &commandId, const String &reason);

} // namespace command_poller
