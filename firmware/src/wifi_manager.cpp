#include "wifi_manager.h"
#include "config.h"
#include "network_task.h"
#include "server_config.h"
#include <WiFiManager.h>

namespace wifi_manager {

namespace {

WiFiManager wm;
bool portalActive = false;
bool webPortalActive = false;

unsigned long lastReconnectAttemptMs = 0;
const unsigned long RECONNECT_INTERVAL_MS = 10000;

// Buffer statis — WiFiManagerParameter menyimpan pointer ke buffer ini.
char serverUrlParam[129];
char apiKeyParam[257];
char deviceCodeParam[33];
char dashboardUrlParam[129];

WiFiManagerParameter paramServerUrl(
    "server_url",
    "Server URL (Laravel lokal ATAU Supabase, tanpa /rest/v1)",
    serverUrlParam,
    sizeof(serverUrlParam) - 1);

WiFiManagerParameter paramApiKey(
    "api_key",
    "API Key (wajib Supabase, kosong untuk Laravel lokal)",
    apiKeyParam,
    sizeof(apiKeyParam) - 1);

WiFiManagerParameter paramDeviceCode(
    "device_code",
    "Device Code",
    deviceCodeParam,
    sizeof(deviceCodeParam) - 1);

WiFiManagerParameter paramDashboardUrl(
    "dashboard_url",
    "Dashboard URL (kosong=jika Laravel lokal, isi jika pakai Supabase)",
    dashboardUrlParam,
    sizeof(dashboardUrlParam) - 1);

void copyToParamBuffer(char *dest, size_t destSize, const String &value) {
    if (destSize == 0) {
        return;
    }
    strncpy(dest, value.c_str(), destSize - 1);
    dest[destSize - 1] = '\0';
}

void refreshServerParamValues() {
    copyToParamBuffer(serverUrlParam, sizeof(serverUrlParam), server_config::serverUrl());
    copyToParamBuffer(apiKeyParam, sizeof(apiKeyParam), server_config::apiKey());
    copyToParamBuffer(deviceCodeParam, sizeof(deviceCodeParam), server_config::deviceCode());
    copyToParamBuffer(dashboardUrlParam, sizeof(dashboardUrlParam), server_config::dashboardUrl());
}

void saveParamsCallback() {
    const bool changed = server_config::save(
        String(paramServerUrl.getValue()),
        String(paramApiKey.getValue()),
        String(paramDeviceCode.getValue()),
        String(paramDashboardUrl.getValue()));

    if (changed) {
        network_task::resetServerConnection();
    }
}

void registerServerParams() {
    refreshServerParamValues();
    wm.addParameter(&paramServerUrl);
    wm.addParameter(&paramApiKey);
    wm.addParameter(&paramDeviceCode);
    wm.addParameter(&paramDashboardUrl);
    wm.setSaveParamsCallback(saveParamsCallback);
}

void syncPortalFlags() {
    portalActive = wm.getConfigPortalActive();
    webPortalActive = wm.getWebPortalActive();
}

void ensureWebPortal() {
    if (WiFi.status() != WL_CONNECTED) {
        return;
    }

    refreshServerParamValues();
    if (!wm.getWebPortalActive()) {
        wm.startWebPortal();
        Serial.print(F("[wifi] Web portal aktif di http://"));
        Serial.println(WiFi.localIP());
    }
    syncPortalFlags();
}

void onWifiConnected() {
    ensureWebPortal();
}

} // namespace

void begin() {
    wm.setConfigPortalBlocking(false);
    wm.setConfigPortalTimeout(WIFI_PORTAL_TIMEOUT_SEC);
    registerServerParams();

    // Coba sambung pakai kredensial tersimpan di NVS. Kalau belum pernah
    // disetel sama sekali, WiFiManager otomatis membuka access point
    // konfigurasi (non-blocking — loop() lain tetap jalan).
    if (wm.autoConnect(WIFI_AP_NAME, WIFI_AP_PASSWORD)) {
        onWifiConnected();
    }
    syncPortalFlags();
}

void loop() {
    static bool wasConnected = false;

    wm.process();
    syncPortalFlags();

    const bool connected = WiFi.status() == WL_CONNECTED;
    if (connected && !wasConnected) {
        onWifiConnected();
    }
    wasConnected = connected;

    if (portalActive || webPortalActive || connected) {
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

bool isWebPortalActive() {
    return webPortalActive;
}

void startApConfigPortal() {
    refreshServerParamValues();

    if (wm.getWebPortalActive()) {
        wm.stopWebPortal();
    }

    wm.startConfigPortal(WIFI_AP_NAME, WIFI_AP_PASSWORD);
    syncPortalFlags();
    Serial.println(F("[wifi] AP portal aktif — sambungkan HP ke Absensi-Setup"));
}

void startConfigPortal() {
    startApConfigPortal();
}

void stopConfigPortal() {
    if (webPortalActive) {
        wm.stopWebPortal();
        syncPortalFlags();
        return;
    }

    if (!portalActive) {
        return;
    }

    wm.stopConfigPortal();
    syncPortalFlags();
    if (!portalActive && !webPortalActive && WiFi.status() != WL_CONNECTED) {
        WiFi.reconnect();
        lastReconnectAttemptMs = millis();
    } else if (WiFi.status() == WL_CONNECTED) {
        ensureWebPortal();
    }
}

String currentSSID() {
    return WiFi.SSID();
}

String localIPString() {
    return WiFi.localIP().toString();
}

} // namespace wifi_manager
