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
bool portalFormSynced = false;

unsigned long lastReconnectAttemptMs = 0;
const unsigned long RECONNECT_INTERVAL_MS = 10000;

// Buffer statis — WiFiManagerParameter menyimpan pointer ke buffer ini.
char serverModeParam[16] = "laravel";
char serverUrlParam[129];
char apiKeyParam[257];
char deviceCodeParam[33];
char dashboardUrlParam[129];

// Radio + skrip toggle field (WiFiManager custom HTML parameter).
const char PARAM_MODE_HTML[] PROGMEM = R"HTML(
<br/><fieldset style='margin:8px 0;padding:8px;border:1px solid #ccc;border-radius:6px'>
<legend><b>Mode Database / Server</b></legend>
<label style='display:block;margin:4px 0'>
  <input type='radio' name='mode_pick' value='laravel' onclick='applyServerMode("laravel")'>
  <b>Laravel Lokal</b> — PostgreSQL via server kantor (http://IP:8008)
</label>
<label style='display:block;margin:4px 0'>
  <input type='radio' name='mode_pick' value='supabase' onclick='applyServerMode("supabase")'>
  <b>Supabase Cloud</b> — database online + dashboard Laravel terpisah
</label>
</fieldset>
)HTML";

const char PORTAL_HEAD_HTML[] PROGMEM = R"HEAD(
<style>
  .wm-hidden-field { display: none !important; }
</style>
<script>
function wmHideNamedField(name, hide) {
  var input = document.querySelector('[name="' + name + '"]');
  if (!input) return;
  input.style.display = hide ? 'none' : '';
  var prev = input.previousElementSibling;
  if (prev && prev.tagName === 'LABEL') {
    prev.style.display = hide ? 'none' : '';
  }
  var br = input.nextElementSibling;
  if (br && br.tagName === 'BR') {
    br.style.display = hide ? 'none' : '';
  }
}

function applyServerMode(mode) {
  var hidden = document.querySelector('[name="server_mode"]');
  if (hidden) hidden.value = mode;

  var isLocal = (mode === 'laravel');
  wmHideNamedField('api_key', isLocal);
  wmHideNamedField('dashboard_url', isLocal);

  var urlLabel = document.querySelector('label[for="server_url"]');
  if (urlLabel) {
    urlLabel.textContent = isLocal
      ? 'URL Laravel (contoh: http://192.168.100.249:8008)'
      : 'URL Supabase (contoh: https://xxxx.supabase.co)';
  }

  var picks = document.querySelectorAll('input[name="mode_pick"]');
  for (var i = 0; i < picks.length; i++) {
    picks[i].checked = (picks[i].value === mode);
  }
}

function initServerModeForm() {
  var hidden = document.querySelector('[name="server_mode"]');
  var mode = (hidden && hidden.value) ? hidden.value : 'laravel';
  applyServerMode(mode);
}

document.addEventListener('DOMContentLoaded', function() {
  setTimeout(initServerModeForm, 120);
});
</script>
)HEAD";

WiFiManagerParameter paramModeHtml(PARAM_MODE_HTML);

WiFiManagerParameter paramServerMode(
    "server_mode",
    "",
    serverModeParam,
    sizeof(serverModeParam) - 1,
    "type=\"hidden\"");

WiFiManagerParameter paramServerUrl(
    "server_url",
    "URL Laravel (contoh: http://192.168.100.249:8008)",
    serverUrlParam,
    sizeof(serverUrlParam) - 1);

WiFiManagerParameter paramApiKey(
    "api_key",
    "API Key Supabase (Publishable key)",
    apiKeyParam,
    sizeof(apiKeyParam) - 1);

WiFiManagerParameter paramDeviceCode(
    "device_code",
    "Device Code (contoh: DEV-001)",
    deviceCodeParam,
    sizeof(deviceCodeParam) - 1);

WiFiManagerParameter paramDashboardUrl(
    "dashboard_url",
    "URL Dashboard Laravel (heartbeat ONLINE/OFFLINE)",
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
    const bool supabase = server_config::useRestApi();
    copyToParamBuffer(serverModeParam, sizeof(serverModeParam), supabase ? "supabase" : "laravel");
    copyToParamBuffer(serverUrlParam, sizeof(serverUrlParam), server_config::serverUrl());
    copyToParamBuffer(apiKeyParam, sizeof(apiKeyParam), server_config::apiKey());
    copyToParamBuffer(deviceCodeParam, sizeof(deviceCodeParam), server_config::deviceCode());
    copyToParamBuffer(dashboardUrlParam, sizeof(dashboardUrlParam), server_config::dashboardUrl());
}

void saveParamsCallback() {
    String mode = String(paramServerMode.getValue());
    mode.trim();
    if (mode.length() == 0) {
        mode = "laravel";
    }

    String url = String(paramServerUrl.getValue());
    String key = String(paramApiKey.getValue());
    String code = String(paramDeviceCode.getValue());
    String dashboard = String(paramDashboardUrl.getValue());

    url.trim();
    key.trim();
    code.trim();
    dashboard.trim();

    if (mode == "laravel") {
        key = "";
        if (dashboard.length() == 0) {
            dashboard = url;
        }
    }

    const bool changed = server_config::save(url, key, code, dashboard);
    if (changed) {
        network_task::resetServerConnection();
    }
    refreshServerParamValues();
}

void registerServerParams() {
    refreshServerParamValues();
    wm.addParameter(&paramModeHtml);
    wm.addParameter(&paramServerMode);
    wm.addParameter(&paramServerUrl);
    wm.addParameter(&paramApiKey);
    wm.addParameter(&paramDeviceCode);
    wm.addParameter(&paramDashboardUrl);
    wm.setSaveParamsCallback(saveParamsCallback);
    wm.setCustomHeadElement(PORTAL_HEAD_HTML);
}

void syncPortalFlags() {
    portalActive = wm.getConfigPortalActive();
    webPortalActive = wm.getWebPortalActive();
    if (!portalActive && !webPortalActive) {
        portalFormSynced = false;
    }
}

// WiFiManager punya storage parameter sendiri — sering kosong walau srv_cfg NVS sudah terisi.
// Setelah portal aktif, isi ulang buffer dari server_config agar form tidak blank.
void syncPortalFormIfNeeded() {
    if (!portalActive && !webPortalActive) {
        return;
    }
    if (portalFormSynced) {
        return;
    }
    refreshServerParamValues();
    portalFormSynced = true;
}

void ensureWebPortal() {
    if (WiFi.status() != WL_CONNECTED) {
        return;
    }

    refreshServerParamValues();
    portalFormSynced = false;
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

    if (wm.autoConnect(WIFI_AP_NAME, WIFI_AP_PASSWORD)) {
        onWifiConnected();
    }
    syncPortalFlags();
}

void loop() {
    static bool wasConnected = false;

    wm.process();
    syncPortalFlags();
    syncPortalFormIfNeeded();

    // Setelah WM load parameter internal, timpa lagi dari NVS kita (fix form kosong).
    static bool wasPortalOpen = false;
    const bool portalOpen = portalActive || webPortalActive;
    if (portalOpen && !wasPortalOpen) {
        refreshServerParamValues();
        portalFormSynced = false;
        syncPortalFormIfNeeded();
    }
    wasPortalOpen = portalOpen;

    const bool connected = WiFi.status() == WL_CONNECTED;
    if (connected && !wasConnected) {
        onWifiConnected();
    }
    wasConnected = connected;

    if (portalActive || webPortalActive || connected) {
        return;
    }

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
    portalFormSynced = false;

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
