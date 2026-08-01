#pragma once

#include "command_poller.h"
#include <Arduino.h>

/**
 * Semua kerja jaringan (sync antrian absensi, refresh cache karyawan,
 * heartbeat status device, polling command) dijalankan di task terpisah
 * di core 0, supaya loop() utama di core 1 — yang megang keypad, LCD, dan
 * sensor sidik jari — tidak pernah ketahan panggilan HTTP yang blocking.
 *
 * Sebelum ini semuanya jalan di loop() yang sama, jadi setiap kali ada
 * request HTTP (bisa 0.5-2 detik) keypad ikut berhenti merespon.
 */
namespace network_task {

void begin(const String &deviceId);

// True kalau device_id sudah berhasil di-resolve ke server.
bool isDeviceIdResolved();

// device_id hasil resolve — kosong kalau belum berhasil.
String getDeviceId();

// Ambil command enroll/delete yang sudah diambil task jaringan, kalau ada.
// Non-blocking — cuma baca variabel yang sudah disiapkan task lain.
bool takePendingCommand(command_poller::PendingCommand &outCommand);

// Minta task jaringan refresh cache karyawan sesegera mungkin (mis. setelah
// enroll selesai). Non-blocking: cuma menaikkan flag.
void requestCacheRefresh();

// Minta upload antrian absensi segera (mis. setelah absen baru).
void requestSyncNow();

/**
 * Hasil eksekusi command oleh core utama (kerja fisik sensor saja).
 * Pelaporan ke server dilakukan task jaringan di core 0 — core utama tidak
 * boleh memanggil HTTP sama sekali supaya keypad/LCD tidak pernah ketahan.
 */
struct CommandResult {
    String commandId;
    bool success = false;
    int slotId = -1;
    String employeeId;   // diisi kalau enroll sukses -> perlu insert template
    String errorReason;  // diisi kalau gagal
};

// Serahkan hasil command ke task jaringan untuk dilaporkan ke server.
// Non-blocking.
void submitCommandResult(const CommandResult &result);

/**
 * Hentikan sementara semua aktivitas jaringan.
 *
 * Aktivitas WiFi/TLS di core 0 terbukti mengganggu penerimaan UART sensor
 * sidik jari di core 1 (balasan sensor jadi PACKET_RECV_ERR). Selama proses
 * enroll — yang butuh komunikasi UART bersih dan cuma berlangsung beberapa
 * detik — jaringan dihentikan dulu.
 *
 * pause() blocking sampai task benar-benar berhenti di tengah siklusnya
 * (bukan di tengah request HTTP), maksimal ~10 detik.
 */
void pause();
void resume();

int pendingQueueCount();

// True kalau Supabase terakhir merespons dengan sukses (heartbeat/cache).
bool isServerReachable();

} // namespace network_task
