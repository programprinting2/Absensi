#include "boot_session.h"
#include <Preferences.h>

namespace boot_session {

namespace {

Preferences prefs;
uint32_t currentBootId = 0;
bool loaded = false;

} // namespace

void begin() {
    if (loaded) {
        return;
    }

    if (prefs.begin("boot_sess", false)) {
        currentBootId = prefs.getUInt("id", 0) + 1;
        prefs.putUInt("id", currentBootId);
        prefs.end();
    }

    loaded = true;
    Serial.print(F("[boot] session id="));
    Serial.println(currentBootId);
}

uint32_t id() {
    if (!loaded) {
        begin();
    }
    return currentBootId;
}

} // namespace boot_session
