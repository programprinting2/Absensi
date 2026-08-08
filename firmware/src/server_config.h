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

// true = Supabase cloud (/rest/v1/ + API key). false = Laravel lokal (satu URL, key kosong).
bool useRestApi();

// URL dasar heartbeat — sama dengan Server URL saat mode Laravel lokal.
String heartbeatBaseUrl();

bool hasStoredConfig();

bool save(const String &url, const String &key, const String &code, const String &dashboardUrl);

} // namespace server_config
