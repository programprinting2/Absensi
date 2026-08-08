#include "server_config.h"
#include "config.h"
#include <Preferences.h>

namespace server_config {

namespace {

Preferences prefs;
bool loaded = false;
bool storedInNvs = false;

String cachedUrl;
String cachedKey;
String cachedDeviceCode;
String cachedDashboardUrl;

String trimTrailingSlash(String url) {
    while (url.endsWith("/")) {
        url.remove(url.length() - 1);
    }
    return url;
}

String normalizeUrl(const String &url) {
    String trimmed = url;
    trimmed.trim();
    return trimTrailingSlash(trimmed);
}

void loadDefaults() {
    cachedUrl = normalizeUrl(String(SUPABASE_URL));
    cachedKey = String(SUPABASE_ANON_KEY);
    cachedDeviceCode = String(DEVICE_CODE);
    cachedDashboardUrl = normalizeUrl(String(DEFAULT_DASHBOARD_URL));
}

bool isValidServerUrl(const String &url) {
    if (url.length() == 0) {
        return false;
    }
    return url.startsWith("http://") || url.startsWith("https://");
}

bool isValidDashboardUrl(const String &url) {
    if (url.length() == 0) {
        return false;
    }
    return url.startsWith("http://") || url.startsWith("https://");
}

bool isSupabaseUrl(const String &url) {
    return url.indexOf("supabase.co") >= 0;
}

bool isValidDataConfig(const String &url, const String &key, const String &code) {
    if (!isValidServerUrl(url) || code.length() == 0) {
        return false;
    }
    // Supabase cloud wajib API key. Laravel lokal (http) API key boleh kosong.
    if (isSupabaseUrl(url) && key.length() == 0) {
        return false;
    }
    if (url.startsWith("https://") && !isSupabaseUrl(url) && key.length() == 0) {
        return false;
    }
    return true;
}

} // namespace

void begin() {
    if (loaded) {
        return;
    }

    loadDefaults();

    if (!prefs.begin("srv_cfg", true)) {
        loaded = true;
        return;
    }

    storedInNvs = prefs.getBool("configured", false);
    if (storedInNvs) {
        String url = prefs.getString("url", "");
        String key = prefs.getString("key", "");
        String code = prefs.getString("code", "");
        String dashboard = prefs.getString("dashboard", "");

        if (isValidDataConfig(url, key, code)) {
            cachedUrl = normalizeUrl(url);
            cachedKey = key;
            cachedDeviceCode = code;
        } else {
            storedInNvs = false;
        }

        if (isValidDashboardUrl(dashboard)) {
            cachedDashboardUrl = normalizeUrl(dashboard);
        }
    }

    prefs.end();
    loaded = true;

    Serial.print(F("[server_config] url="));
    Serial.println(cachedUrl);
    Serial.print(F("[server_config] dashboard="));
    Serial.println(cachedDashboardUrl);
    Serial.print(F("[server_config] device_code="));
    Serial.println(cachedDeviceCode);
    Serial.print(F("[server_config] mode="));
    Serial.println(useRestApi() ? F("supabase") : F("laravel"));
    Serial.print(F("[server_config] source="));
    Serial.println(storedInNvs ? F("NVS") : F("default(config.h)"));
}

String serverUrl() {
    return cachedUrl;
}

String apiKey() {
    return cachedKey;
}

String deviceCode() {
    return cachedDeviceCode;
}

String dashboardUrl() {
    return cachedDashboardUrl;
}

bool useTls() {
    return cachedUrl.startsWith("https://");
}

bool dashboardUseTls() {
    return cachedDashboardUrl.startsWith("https://");
}

bool useRestApi() {
    return isSupabaseUrl(cachedUrl);
}

String heartbeatBaseUrl() {
    if (!useRestApi()) {
        return cachedUrl;
    }
    return cachedDashboardUrl.length() > 0 ? cachedDashboardUrl : cachedUrl;
}

bool hasStoredConfig() {
    return storedInNvs;
}

bool save(const String &url, const String &key, const String &code, const String &dashboardUrlIn) {
    String normalizedUrl = normalizeUrl(url);
    String trimmedKey = key;
    trimmedKey.trim();
    String trimmedCode = code;
    trimmedCode.trim();
    String normalizedDashboard = normalizeUrl(dashboardUrlIn);

    if (!isValidDataConfig(normalizedUrl, trimmedKey, trimmedCode)) {
        Serial.println(F("[server_config] save ditolak: Server URL/API Key/Device Code tidak valid"));
        return false;
    }

    if (normalizedDashboard.length() == 0 && !isSupabaseUrl(normalizedUrl)) {
        normalizedDashboard = normalizedUrl;
    }

    if (!isValidDashboardUrl(normalizedDashboard)) {
        Serial.println(F("[server_config] save ditolak: dashboard URL tidak valid"));
        return false;
    }

    if (!prefs.begin("srv_cfg", false)) {
        Serial.println(F("[server_config] save gagal: NVS tidak bisa dibuka"));
        return false;
    }

    prefs.putString("url", normalizedUrl);
    prefs.putString("key", trimmedKey);
    prefs.putString("code", trimmedCode);
    prefs.putString("dashboard", normalizedDashboard);
    prefs.putBool("configured", true);
    prefs.end();

    cachedUrl = normalizedUrl;
    cachedKey = trimmedKey;
    cachedDeviceCode = trimmedCode;
    cachedDashboardUrl = normalizedDashboard;
    storedInNvs = true;
    loaded = true;

    Serial.println(F("[server_config] disimpan ke NVS"));
    Serial.print(F("[server_config] dashboard="));
    Serial.println(cachedDashboardUrl);

    return true;
}

} // namespace server_config
