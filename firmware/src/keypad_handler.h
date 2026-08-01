#pragma once

#include <Arduino.h>

namespace keypad_handler {

void begin();

// Mengembalikan karakter yang ditekan, atau '\0' jika tidak ada.
char poll();

} // namespace keypad_handler
