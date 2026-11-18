
<?php

require_once __DIR__ . "/guard.php";

// 1. Terima permintaan hanya melalui metode POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Method not allowed.");
}

// 2. Verifikasi token CSRF.
if (!verifyAttendanceAdminCsrf()) {
    http_response_code(403);
    exit("Permintaan tidak valid.");
}

// 3. Validasi ID.
$scheduleId = filter_var(
    $_POST["schedule_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

$transactionId = filter_var(
    $_POST["transaction_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (
    $scheduleId === false || $scheduleId === null ||
    $transactionId === false || $transactionId === null
) {
    http_response_code(400);
    exit("ID tidak valid.");
}

// 4. Validasi status kehadiran.
$attendanceStatus = $_POST["attendance_status"] ?? "";

if (!in_array(
    $attendanceStatus,
    ["Hadir", "Tidak Hadir"],
    true
)) {
    http_response_code(400);
    exit("Status kehadiran tidak valid.");
}

$notes = trim(
    (string) ($_POST["attendance_notes"] ?? "")
);

// 5. Cek jika transaksi benar terkait dengan jadwal yang dipilih
$queryCheck = "
    SELECT
        tr.transaction_id,
        tr.transaction_status,

        t.training_interaction,
        t.training_online_type

    FROM transactions tr

    JOIN schedules s
        ON tr.schedule_id = s.schedule_id

    JOIN trainings t
        ON s.training_id = t.training_id

    WHERE tr.transaction_id = $1
    AND tr.schedule_id = $2
    AND tr.transaction_status = 'Terdaftar'
";

$resultCheck = pg_query_params(
    $conn,
    $queryCheck,
    [$transactionId, $scheduleId]
);

if (!$resultCheck) {
    error_log(pg_last_error($conn));
    exit("Gagal memeriksa data peserta.");
}

$participant = pg_fetch_assoc($resultCheck);

if (!$participant) {
    http_response_code(404);
    exit("Peserta tidak ditemukan.");
}

// Pelatihan Daring Asinkron tidak menggunakan verifikasi kehadiran.
if (
    $participant["training_interaction"] === "Daring" &&
    $participant["training_online_type"] === "Asinkron"
) {
    http_response_code(400);
    exit("Pelatihan asinkron tidak memerlukan verifikasi kehadiran.");
}

// 6. Simpan atau perbarui keputusan kehadiran.
$queryUpdate = "
    INSERT INTO attendance_records (
        transaction_id,
        attendance_status,
        attendance_evidence_type,
        attendance_verified_by,
        attendance_verified_at,
        attendance_notes
    )

    VALUES (
        $1,
        $2,
        'Verifikasi Manual',
        $3,
        CURRENT_TIMESTAMP,
        $4
    )

    ON CONFLICT (transaction_id)

    DO UPDATE SET
        attendance_status = EXCLUDED.attendance_status,

        attendance_verified_by = EXCLUDED.attendance_verified_by,

        attendance_verified_at = CURRENT_TIMESTAMP,

        attendance_notes = EXCLUDED.attendance_notes
";

$resultUpdate = pg_query_params(
    $conn,
    $queryUpdate,
    [
        $transactionId,
        $attendanceStatus,
        $_SESSION["account_id"],
        $notes !== "" ? $notes : null
    ]
);

if (!$resultUpdate) {
    error_log(pg_last_error($conn));
    exit("Gagal memperbarui kehadiran peserta.");
}

// 7. Kembali ke daftar kehadiran.
header(
    "Location: index.php?schedule_id="
        . $scheduleId
        . "&updated=1"
);

exit;

?>
