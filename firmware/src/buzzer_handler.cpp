#include "buzzer_handler.h"
#include "config.h"
#include <Arduino.h>

namespace buzzer_handler {

namespace {

void startTone() {
#if BUZZER_USE_TONE
#if defined(ESP_ARDUINO_VERSION_MAJOR) && ESP_ARDUINO_VERSION_MAJOR >= 3
    ledcAttach(BUZZER_PIN, BUZZER_FREQ_HZ, 10);
    ledcWrite(BUZZER_PIN, BUZZER_LEDC_DUTY);
#else
    const int channel = 0;
    ledcSetup(channel, BUZZER_FREQ_HZ, 10);
    ledcAttachPin(BUZZER_PIN, channel);
    ledcWrite(channel, BUZZER_LEDC_DUTY);
#endif
#else
    digitalWrite(BUZZER_PIN, HIGH);
#endif
}

void stopTone() {
#if BUZZER_USE_TONE
#if defined(ESP_ARDUINO_VERSION_MAJOR) && ESP_ARDUINO_VERSION_MAJOR >= 3
    ledcWrite(BUZZER_PIN, 0);
    ledcDetach(BUZZER_PIN);
#else
    ledcWrite(0, 0);
#endif
#else
    digitalWrite(BUZZER_PIN, LOW);
#endif
}

void soundOn(unsigned int durationMs) {
    startTone();
    delay(durationMs);
    stopTone();
}

void beep(unsigned int durationMs) {
    soundOn(durationMs);
}

} // namespace

void begin() {
    pinMode(BUZZER_PIN, OUTPUT);
    digitalWrite(BUZZER_PIN, LOW);
}

void beepSuccess() {
    beep(BUZZER_SUCCESS_MS);
}

void beepFail() {
    for (int i = 0; i < BUZZER_FAIL_COUNT; i++) {
        beep(BUZZER_FAIL_BEEP_MS);
        if (i < BUZZER_FAIL_COUNT - 1) {
            delay(BUZZER_FAIL_GAP_MS);
        }
    }
}

void beepKey() {
    beep(BUZZER_KEY_MS);
}

} // namespace buzzer_handler
