#include "dashboard_client.h"
#include "attendance_rules.h"
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

bool evaluateAttendance(const String &employeeId, const String &attendanceType,
                          time_t eventTime, time_t breakStartTime,
                          attendance_rules::AttendanceIndicator &out,
                          bool &allowedOut, String &scheduleNameOut) {
    allowedOut = true;
    scheduleNameOut = "";

    String baseUrl = server_config::heartbeatBaseUrl();
    if (baseUrl.length() == 0) {
        return false;
    }

    const bool useTls = baseUrl.startsWith("https://");
    String url = baseUrl + "/api/device/evaluate-attendance";

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
    doc["employee_id"] = employeeId;
    doc["attendance_type"] = attendanceType;

    char timeBuf[32];
    struct tm *tmInfo = gmtime(&eventTime);
    strftime(timeBuf, sizeof(timeBuf), "%Y-%m-%dT%H:%M:%SZ", tmInfo);
    doc["event_time"] = timeBuf;

    if (breakStartTime > 0) {
        struct tm *breakTm = gmtime(&breakStartTime);
        char breakBuf[32];
        strftime(breakBuf, sizeof(breakBuf), "%Y-%m-%dT%H:%M:%SZ", breakTm);
        doc["break_start_time"] = breakBuf;
    }

    String body;
    serializeJson(doc, body);

    int code = http.POST(body);
    if (code != 200) {
        Serial.print(F("[dashboard] evaluate FAIL code="));
        Serial.print(code);
        Serial.print(F(" url="));
        Serial.println(url);
        http.end();
        return false;
    }

    String response = http.getString();
    http.end();

    JsonDocument respDoc;
    if (deserializeJson(respDoc, response) != DeserializationError::Ok) {
        return false;
    }

    if (!respDoc["ok"].as<bool>()) {
        return false;
    }

    allowedOut = respDoc["allowed"].isNull() ? true : respDoc["allowed"].as<bool>();
    if (!respDoc["schedule_name"].isNull()) {
        scheduleNameOut = respDoc["schedule_name"].as<String>();
    }

    String level = respDoc["level"].as<String>();
    if (level == "rejected" || !allowedOut) {
        out.level = attendance_rules::IndicatorLevel::Warning;
    } else if (level == "warning") {
        out.level = attendance_rules::IndicatorLevel::Warning;
    } else if (level == "info") {
        out.level = attendance_rules::IndicatorLevel::Info;
    } else {
        out.level = attendance_rules::IndicatorLevel::Ok;
    }

  out.barText = respDoc["bar_text"].as<String>();
  return true;
}

} // namespace dashboard_client
