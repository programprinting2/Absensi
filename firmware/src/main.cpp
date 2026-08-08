#include "app_state.h"
#include "attendance_rules.h"
#include "buzzer_handler.h"
#include "command_poller.h"
#include "config.h"
#include "device_config.h"
#include "employee_cache.h"
#include "fingerprint_handler.h"
#include "keypad_handler.h"
#include "lcd_ui.h"
#include "network_task.h"
#include "ntp_time.h"
#include "server_config.h"
#include "storage_queue.h"
#include "wifi_manager.h"
#include <Arduino.h>
#include <SPIFFS.h>

namespace {

AppState state = AppState::Boot;

String inputBuffer;
AttendanceType currentMode = AttendanceType::ClockIn;
String pendingEmployeeId;
String pendingEmployeeName;

char wifiResetLastKey = '\0';
unsigned long wifiResetLastKeyMs = 0;
const unsigned long WIFI_RESET_COMBO_WINDOW_MS = 3000;

String lastIdleClockText = "";
String lastIdleSecText = "";
String lastIdleDateText = "";
String lastIdleHeaderText = "";
int lastIdleQueueCount = -1;
bool lastIdleWifiConnected = false;
bool lastIdleSensorReady = false;
bool lastIdleServerOk = false;
String lastIdleModeLabel = "";
bool idlePortalScreenShown = false;
bool wifiInfoActive = false;
bool wifiInfoScreenShown = false;

command_poller::PendingCommand activeCommand;
bool lastResultWasFailure = false;
bool awaitingFingerLift = false;

const char *DAYS_ID[7] = {"Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"};
const char *MONTHS_ID[12] = {"Januari", "Februari", "Maret", "April", "Mei", "Juni",
                               "Juli", "Agustus", "September", "Oktober", "November", "Desember"};

String formatClock(time_t t) {
    struct tm *tmInfo = localtime(&t);
    char buf[8];
    snprintf(buf, sizeof(buf), "%02d:%02d", tmInfo->tm_hour, tmInfo->tm_min);
    return String(buf);
}

String formatSeconds(time_t t) {
    struct tm *tmInfo = localtime(&t);
    char buf[4];
    snprintf(buf, sizeof(buf), "%02d", tmInfo->tm_sec);
    return String(buf);
}

String formatDateIndonesian(time_t t) {
    struct tm *tmInfo = localtime(&t);
    return String(DAYS_ID[tmInfo->tm_wday]) + ", " + String(tmInfo->tm_mday) + " " +
           MONTHS_ID[tmInfo->tm_mon] + " " + String(tmInfo->tm_year + 1900);
}

void recordFailure(const String &reason);

String currentHeaderText() {
    int queueCount = network_task::pendingQueueCount();
    return queueCount > 0 ? String("Upload: ") + queueCount : device_config::deviceName();
}

void currentChromeStatus(bool &wifiConnected, bool &sensorReady, bool &serverOk) {
    wifiConnected = wifi_manager::isConnected();
    sensorReady = fingerprint_handler::isReady();
    serverOk = network_task::isServerReachable();
}

void recordAttendance(const String &employeeId, const String &employeeName, AttendanceType type,
                      AttendanceMethod method) {
    time_t now = ntp_time::now();
    if (now <= 0) {
        recordFailure("Waktu belum sync");
        return;
    }

    if (employeeId.length() == 0) {
        recordFailure("Data karyawan invalid");
        return;
    }

    bool queued = storage_queue::enqueue(employeeId, attendanceTypeToString(type),
                                          method == AttendanceMethod::Fingerprint ? "fingerprint" : "pin",
                                          now, !wifi_manager::isConnected());

    if (!queued) {
        recordFailure("Gagal Simpan Lokal");
        return;
    }

    network_task::requestSyncNow();

    attendance_rules::AttendanceIndicator indicator =
        attendance_rules::evaluate(employeeId, type, now);

    buzzer_handler::beepSuccess();
    lcd_ui::showAttendanceResult(type, employeeName, attendanceTypeToLabel(type),
                                 formatClock(now), indicator);
    lastResultWasFailure = false;
    // Tunggu jari yang barusan absen terangkat dulu sebelum menerima scan
    // baru -- supaya orang berikutnya tetap bisa langsung scan begitu jari
    // sebelumnya lepas, tanpa risiko jari yang sama ke-scan dobel.
    awaitingFingerLift = true;
    state = AppState::ShowResult;
}

void recordFailure(const String &reason) {
    buzzer_handler::beepFail();
    lcd_ui::showAttendanceFailed(reason);
    lastResultWasFailure = true;
    awaitingFingerLift = false;
    state = AppState::ShowResult;
}

void redrawIdle(bool force = false) {
    time_t now = ntp_time::now();
    bool wifiConnected = false;
    bool sensorReady = false;
    bool serverOk = false;
    currentChromeStatus(wifiConnected, sensorReady, serverOk);
    int queueCount = network_task::pendingQueueCount();
    String headerText = currentHeaderText();
    String clockText = formatClock(now);
    String secText = formatSeconds(now);
    String dateText = ntp_time::hasSyncedOnce() ? formatDateIndonesian(now) : "Menyinkronkan waktu...";
    String modeLabel = attendanceTypeToLabel(currentMode);

    if (!force &&
        clockText == lastIdleClockText && secText == lastIdleSecText &&
        dateText == lastIdleDateText && headerText == lastIdleHeaderText &&
        queueCount == lastIdleQueueCount &&
        wifiConnected == lastIdleWifiConnected && sensorReady == lastIdleSensorReady &&
        serverOk == lastIdleServerOk && modeLabel == lastIdleModeLabel) {
        return;
    }

    bool hasFrame = lastIdleClockText.length() > 0;
    if (force || !hasFrame) {
        lcd_ui::showIdle(wifiConnected, sensorReady, serverOk, headerText,
                         clockText, secText, dateText, modeLabel);
    } else {
        uint8_t parts = 0;
        if (headerText != lastIdleHeaderText || queueCount != lastIdleQueueCount) {
            parts |= lcd_ui::IDLE_HEADER;
        }
        if (wifiConnected != lastIdleWifiConnected || sensorReady != lastIdleSensorReady ||
            serverOk != lastIdleServerOk) {
            parts |= lcd_ui::IDLE_ICONS;
        }
        if (clockText != lastIdleClockText) {
            parts |= lcd_ui::IDLE_CLOCK;
        } else if (secText != lastIdleSecText) {
            parts |= lcd_ui::IDLE_SECONDS;
        }
        if (dateText != lastIdleDateText) {
            parts |= lcd_ui::IDLE_DATE;
        }
        if (modeLabel != lastIdleModeLabel) {
            parts |= lcd_ui::IDLE_MODE;
        }
        if (parts == 0) {
            return;
        }
        lcd_ui::updateIdle(parts, wifiConnected, sensorReady, serverOk, headerText,
                           clockText, secText, dateText, modeLabel);
    }

    lastIdleClockText = clockText;
    lastIdleSecText = secText;
    lastIdleDateText = dateText;
    lastIdleHeaderText = headerText;
    lastIdleQueueCount = queueCount;
    lastIdleWifiConnected = wifiConnected;
    lastIdleSensorReady = sensorReady;
    lastIdleServerOk = serverOk;
    lastIdleModeLabel = modeLabel;
}

String maskedPinDisplay(const String &buffer) {
    String masked;
    for (unsigned int i = 0; i < buffer.length(); i++) {
        if (i > 0) masked += ' ';
        masked += '*';
    }
    return masked;
}

void handleEnrollFlow() {
    network_task::CommandResult result;
    result.commandId = activeCommand.id;

    int maxSlots = fingerprint_handler::capacity();
    if (maxSlots <= 0) {
        maxSlots = 200;
    }
    int slot = employee_cache::nextFreeSlot(maxSlots);
    if (slot < 0) {
        result.errorReason = "Tidak ada slot kosong di sensor";
        lcd_ui::showEnrollScreen("Daftarkan sidik jari !", activeCommand.employeeName,
                                 activeCommand.employeeCode, lcd_ui::COLOR_RED,
                                 "Gagal, coba lagi !", true);
        network_task::submitCommandResult(result);
        return;
    }

    lcd_ui::showEnrollPrompt(activeCommand.employeeName, activeCommand.employeeCode, 1, 2);
    if (!fingerprint_handler::captureImage(1)) {
        result.errorReason = "Gagal membaca sidik jari (1)";
        lcd_ui::showEnrollScreen("Daftarkan sidik jari !", activeCommand.employeeName,
                                 activeCommand.employeeCode, lcd_ui::COLOR_RED,
                                 "Gagal, coba lagi !", true);
        fingerprint_handler::recoverAfterError();
        network_task::submitCommandResult(result);
        return;
    }

    lcd_ui::showEnrollScreen("Daftarkan sidik jari !", activeCommand.employeeName,
                             activeCommand.employeeCode, lcd_ui::COLOR_CYAN, "Angkat jari ...");
    fingerprint_handler::waitForFingerLifted();

    lcd_ui::showEnrollPrompt(activeCommand.employeeName, activeCommand.employeeCode, 2, 2);
    if (!fingerprint_handler::captureImage(2)) {
        result.errorReason = "Gagal membaca sidik jari (2)";
        lcd_ui::showEnrollScreen("Daftarkan sidik jari !", activeCommand.employeeName,
                                 activeCommand.employeeCode, lcd_ui::COLOR_RED,
                                 "Gagal, coba lagi !", true);
        fingerprint_handler::recoverAfterError();
        network_task::submitCommandResult(result);
        return;
    }

    if (!fingerprint_handler::finalizeEnroll(slot)) {
        result.errorReason = "Sidik jari 1 & 2 tidak cocok";
        lcd_ui::showEnrollScreen("Daftarkan sidik jari !", activeCommand.employeeName,
                                 activeCommand.employeeCode, lcd_ui::COLOR_RED,
                                 "Gagal, coba lagi !", true);
        fingerprint_handler::recoverAfterError();
        network_task::submitCommandResult(result);
        return;
    }

    result.success = true;
    result.slotId = slot;
    result.employeeId = activeCommand.employeeId;
    lcd_ui::showEnrollScreen("Daftarkan sidik jari !", activeCommand.employeeName,
                             activeCommand.employeeCode, lcd_ui::COLOR_GREEN, "Berhasil !");
    network_task::submitCommandResult(result);
}

void handleDeleteFlow() {
    network_task::CommandResult result;
    result.commandId = activeCommand.id;

    if (activeCommand.fingerprintSlotId < 0) {
        result.errorReason = "Slot tidak valid";
        lcd_ui::showEnrollScreen("Hapus sidik jari", activeCommand.employeeName,
                                 activeCommand.employeeCode, lcd_ui::COLOR_RED,
                                 "Gagal, coba lagi !", true);
        network_task::submitCommandResult(result);
        return;
    }

    if (!fingerprint_handler::deleteAtSlot(activeCommand.fingerprintSlotId)) {
        result.errorReason = "Gagal hapus di sensor";
        lcd_ui::showEnrollScreen("Hapus sidik jari", activeCommand.employeeName,
                                 activeCommand.employeeCode, lcd_ui::COLOR_RED,
                                 "Gagal, coba lagi !", true);
        network_task::submitCommandResult(result);
        return;
    }

    result.success = true;
    result.slotId = activeCommand.fingerprintSlotId;
    lcd_ui::showEnrollScreen("Hapus sidik jari", activeCommand.employeeName,
                             activeCommand.employeeCode, lcd_ui::COLOR_GREEN, "Berhasil !");
    network_task::submitCommandResult(result);
}

} // namespace

void setup() {
    Serial.begin(115200);
    SPIFFS.begin(true);

    lcd_ui::begin();
    lcd_ui::showBoot();

    keypad_handler::begin();
    fingerprint_handler::begin();
    buzzer_handler::begin();
    device_config::begin();
    employee_cache::begin();
    storage_queue::begin();
    server_config::begin();
    wifi_manager::begin();
    network_task::begin();

    state = AppState::WifiConnecting;
    lcd_ui::showWifiConnecting();
}

void loop() {
    wifi_manager::loop();
    fingerprint_handler::retryDetectIfNeeded();

    static bool ntpSyncedAfterConnect = false;
    if (wifi_manager::isConnected()) {
        if (!ntpSyncedAfterConnect) {
            ntp_time::trySync();
            ntpSyncedAfterConnect = true;
        }
    } else {
        ntpSyncedAfterConnect = false;
    }

    static AppState lastSeenState = AppState::Boot;
    if (state != lastSeenState && state == AppState::Idle) {
        lcd_ui::invalidateIdle();
        fingerprint_handler::resetLed();
        lastIdleClockText = "";
        lastIdleHeaderText = "";
        lastIdleQueueCount = -1;
    }
    lastSeenState = state;

    switch (state) {
        case AppState::Boot:
        case AppState::WifiConnecting: {
            if (ntp_time::hasSyncedOnce() || !wifi_manager::isConnected()) {
                state = AppState::Idle;
            }
            break;
        }

        case AppState::Idle: {
            if (wifi_manager::isConfigPortalActive()) {
                if (!idlePortalScreenShown) {
                    lcd_ui::showWifiPortal(WIFI_AP_NAME, WIFI_AP_PASSWORD);
                    idlePortalScreenShown = true;
                    lastIdleClockText = "";
                }
                char portalKey = keypad_handler::poll();
                if (portalKey == '*') {
                    wifi_manager::stopConfigPortal();
                    idlePortalScreenShown = false;
                    redrawIdle();
                }
                break;
            }
            idlePortalScreenShown = false;

            if (wifiInfoActive) {
                if (!wifiInfoScreenShown) {
                    lcd_ui::showWifiInfo(wifi_manager::isConnected(), wifi_manager::currentSSID(),
                                         wifi_manager::localIPString());
                    wifiInfoScreenShown = true;
                    lastIdleClockText = "";
                }
                char infoKey = keypad_handler::poll();
                if (infoKey == '*') {
                    wifiInfoActive = false;
                    wifiInfoScreenShown = false;
                    redrawIdle();
                }
                break;
            }

            redrawIdle();

            char key = keypad_handler::poll();

            if (key != '\0') {
                unsigned long now = millis();
                if (key == '1' && wifiResetLastKey == '*' && now - wifiResetLastKeyMs <= WIFI_RESET_COMBO_WINDOW_MS) {
                    wifi_manager::startApConfigPortal();
                    wifiResetLastKey = '\0';
                    break;
                }
                if (key == '2' && wifiResetLastKey == '*' && now - wifiResetLastKeyMs <= WIFI_RESET_COMBO_WINDOW_MS) {
                    wifiInfoActive = true;
                    wifiResetLastKey = '\0';
                    break;
                }
                wifiResetLastKey = key;
                wifiResetLastKeyMs = now;
            }

            AttendanceType type;
            bool modeJustChanged = false;
            if (attendanceTypeFromModeKey(key, type)) {
                currentMode = type;
                modeJustChanged = true;
            } else if (key == '#') {
                inputBuffer = "";
                state = AppState::InputId;
                bool wifiConnected = false;
                bool sensorReady = false;
                bool serverOk = false;
                currentChromeStatus(wifiConnected, sensorReady, serverOk);
                lcd_ui::showInputId(inputBuffer, false, currentHeaderText(),
                                    attendanceTypeToLabel(currentMode),
                                    wifiConnected, sensorReady, serverOk);
                break;
            }

            if (modeJustChanged) {
                redrawIdle();
            }

            if (network_task::takePendingCommand(activeCommand)) {
                if (activeCommand.commandType == "start_wifi_portal") {
                    wifi_manager::startApConfigPortal();
                    network_task::CommandResult result;
                    result.commandId = activeCommand.id;
                    result.success = true;
                    network_task::submitCommandResult(result);
                    break;
                }
                state = AppState::EnrollMode;
                break;
            }

            int slotId = fingerprint_handler::pollForMatch();
            if (slotId >= 0) {
                employee_cache::Employee employee;
                if (employee_cache::findBySlotId(slotId, employee)) {
                    recordAttendance(employee.id, employee.fullName, currentMode, AttendanceMethod::Fingerprint);
                } else {
                    fingerprint_handler::indicateFailure();
                    recordFailure("Sidik Jari Tidak Dikenali");
                }
                break;
            }
            if (slotId == -2) {
                fingerprint_handler::indicateFailure();
                recordFailure("Sidik Jari Tidak Dikenali");
                break;
            }
            break;
        }

        case AppState::InputId: {
            char key = keypad_handler::poll();
            if (key >= '0' && key <= '9') {
                inputBuffer += key;
                lcd_ui::updateInputId(inputBuffer, true);
            } else if (key == '#') {
                if (inputBuffer.length() == 0) {
                    break;
                }

                employee_cache::Employee employee;
                if (employee_cache::findByEmployeeCode(inputBuffer.toInt(), employee)) {
                    pendingEmployeeId = employee.id;
                    pendingEmployeeName = employee.fullName;
                    inputBuffer = "";
                    state = AppState::InputPin;
                    bool wifiConnected = false;
                    bool sensorReady = false;
                    bool serverOk = false;
                    currentChromeStatus(wifiConnected, sensorReady, serverOk);
                    lcd_ui::showInputPin(pendingEmployeeName, "", false, currentHeaderText(),
                                         attendanceTypeToLabel(currentMode),
                                         wifiConnected, sensorReady, serverOk);
                } else {
                    inputBuffer = "";
                    recordFailure("ID Tidak Ditemukan");
                }
            } else if (key == '*') {
                if (inputBuffer.length() > 0) {
                    inputBuffer.remove(inputBuffer.length() - 1);
                    lcd_ui::updateInputId(inputBuffer, inputBuffer.length() > 0);
                } else {
                    state = AppState::Idle;
                }
            }
            break;
        }

        case AppState::InputPin: {
            char key = keypad_handler::poll();
            AttendanceType type;
            if (attendanceTypeFromModeKey(key, type)) {
                currentMode = type;
                bool wifiConnected = false;
                bool sensorReady = false;
                bool serverOk = false;
                currentChromeStatus(wifiConnected, sensorReady, serverOk);
                lcd_ui::showInputPin(pendingEmployeeName, maskedPinDisplay(inputBuffer),
                                     inputBuffer.length() > 0, currentHeaderText(),
                                     attendanceTypeToLabel(currentMode),
                                     wifiConnected, sensorReady, serverOk);
            } else if (key >= '0' && key <= '9') {
                inputBuffer += key;
                lcd_ui::updateInputPin(maskedPinDisplay(inputBuffer), true);
            } else if (key == '#') {
                if (inputBuffer.length() == 0) {
                    break;
                }

                if (employee_cache::verifyPin(pendingEmployeeId, inputBuffer)) {
                    recordAttendance(pendingEmployeeId, pendingEmployeeName, currentMode, AttendanceMethod::Pin);
                } else {
                    recordFailure("PIN Salah");
                }
                inputBuffer = "";
            } else if (key == '*') {
                if (inputBuffer.length() > 0) {
                    inputBuffer.remove(inputBuffer.length() - 1);
                    lcd_ui::updateInputPin(maskedPinDisplay(inputBuffer), inputBuffer.length() > 0);
                } else {
                    state = AppState::InputId;
                    bool wifiConnected = false;
                    bool sensorReady = false;
                    bool serverOk = false;
                    currentChromeStatus(wifiConnected, sensorReady, serverOk);
                    lcd_ui::showInputId("", false, currentHeaderText(),
                                        attendanceTypeToLabel(currentMode),
                                        wifiConnected, sensorReady, serverOk);
                }
            }
            break;
        }

        case AppState::EnrollMode: {
            network_task::pause();

            if (activeCommand.commandType == "delete_fingerprint") {
                handleDeleteFlow();
            } else {
                handleEnrollFlow();
            }

            fingerprint_handler::recoverAfterError();
            network_task::resume();
            delay(2000);
            state = AppState::Idle;
            break;
        }

        case AppState::ShowResult: {
            static unsigned long shownAt = 0;
            if (shownAt == 0) shownAt = millis();

            // Notif gagal: boleh langsung scan ulang kapan saja selama notif tampil.
            // Notif sukses: tunggu jari yang barusan absen terangkat dulu (sekali
            // terdeteksi NOFINGER), baru orang berikutnya boleh langsung scan --
            // supaya jari yang sama tidak ke-scan dobel selagi masih menempel.
            bool canScanNow = lastResultWasFailure;
            if (!canScanNow) {
                if (awaitingFingerLift) {
                    if (fingerprint_handler::pollForMatch() == -1) {
                        awaitingFingerLift = false;
                    }
                } else {
                    canScanNow = true;
                }
            }

            if (canScanNow) {
                int slotId = fingerprint_handler::pollForMatch();
                if (slotId >= 0) {
                    employee_cache::Employee employee;
                    if (employee_cache::findBySlotId(slotId, employee)) {
                        recordAttendance(employee.id, employee.fullName, currentMode, AttendanceMethod::Fingerprint);
                    } else {
                        fingerprint_handler::indicateFailure();
                        recordFailure("Sidik Jari Tidak Dikenali");
                    }
                    shownAt = millis();
                    break;
                }
                if (slotId == -2) {
                    fingerprint_handler::indicateFailure();
                    recordFailure("Sidik Jari Tidak Dikenali");
                    shownAt = millis();
                    break;
                }
            }

            if (millis() - shownAt > 3000) {
                shownAt = 0;
                awaitingFingerLift = false;
                state = AppState::Idle;
            }
            break;
        }

        default:
            state = AppState::Idle;
            break;
    }
}
