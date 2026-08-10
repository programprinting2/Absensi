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
// Harus muat info box + skrip isi form (URL/key bisa panjang). Buffer 1024
// memotong di tengah <script>, sehingga field database + tombol Save "hilang".
char configInjectHtml[3072];

void appendJsEscaped(const String &value, String &out) {
    for (unsigned int i = 0; i < value.length(); i++) {
        const char c = value[i];
        if (c == '\\' || c == '"') {
            out += '\\';
        }
        out += c;
    }
}

void buildConfigInjectHtml() {
    const bool supabase = server_config::useRestApi();
    const char *mode = supabase ? "supabase" : "laravel";
    const char *modeLabel = supabase ? "Supabase Cloud" : "Laravel Lokal";

    const String url = server_config::serverUrl();
    const String key = server_config::apiKey();
    const String code = server_config::deviceCode();
    const String dash = server_config::dashboardUrl();

    String urlJs, keyJs, codeJs, dashJs;
    appendJsEscaped(url, urlJs);
    appendJsEscaped(key, keyJs);
    appendJsEscaped(code, codeJs);
    appendJsEscaped(dash, dashJs);

    String dashLine;
    if (supabase && dash.length() > 0) {
        dashLine = String("Dashboard: ") + dash + "<br/>";
    }

    snprintf(
        configInjectHtml,
        sizeof(configInjectHtml),
        R"HTML(
<br/><div id="wm_active_cfg" style="margin:8px 0;padding:10px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:6px;font-size:13px;line-height:1.45">
<b>Konfigurasi aktif (NVS)</b><br/>
Mode: %s<br/>
URL: %s<br/>
Device: %s<br/>
%s
</div>
<script>
window.wmSrvCfg={mode:"%s",server_url:"%s",api_key:"%s",device_code:"%s",dashboard_url:"%s"};
function wmFillServerFields(){
  var c=window.wmSrvCfg;if(!c)return;
  var m=document.querySelector('[name="server_mode"]');
  if(m)m.value=c.mode;
  var fields=[["server_url",c.server_url],["api_key",c.api_key],["device_code",c.device_code],["dashboard_url",c.dashboard_url]];
  for(var i=0;i<fields.length;i++){
    var el=document.querySelector('[name="'+fields[i][0]+'"]');
    if(el)el.value=fields[i][1]||'';
  }
  if(typeof applyServerMode==="function")applyServerMode(c.mode);
}
document.addEventListener("DOMContentLoaded",function(){wmFillServerFields();});
setTimeout(wmFillServerFields,250);
setTimeout(wmFillServerFields,800);
</script>
)HTML",
        modeLabel,
        url.length() > 0 ? url.c_str() : "(belum diset)",
        code.length() > 0 ? code.c_str() : "(belum diset)",
        supabase && dash.length() > 0 ? dashLine.c_str() : "",
        mode,
        urlJs.c_str(),
        keyJs.c_str(),
        codeJs.c_str(),
        dashJs.c_str());
}

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
  var input = document.querySelector('input[name="' + name + '"]');
  if (!input) return;
  input.style.display = hide ? 'none' : '';
  var node = input.previousSibling;
  while (node) {
    var prev = node.previousSibling;
    if (node.nodeType === 3) {
      if (hide) {
        if (!node._wmOrigText) node._wmOrigText = node.textContent;
        node.textContent = '';
      } else if (node._wmOrigText) {
        node.textContent = node._wmOrigText;
      }
    } else if (node.nodeType === 1 && node.tagName === 'BR') {
      node.style.display = hide ? 'none' : '';
    } else if (node.nodeType === 1 && (node.tagName === 'INPUT' || node.tagName === 'FIELDSET')) {
      break;
    }
    node = prev;
  }
  var next = input.nextElementSibling;
  if (next && next.tagName === 'BR') {
    next.style.display = hide ? 'none' : '';
  }
}

function wmSetInputLabel(name, text) {
  var input = document.querySelector('input[name="' + name + '"]');
  if (!input) return;
  var node = input.previousSibling;
  while (node) {
    if (node.nodeType === 3 && node.textContent.trim()) {
      node.textContent = text;
      node._wmOrigText = text;
      return;
    }
    node = node.previousSibling;
  }
}

function applyServerMode(mode) {
  var hidden = document.querySelector('[name="server_mode"]');
  if (hidden) hidden.value = mode;

  var isLocal = (mode === 'laravel');
  wmHideNamedField('api_key', isLocal);
  wmHideNamedField('dashboard_url', isLocal);

  wmSetInputLabel('server_url', isLocal
    ? 'URL Laravel (contoh: http://192.168.100.100:8008)'
    : 'URL Supabase (contoh: https://xxxx.supabase.co)');

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

WiFiManagerParameter paramConfigInject(configInjectHtml);

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
    buildConfigInjectHtml();
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
    wm.addParameter(&paramConfigInject);
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
