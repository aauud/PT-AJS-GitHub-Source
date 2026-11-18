<?php

require_once __DIR__ . "/../guard.php";

// Ambil transaksi pelatihan Daring Asinkron milik Member yang memenuhi syarat.

// Transaksi harus memenuhi ketentuan:
    // Dimiliki oleh Member yang sedang login.
    // Memiliki status Terdaftar.
    // Terkait dengan pelatihan Daring Asinkron.

function getOwnedAsyncTransaction(
    $conn,
    $transactionId,
    $accountId
) {

    $query = "
        SELECT
            tr.transaction_id,
            tr.transaction_amount,
            tr.transaction_status,

            s.schedule_id,

            t.training_id,
            t.training_code,
            t.training_topic,
            t.training_category

        FROM transactions tr

        JOIN schedules s
            ON tr.schedule_id = s.schedule_id

        JOIN trainings t
            ON s.training_id = t.training_id

        WHERE tr.transaction_id = $1
        AND tr.account_id = $2
        AND tr.transaction_status = 'Terdaftar'
        AND t.training_interaction = 'Daring'
        AND t.training_online_type = 'Asinkron'
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
