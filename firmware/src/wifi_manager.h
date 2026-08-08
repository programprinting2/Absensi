#pragma once

#include <Arduino.h>

namespace wifi_manager {

void begin();
void loop();
bool isConnected();

// True selama access point konfigurasi (captive portal AP) sedang aktif —
// dipakai lcd_ui buat nampilin instruksi setup ke user.
bool isConfigPortalActive();

// True selama web portal WiFiManager aktif di IP lokal (STA mode, jaringan kantor).
bool isWebPortalActive();

// Buka portal AP Absensi-Setup (untuk setup via HP — *+1 / perintah start_wifi_portal).
void startApConfigPortal();

// Alias legacy — sama dengan startApConfigPortal.
void startConfigPortal();

// Tutup portal tanpa restart — device kembali ke WiFi tersimpan.
void stopConfigPortal();

// Nama WiFi yang sedang/tersambung terakhir (kosong kalau belum konek).
String currentSSID();

// IP device di jaringan saat ini ("0.0.0.0" kalau belum konek).
String localIPString();

} // namespace wifi_manager
