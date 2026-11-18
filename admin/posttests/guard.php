<?php

require_once __DIR__ . "/../guard.php";

// Buat token CSRF jika belum tersedia.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Ambil data pelatihan yang memenuhi syarat.
function getTraining($conn, $trainingId)
{

    $query = "
        SELECT
            training_id,
            training_code,
            training_topic,
            training_interaction,
            training_online_type

        FROM trainings

        WHERE training_id = $1
    ";

    $result = pg_query_params(
        $conn,
        $query,
        [$trainingId]
    );

    if (!$result) {
        error_log(pg_last_error($conn));
        exit("Gagal mengambil data pelatihan.");
    }

    return pg_fetch_assoc($result);
}

// Ambil data posttest beserta informasi pelatihannya.
function getPosttest($conn, $posttestId)
{

    $query = "
        SELECT
            p.*,
            t.training_id,
            t.training_code,
            t.training_topic

        FROM posttests p

        JOIN trainings t
            ON p.training_id = t.training_id

        WHERE p.posttest_id = $1
    ";

    $result = pg_query_params(
        $conn,
        $query,
        [$posttestId]
    );

    if (!$result) {
        error_log(pg_last_error($conn));
        exit("Gagal mengambil data posttest.");
    }

    return pg_fetch_assoc($result);
}

// Hitung jumlah percobaan yang sudah ada.
function getPosttestAttemptCount($conn, $posttestId)
{

    $result = pg_query_params(
        $conn,
        "
        SELECT COUNT(*) AS total
        FROM posttest_attempts
        WHERE posttest_id = $1
        ",
        [$posttestId]
    );

    if (!$result) {
        error_log(pg_last_error($conn));
        exit("Gagal memeriksa riwayat posttest.");
    }

    return (int) pg_fetch_result($result, 0, "total");
}

// Periksa status penguncian pertanyaan dan alasan penguncian.
function getPosttestQuestionLockInfo($conn, $posttest)
{

    $isActive = ($posttest["posttest_is_active"] === "t");

    $attemptCount = getPosttestAttemptCount(
        $conn,
        $posttest["posttest_id"]
    );

    $reasons = [];

    if ($isActive) {

        $reasons[] =
            "Posttest masih aktif. Nonaktifkan posttest terlebih dahulu.";
    }

    if ($attemptCount > 0) {

        $reasons[] =
            "Terdapat " . $attemptCount .
            " riwayat pengerjaan. Pertanyaan tidak dapat diubah " .
            "agar hasil pengerjaan sebelumnya tetap konsisten.";
    }

    return [
        "locked" => ($isActive || $attemptCount > 0),
        "reasons" => $reasons
    ];
}

// Pastikan pembatasan tetap berlaku pada proses perubahan data.
function areQuestionsLocked($conn, $posttest)
{

    $lockInfo = getPosttestQuestionLockInfo($conn, $posttest);

    return $lockInfo["locked"];
}

// Verifikasi token CSRF.
function verifyPosttestCsrf()
{

    $submittedToken = $_POST["csrf_token"] ?? "";

    return (
        is_string($submittedToken) &&
        !empty($_SESSION["csrf_token"]) &&
        hash_equals(
            $_SESSION["csrf_token"],
            $submittedToken
        )
    );
}
