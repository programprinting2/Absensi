#include "dashboard_client.h"
#include "server_config.h"
#include "wifi_manager.h"
#include <ArduinoJson.h>
#include <HTTPClient.h>
#include <WiFiClient.h>
#include <WiFiClientSecure.h>

namespace dashboard_client {

namespace {

const unsigned long HTTP_TIMEOUT_MS = 8000;

bool beginHttp(HTTPClient &http, WiFiClient &plainClient, WiFiClientSecure &secureClient,
               const String &url, bool useTls) {
    if (useTls) {
        secureClient.setInsecure();
        return http.begin(secureClient, url);
    }
    return http.begin(plainClient, url);
}

} // namespace

bool sendHeartbeat(int fingerprintCapacity) {
    String baseUrl = server_config::heartbeatBaseUrl();
    if (baseUrl.length() == 0) {
        return false;
    }

    const bool useTls = baseUrl.startsWith("https://");
    String url = baseUrl + "/api/device/heartbeat";

    WiFiClient plainClient;
    WiFiClientSecure secureClient;
    HTTPClient http;
    if (!beginHttp(http, plainClient, secureClient, url, useTls)) {
        return false;
    }

    http.setTimeout(HTTP_TIMEOUT_MS);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Accept", "application/json");

    JsonDocument doc;
    doc["device_code"] = server_config::deviceCode();
    if (fingerprintCapacity > 0) {
        doc["fingerprint_capacity"] = fingerprintCapacity;
    }

    const String localIp = wifi_manager::localIPString();
    if (localIp.length() > 0 && localIp != "0.0.0.0") {
        doc["local_ip"] = localIp;
    }

    String body;
    serializeJson(doc, body);

    int code = http.POST(body);
    if (code == 200) {
        Serial.print(F("[dashboard] heartbeat OK -> "));
        Serial.println(url);
    } else {
        Serial.print(F("[dashboard] heartbeat FAIL code="));
        Serial.print(code);
        Serial.print(F(" url="));
        Serial.println(url);
    }
    http.end();

    return code == 200;
}

} // namespace dashboard_client
