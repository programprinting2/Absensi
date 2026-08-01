#include "supabase_client.h"
#include "config.h"
#include <ArduinoJson.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>

namespace supabase_client {

namespace {

const unsigned long HTTP_TIMEOUT_MS = 8000;

void applyCommonHeaders(HTTPClient &http, bool preferReturn = false) {
    http.setTimeout(HTTP_TIMEOUT_MS);
    http.addHeader("apikey", SUPABASE_ANON_KEY);
    http.addHeader("Authorization", String("Bearer ") + SUPABASE_ANON_KEY);
    http.addHeader("Content-Type", "application/json");
    if (preferReturn) {
        http.addHeader("Prefer", "return=minimal");
    }
}

String restUrl(const String &path) {
    return String(SUPABASE_URL) + "/rest/v1/" + path;
}

void logResult(const char *label, const String &url, int code) {
    Serial.print(F("[supabase] "));
    Serial.print(label);
    Serial.print(F(" -> code="));
    Serial.print(code);
    if (code < 0) {
        Serial.print(F(" ("));
        Serial.print(HTTPClient::errorToString(code));
        Serial.print(F(")"));
    }
    Serial.print(F(" url="));
    Serial.println(url);
}

} // namespace

InsertResult insertAttendanceLog(const String &deviceId, const String &employeeId,
                                  const String &attendanceType, const String &method,
                                  time_t eventTime, bool isOfflineCapture,
                                  const String &clientUuid, int *httpCodeOut) {
    if (httpCodeOut) {
        *httpCodeOut = -1;
    }

    WiFiClientSecure client;
    client.setInsecure(); // TODO: pin root CA untuk produksi

    HTTPClient http;
    String url = restUrl("attendance_logs");
    http.begin(client, url);
    applyCommonHeaders(http, true);

    JsonDocument doc;
    doc["device_id"] = deviceId;
    doc["employee_id"] = employeeId;
    doc["attendance_type"] = attendanceType;
    doc["method"] = method;

    char timeBuf[32];
    struct tm *tmInfo = gmtime(&eventTime);
    strftime(timeBuf, sizeof(timeBuf), "%Y-%m-%dT%H:%M:%SZ", tmInfo);
    doc["event_time"] = timeBuf;
    doc["is_offline_capture"] = isOfflineCapture;
    doc["client_uuid"] = clientUuid;

    String body;
    serializeJson(doc, body);

    int code = http.POST(body);
    if (httpCodeOut) {
        *httpCodeOut = code;
    }
    logResult("insertAttendanceLog", url, code);
    if (code < 0 || code >= 400) {
        Serial.print(F("[supabase] request: "));
        Serial.println(body);
        Serial.print(F("[supabase] response body: "));
        Serial.println(http.getString());
    }
    http.end();

    if (code == 201 || code == 200) {
        return InsertResult::Success;
    }
    if (code == 409) {
        return InsertResult::DuplicateIgnored;
    }
    return InsertResult::Failed;
}

bool fetchActiveEmployees(String &outJson) {
    WiFiClientSecure client;
    client.setInsecure();

    HTTPClient http;
    String url = restUrl("employees?is_active=eq.true&select=id,employee_code,full_name,pin_salt,pin_hash");
    http.begin(client, url);
    applyCommonHeaders(http);

    int code = http.GET();
    logResult("fetchActiveEmployees", url, code);
    if (code == 200) {
        outJson = http.getString();
        http.end();
        return true;
    }
    http.end();
    return false;
}

bool fetchFingerprintTemplates(const String &deviceId, String &outJson) {
    WiFiClientSecure client;
    client.setInsecure();

    HTTPClient http;
    String url = restUrl("fingerprint_templates?device_id=eq." + deviceId + "&select=employee_id,fingerprint_slot_id");
    http.begin(client, url);
    applyCommonHeaders(http);

    int code = http.GET();
    logResult("fetchFingerprintTemplates", url, code);
    if (code == 200) {
        outJson = http.getString();
        http.end();
        return true;
    }
    http.end();
    return false;
}

bool fetchDeviceSettings(const String &deviceId, String &outJson) {
    auto tryGet = [&](const String &select) -> bool {
        WiFiClientSecure client;
        client.setInsecure();

        HTTPClient http;
        String url = restUrl("devices?id=eq." + deviceId + "&select=" + select);
        http.begin(client, url);
        applyCommonHeaders(http);

        int code = http.GET();
        logResult("fetchDeviceSettings", url, code);
        if (code == 200) {
            outJson = http.getString();
            http.end();
            return true;
        }
        http.end();
        return false;
    };

    // Coba lengkap dulu; fallback ke name saja kalau migration lcd_config belum jalan.
    if (tryGet("name,lcd_config")) {
        return true;
    }
    return tryGet("name");
}

bool fetchActiveWorkSchedule(String &outJson) {
    WiFiClientSecure client;
    client.setInsecure();

    HTTPClient http;
    String url = restUrl("work_schedules?is_active=eq.true&select=clock_in_time,clock_out_time,break_duration_minutes");
    http.begin(client, url);
    applyCommonHeaders(http);

    int code = http.GET();
    logResult("fetchActiveWorkSchedule", url, code);
    if (code == 200) {
        outJson = http.getString();
        http.end();
        return true;
    }
    http.end();
    return false;
}

bool fetchPendingCommands(const String &deviceId, String &outJson) {
    WiFiClientSecure client;
    client.setInsecure();

    HTTPClient http;
    String url = restUrl("device_commands?device_id=eq." + deviceId + "&status=eq.pending&select=*");
    http.begin(client, url);
    applyCommonHeaders(http);

    int code = http.GET();
    logResult("fetchPendingCommands", url, code);
    if (code == 200) {
        outJson = http.getString();
        http.end();
        return true;
    }
    http.end();
    return false;
}

bool updateCommandStatus(const String &commandId, const String &status, const String &resultJson) {
    WiFiClientSecure client;
    client.setInsecure();

    HTTPClient http;
    String url = restUrl("device_commands?id=eq." + commandId);
    http.begin(client, url);
    applyCommonHeaders(http, true);
    http.addHeader("X-HTTP-Method-Override", "PATCH");

    JsonDocument doc;
    doc["status"] = status;
    if (resultJson.length() > 0) {
        JsonDocument resultDoc;
        deserializeJson(resultDoc, resultJson);
        doc["result"] = resultDoc;
    }
    doc["updated_at"] = "now()";

    String body;
    serializeJson(doc, body);

    int code = http.sendRequest("PATCH", body);
    logResult("updateCommandStatus", url, code);
    http.end();

    return code == 200 || code == 204;
}

bool insertFingerprintTemplate(const String &employeeId, const String &deviceId, int slotId) {
    WiFiClientSecure client;
    client.setInsecure();

    HTTPClient http;
    String url = restUrl("fingerprint_templates");
    http.begin(client, url);
    applyCommonHeaders(http, true);

    JsonDocument doc;
    doc["employee_id"] = employeeId;
    doc["device_id"] = deviceId;
    doc["fingerprint_slot_id"] = slotId;

    String body;
    serializeJson(doc, body);

    int code = http.POST(body);
    logResult("insertFingerprintTemplate", url, code);
    if (code < 0 || code >= 400) {
        Serial.print(F("[supabase] response body: "));
        Serial.println(http.getString());
    }
    http.end();

    return code == 201 || code == 200;
}

bool getDeviceIdByCode(const String &deviceCode, String &outDeviceId) {
    WiFiClientSecure client;
    client.setInsecure();

    HTTPClient http;
    String url = restUrl("devices?device_code=eq." + deviceCode + "&select=id");
    http.begin(client, url);
    applyCommonHeaders(http);

    int code = http.GET();
    logResult("getDeviceIdByCode", url, code);
    if (code != 200) {
        http.end();
        return false;
    }

    String payload = http.getString();
    http.end();

    JsonDocument doc;
    if (deserializeJson(doc, payload) != DeserializationError::Ok) {
        Serial.println(F("[supabase] getDeviceIdByCode: gagal parse JSON"));
        return false;
    }
    JsonArray arr = doc.as<JsonArray>();
    if (arr.size() == 0) {
        Serial.println(F("[supabase] getDeviceIdByCode: device_code tidak ditemukan"));
        return false;
    }
    outDeviceId = arr[0]["id"].as<String>();
    return true;
}

bool updateDeviceStatus(const String &deviceId, int fingerprintCapacity) {
    WiFiClientSecure client;
    client.setInsecure();

    HTTPClient http;
    String url = restUrl("devices?id=eq." + deviceId);
    http.begin(client, url);
    applyCommonHeaders(http, true);
    http.addHeader("X-HTTP-Method-Override", "PATCH");

    JsonDocument doc;
    doc["last_seen_at"] = "now()";
    if (fingerprintCapacity > 0) {
        doc["fingerprint_capacity"] = fingerprintCapacity;
    }

    String body;
    serializeJson(doc, body);

    int code = http.sendRequest("PATCH", body);
    logResult("updateDeviceStatus", url, code);
    http.end();

    return code == 200 || code == 204;
}

} // namespace supabase_client
