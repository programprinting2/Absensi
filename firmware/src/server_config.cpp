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
String cachedMode = "laravel";

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

String normalizeMode(const String &mode) {
    String m = mode;
    m.trim();
    if (m == "supabase" || m == "rest") {
        return m;
    }
    return "laravel";
}

void loadDefaults() {
    cachedUrl = normalizeUrl(String(SUPABASE_URL));
    cachedKey = String(SUPABASE_ANON_KEY);
    cachedDeviceCode = String(DEVICE_CODE);
    cachedDashboardUrl = normalizeUrl(String(DEFAULT_DASHBOARD_URL));
    cachedMode = "laravel";
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

String inferModeFromUrl(const String &url) {
    if (isSupabaseUrl(url)) {
        return "supabase";
    }
    return "laravel";
}

bool isValidDataConfig(const String &mode, const String &url, const String &key, const String &code) {
    if (!isValidServerUrl(url) || code.length() == 0) {
        return false;
    }

    const String normalizedMode = normalizeMode(mode);

    if (normalizedMode == "laravel") {
        return true;
    }

    if (normalizedMode == "supabase") {
        return isSupabaseUrl(url) && key.length() > 0;
    }

    if (normalizedMode == "rest") {
        return !isSupabaseUrl(url) && key.length() > 0;
    }

    return false;
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
        String mode = prefs.getString("mode", "");

        if (mode.length() == 0) {
            mode = inferModeFromUrl(url);
        }

        if (isValidDataConfig(mode, url, key, code)) {
            cachedUrl = normalizeUrl(url);
            cachedKey = key;
            cachedDeviceCode = code;
            cachedMode = normalizeMode(mode);
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
    Serial.println(apiMode());
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

String apiMode() {
    return cachedMode;
}

bool useRestApi() {
    return cachedMode == "supabase" || cachedMode == "rest";
}

String heartbeatBaseUrl() {
    if (cachedMode == "laravel" || cachedMode == "rest") {
        return cachedUrl;
    }
    return cachedDashboardUrl.length() > 0 ? cachedDashboardUrl : cachedUrl;
}

bool hasStoredConfig() {
    return storedInNvs;
}

bool save(const String &url, const String &key, const String &code, const String &dashboardUrlIn,
          const String &mode) {
    String normalizedUrl = normalizeUrl(url);
    String trimmedKey = key;
    trimmedKey.trim();
    String trimmedCode = code;
    trimmedCode.trim();
    String normalizedDashboard = normalizeUrl(dashboardUrlIn);
    const String normalizedMode = normalizeMode(mode);

    if (!isValidDataConfig(normalizedMode, normalizedUrl, trimmedKey, trimmedCode)) {
        Serial.println(F("[server_config] save ditolak: Server URL/API Key/Device Code tidak valid"));
        return false;
    }

    if (normalizedMode == "laravel" || normalizedMode == "rest") {
        if (normalizedDashboard.length() == 0) {
            normalizedDashboard = normalizedUrl;
        }
    }

    if (normalizedMode == "supabase" && normalizedDashboard.length() == 0) {
        Serial.println(F("[server_config] save ditolak: dashboard URL wajib untuk Supabase"));
        return false;
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
    prefs.putString("mode", normalizedMode);
    prefs.putBool("configured", true);
    prefs.end();

    cachedUrl = normalizedUrl;
    cachedKey = trimmedKey;
    cachedDeviceCode = trimmedCode;
    cachedDashboardUrl = normalizedDashboard;
    cachedMode = normalizedMode;
    storedInNvs = true;
    loaded = true;

    Serial.println(F("[server_config] disimpan ke NVS"));
    Serial.print(F("[server_config] mode="));
    Serial.println(cachedMode);
    Serial.print(F("[server_config] dashboard="));
    Serial.println(cachedDashboardUrl);

    return true;
}

} // namespace server_config
