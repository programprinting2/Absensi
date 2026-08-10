#pragma once

#include <Arduino.h>

namespace boot_session {

// Dipanggil sekali saat boot — increment ID sesi di NVS.
void begin();

uint32_t id();

} // namespace boot_session
