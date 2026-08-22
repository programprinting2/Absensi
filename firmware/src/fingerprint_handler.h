#pragma once

#include <Arduino.h>

namespace fingerprint_handler {

void begin();

// True kalau sensor benar-benar merespon (handshake sukses).
bool isReady();

// Coba deteksi ulang sensor kalau sebelumnya gagal. Dipanggil berkala dari
// loop() — supaya sensor yang belum siap saat boot (mis. tepat setelah upload
// firmware) bisa pulih sendiri tanpa harus cabut-colok power.
void retryDetectIfNeeded();

// Non-blocking: cek apakah ada jari di sensor & cocok dengan template tersimpan.
// Return slot ID (>=0) jika match di sensor, -1 jika tidak ada jari (idle),
// -2 jika jari terbaca tapi tidak valid (tidak cocok / confidence rendah).
int pollForMatch();

// Sama seperti pollForMatch(), tapi ulangi hingga maxAttempts kali jika jari
// terbaca tapi tidak cocok / confidence rendah (-2).
int pollForMatchWithRetry(int maxAttempts = 3);

// Paksa LED sensor merah setelah validasi gagal di aplikasi (mis. slot cocok
// di flash sensor tapi tidak terdaftar di sistem — sensor defaultnya hijau).
void indicateFailure();

// Matikan LED saat kembali idle (supaya tidak nyangkut hijau/merah).
void resetLed();

// Alur enroll 3 langkah eksplisit (dipanggil berurutan dari main.cpp supaya
// LCD bisa kasih instruksi tiap langkah — sensor perlu 2 capture yang benar-
// benar beda posisi/tekanan, jadi WAJIB ada jeda "angkat jari" di antaranya,
// bukan sekadar delay tetap.
//
// 1. captureImage(1) — minta taruh jari, ambil citra pertama.
// 2. waitForFingerLifted() — tunggu jari benar-benar terangkat.
// 3. captureImage(2) — minta taruh JARI YANG SAMA lagi, ambil citra kedua.
// 4. finalizeEnroll(slotId) — gabungkan 2 citra jadi model, simpan ke slot.
//
// Semua blocking (dipanggil dari state ENROLL_MODE saja).

// Return true kalau citra berhasil diambil & diproses ke buffer 1/2.
bool captureImage(int bufferSlot, unsigned long timeoutMs = 15000);

void waitForFingerLifted(unsigned long timeoutMs = 10000);

// Return true kalau model berhasil dibuat & disimpan ke slotId.
bool finalizeEnroll(int slotId);

bool deleteAtSlot(int slotId);

// Pulihkan UART sensor setelah enroll gagal / komunikasi korup (PACKET_RECV_ERR).
// Panggil sebelum kembali ke layar Idle supaya pollForMatch tidak macet.
void recoverAfterError();

// Kapasitas asli modul (dibaca dari sensor saat begin(), bukan ditebak).
// 0 kalau belum berhasil dibaca (mis. sensor tidak terdeteksi).
uint16_t capacity();

} // namespace fingerprint_handler
