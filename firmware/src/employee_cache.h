#pragma once

#include <Arduino.h>

namespace employee_cache {

struct Employee {
    String id; // uuid
    int employeeCode = 0;
    String fullName;
    String displayName;
    bool hasPin = false;
};

// Muat cache terakhir dari SPIFFS (kalau ada) supaya tetap bisa dipakai
// offline sebelum sempat refresh dari server.
void begin();

// Ambil daftar karyawan aktif + mapping sidik jari milik device ini dari
// Supabase, lalu simpan ke SPIFFS. Panggil berkala saat WiFi tersambung.
void refresh(const String &deviceId);

bool isLoaded();

bool findByEmployeeCode(int code, Employee &out);
bool findByEmployeeId(const String &id, Employee &out);
bool findBySlotId(int slotId, Employee &out);

// True jika karyawan sudah punya mapping sidik jari di cache device ini.
bool findSlotForEmployee(const String &employeeId, int &outSlotId);

// Slot sidik jari terendah (1..maxSlots) yang belum dipetakan di cache device
// ini. Jangan scan flash sensor pakai loadModel() — modul ZW111 klon tidak
// andal untuk command itu dan bisa bikin UART macet.
int nextFreeSlot(int maxSlots);

// Verifikasi PIN (plain, dari keypad) terhadap hash tersimpan milik employeeId.
bool verifyPin(const String &employeeId, const String &pin);

} // namespace employee_cache
