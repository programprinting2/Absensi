#pragma once

#include <Arduino.h>

namespace wifi_manager {

void begin();
void loop();
bool isConnected();

// True selama access point konfigurasi (captive portal) sedang aktif —
// dipakai lcd_ui buat nampilin instruksi setup ke user.
bool isConfigPortalActive();

// Buka ulang portal konfigurasi secara manual (mis. dipicu kombinasi
// tombol keypad), buat ganti WiFi tanpa reflash firmware.
void startConfigPortal();

// Tutup portal tanpa restart — device kembali ke WiFi tersimpan.
void stopConfigPortal();

// Nama WiFi yang sedang/tersambung terakhir (kosong kalau belum konek).
String currentSSID();

// IP device di jaringan saat ini ("0.0.0.0" kalau belum konek).
String localIPString();

} // namespace wifi_manager
