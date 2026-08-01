#pragma once

enum class AppState {
    Boot,
    WifiConnecting,
    Idle,        // nampilin mode aktif (A/B/C/D), tempel jari atau tekan # buat ID+PIN
    InputId,
    InputPin,
    ShowResult,
    EnrollMode,
    Menu,
};

enum class AttendanceMethod {
    Fingerprint,
    Pin,
};

// Harus sama persis dengan nilai kolom attendance_type di Supabase.
// Dipilih langsung oleh karyawan via keypad: A=Masuk, B=Istirahat, C=Kembali, D=Pulang.
enum class AttendanceType {
    ClockIn,
    BreakStart,
    BreakEnd,
    ClockOut,
};

inline const char *attendanceTypeToString(AttendanceType type) {
    switch (type) {
        case AttendanceType::ClockIn: return "clock_in";
        case AttendanceType::BreakStart: return "break_start";
        case AttendanceType::BreakEnd: return "break_end";
        case AttendanceType::ClockOut: return "clock_out";
    }
    return "clock_in";
}

inline const char *attendanceTypeToLabel(AttendanceType type) {
    switch (type) {
        case AttendanceType::ClockIn: return "MASUK";
        case AttendanceType::BreakStart: return "ISTIRAHAT";
        case AttendanceType::BreakEnd: return "KEMBALI";
        case AttendanceType::ClockOut: return "PULANG";
    }
    return "MASUK";
}

// Return true jika key adalah tombol mode (A/B/C/D) dan isi outType.
inline bool attendanceTypeFromModeKey(char key, AttendanceType &outType) {
    switch (key) {
        case 'A': outType = AttendanceType::ClockIn; return true;
        case 'B': outType = AttendanceType::BreakStart; return true;
        case 'C': outType = AttendanceType::BreakEnd; return true;
        case 'D': outType = AttendanceType::ClockOut; return true;
        default: return false;
    }
}
