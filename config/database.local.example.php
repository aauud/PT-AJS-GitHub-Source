<?php

$host = "localhost";
$port = "5432";
$dbname = "NAMA_DATABASE"; // isi dengan nama database yang akan menggunakan schema database ajs demo ini
$username = "USERNAME_POSTGRESQL"; // isi dengan username postgresql
$password = "PASSWORD_POSTGRESQL"; // isi dengan password postgresql

$conn = pg_connect(
    "host=$host port=$port dbname=$dbname user=$username password=$password"
);

if (!$conn) {
    error_log("Koneksi ke basis data gagal.");
    exit("Tidak dapat menghubungkan ke basis data.");
}