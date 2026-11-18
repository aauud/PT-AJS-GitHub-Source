<?php

require_once __DIR__ . "/../guard.php";

// Buat token CSRF

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Ambil transaksi pelatihan yang sudah terdaftar.
function getAttendanceTransaction(
    $conn,
    $transactionId,
    $accountId
) {

    $query = "
        SELECT
            tr.transaction_id,
            tr.transaction_status,

            s.schedule_id,
            s.schedule_start_date,
            s.schedule_start_time,
            s.schedule_duration,

            t.training_id,
            t.training_code,
            t.training_topic,
            t.training_interaction,
            t.training_online_type

        FROM transactions tr

        JOIN schedules s
            ON tr.schedule_id = s.schedule_id

        JOIN trainings t
            ON s.training_id = t.training_id

        WHERE tr.transaction_id = $1
        AND tr.account_id = $2
        AND tr.transaction_status = 'Terdaftar'
    ";

    $result = pg_query_params(
        $conn,
        $query,
        [$transactionId, $accountId]
    );

    if (!$result) {
        error_log(pg_last_error($conn));
        return false;
    }

    return pg_fetch_assoc($result);
}

// CSRF validation.
function verifyAttendanceCsrf()
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
?>