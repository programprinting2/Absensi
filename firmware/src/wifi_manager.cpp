#include "wifi_manager.h"
#include "config.h"
#include <WiFiManager.h>

namespace wifi_manager {

namespace {
WiFiManager wm;
bool portalActive = false;

unsigned long lastReconnectAttemptMs = 0;
const unsigned long RECONNECT_INTERVAL_MS = 10000;
} // namespace

void begin() {
    wm.setConfigPortalBlocking(false);
    wm.setConfigPortalTimeout(WIFI_PORTAL_TIMEOUT_SEC);

    // Coba sambung pakai kredensial tersimpan di NVS. Kalau belum pernah
    // disetel sama sekali, WiFiManager otomatis membuka access point
    // konfigurasi (non-blocking — loop() lain tetap jalan).
    wm.autoConnect(WIFI_AP_NAME, WIFI_AP_PASSWORD);
    portalActive = wm.getConfigPortalActive();
}

void loop() {
    wm.process();
    portalActive = wm.getConfigPortalActive();

    if (portalActive || WiFi.status() == WL_CONNECTED) {
        return;
    }

    // Sudah pernah disetel tapi sedang terputus (mis. WiFi kantor sempat
    // mati) — coba sambung ulang berkala tanpa membuka portal lagi.
    unsigned long now = millis();
    if (now - lastReconnectAttemptMs >= RECONNECT_INTERVAL_MS) {
        WiFi.reconnect();
        lastReconnectAttemptMs = now;
    }
}

bool isConnected() {
    return WiFi.status() == WL_CONNECTED;
}

bool isConfigPortalActive() {
    return portalActive;
}

void startConfigPortal() {
    wm.startConfigPortal(WIFI_AP_NAME, WIFI_AP_PASSWORD);
    portalActive = true;
}

void stopConfigPortal() {
    if (!portalActive) {
        return;
    }
    wm.stopConfigPortal();
    portalActive = wm.getConfigPortalActive();
    if (!portalActive && WiFi.status() != WL_CONNECTED) {
        WiFi.reconnect();
        lastReconnectAttemptMs = millis();
    }
}

String currentSSID() {
    return WiFi.SSID();
}

String localIPString() {
    return WiFi.localIP().toString();
}

} // namespace wifi_manager
