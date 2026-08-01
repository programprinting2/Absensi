#include "fingerprint_handler.h"
#include "config.h"
#include <Adafruit_Fingerprint.h>
#include <HardwareSerial.h>

namespace fingerprint_handler {

namespace {
HardwareSerial fingerSerial(2);
Adafruit_Fingerprint finger(&fingerSerial);
const int MAX_SLOTS = 200; // sesuaikan dengan kapasitas modul ZW111 yang dipakai
bool sensorReady = false;
} // namespace

namespace {

// Buang sisa byte di buffer UART. Setelah upload firmware (ESP32 reset tapi
// sensor tetap menyala), sering ada potongan frame lama yang menyangkut —
// kalau tidak dibuang, semua balasan sensor jadi ngawur/desync.
void drainSerial() {
    unsigned long start = millis();
    while (fingerSerial.available() && millis() - start < 200) {
        fingerSerial.read();
    }
}

// Coba satu kombinasi pin+baud, return true kalau sensor benar-benar menjawab.
bool probe(int rxPin, int txPin, uint32_t baud) {
    fingerSerial.end();
    delay(50);
    fingerSerial.begin(baud, SERIAL_8N1, rxPin, txPin);
    finger.begin(baud);
    delay(100);
    drainSerial();

    // Coba 2x — percobaan pertama sering gagal kalau sensor baru bangun.
    if (finger.verifyPassword()) {
        return true;
    }
    delay(100);
    drainSerial();
    return finger.verifyPassword();
}

const char *errorName(int code) {
    switch (code) {
        case FINGERPRINT_OK: return "OK";
        case FINGERPRINT_PACKETRECIEVEERR: return "PACKET_RECV_ERR (komunikasi kacau)";
        case FINGERPRINT_NOFINGER: return "NOFINGER";
        case FINGERPRINT_IMAGEFAIL: return "IMAGEFAIL (gagal ambil citra)";
        case FINGERPRINT_IMAGEMESS: return "IMAGEMESS (citra kotor/buram)";
        case FINGERPRINT_FEATUREFAIL: return "FEATUREFAIL (fitur jari tak terbaca)";
        case FINGERPRINT_INVALIDIMAGE: return "INVALIDIMAGE (citra tak valid)";
        case FINGERPRINT_ENROLLMISMATCH: return "ENROLLMISMATCH (2 citra beda jari)";
        case FINGERPRINT_BADLOCATION: return "BADLOCATION (slot tidak valid)";
        case FINGERPRINT_FLASHERR: return "FLASHERR (gagal tulis flash sensor)";
        case FINGERPRINT_TIMEOUT: return "TIMEOUT (sensor tidak menjawab)";
        default: return "UNKNOWN";
    }
}

} // namespace

void begin() {
    // Urutan percobaan: konfigurasi resmi dulu, lalu kombinasi RX/TX tertukar
    // (kesalahan paling umum saat pindah kabel), lalu baud alternatif yang
    // dipakai sebagian modul klon.
    struct Attempt {
        int rx;
        int tx;
        uint32_t baud;
        const char *label;
    };

    const Attempt attempts[] = {
        {FINGERPRINT_RX_PIN, FINGERPRINT_TX_PIN, 57600, "pin sesuai config, 57600"},
        {FINGERPRINT_TX_PIN, FINGERPRINT_RX_PIN, 57600, "pin RX/TX TERTUKAR, 57600"},
        {FINGERPRINT_RX_PIN, FINGERPRINT_TX_PIN, 9600, "pin sesuai config, 9600"},
        {FINGERPRINT_TX_PIN, FINGERPRINT_RX_PIN, 9600, "pin RX/TX TERTUKAR, 9600"},
    };

    Serial.println(F("[fp] Mendeteksi sensor sidik jari..."));

    for (const Attempt &a : attempts) {
        Serial.print(F("[fp]   coba "));
        Serial.print(a.label);
        Serial.print(F(" (RX="));
        Serial.print(a.rx);
        Serial.print(F(" TX="));
        Serial.print(a.tx);
        Serial.print(F(") -> "));

        if (probe(a.rx, a.tx, a.baud)) {
            Serial.println(F("BERHASIL"));
            sensorReady = true;

            if (a.rx != FINGERPRINT_RX_PIN || a.baud != 57600) {
                Serial.println(F("[fp] !! Konfigurasi di config.h TIDAK cocok dengan wiring fisik."));
                Serial.print(F("[fp] !! Yang benar: FINGERPRINT_RX_PIN="));
                Serial.print(a.rx);
                Serial.print(F(" FINGERPRINT_TX_PIN="));
                Serial.print(a.tx);
                Serial.print(F(" baud="));
                Serial.println(a.baud);
            }

            finger.getParameters();
            Serial.print(F("[fp] capacity dari modul: "));
            Serial.println(finger.capacity);
            return;
        }

        Serial.println(F("gagal"));
    }

    sensorReady = false;
    Serial.println(F("[fp] SENSOR TIDAK TERDETEKSI di semua kombinasi."));
    Serial.println(F("[fp] Cek: kabel kuning/hitam terpasang?  3.3V & GND nyambung?"));
    Serial.println(F("[fp] Akan dicoba deteksi ulang otomatis tiap 10 detik."));
}

void retryDetectIfNeeded() {
    if (sensorReady) {
        return;
    }

    static unsigned long lastRetryMs = 0;
    unsigned long now = millis();
    if (lastRetryMs != 0 && now - lastRetryMs < 10000) {
        return;
    }
    lastRetryMs = now;

    // Cukup coba konfigurasi resmi saja — kombinasi lain sudah diuji saat boot.
    if (probe(FINGERPRINT_RX_PIN, FINGERPRINT_TX_PIN, 57600)) {
        sensorReady = true;
        finger.getParameters();
        Serial.print(F("[fp] Sensor pulih & terdeteksi. Capacity: "));
        Serial.println(finger.capacity);
    }
}

bool isReady() {
    return sensorReady;
}

int pollForMatch() {
    // Kalau sensor tidak terdeteksi saat boot, JANGAN panggil getImage():
    // library akan blocking ~1 detik menunggu balasan yang tak pernah datang,
    // dan karena ini dipanggil tiap loop(), keypad ikut nge-lag parah.
    if (!sensorReady) {
        return -1;
    }

    int p = finger.getImage();
    if (p == FINGERPRINT_NOFINGER) {
        return -1;
    }
    if (p != FINGERPRINT_OK) {
        // Balasan UART korup — coba pulihkan sekali supaya loop berikutnya
        // tidak menahan ~1 detik tiap iterasi (terasa "macet").
        if (p == FINGERPRINT_PACKETRECIEVEERR || p == FINGERPRINT_TIMEOUT) {
            recoverAfterError();
        }
        return -1;
    }
    if (finger.image2Tz(1) != FINGERPRINT_OK) {
        return -2; // jari terbaca tapi citra gagal diproses
    }
    if (finger.fingerSearch() != FINGERPRINT_OK) {
        return -2;
    }
    if (finger.confidence < MIN_MATCH_CONFIDENCE) {
        return -2; // cocok lemah / template sisa di flash sensor
    }
    return finger.fingerID;
}

void indicateFailure() {
    if (!sensorReady) {
        return;
    }
    // Modul bi-color / Aura: merah eksplisit (coba beberapa kali).
    for (int attempt = 0; attempt < 3; attempt++) {
        uint8_t r = finger.LEDcontrol(FINGERPRINT_LED_ON, 0, FINGERPRINT_LED_RED);
        if (r == FINGERPRINT_OK) {
            return;
        }
        delay(20);
        drainSerial();
    }
    // Modul LED tunggal: matikan saja — jangan LEDcontrol(true) karena bisa
    // tetap hijau (sisa state scan sukses di hardware).
    finger.LEDcontrol(false);
}

void resetLed() {
    if (!sensorReady) {
        return;
    }
    finger.LEDcontrol(FINGERPRINT_LED_OFF, 0, FINGERPRINT_LED_RED);
    finger.LEDcontrol(false);
}

bool captureImage(int bufferSlot, unsigned long timeoutMs) {
    unsigned long start = millis();
    int p = -1;

    drainSerial(); // pastikan tidak ada sisa frame lama yang bikin balasan desync

    while (p != FINGERPRINT_OK) {
        p = finger.getImage();
        if (p != FINGERPRINT_OK && p != FINGERPRINT_NOFINGER) {
            Serial.print(F("[fp] getImage gagal: "));
            Serial.println(errorName(p));
            return false; // gangguan komunikasi sensor
        }
        if (millis() - start > timeoutMs) {
            Serial.println(F("[fp] getImage timeout, jari tidak terdeteksi"));
            return false;
        }
        delay(50);
    }

    // PACKET_RECV_ERR biasanya gangguan sesaat di jalur UART, bukan citra
    // jari yang jelek — coba ulang beberapa kali sebelum menyerah.
    int tz = FINGERPRINT_PACKETRECIEVEERR;
    for (int attempt = 1; attempt <= 3; attempt++) {
        tz = finger.image2Tz(bufferSlot);
        if (tz != FINGERPRINT_PACKETRECIEVEERR) {
            break;
        }
        Serial.print(F("[fp] image2Tz percobaan "));
        Serial.print(attempt);
        Serial.println(F(" kena PACKET_RECV_ERR, ulangi..."));
        delay(80);
        drainSerial();
    }

    if (tz != FINGERPRINT_OK) {
        Serial.print(F("[fp] image2Tz(buffer "));
        Serial.print(bufferSlot);
        Serial.print(F(") gagal: "));
        Serial.println(errorName(tz));
    }
    return tz == FINGERPRINT_OK;
}

void waitForFingerLifted(unsigned long timeoutMs) {
    unsigned long start = millis();
    while (finger.getImage() != FINGERPRINT_NOFINGER) {
        if (millis() - start > timeoutMs) {
            return; // tetap lanjut walau belum sempat kedeteksi terangkat
        }
        delay(50);
    }
}

bool finalizeEnroll(int slotId) {
    int model = finger.createModel();
    if (model != FINGERPRINT_OK) {
        Serial.print(F("[fp] createModel gagal: "));
        Serial.println(errorName(model));
        return false;
    }

    Serial.print(F("[fp] createModel OK, simpan ke slot "));
    Serial.println(slotId);

    int store = finger.storeModel(slotId);
    if (store != FINGERPRINT_OK) {
        Serial.print(F("[fp] storeModel gagal: "));
        Serial.println(errorName(store));
    }
    return store == FINGERPRINT_OK;
}

bool deleteAtSlot(int slotId) {
    return finger.deleteModel(slotId) == FINGERPRINT_OK;
}

void recoverAfterError() {
    drainSerial();
    delay(50);
    if (!finger.verifyPassword()) {
        Serial.println(F("[fp] recover: sensor tidak merespon, coba deteksi ulang..."));
        sensorReady = false;
    }
}

uint16_t capacity() {
    return finger.capacity;
}

} // namespace fingerprint_handler
