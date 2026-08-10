#pragma once

#include <Arduino.h>

namespace storage_queue {

struct PendingEvent {
    String path;       // path file di SPIFFS, dipakai untuk hapus setelah sync
    String clientUuid;
    String employeeId;
    String attendanceType;
    String method;
    time_t eventTime;
    bool isOfflineCapture;
    bool needsTimeCorrection = false;
    uint32_t bootId = 0;
    int retryCount = 0;
};

void begin();

// Simpan satu event absensi ke antrian lokal. clientUuid dibuat di sini,
// sekali saja per kejadian nyata (bukan per percobaan sync).
// Return false kalau gagal ditulis ke SPIFFS (mis. penuh) — event tersebut
// TIDAK tersimpan sama sekali dan tidak akan pernah tersinkron.
bool enqueue(const String &employeeId, const String &attendanceType,
             const String &method, time_t eventTime, bool isOfflineCapture,
             bool needsTimeCorrection, uint32_t bootId);

// Ambil daftar event yang masih menunggu sync, urut FIFO (nama file = timestamp).
int listPending(PendingEvent *outEvents, int maxCount);

int countPending();

// Naikkan retry_count file antrian; return false kalau file tidak bisa dibaca.
bool incrementRetry(const String &path, int &outRetryCount);

int maxRetryCount();

void remove(const String &path);

} // namespace storage_queue
