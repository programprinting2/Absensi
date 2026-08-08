#pragma once

namespace dashboard_client {

// Kirim heartbeat ke Laravel dashboard (indikator online/offline).
// Tidak mempengaruhi jalur data absensi/enroll.
bool sendHeartbeat(int fingerprintCapacity);

} // namespace dashboard_client
