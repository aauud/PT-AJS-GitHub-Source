<?php

// 1. Autentikasi dan jalur aplikasi bersama
require_once __DIR__ . "/guard.php";
require_once __DIR__ . "/../includes/app_path.php";

// 2. Validasi ID jadwal
$scheduleIdInput = $_SERVER["REQUEST_METHOD"] === "POST"
    ? ($_POST["schedule_id"] ?? "")
    : ($_GET["schedule_id"] ?? "");

$scheduleId = filter_var(
    $scheduleIdInput,
    FILTER_VALIDATE_INT
);

if ($scheduleId === false || $scheduleId <= 0) {
    http_response_code(400);
    exit("ID jadwal tidak valid.");
}

// 3. Buat token CSRF
if (
    !isset($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"])
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$error = "";

// 4. Ambil jadwal dan informasi pelatihan
$querySchedule = "

    SELECT
        s.schedule_id,
        s.schedule_start_date,
        s.schedule_duration,
        s.schedule_start_time,
        s.schedule_end_time,

        t.training_id,
        t.training_code,
        t.training_topic,
        t.training_category,
        t.training_interaction,
        t.training_online_type,
        t.training_fee

    FROM schedules s

    JOIN trainings t
        ON s.training_id = t.training_id

    WHERE s.schedule_id = $1
    AND s.schedule_start_date >= CURRENT_DATE

";

$resultSchedule = pg_query_params(
    $conn,
    $querySchedule,
    [$scheduleId]
);

if (!$resultSchedule) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil data jadwal.");
}

$schedule = pg_fetch_assoc($resultSchedule);

if (!$schedule) {
    http_response_code(404);
    exit("Jadwal tidak ditemukan atau sudah tidak tersedia.");
}

// 5. Proses pendaftaran

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION["csrf_token"], $submittedToken)
    ) {

        $error = "Permintaan tidak valid. Silakan coba kembali.";
    } else {

        // Biaya diambil dari basis data, bukan dari nilai yang dikirim melalui formulir.

        $queryInsert = "

            INSERT INTO transactions (
                account_id,
                schedule_id,
                transaction_amount
            )

            SELECT
                $1,
                s.schedule_id,
                t.training_fee

            FROM schedules s

            JOIN trainings t
                ON s.training_id = t.training_id

            WHERE s.schedule_id = $2
            AND s.schedule_start_date >= CURRENT_DATE

            ON CONFLICT (account_id, schedule_id)
            WHERE transaction_status <> 'Dibatalkan'

            DO NOTHING

            RETURNING transaction_id

        ";

        $resultInsert = pg_query_params(
            $conn,
            $queryInsert,
            [
                $_SESSION["account_id"],
                $scheduleId
            ]
        );

        if (!$resultInsert) {

            error_log(pg_last_error($conn));

            $error = "Gagal membuat pendaftaran.";
        } else {

            $newTransaction = pg_fetch_assoc($resultInsert);

            if ($newTransaction) {

                // Pendaftaran berhasil dibuat.

                header(
                    "Location: " . app_url(
                        "member/transactions/detail.php?id=" .
                            rawurlencode((string) $newTransaction["transaction_id"]) .
                            "&created=1"
                    )
                );

                exit;
            } else {

                // Periksa pendaftaran aktif yang sudah ada.

                $queryExisting = "

                    SELECT transaction_id

                    FROM transactions

                    WHERE account_id = $1
                    AND schedule_id = $2
                    AND transaction_status <> 'Dibatalkan'

                    ORDER BY transaction_id DESC

                    LIMIT 1

                ";

                $resultExisting = pg_query_params(
                    $conn,
                    $queryExisting,
                    [
                        $_SESSION["account_id"],
                        $scheduleId
                    ]
                );

                if (!$resultExisting) {

                    error_log(pg_last_error($conn));

                    $error = "Gagal memeriksa pendaftaran sebelumnya.";
                } else {

                    $existing = pg_fetch_assoc($resultExisting);

                    if ($existing) {

                        header(
                            "Location: " . app_url(
                                "member/transactions/detail.php?id=" .
                                    rawurlencode((string) $existing["transaction_id"]) .
                                    "&existing=1"
                            )
                        );

                        exit;
                    } else {

                        $error = "Jadwal tidak dapat didaftarkan.";
                    }
                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Konfirmasi Pendaftaran - PT AJS</title>

    <!-- CSS Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- Tema PT AJS -->

    <link
        rel="stylesheet"
        href="<?= htmlspecialchars(
                    app_url("assets/css/ajs-theme.css"),
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>">
</head>


<body class="bg-light">

    <?php
    require_once __DIR__ . "/../includes/navbar.php";
    ?>


    <main class="page-content">

        <div class="container py-5">

            <h2 class="mb-4">
                Konfirmasi Pendaftaran Pelatihan
            </h2>


            <a
                href="<?= htmlspecialchars(
                            app_url(
                                "training_detail.php?id=" .
                                    rawurlencode((string) $schedule["training_id"])
                            ),
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                class="btn btn-secondary mb-3">

                Kembali

            </a>


            <?php if ($error !== ""): ?>

                <div class="alert alert-danger">

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                </div>

            <?php endif; ?>


            <div class="card shadow-sm">

                <div class="card-body p-4">

                    <h4 class="mb-3">

                        <?= htmlspecialchars(
                            $schedule["training_topic"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </h4>


                    <p>

                        <strong>Kode:</strong>

                        <?= htmlspecialchars(
                            $schedule["training_code"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </p>


                    <p>

                        <strong>Kategori:</strong>

                        <?= htmlspecialchars(
                            $schedule["training_category"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </p>


                    <p>

                        <strong>Metode:</strong>

                        <?= htmlspecialchars(
                            $schedule["training_interaction"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                        <?php if (!empty($schedule["training_online_type"])): ?>

                            -
                            <?= htmlspecialchars(
                                $schedule["training_online_type"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        <?php endif; ?>

                    </p>


                    <p>

                        <strong>Tanggal:</strong>

                        <?= date(
                            "d/m/Y",
                            strtotime($schedule["schedule_start_date"])
                        ) ?>

                    </p>


                    <p>

                        <strong>Durasi:</strong>

                        <?= (int) $schedule["schedule_duration"] ?>

                        hari

                    </p>


                    <hr>


                    <h5>

                        Total Biaya:

                        Rp <?= number_format(
                                (float) $schedule["training_fee"],
                                0,
                                ",",
                                "."
                            ) ?>

                    </h5>


                    <p class="text-secondary">

                        Pastikan program dan jadwal yang dipilih sudah sesuai
                        sebelum melanjutkan pendaftaran.

                    </p>


                    <form
                        method="POST"
                        action="<?= htmlspecialchars(
                                    app_url("member/register.php"),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>">

                        <input
                            type="hidden"
                            name="schedule_id"
                            value="<?= (int) $schedule["schedule_id"] ?>">


                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(
                                        $_SESSION["csrf_token"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>">


                        <button
                            type="submit"
                            class="btn btn-primary">

                            Konfirmasi Pendaftaran

                        </button>

                    </form>

                </div>

            </div>

        </div>

    </main>


    <?php
    require_once __DIR__ . "/../includes/footer.php";
    ?>

</body>

</html>