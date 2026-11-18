<?php

$localConfigPath = __DIR__ . "/midtrans.local.php";

if (!is_file($localConfigPath)) {
    error_log("Konfigurasi Midtrans lokal tidak ditemukan.");
    http_response_code(500);
    exit("Konfigurasi pembayaran belum tersedia.");
}

$midtransConfig = require $localConfigPath;

if (
    !is_array($midtransConfig) ||
    empty($midtransConfig["server_key"])
) {
    error_log("Konfigurasi Midtrans tidak lengkap.");
    http_response_code(500);
    exit("Konfigurasi pembayaran tidak valid.");
}

return $midtransConfig;