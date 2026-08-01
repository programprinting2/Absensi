#pragma once

#include "app_state.h"
#include "attendance_rules.h"
#include <Arduino.h>

namespace lcd_ui {

constexpr uint16_t COLOR_YELLOW = 0xFFE0;
constexpr uint16_t COLOR_RED = 0xF800;
constexpr uint16_t COLOR_GREEN = 0x07E0;
constexpr uint16_t COLOR_CYAN = 0x07FF;

enum IdleUpdatePart : uint8_t {
    IDLE_ALL = 0xFF,
    IDLE_HEADER = 0x01,
    IDLE_ICONS = 0x02,
    IDLE_CLOCK = 0x04,
    IDLE_SECONDS = 0x08,
    IDLE_DATE = 0x10,
    IDLE_MODE = 0x20,
};

void begin();
void invalidateIdle();
void invalidateInput();

void showBoot();
void showWifiConnecting();
void showWifiPortal(const String &apName, const String &apPassword);
void showWifiInfo(bool connected, const String &ssid, const String &ip);

void showIdle(bool wifiOk, bool sensorOk, bool serverOk,
              const String &headerText, const String &clockText, const String &secText,
              const String &dateText, const String &modeLabel);

void updateIdle(uint8_t parts, bool wifiOk, bool sensorOk, bool serverOk,
                const String &headerText, const String &clockText, const String &secText,
                const String &dateText, const String &modeLabel);

void showInputId(const String &buffer, bool hasChars, const String &headerText,
                 const String &modeLabel, bool wifiOk, bool sensorOk, bool serverOk);

void updateInputId(const String &buffer, bool hasChars);

void showInputPin(const String &employeeName, const String &maskedPin, bool hasChars,
                  const String &headerText, const String &modeLabel,
                  bool wifiOk, bool sensorOk, bool serverOk);

void updateInputPin(const String &maskedPin, bool hasChars);

void showMessage(const String &title, const String &body, uint16_t colorHint = 0);

void showEnrollScreen(const String &title, const String &employeeName, int employeeCode,
                      uint16_t barColor, const String &barText, bool lightTextOnBar = false);

void showEnrollPrompt(const String &employeeName, int employeeCode, int step, int totalSteps);

void showAttendanceResult(AttendanceType type, const String &employeeName,
                          const String &modeLabel, const String &timeText,
                          const attendance_rules::AttendanceIndicator &indicator);

void showAttendanceFailed(const String &reason);

} // namespace lcd_ui
