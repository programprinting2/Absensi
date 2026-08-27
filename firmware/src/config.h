#pragma once

// Ganti nilai-nilai ini sebelum flashing ke device.

// WiFi diatur lewat WiFiManager (captive portal), bukan hardcode di sini.
// Saat device belum pernah disetel (atau kredensial direset), device akan
// membuka WiFi access point sendiri dengan nama & password di bawah ini —
// sambungkan HP ke AP tersebut, browser akan otomatis diarahkan ke halaman
// setup untuk memilih WiFi kantor & memasukkan passwordnya.
#define WIFI_AP_NAME "Absensi-Setup"
#define WIFI_AP_PASSWORD "123456789" // min. 8 karakter, dipakai buat proteksi AP setup
#define WIFI_PORTAL_TIMEOUT_SEC 180

// Default server API — dipakai saat first boot (belum pernah setup portal).
// Setelah setup lewat WiFiManager, nilai tersimpan di NVS dan bisa diganti
// tanpa reflash (Supabase cloud atau PostgREST lokal).
// Default mode lokal — Laravel (satu URL untuk data + heartbeat). Ganti lewat WiFi Manager tanpa reflash.
#define SUPABASE_URL "http://192.168.100.249:8008"
#define SUPABASE_ANON_KEY ""
#define DEVICE_CODE "DEV-001"

// Mode Supabase (cloud) — isi lewat WiFi Manager jika perlu:
// URL: https://kjpkphjmulnxsyyahxet.supabase.co
// KEY: sb_publishable_KSO_D-4nAnuncrkYtMMhBA_iIfu-Spb

#define DEFAULT_DASHBOARD_URL SUPABASE_URL

// Pin fingerprint ZW111 (UART2). BUKAN P16/P17 — pin itu dipakai TFT
// (jalur tetap di expansion board), fingerprint dipindah ke sini karena
// dia nyambung pakai kabel bebas jadi gampang dipindah.
#define FINGERPRINT_RX_PIN 19
#define FINGERPRINT_TX_PIN 22

// Match di sensor dengan confidence di bawah ini dianggap gagal (menyaring
// template "hantu" di modul ZW111 klon).
#define MIN_MATCH_CONFIDENCE 35

// Pin TFT ST7735 — FIXED oleh jalur PCB expansion board, jangan diubah
// kecuali memang pindah ke expansion board lain.
#define TFT_CS_PIN 5
#define TFT_DC_PIN 17
#define TFT_RST_PIN 16
#define TFT_MOSI_PIN 23
#define TFT_SCK_PIN 18
#define TFT_BL_PIN 4 // backlight, harus di-drive HIGH

// Pin keypad matrix 4x4
#define KEYPAD_ROW_PINS { 32, 33, 25, 26 }
#define KEYPAD_COL_PINS { 27, 14, 12, 13 }

// Pin buzzer (2 pin: sinyal + GND). Modul active = bunyi sendiri saat diberi
// tegangan; passive = butuh sinyal frekuensi (lebih keras pakai tone/PWM).
#define BUZZER_PIN 21
#define BUZZER_USE_TONE true       // true = LEDC PWM (passive, lebih keras); false = active buzzer ON/OFF
#define BUZZER_FREQ_HZ 1200        // nada lebih rendah
#define BUZZER_LEDC_DUTY 900       // ~88% duty cycle supaya lebih keras (resolusi 10-bit)
#define BUZZER_SUCCESS_MS 750      // sukses: 1 beep (50% dari 1,5 detik)
#define BUZZER_FAIL_BEEP_MS 120    // gagal: 3x beep pendek
#define BUZZER_FAIL_GAP_MS 120
#define BUZZER_FAIL_COUNT 3
#define BUZZER_KEY_MS 120          // klik tombol keypad: sekali beep, durasi sama seperti beep gagal

#define NTP_SERVER "pool.ntp.org"
#define GMT_OFFSET_SEC (7 * 3600) // WIB
#define DAYLIGHT_OFFSET_SEC 0

#define SYNC_INTERVAL_MS 30000
// Refresh teks LCD + jadwal kerja (ringan) — dipisah dari cache karyawan.
#define DEVICE_CONFIG_REFRESH_MS 30000
// Polling command jalan di core 0 (network_task), tidak menahan UI —
// jadi aman dipercepat supaya tombol "Tambah Sidik Jari" di Laravel
// terasa langsung direspon device.
#define COMMAND_POLL_INTERVAL_MS 2000
