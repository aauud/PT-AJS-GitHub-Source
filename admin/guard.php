<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/app_path.php";

// Arahkan pengguna yang belum login ke halaman login.
if (!isset($_SESSION["account_id"])) {
    header("Location: " . app_url("login.php"));
    exit;
}

// Periksa peran akun langsung dari basis data.
$queryRole = "
    SELECT r.role_name
    FROM accounts a
    JOIN roles r ON a.role_id = r.role_id
    WHERE a.account_id = $1
";

$resultRole = pg_query_params(
    $conn,
    $queryRole,
    [$_SESSION["account_id"]]
);

if (!$resultRole) {
    error_log(pg_last_error($conn));
    http_response_code(500);
    exit("Gagal memeriksa hak akses.");
}

$role = pg_fetch_assoc($resultRole);

if (!$role || $role["role_name"] !== "Admin") {
    http_response_code(403);
    exit("Anda tidak memiliki akses ke halaman ini.");
}

// Sinkronkan peran pada sesi dengan data akun.
$_SESSION["role_name"] = $role["role_name"];
