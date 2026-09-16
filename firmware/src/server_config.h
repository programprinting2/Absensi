#pragma once

#include <Arduino.h>

namespace server_config {

void begin();

String serverUrl();
String apiKey();
String deviceCode();
String dashboardUrl();
bool useTls();
bool dashboardUseTls();

// "laravel" | "supabase" | "rest" (PostgREST di server Rocky sendiri)
String apiMode();

// true = Supabase cloud atau Server REST (/rest/v1/ + API key JWT).
bool useRestApi();

// URL dasar heartbeat/evaluate — Laravel lokal & Server REST pakai serverUrl; Supabase pakai dashboardUrl.
String heartbeatBaseUrl();

bool hasStoredConfig();

bool save(const String &url, const String &key, const String &code, const String &dashboardUrl,
          const String &mode);

} // namespace server_config
