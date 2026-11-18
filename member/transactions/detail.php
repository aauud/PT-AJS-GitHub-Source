<?php

require_once __DIR__ . "/../../includes/app_path.php";
require_once __DIR__ . "/../guard.php";

// 1. Token CSRF

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}


// 2. Validasi ID transaksi.
// Pertahankan nama parameter "id".

$transactionId = filter_var(
    $_GET["id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($transactionId === false || $transactionId === null) {
    http_response_code(400);
    exit("ID transaksi tidak valid.");
}

// 3. Ambil transaksi milik Member yang sedang login.
$query = "
    SELECT
        tr.transaction_id,
        tr.transaction_date,
        tr.transaction_time,
        tr.transaction_amount,
        tr.transaction_status,

        t.training_code,
        t.training_topic,
        t.training_interaction,
        t.training_online_type,

        s.schedule_start_date,
        s.schedule_duration

    FROM transactions tr

    JOIN schedules s
        ON tr.schedule_id = s.schedule_id

    JOIN trainings t
        ON s.training_id = t.training_id

    WHERE tr.transaction_id = $1
    AND tr.account_id = $2
";

$result = pg_query_params(
    $conn,
    $query,
    [
        $transactionId,
        $_SESSION["account_id"]
    ]
);

if (!$result) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil detail transaksi.");
}

$transaction = pg_fetch_assoc($result);

if (!$transaction) {
    http_response_code(404);
    exit("Transaksi tidak ditemukan.");
}


// 4. Ambil riwayat percobaan pembayaran.
$queryPayments = "
    SELECT
        payment_id,
        payment_method,
        payment_status,
        payment_amount,
        payment_created_at,
        payment_expiry_at,
        payment_paid_at

    FROM payments

    WHERE transaction_id = $1

    ORDER BY
        payment_created_at DESC,
        payment_id DESC
";

$resultPayments = pg_query_params(
    $conn,
    $queryPayments,
    [$transactionId]
);

if (!$resultPayments) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil informasi pembayaran.");
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Transaksi</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="<?= htmlspecialchars(
                    app_url("assets/css/ajs-theme.css"),
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>">
</head>

<body>

    <?php
    require_once __DIR__ . "/../../includes/navbar.php";
    ?>

    <main class="page-content">


        <div class="container mt-5">

            <a href="index.php" class="btn btn-secondary mb-3">
                Kembali ke Riwayat
            </a>

            <?php if (isset($_GET["created"])): ?>

                <div class="alert alert-success">
                    Pendaftaran pelatihan berhasil dibuat.
                </div>

            <?php elseif (isset($_GET["existing"])): ?>

                <div class="alert alert-info">
                    Anda sudah memiliki pendaftaran aktif untuk jadwal ini.
                </div>

            <?php endif; ?>

            <h2>Detail Transaksi</h2>

            <div class="card mb-4">

                <div class="card-body">

                    <p>
                        <strong>Nomor Transaksi:</strong>
                        <?= htmlspecialchars($transaction["transaction_id"]) ?>
                    </p>

                    <p>
                        <strong>Pelatihan:</strong>
                        <?= htmlspecialchars($transaction["training_topic"]) ?>
                    </p>

                    <p>
                        <strong>Kode Pelatihan:</strong>
                        <?= htmlspecialchars($transaction["training_code"]) ?>
                    </p>

                    <p>
                        <strong>Tanggal Pelaksanaan:</strong>
                        <?= date(
                            "d/m/Y",
                            strtotime($transaction["schedule_start_date"])
                        ) ?>
                    </p>

                    <p>
                        <strong>Durasi:</strong>
                        <?= htmlspecialchars($transaction["schedule_duration"]) ?>
                        hari
                    </p>

                    <p>
                        <strong>Tanggal Pendaftaran:</strong>
                        <?= date(
                            "d/m/Y",
                            strtotime($transaction["transaction_date"])
                        ) ?>
                    </p>

                    <p>
                        <strong>Total Biaya:</strong>
                        Rp <?= number_format(
                                (float) $transaction["transaction_amount"],
                                0,
                                ",",
                                "."
                            ) ?>
                    </p>

                    <p>
                        <strong>Status Transaksi:</strong>
                        <?= htmlspecialchars($transaction["transaction_status"]) ?>
                    </p>

                </div>

            </div>

            <h3>Informasi Pembayaran</h3>

            <?php if (pg_num_rows($resultPayments) > 0): ?>

                <div class="table-responsive">

                    <table class="table table-bordered">

                        <thead>
                            <tr>
                                <th>ID Pembayaran</th>
                                <th>Metode</th>
                                <th>Jumlah</th>
                                <th>Status</th>
                                <th>Waktu Pembayaran</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php while ($payment = pg_fetch_assoc($resultPayments)): ?>

                                <tr>

                                    <td>
                                        <?= htmlspecialchars($payment["payment_id"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($payment["payment_method"]) ?>
                                    </td>

                                    <td>
                                        Rp <?= number_format(
                                                (float) $payment["payment_amount"],
                                                0,
                                                ",",
                                                "."
                                            ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($payment["payment_status"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $payment["payment_paid_at"] ?? "-"
                                        ) ?>
                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="alert alert-warning">

                    Belum terdapat data pembayaran untuk transaksi ini.

                    <br>

                    Silakan buat pembayaran untuk melihat instruksi pembayaran.

                </div>

            <?php endif; ?>

            <?php if (
                $transaction["transaction_status"] === "Menunggu Pembayaran"
            ): ?>

                <hr>

                <h4>Bayar dengan QRIS</h4>

                <p>
                    Lanjutkan pembayaran menggunakan QRIS.
                    Nominal pembayaran akan mengikuti biaya transaksi.
                </p>

                <form method="POST" action="create_payment.php">

                    <input
                        type="hidden"
                        name="transaction_id"
                        value="<?= $transaction["transaction_id"] ?>">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">

                    <button type="submit" class="btn btn-success">
                        Buat / Tampilkan QRIS
                    </button>

                </form>

            <?php endif; ?>

            <?php if (
                $transaction["transaction_status"] === "Terdaftar"
            ): ?>

                <?php

                $isAsyncTraining = (
                    $transaction["training_interaction"] === "Daring" &&
                    $transaction["training_online_type"] === "Asinkron"
                );

                ?>

                <hr class="my-4">

                <h4>Aktivitas Pelatihan</h4>

                <p class="text-muted">

                    Akses aktivitas pelatihan sesuai dengan
                    metode pelaksanaan program yang Anda ikuti.

                </p>


                <?php if ($isAsyncTraining): ?>

                    <a
                        href="<?= htmlspecialchars(
                                    app_url("member/learning/detail.php")
                                        . "?transaction_id="
                                        . (int) $transaction["transaction_id"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                        class="btn btn-primary">
                        Buka Pembelajaran
                    </a>


                <?php else: ?>

                    <div class="d-flex flex-wrap gap-2">

                        <a
                            href="<?= htmlspecialchars(
                                        app_url("member/attendance/index.php")
                                            . "?transaction_id="
                                            . (int) $transaction["transaction_id"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                            class="btn btn-primary">
                            Lihat Kehadiran
                        </a>


                        <a
                            href="<?= htmlspecialchars(
                                        app_url("member/posttests/index.php")
                                            . "?transaction_id="
                                            . (int) $transaction["transaction_id"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                            class="btn btn-outline-primary">
                            Lihat Posttest
                        </a>

                    </div>

                    <small class="text-muted d-block mt-2">

                        Akses posttest mengikuti verifikasi kehadiran
                        dan jadwal pengerjaan yang ditentukan Admin.

                    </small>

                <?php endif; ?>

            <?php endif; ?>

        </div>

        <br>
        <br>

    </main>

    <?php
    require_once __DIR__ . "/../../includes/footer.php";
    ?>

</body>

</html>