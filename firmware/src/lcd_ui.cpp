#include "lcd_ui.h"
#include "config.h"
#include "device_config.h"
#include <Adafruit_GFX.h>
#include <Adafruit_ST7735.h>
#include <SPI.h>

#define TFT_BLACK     0x0000
#define TFT_WHITE     0xFFFF
#define TFT_RED       0xF800
#define TFT_GREEN     0x07E0
#define TFT_YELLOW    0xFFE0
#define TFT_CYAN      0x07FF
#define TFT_NAVY      0x0010
#define TFT_DARKGREY  0x7BEF

namespace lcd_ui {

namespace {

Adafruit_ST7735 tft = Adafruit_ST7735(TFT_CS_PIN, TFT_DC_PIN, TFT_RST_PIN);

const uint8_t ICON_WIFI[8] PROGMEM = {
    0x00, 0x3C, 0x42, 0x99, 0x24, 0x18, 0x18, 0x00
};
const uint8_t ICON_CHIP[8] PROGMEM = {
    0x00, 0x7E, 0x42, 0x5A, 0x5A, 0x42, 0x7E, 0x00
};
const uint8_t ICON_DB[8] PROGMEM = {
    0x00, 0x3C, 0x42, 0x42, 0x42, 0x42, 0x3C, 0x00
};
const uint8_t ICON_X[8] PROGMEM = {
    0x81, 0x42, 0x24, 0x18, 0x18, 0x24, 0x42, 0x81
};

const int TOP_BAR_H = 12;
const int MODE_BADGE_Y = 72;
const int MODE_BADGE_H = 24;
const int FOOTER_Y = 112;

bool idleFrameActive = false;
bool inputFrameActive = false;
int idleSecX = 0;
int idleSecY = 0;
int idleClockX = 0;
int idleClockY = 0;
int idleClockW = 0;

void clearBlack() {
    tft.fillScreen(TFT_BLACK);
}

void clearWhite() {
    tft.fillScreen(TFT_WHITE);
}

int textWidth(const String &text, uint8_t size) {
    return (int)text.length() * 6 * size;
}

void printTruncated(int x, int y, uint8_t size, const String &text, int maxChars, uint16_t fg,
                    uint16_t bg) {
    String out = text;
    if ((int)out.length() > maxChars) {
        out = out.substring(0, maxChars - 1) + "~";
    }
    tft.setTextSize(size);
    tft.setTextColor(fg, bg);
    tft.setCursor(x, y);
    tft.print(out);
}

void printCentered(int y, uint8_t size, const String &text, uint16_t fg, uint16_t bg) {
    int w = textWidth(text, size);
    int x = (tft.width() - w) / 2;
    if (x < 0) {
        x = 0;
    }
    tft.setTextSize(size);
    tft.setTextColor(fg, bg);
    tft.setCursor(x, y);
    tft.print(text);
}

// Sama seperti printCentered, tapi dipotong dulu kalau lebih lebar dari layar --
// dipakai untuk teks panjang (nama karyawan) di font yang lebih besar supaya
// tidak meluber ke luar layar.
void printCenteredFit(int y, uint8_t size, const String &text, uint16_t fg, uint16_t bg) {
    int maxChars = (tft.width() - 8) / (6 * size);
    String out = text;
    if ((int)out.length() > maxChars && maxChars > 1) {
        out = out.substring(0, maxChars - 1) + "~";
    }
    printCentered(y, size, out, fg, bg);
}

void drawStatusIcons(bool wifiOk, bool sensorOk, bool serverOk, uint16_t color) {
    int x = tft.width() - 28;
    tft.drawBitmap(x, 2, wifiOk ? ICON_WIFI : ICON_X, 8, 8, color);
    tft.drawBitmap(x + 10, 2, sensorOk ? ICON_CHIP : ICON_X, 8, 8, color);
    tft.drawBitmap(x + 20, 2, serverOk ? ICON_DB : ICON_X, 8, 8, color);
}

void drawTopBar(const String &headerText, bool wifiOk, bool sensorOk, bool serverOk) {
    tft.fillRect(0, 0, tft.width(), TOP_BAR_H, TFT_BLACK);
    printTruncated(2, 2, 1, headerText, 14, TFT_YELLOW, TFT_BLACK);
    drawStatusIcons(wifiOk, sensorOk, serverOk, TFT_YELLOW);
}

void drawModeBadgeOutline(const String &modeLabel) {
    String label = modeLabel;
    label.toUpperCase();

    const int x = 2;
    const int boxW = tft.width() - 4;
    const int y = MODE_BADGE_Y;

    tft.fillRect(0, y - 2, tft.width(), MODE_BADGE_H + 4, TFT_BLACK);
    tft.drawRect(x, y, boxW, MODE_BADGE_H, TFT_WHITE);

    const uint8_t textSize = 2;
    int w = textWidth(label, textSize);
    int textX = x + (boxW - w) / 2;
    if (textX < x + 2) {
        textX = x + 2;
    }
    int textY = y + (MODE_BADGE_H - 8 * textSize) / 2 + 1;

    tft.setTextSize(textSize);
    tft.setTextColor(TFT_WHITE, TFT_BLACK);
    tft.setCursor(textX, textY);
    tft.print(label);
}

void drawModeBadgeFilled(const String &modeLabel, int y) {
    String label = modeLabel;
    label.toUpperCase();

    tft.setTextSize(1);
    int w = textWidth(label, 1);
    int padX = 12;
    int padY = 4;
    int boxW = w + padX * 2;
    int boxH = 10 + padY * 2;
    int x = (tft.width() - boxW) / 2;

    tft.fillRoundRect(x, y, boxW, boxH, boxH / 2, TFT_CYAN);
    tft.setTextColor(TFT_BLACK, TFT_CYAN);
    tft.setCursor(x + padX, y + padY);
    tft.print(label);
}

void drawInputFooter(bool hasChars) {
    tft.fillRect(0, FOOTER_Y, tft.width(), 16, TFT_BLACK);
    tft.setTextSize(1);
    tft.setTextColor(TFT_CYAN, TFT_BLACK);
    if (hasChars) {
        tft.setCursor(4, FOOTER_Y + 2);
        tft.print("* HAPUS");
        int confirmW = textWidth("# CONFIRM", 1);
        tft.setCursor(tft.width() - confirmW - 4, FOOTER_Y + 2);
        tft.print("# CONFIRM");
    } else {
        tft.setCursor(4, FOOTER_Y + 2);
        tft.print("* KEMBALI");
    }
}

void drawIdleFooter() {
    tft.fillRect(0, FOOTER_Y, tft.width(), 16, TFT_BLACK);
    printCentered(FOOTER_Y + 2, 1, "Gunakan PIN tekan #", TFT_CYAN, TFT_BLACK);
}

void drawIdleClock(const String &clockText, const String &secText) {
    const uint8_t clockSize = 4;
    idleClockY = 24;
    idleClockW = textWidth(clockText, clockSize);
    idleClockX = (tft.width() - idleClockW) / 2;
    if (idleClockX < 0) {
        idleClockX = 0;
    }

    tft.fillRect(0, idleClockY - 2, tft.width(), 28, TFT_BLACK);
    tft.setTextSize(clockSize);
    tft.setTextColor(TFT_CYAN, TFT_BLACK);
    tft.setCursor(idleClockX, idleClockY);
    tft.print(clockText);

    idleSecX = idleClockX + idleClockW + 2;
    idleSecY = idleClockY + 2;
    tft.setTextSize(1);
    tft.setTextColor(TFT_CYAN, TFT_BLACK);
    tft.setCursor(idleSecX, idleSecY);
    tft.print(secText);
}

void drawIdleDate(const String &dateText) {
    tft.fillRect(0, 54, tft.width(), 12, TFT_BLACK);
    printCentered(56, 1, dateText, TFT_CYAN, TFT_BLACK);
}

void drawColorBar(uint16_t color, const String &text, bool lightTextOnRed) {
    int barY = tft.height() - 18;
    tft.fillRect(0, barY, tft.width(), 18, color);

    String display = text;
    if ((int)display.length() > 28) {
        display = display.substring(0, 27) + "~";
    }

    uint16_t fg = lightTextOnRed ? TFT_WHITE : TFT_BLACK;
    int w = textWidth(display, 1);
    int x = (tft.width() - w) / 2;
    if (x < 0) {
        x = 0;
    }

    tft.setTextSize(1);
    tft.setTextColor(fg, color);
    tft.setCursor(x, barY + 5);
    tft.print(display);
}

uint16_t indicatorColor(attendance_rules::IndicatorLevel level) {
    switch (level) {
        case attendance_rules::IndicatorLevel::Info:
            return TFT_YELLOW;
        case attendance_rules::IndicatorLevel::Warning:
            return TFT_RED;
        default:
            return TFT_GREEN;
    }
}

void drawInputChrome(const String &headerText, const String &modeLabel, bool wifiOk, bool sensorOk,
                     bool serverOk) {
    clearBlack();
    drawTopBar(headerText, wifiOk, sensorOk, serverOk);
    drawModeBadgeOutline(modeLabel);
}

} // namespace

void begin() {
    pinMode(TFT_BL_PIN, OUTPUT);
    digitalWrite(TFT_BL_PIN, HIGH);

    SPI.begin(TFT_SCK_PIN, -1, TFT_MOSI_PIN, -1);
    tft.initR(INITR_BLACKTAB);
    tft.setRotation(1);
    clearBlack();
}

void invalidateIdle() {
    idleFrameActive = false;
}

void invalidateInput() {
    inputFrameActive = false;
}

void showBoot() {
    invalidateIdle();
    invalidateInput();
    clearBlack();
    tft.setTextColor(TFT_WHITE, TFT_BLACK);
    tft.setTextSize(2);
    tft.setCursor(4, 40);
    tft.print("Absensi");
    tft.setTextSize(1);
    tft.setCursor(4, 60);
    tft.print("Memulai...");
}

void showWifiConnecting() {
    invalidateIdle();
    invalidateInput();
    clearBlack();
    tft.setTextColor(TFT_YELLOW, TFT_BLACK);
    tft.setTextSize(2);
    tft.setCursor(4, 40);
    tft.print("WiFi");
    tft.setTextSize(1);
    tft.setCursor(4, 60);
    tft.print("Menghubungkan...");
}

void showWifiPortal(const String &apName, const String &apPassword) {
    invalidateIdle();
    invalidateInput();
    clearBlack();
    tft.setTextColor(TFT_YELLOW, TFT_BLACK);
    tft.setTextSize(2);
    tft.setCursor(4, 4);
    tft.print("Setup WiFi");
    tft.setTextSize(1);
    tft.setTextColor(TFT_WHITE, TFT_BLACK);
    tft.setCursor(4, 28);
    tft.print("Sambungkan HP:");
    tft.setTextColor(TFT_CYAN, TFT_BLACK);
    tft.setCursor(4, 40);
    tft.print(apName);
    tft.setTextColor(TFT_WHITE, TFT_BLACK);
    tft.setCursor(4, 54);
    tft.print("Pass: ");
    tft.print(apPassword);
    tft.setTextColor(TFT_DARKGREY, TFT_BLACK);
    tft.setCursor(4, 68);
    tft.print("* = batal");
}

void showIdle(bool wifiOk, bool sensorOk, bool serverOk, const String &headerText,
              const String &clockText, const String &secText, const String &dateText,
              const String &modeLabel) {
    clearBlack();
    drawTopBar(headerText, wifiOk, sensorOk, serverOk);
    drawIdleClock(clockText, secText);
    drawIdleDate(dateText);
    drawModeBadgeOutline(modeLabel);
    drawIdleFooter();
    idleFrameActive = true;
}

void updateIdle(uint8_t parts, bool wifiOk, bool sensorOk, bool serverOk, const String &headerText,
                const String &clockText, const String &secText, const String &dateText,
                const String &modeLabel) {
    if (!idleFrameActive || parts == IDLE_ALL) {
        showIdle(wifiOk, sensorOk, serverOk, headerText, clockText, secText, dateText, modeLabel);
        return;
    }

    if (parts & IDLE_HEADER) {
        tft.fillRect(0, 0, tft.width() - 30, TOP_BAR_H, TFT_BLACK);
        printTruncated(2, 2, 1, headerText, 14, TFT_YELLOW, TFT_BLACK);
    }
    if (parts & IDLE_ICONS) {
        tft.fillRect(tft.width() - 30, 0, 30, TOP_BAR_H, TFT_BLACK);
        drawStatusIcons(wifiOk, sensorOk, serverOk, TFT_YELLOW);
    }
    if (parts & IDLE_CLOCK) {
        drawIdleClock(clockText, secText);
    } else if (parts & IDLE_SECONDS) {
        tft.fillRect(idleSecX - 1, idleSecY - 1, 22, 10, TFT_BLACK);
        tft.setTextSize(1);
        tft.setTextColor(TFT_CYAN, TFT_BLACK);
        tft.setCursor(idleSecX, idleSecY);
        tft.print(secText);
    }
    if (parts & IDLE_DATE) {
        drawIdleDate(dateText);
    }
    if (parts & IDLE_MODE) {
        drawModeBadgeOutline(modeLabel);
    }
}

void showInputId(const String &buffer, bool hasChars, const String &headerText,
                 const String &modeLabel, bool wifiOk, bool sensorOk, bool serverOk) {
    drawInputChrome(headerText, modeLabel, wifiOk, sensorOk, serverOk);

    tft.fillRect(0, 14, tft.width(), 18, TFT_BLACK);
    printCentered(16, 1, "INPUT ID", TFT_CYAN, TFT_BLACK);

    tft.fillRect(0, 36, tft.width(), 32, TFT_BLACK);
    printCentered(40, 3, buffer.length() ? buffer : "_", TFT_CYAN, TFT_BLACK);

    drawInputFooter(hasChars);
    inputFrameActive = true;
}

void updateInputId(const String &buffer, bool hasChars) {
    if (!inputFrameActive) {
        return;
    }
    tft.fillRect(0, 36, tft.width(), 32, TFT_BLACK);
    printCentered(40, 3, buffer.length() ? buffer : "_", TFT_CYAN, TFT_BLACK);
    drawInputFooter(hasChars);
}

void showInputPin(const String &employeeName, const String &maskedPin, bool hasChars,
                  const String &headerText, const String &modeLabel, bool wifiOk, bool sensorOk,
                  bool serverOk) {
    drawInputChrome(headerText, modeLabel, wifiOk, sensorOk, serverOk);

    tft.fillRect(0, 14, tft.width(), 18, TFT_BLACK);
    printCenteredFit(15, 2, employeeName, TFT_CYAN, TFT_BLACK);

    tft.fillRect(0, 36, tft.width(), 32, TFT_BLACK);
    printCentered(44, 2, maskedPin.length() ? maskedPin : "-", TFT_CYAN, TFT_BLACK);

    drawInputFooter(hasChars);
    inputFrameActive = true;
}

void updateInputPin(const String &maskedPin, bool hasChars) {
    if (!inputFrameActive) {
        return;
    }
    tft.fillRect(0, 36, tft.width(), 32, TFT_BLACK);
    printCentered(44, 2, maskedPin.length() ? maskedPin : "-", TFT_CYAN, TFT_BLACK);
    drawInputFooter(hasChars);
}

void showWifiInfo(bool connected, const String &ssid, const String &ip) {
    invalidateIdle();
    invalidateInput();
    clearBlack();
    tft.setTextColor(TFT_YELLOW, TFT_BLACK);
    tft.setTextSize(2);
    tft.setCursor(4, 4);
    tft.print("Info WiFi");

    tft.setTextSize(1);
    if (connected) {
        tft.setTextColor(TFT_WHITE, TFT_BLACK);
        tft.setCursor(4, 32);
        tft.print("Terhubung ke:");
        tft.setTextColor(TFT_CYAN, TFT_BLACK);
        tft.setCursor(4, 44);
        tft.print(ssid);

        tft.setTextColor(TFT_WHITE, TFT_BLACK);
        tft.setCursor(4, 62);
        tft.print("IP Address:");
        tft.setTextColor(TFT_CYAN, TFT_BLACK);
        tft.setCursor(4, 74);
        tft.print(ip);
    } else {
        tft.setTextColor(TFT_RED, TFT_BLACK);
        tft.setCursor(4, 40);
        tft.print("Belum terhubung WiFi");
    }

    tft.setTextColor(TFT_DARKGREY, TFT_BLACK);
    tft.setCursor(4, 112);
    tft.print("* = kembali");
}

void showMessage(const String &title, const String &body, uint16_t colorHint) {
    invalidateIdle();
    invalidateInput();
    clearBlack();
    tft.setTextColor(colorHint == 0 ? TFT_WHITE : colorHint, TFT_BLACK);
    tft.setTextSize(2);
    tft.setCursor(4, 8);
    printTruncated(4, 8, 2, title, 12, colorHint == 0 ? TFT_WHITE : colorHint, TFT_BLACK);
    tft.setTextSize(1);
    tft.setTextColor(TFT_WHITE, TFT_BLACK);
    tft.setCursor(4, 36);
    printTruncated(4, 36, 1, body, 28, TFT_WHITE, TFT_BLACK);
}

void showEnrollScreen(const String &title, const String &employeeName, int employeeCode,
                      uint16_t barColor, const String &barText, bool lightTextOnBar) {
    invalidateIdle();
    invalidateInput();
    clearWhite();

    printCentered(8, 1, title, TFT_BLACK, TFT_WHITE);

    String name = employeeName.length() ? employeeName : "Karyawan";
    printCentered(34, 2, name, TFT_NAVY, TFT_WHITE);

    if (employeeCode > 0) {
        printCentered(54, 2, String("ID: ") + employeeCode, TFT_NAVY, TFT_WHITE);
    }

    drawColorBar(barColor, barText, lightTextOnBar);
}

void showEnrollPrompt(const String &employeeName, int employeeCode, int step, int totalSteps) {
    String barText = "(Scan " + String(step) + "/" + String(totalSteps) + ") Tempel jari ...";
    showEnrollScreen("Daftarkan sidik jari !", employeeName, employeeCode, TFT_YELLOW, barText);
}

void showAttendanceResult(AttendanceType type, const String &employeeName,
                          const String &modeLabel, const String &timeText,
                          const attendance_rules::AttendanceIndicator &indicator) {
    invalidateIdle();
    invalidateInput();
    clearWhite();

    const device_config::ModeTexts &texts = device_config::modeTexts(type);
    uint16_t barColor = indicatorColor(indicator.level);
    bool lightTextOnRed = indicator.level == attendance_rules::IndicatorLevel::Warning;

    printCentered(8, 1, texts.header, TFT_BLACK, TFT_WHITE);
    printCentered(26, 2, employeeName, TFT_NAVY, TFT_WHITE);
    drawModeBadgeFilled(modeLabel, 48);
    printCentered(72, 4, timeText, TFT_BLACK, TFT_WHITE);
    drawColorBar(barColor, indicator.barText, lightTextOnRed);
}

void showAttendanceFailed(const String &reason) {
    invalidateIdle();
    invalidateInput();
    clearWhite();

    printCentered(24, 2, "Gagal", TFT_RED, TFT_WHITE);
    printCentered(48, 1, reason, TFT_BLACK, TFT_WHITE);
    drawColorBar(TFT_RED, reason, true);
}

} // namespace lcd_ui
