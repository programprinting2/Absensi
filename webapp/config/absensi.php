<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WiFi setup portal (ESP32 WiFiManager)
    |--------------------------------------------------------------------------
    |
    | Harus selaras dengan WIFI_AP_NAME, WIFI_AP_PASSWORD di firmware/config.h
    |
    */

    'wifi_ap_name' => env('DEVICE_WIFI_AP_NAME', 'Absensi-Setup'),
    'wifi_ap_password' => env('DEVICE_WIFI_AP_PASSWORD', '123456789'),
    'wifi_portal_url' => env('DEVICE_WIFI_PORTAL_URL', 'http://192.168.4.1'),

];
