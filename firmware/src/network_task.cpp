#include "network_task.h"
#include "config.h"
#include "dashboard_client.h"
#include "device_config.h"
#include "server_config.h"
#include "employee_cache.h"
#include "fingerprint_handler.h"
#include "supabase_client.h"
#include "sync_manager.h"
#include "wifi_manager.h"

namespace network_task {

namespace {

const unsigned long DEVICE_ID_RETRY_MS = 10000;
const unsigned long EMPLOYEE_CACHE_REFRESH_MS = 5UL * 60UL * 1000UL;
const unsigned long DEVICE_HEARTBEAT_MS = 30UL * 1000UL;

String deviceCodeToResolve;
String deviceId;

// Diakses dari dua core — pakai volatile + section kritis seperlunya.
volatile bool deviceIdResolved = false;
volatile bool cacheRefreshRequested = false;
volatile bool hasPendingCommand = false;
volatile bool commandInFlight = false; // sudah diserahkan ke core 1, hasil belum kembali
volatile bool hasCommandResult = false;
volatile int cachedQueueCount = 0;
volatile bool serverReachable = false;

// Jeda sementara aktivitas jaringan (dipakai selama enroll sidik jari).
volatile bool pauseRequested = false;
volatile bool taskParked = false; // true = task benar-benar berhenti, bukan di tengah HTTP

command_poller::PendingCommand pendingCommand;
CommandResult commandResult;
portMUX_TYPE commandMux = portMUX_INITIALIZER_UNLOCKED;

// Command yang sudah pernah diserahkan ke core 1, supaya kalau markInProgress
// sempat gagal terkirim (statusnya masih 'pending' di server) command yang sama
// tidak diambil & dijalankan berulang-ulang tanpa henti.
String lastHandledCommandId;

void resolveDeviceId() {
    static unsigned long lastAttemptMs = 0;
    unsigned long nowMs = millis();
    if (lastAttemptMs != 0 && nowMs - lastAttemptMs < DEVICE_ID_RETRY_MS) {
        return;
    }
    lastAttemptMs = nowMs;

    Serial.print(F("[net] Mencoba resolve device_id via device_code="));
    Serial.println(deviceCodeToResolve);
    String resolved;
    if (supabase_client::getDeviceIdByCode(deviceCodeToResolve, resolved)) {
        Serial.print(F("[net] device_id resolved: "));
        Serial.println(resolved);
        deviceId = resolved;
        sync_manager::begin(deviceId);
        command_poller::begin(deviceId);
        employee_cache::refresh(deviceId);
        device_config::refresh(deviceId);
        deviceIdResolved = true;
        serverReachable = true;
    }
}

void taskLoop(void *param) {
    unsigned long lastCacheRefreshMs = millis();
    unsigned long lastConfigRefreshMs = millis();
    unsigned long lastHeartbeatMs = 0;

    for (;;) {
        // Dijeda (mis. selama enroll sidik jari) — jangan sentuh jaringan
        // sama sekali supaya UART sensor tidak terganggu.
        if (pauseRequested) {
            taskParked = true;
            vTaskDelay(pdMS_TO_TICKS(50));
            continue;
        }
        taskParked = false;

        if (!wifi_manager::isConnected()) {
            serverReachable = false;
            cachedQueueCount = sync_manager::pendingCount();
            vTaskDelay(pdMS_TO_TICKS(500));
            continue;
        }

        // Heartbeat ke Laravel — independen dari resolve device_id (Supabase/PostgREST).
        // Indikator ONLINE harus jalan meski data API belum/gagal connect.
        unsigned long now = millis();
        if (lastHeartbeatMs == 0 || now - lastHeartbeatMs >= DEVICE_HEARTBEAT_MS) {
            dashboard_client::sendHeartbeat(fingerprint_handler::capacity());
            lastHeartbeatMs = millis();
        }

        if (!deviceIdResolved) {
            cachedQueueCount = sync_manager::pendingCount();
            resolveDeviceId();
            vTaskDelay(pdMS_TO_TICKS(200));
            continue;
        }

        sync_manager::loop();
        cachedQueueCount = sync_manager::pendingCount();

        if (cacheRefreshRequested || now - lastCacheRefreshMs >= EMPLOYEE_CACHE_REFRESH_MS) {
            cacheRefreshRequested = false;
            employee_cache::refresh(deviceId);
            lastCacheRefreshMs = millis();
        }

        if (now - lastConfigRefreshMs >= DEVICE_CONFIG_REFRESH_MS) {
            if (device_config::refresh(deviceId)) {
                serverReachable = true;
            }
            lastConfigRefreshMs = millis();
        }

        // Hasil command dari core 1 sudah siap? Laporkan ke server di sini,
        // supaya core 1 tidak pernah menyentuh HTTP.
        if (hasCommandResult) {
            CommandResult result;
            portENTER_CRITICAL(&commandMux);
            result = commandResult;
            hasCommandResult = false;
            portEXIT_CRITICAL(&commandMux);

            if (result.success) {
                bool mappingOk = true;
                if (result.employeeId.length() > 0) {
                    mappingOk = supabase_client::insertFingerprintTemplate(
                        result.employeeId, deviceId, result.slotId);
                }

                if (mappingOk) {
                    if (result.employeeId.length() > 0) {
                        command_poller::markCompleted(result.commandId, result.slotId);
                        employee_cache::refresh(deviceId);
                        lastCacheRefreshMs = millis();
                    } else {
                        command_poller::markCompletedSimple(result.commandId);
                    }
                } else {
                    command_poller::markFailed(result.commandId, "Gagal menyimpan mapping ke server");
                }
            } else {
                command_poller::markFailed(result.commandId, result.errorReason);
            }

            commandInFlight = false;
        }

        if (!hasPendingCommand && !commandInFlight) {
            command_poller::PendingCommand cmd;
            if (command_poller::poll(cmd) && cmd.id != lastHandledCommandId) {
                // Tandai in_progress DI SINI (core 0) supaya command langsung
                // keluar dari daftar pending dan tidak terambil dua kali.
                command_poller::markInProgress(cmd.id);
                lastHandledCommandId = cmd.id;

                portENTER_CRITICAL(&commandMux);
                pendingCommand = cmd;
                hasPendingCommand = true;
                commandInFlight = true;
                portEXIT_CRITICAL(&commandMux);
            }
        }

        vTaskDelay(pdMS_TO_TICKS(200));
    }
}

} // namespace

void begin() {
    deviceCodeToResolve = server_config::deviceCode();

    xTaskCreatePinnedToCore(
        taskLoop,
        "network",
        8192,
        nullptr,
        1,   // prioritas rendah — UI di core 1 tetap diutamakan
        nullptr,
        0    // core 0 (loop() Arduino jalan di core 1)
    );
}

void resetServerConnection() {
    deviceIdResolved = false;
    deviceId = "";
    deviceCodeToResolve = server_config::deviceCode();
    serverReachable = false;
    lastHandledCommandId = "";
    Serial.println(F("[net] server config berubah — resolve device_id ulang"));
}

bool isDeviceIdResolved() {
    return deviceIdResolved;
}

String getDeviceId() {
    return deviceId;
}

bool takePendingCommand(command_poller::PendingCommand &outCommand) {
    if (!hasPendingCommand) {
        return false;
    }

    portENTER_CRITICAL(&commandMux);
    outCommand = pendingCommand;
    hasPendingCommand = false;
    portEXIT_CRITICAL(&commandMux);

    return true;
}

void requestCacheRefresh() {
    cacheRefreshRequested = true;
}

void requestSyncNow() {
    sync_manager::requestNow();
}

void submitCommandResult(const CommandResult &result) {
    portENTER_CRITICAL(&commandMux);
    commandResult = result;
    hasCommandResult = true;
    portEXIT_CRITICAL(&commandMux);
}

void pause() {
    pauseRequested = true;

    // Tunggu sampai task benar-benar berhenti — kalau dia sedang di tengah
    // request HTTP, biarkan selesai dulu daripada dipotong paksa.
    unsigned long start = millis();
    while (!taskParked && millis() - start < 10000) {
        delay(10);
    }
}

void resume() {
    pauseRequested = false;
}

int pendingQueueCount() {
    return cachedQueueCount;
}

bool isServerReachable() {
    return serverReachable;
}

} // namespace network_task
