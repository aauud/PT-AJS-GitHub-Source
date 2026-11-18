<?php

require_once __DIR__ . "/../guard.php";

// Hanya izinkan penghapusan melalui metode POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Metode permintaan tidak diizinkan.");
}

// Validasi token CSRF.
$submittedToken = $_POST["csrf_token"] ?? "";

if (
    !is_string($submittedToken) ||
    empty($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $submittedToken)
) {
    http_response_code(403);
    exit("Permintaan tidak valid.");
}

// Validasi ID.
$trainingId = filter_var(
    $_POST["training_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

$moduleId = filter_var(
    $_POST["module_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (
    $trainingId === false || $trainingId === null ||
    $moduleId === false || $moduleId === null
) {
    http_response_code(400);
    exit("ID pelatihan atau modul tidak valid.");
}

// Mulai transaksi basis data.
if (!pg_query($conn, "BEGIN")) {
    error_log(pg_last_error($conn));
    exit("Gagal memulai proses penghapusan.");
}

// Kunci data modul yang dipilih.
$queryLock = "
    SELECT module_id
    FROM training_modules
    WHERE module_id = $1
    AND training_id = $2
    FOR UPDATE
";

$resultLock = pg_query_params(
    $conn,
    $queryLock,
    [$moduleId, $trainingId]
);

if (!$resultLock) {

    pg_query($conn, "ROLLBACK");

    error_log(pg_last_error($conn));

    exit("Gagal memeriksa data modul.");
}

$module = pg_fetch_assoc($resultLock);

if (!$module) {

    pg_query($conn, "ROLLBACK");

    http_response_code(404);

    exit("Modul tidak ditemukan.");
}

// Periksa apakah modul memiliki riwayat progres pembelajaran.
$queryProgress = "
    SELECT EXISTS (
        SELECT 1
        FROM module_progress
        WHERE module_id = $1
    ) AS has_progress
";

$resultProgress = pg_query_params(
    $conn,
    $queryProgress,
    [$moduleId]
);

if (!$resultProgress) {

    pg_query($conn, "ROLLBACK");

    error_log(pg_last_error($conn));

    exit("Gagal memeriksa progres pembelajaran.");
}

$hasProgress = pg_fetch_result(
    $resultProgress,
    0,
    "has_progress"
);

// Tolak penghapusan jika sudah ada riwayat progres.
if ($hasProgress === "t") {

    pg_query($conn, "ROLLBACK");

    header(
        "Location: index.php?training_id="
            . $trainingId
            . "&delete_blocked=1"
    );

    exit;
}

// Hapus modul.
$queryDelete = "
    DELETE FROM training_modules
    WHERE module_id = $1
    AND training_id = $2
";

$resultDelete = pg_query_params(
    $conn,
    $queryDelete,
    [$moduleId, $trainingId]
);

if (!$resultDelete) {

    pg_query($conn, "ROLLBACK");

    error_log(pg_last_error($conn));

    header(
        "Location: index.php?training_id="
            . $trainingId
            . "&delete_error=1"
    );

    exit;
}

// menyesuaikan data di database dengan hasil penghapusan.
if (!pg_query($conn, "COMMIT")) {

    error_log(pg_last_error($conn));

    pg_query($conn, "ROLLBACK");

    exit("Gagal menyelesaikan penghapusan modul.");
}

// Kembali ke daftar modul.
header(
    "Location: index.php?training_id="
        . $trainingId
        . "&deleted=1"
);

exit;
