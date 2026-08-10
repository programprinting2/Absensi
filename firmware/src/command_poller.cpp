#include "command_poller.h"
#include "config.h"
#include "employee_cache.h"
#include "supabase_client.h"
#include "wifi_manager.h"
#include <ArduinoJson.h>

namespace command_poller {

namespace {
String deviceId;
unsigned long lastPollMs = 0;
} // namespace

void begin(const String &deviceIdIn) {
    deviceId = deviceIdIn;
}

bool poll(PendingCommand &outCommand) {
    unsigned long now = millis();
    if (now - lastPollMs < COMMAND_POLL_INTERVAL_MS) {
        return false;
    }
    lastPollMs = now;

    if (!wifi_manager::isConnected()) {
        return false;
    }

    String json;
    if (!supabase_client::fetchPendingCommands(deviceId, json)) {
        return false;
    }

    JsonDocument doc;
    if (deserializeJson(doc, json) != DeserializationError::Ok) {
        return false;
    }

    JsonArray arr = doc.as<JsonArray>();
    if (arr.size() == 0) {
        return false;
    }

    JsonObject first = arr[0];
    String type = first["command_type"].as<String>();

    if (type != "enroll_fingerprint" && type != "delete_fingerprint" && type != "start_wifi_portal") {
        return false; // tipe command lain belum diimplementasikan di firmware ini
    }

    outCommand.id = first["id"].as<String>();
    outCommand.commandType = type;
    outCommand.fingerprintSlotId = -1;

    if (type == "start_wifi_portal") {
        return true;
    }

    if (type == "enroll_fingerprint") {
        outCommand.employeeId = first["payload"]["employee_id"].as<String>();
        outCommand.employeeCode = first["payload"]["employee_code"] | 0;
        employee_cache::Employee employee;
        if (employee_cache::findByEmployeeCode(outCommand.employeeCode, employee)) {
            outCommand.employeeName = employee.fullName;
        } else if (employee_cache::findByEmployeeId(outCommand.employeeId, employee)) {
            outCommand.employeeName = employee.fullName;
            outCommand.employeeCode = employee.employeeCode;
        } else {
            outCommand.employeeName = "";
        }
    } else { // delete_fingerprint
        outCommand.fingerprintSlotId = first["payload"]["fingerprint_slot_id"] | -1;
        employee_cache::Employee employee;
        if (employee_cache::findBySlotId(outCommand.fingerprintSlotId, employee)) {
            outCommand.employeeName = employee.fullName;
            outCommand.employeeCode = employee.employeeCode;
        }
    }

    return true;
}

bool markInProgress(const String &commandId) {
    return supabase_client::updateCommandStatus(commandId, "in_progress", "");
}

void markCompleted(const String &commandId, int slotId) {
    JsonDocument doc;
    doc["fingerprint_slot_id"] = slotId;
    String result;
    serializeJson(doc, result);
    supabase_client::updateCommandStatus(commandId, "completed", result);
}

void markCompletedSimple(const String &commandId) {
    supabase_client::updateCommandStatus(commandId, "completed", "");
}

void markFailed(const String &commandId, const String &reason) {
    JsonDocument doc;
    doc["error"] = reason;
    String result;
    serializeJson(doc, result);
    supabase_client::updateCommandStatus(commandId, "failed", result);
}

} // namespace command_poller
