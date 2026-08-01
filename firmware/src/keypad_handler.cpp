#include "keypad_handler.h"
#include "buzzer_handler.h"
#include "config.h"
#include <Keypad.h>

namespace keypad_handler {

namespace {
const byte ROWS = 4;
const byte COLS = 4;

char keys[ROWS][COLS] = {
    {'1', '2', '3', 'A'},
    {'4', '5', '6', 'B'},
    {'7', '8', '9', 'C'},
    {'*', '0', '#', 'D'},
};

byte rowPins[ROWS] = KEYPAD_ROW_PINS;
byte colPins[COLS] = KEYPAD_COL_PINS;

Keypad keypad = Keypad(makeKeymap(keys), rowPins, colPins, ROWS, COLS);
} // namespace

void begin() {
    keypad.setDebounceTime(15);
}

char poll() {
    char key = keypad.getKey();
    if (key != NO_KEY) {
        buzzer_handler::beepKey();
    }
    return key;
}

} // namespace keypad_handler
