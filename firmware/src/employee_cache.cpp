#include "employee_cache.h"
#include "supabase_client.h"
#include <ArduinoJson.h>
#include <SPIFFS.h>
#include <mbedtls/md.h>

namespace employee_cache {

namespace {

const char *EMPLOYEES_CACHE_PATH = "/employees_cache.json";
const char *TEMPLATES_CACHE_PATH = "/fp_templates_cache.json";

JsonDocument employeesDoc;
JsonDocument templatesDoc;
bool loaded = false;

String hmacSha256Hex(const String &key, const String &message) {
    unsigned char hmacResult[32];

    mbedtls_md_context_t ctx;
    mbedtls_md_init(&ctx);
    mbedtls_md_setup(&ctx, mbedtls_md_info_from_type(MBEDTLS_MD_SHA256), 1 /* hmac */);
    mbedtls_md_hmac_starts(&ctx, (const unsigned char *)key.c_str(), key.length());
    mbedtls_md_hmac_update(&ctx, (const unsigned char *)message.c_str(), message.length());
    mbedtls_md_hmac_finish(&ctx, hmacResult);
    mbedtls_md_free(&ctx);

    char hex[65];
    for (int i = 0; i < 32; i++) {
        snprintf(hex + i * 2, 3, "%02x", hmacResult[i]);
    }
    return String(hex);
}

bool loadJsonFile(const char *path, JsonDocument &doc) {
    if (!SPIFFS.exists(path)) {
        return false;
    }
    File file = SPIFFS.open(path, FILE_READ);
    if (!file) {
        return false;
    }
    DeserializationError err = deserializeJson(doc, file);
    file.close();
    return err == DeserializationError::Ok;
}

void saveJsonText(const char *path, const String &json) {
    File file = SPIFFS.open(path, FILE_WRITE);
    if (!file) {
        return;
    }
    file.print(json);
    file.close();
}

JsonArray employeeArray() {
    return employeesDoc.as<JsonArray>();
}

JsonArray templateArray() {
    return templatesDoc.as<JsonArray>();
}

void fillEmployee(JsonObject obj, Employee &out) {
    out.id = obj["id"].as<String>();
    out.employeeCode = obj["employee_code"].as<int>();
    out.fullName = obj["full_name"].as<String>();
    String username = obj["username"].as<String>();
    if (username.length() == 0) {
        out.displayName = out.fullName;
    } else {
        username.toUpperCase();
        out.displayName = username;
    }
    out.hasPin = !obj["pin_hash"].isNull();
}

} // namespace

void begin() {
    loaded = loadJsonFile(EMPLOYEES_CACHE_PATH, employeesDoc);
    loadJsonFile(TEMPLATES_CACHE_PATH, templatesDoc);
}

void refresh(const String &deviceId) {
    String employeesJson;
    if (supabase_client::fetchActiveEmployees(employeesJson)) {
        JsonDocument fresh;
        if (deserializeJson(fresh, employeesJson) == DeserializationError::Ok) {
            employeesDoc = fresh;
            saveJsonText(EMPLOYEES_CACHE_PATH, employeesJson);
            loaded = true;
        }
    }

    String templatesJson;
    if (supabase_client::fetchFingerprintTemplates(deviceId, templatesJson)) {
        JsonDocument fresh;
        if (deserializeJson(fresh, templatesJson) == DeserializationError::Ok) {
            templatesDoc = fresh;
            saveJsonText(TEMPLATES_CACHE_PATH, templatesJson);
        }
    }
}

bool isLoaded() {
    return loaded;
}

bool findByEmployeeCode(int code, Employee &out) {
    for (JsonObject obj : employeeArray()) {
        if (obj["employee_code"].as<int>() == code) {
            fillEmployee(obj, out);
            return true;
        }
    }
    return false;
}

bool findByEmployeeId(const String &id, Employee &out) {
    for (JsonObject obj : employeeArray()) {
        if (obj["id"].as<String>() == id) {
            fillEmployee(obj, out);
            return true;
        }
    }
    return false;
}

bool isSlotUsed(int slotId) {
    for (JsonObject obj : templateArray()) {
        if (obj["fingerprint_slot_id"].as<int>() == slotId) {
            return true;
        }
    }
    return false;
}

int nextFreeSlot(int maxSlots) {
    if (maxSlots < 1) {
        return -1;
    }
    for (int slot = 1; slot <= maxSlots; slot++) {
        if (!isSlotUsed(slot)) {
            return slot;
        }
    }
    return -1;
}

bool findBySlotId(int slotId, Employee &out) {
    String employeeId;
    for (JsonObject obj : templateArray()) {
        if (obj["fingerprint_slot_id"].as<int>() == slotId) {
            employeeId = obj["employee_id"].as<String>();
            break;
        }
    }
    if (employeeId.length() == 0) {
        return false;
    }

    for (JsonObject obj : employeeArray()) {
        if (obj["id"].as<String>() == employeeId) {
            fillEmployee(obj, out);
            return true;
        }
    }
    return false;
}

bool findSlotForEmployee(const String &employeeId, int &outSlotId) {
    if (employeeId.length() == 0) {
        return false;
    }
    for (JsonObject obj : templateArray()) {
        if (obj["employee_id"].as<String>() == employeeId) {
            outSlotId = obj["fingerprint_slot_id"].as<int>();
            return true;
        }
    }
    return false;
}

bool verifyPin(const String &employeeId, const String &pin) {
    for (JsonObject obj : employeeArray()) {
        if (obj["id"].as<String>() != employeeId) {
            continue;
        }
        if (obj["pin_hash"].isNull()) {
            return false;
        }
        String salt = obj["pin_salt"].as<String>();
        String expectedHash = obj["pin_hash"].as<String>();
        return hmacSha256Hex(salt, pin).equalsIgnoreCase(expectedHash);
    }
    return false;
}

} // namespace employee_cache
