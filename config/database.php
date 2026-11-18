<?php

$localConfigPath = __DIR__ . "/database.local.php";

if (!is_file($localConfigPath)) {
    error_log("Konfigurasi basis data lokal tidak ditemukan.");
    http_response_code(500);
    exit("Konfigurasi basis data belum tersedia.");
}

require_once $localConfigPath;

if (!isset($conn) || !$conn) {
    error_log("Koneksi basis data tidak tersedia.");
    http_response_code(500);
    exit("Gagal menghubungkan ke basis data.");
}