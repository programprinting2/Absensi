#pragma once

namespace buzzer_handler {

void begin();

// Absen berhasil — 1 beep panjang (~1,5 detik).
void beepSuccess();

// Absen gagal — 3x beep pendek.
void beepFail();

// Klik tombol keypad — beep sangat pendek.
void beepKey();

} // namespace buzzer_handler
