<?php

require_once __DIR__ . "/../guard.php";


// Buat token CSRF
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Ambil informasi pelatihan dan posttest milik Member.
function getMemberPosttestContext(
    $conn,
    $transactionId,
    $accountId
) {

    $query = "
        SELECT

            tr.transaction_id,
            tr.account_id,
            tr.transaction_status,

            s.schedule_id,
            s.schedule_posttest_open_at,
            s.schedule_posttest_close_at,

            t.training_id,
            t.training_code,
            t.training_topic,
            t.training_interaction::TEXT AS training_interaction,
            COALESCE(
                t.training_online_type::TEXT,
                ''
            ) AS training_online_type,

            p.posttest_id,
            p.posttest_title,
            p.posttest_passing_score,
            p.posttest_is_active,

            ar.attendance_status,

            (
                SELECT COUNT(*)
                FROM training_modules tm
                WHERE tm.training_id = t.training_id
            ) AS total_modules,

            (
                SELECT COUNT(*)

                FROM module_progress mp

                JOIN training_modules tm2
                    ON mp.module_id = tm2.module_id

                WHERE mp.transaction_id = tr.transaction_id
                AND tm2.training_id = t.training_id
                AND mp.module_completed_at IS NOT NULL

            ) AS completed_modules,

            CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Jakarta'
                AS current_wib

        FROM transactions tr

        JOIN schedules s
            ON tr.schedule_id = s.schedule_id

        JOIN trainings t
            ON s.training_id = t.training_id

        LEFT JOIN posttests p
            ON t.training_id = p.training_id

        LEFT JOIN attendance_records ar
            ON tr.transaction_id = ar.transaction_id

        WHERE tr.transaction_id = $1
        AND tr.account_id = $2
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

// Periksa apakah Member sudah lulus.
function hasPassedPosttest(
    $conn,
    $transactionId,
    $posttestId
) {

    $query = "
        SELECT EXISTS (
            SELECT 1
            FROM posttest_attempts
            WHERE transaction_id = $1
            AND posttest_id = $2
            AND attempt_is_passed = TRUE
        ) AS has_passed
    ";

    $result = pg_query_params(
        $conn,
        $query,
        [$transactionId, $posttestId]
    );

    if (!$result) {
        error_log(pg_last_error($conn));
        return null;
    }

    return pg_fetch_result(
        $result,
        0,
        "has_passed"
    ) === "t";
}

// Periksa kelayakan mengikuti posttest.
function checkPosttestEligibility($context)
{

    $tz = new DateTimeZone("Asia/Jakarta");

    // Pendaftaran harus sudah dibayar dan dikonfirmasi.
    if ($context["transaction_status"] !== "Terdaftar") {
        return [
            "eligible" => false,
            "message" => "Pendaftaran belum terkonfirmasi. Selesaikan pembayaran terlebih dahulu."
        ];
    }

    // Pelatihan harus memiliki posttest yang telah dikonfigurasi.
    if (empty($context["posttest_id"])) {
        return [
            "eligible" => false,
            "message" => "Posttest belum tersedia untuk program ini."
        ];
    }

    // Admin harus mengaktifkan posttest.
    if ($context["posttest_is_active"] !== "t") {
        return [
            "eligible" => false,
            "message" => "Posttest belum diaktifkan oleh Admin."
        ];
    }

    // Tentukan metode pelaksanaan.
    $isAsync = (
        $context["training_interaction"] === "Daring" &&
        $context["training_online_type"] === "Asinkron"
    );

    // Daring Asinkron: seluruh modul harus diselesaikan.
    if ($isAsync) {

        $total = (int) $context["total_modules"];
        $completed = (int) $context["completed_modules"];

        if ($total === 0) {
            return [
                "eligible" => false,
                "message" => "Belum ada modul pembelajaran untuk program ini."
            ];
        }

        if ($completed < $total) {
            return [
                "eligible" => false,
                "message" => "Selesaikan seluruh modul pembelajaran sebelum mengerjakan posttest."
            ];
        }

        return [
            "eligible" => true,
            "message" => "Anda memenuhi persyaratan posttest."
        ];
    }

    // Daring Sinkron dan Luring memerlukan kehadiran yang telah diverifikasi.
    if ($context["attendance_status"] !== "Hadir") {
        return [
            "eligible" => false,
            "message" => "Kehadiran Anda belum diverifikasi sebagai Hadir oleh Admin."
        ];
    }

    // Ambil rentang waktu pelaksanaan ujian.
    $openValue = $context["schedule_posttest_open_at"];
    $closeValue = $context["schedule_posttest_close_at"];

    if (empty($openValue) || empty($closeValue)) {
        return [
            "eligible" => false,
            "message" => "Jadwal posttest belum ditentukan oleh Admin."
        ];
    }

    $openTime = new DateTimeImmutable(
        $openValue,
        $tz
    );

    $closeTime = new DateTimeImmutable(
        $closeValue,
        $tz
    );

    $currentTime = new DateTimeImmutable(
        $context["current_wib"],
        $tz
    );

    if ($currentTime < $openTime) {
        return [
            "eligible" => false,
            "message" => "Posttest belum dibuka. Waktu pembukaan: "
                . $openTime->format("d/m/Y H:i")
                . " WIB."
        ];
    }

    if ($currentTime > $closeTime) {
        return [
            "eligible" => false,
            "message" => "Waktu pengerjaan posttest telah berakhir."
        ];
    }

    return [
        "eligible" => true,
        "message" => "Anda memenuhi persyaratan posttest."
    ];
}

// Verifikasi token CSRF.
function verifyMemberPosttestCsrf()
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
