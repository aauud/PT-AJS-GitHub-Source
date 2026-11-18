<?php

require_once __DIR__ . "/../../includes/app_path.php";


require_once __DIR__ . "/guard.php";

// 1. Validasi ID transaksi.
$transactionId = filter_var(
    $_GET["transaction_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($transactionId === false || $transactionId === null) {
    http_response_code(400);
    exit("ID transaksi tidak valid.");
}

// 2. Ambil transaksi milik Member.
$context = getMemberPosttestContext(
    $conn,
    $transactionId,
    $_SESSION["account_id"]
);

if (!$context) {
    http_response_code(404);
    exit("Pendaftaran tidak ditemukan atau Anda tidak memiliki akses.");
}

// 3. Periksa kelayakan.
$eligibility = checkPosttestEligibility($context);

$hasPassed = false;
$attempts = [];

// 4. Ambil riwayat percobaan jika posttest tersedia.
if (!empty($context["posttest_id"])) {

    $passedResult = hasPassedPosttest(
        $conn,
        $transactionId,
        $context["posttest_id"]
    );

    if ($passedResult === null) {
        exit("Gagal memeriksa riwayat posttest.");
    }

    $hasPassed = $passedResult;

    $queryAttempts = "
        SELECT
            attempt_id,
            attempt_number,
            attempt_score,
            attempt_is_passed,
            attempt_submitted_at

        FROM posttest_attempts

        WHERE transaction_id = $1
        AND posttest_id = $2

        ORDER BY attempt_number DESC
    ";

    $resultAttempts = pg_query_params(
        $conn,
        $queryAttempts,
        [
            $transactionId,
            $context["posttest_id"]
        ]
    );

    if (!$resultAttempts) {
        error_log(pg_last_error($conn));
        exit("Gagal mengambil riwayat posttest.");
    }

    while ($row = pg_fetch_assoc($resultAttempts)) {
        $attempts[] = $row;
    }
}

// 5. Tentukan metode pelatihan.
$isAsync = (
    $context["training_interaction"] === "Daring" &&
    $context["training_online_type"] === "Asinkron"
);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Posttest</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


    <link
        rel="stylesheet"
        href="<?= htmlspecialchars(
            app_url("assets/css/ajs-theme.css"),
            ENT_QUOTES,
            "UTF-8"
        ) ?>"
    >
</head>

<body>

    <?php require_once __DIR__ . "/../../includes/navbar.php"; ?>

    <main class="page-content">



    <div class="container mt-5 mb-5">

        <h2>Posttest</h2>

        <h4>
            <?= htmlspecialchars($context["training_topic"]) ?>
        </h4>

        <p class="text-muted">

            <?= htmlspecialchars($context["training_code"]) ?>

            |

            <?= htmlspecialchars($context["training_interaction"]) ?>

            <?= $context["training_online_type"] !== ""
                ? " - " . htmlspecialchars($context["training_online_type"])
                : "" ?>

        </p>

        <a
            href="../transactions/detail.php?id=<?= (int) $transactionId ?>"
            class="btn btn-secondary mb-3">
            Kembali ke Transaksi
        </a>

        <?php if (isset($_GET["blocked"])): ?>

            <div class="alert alert-warning">
                Persyaratan posttest belum terpenuhi.
            </div>

        <?php endif; ?>

        <?php if (empty($context["posttest_id"])): ?>

            <div class="alert alert-info">
                Posttest belum tersedia untuk program ini.
            </div>

        <?php else: ?>

            <div class="card mb-4">

                <div class="card-body">

                    <h5>
                        <?= htmlspecialchars($context["posttest_title"]) ?>
                    </h5>

                    <p>

                        Nilai kelulusan:

                        <strong>
                            <?= htmlspecialchars(
                                $context["posttest_passing_score"]
                            ) ?>%
                        </strong>

                    </p>

                    <p class="mb-2">

                        Status posttest:

                        <?php if ($context["posttest_is_active"] === "t"): ?>

                            <span class="badge bg-success">
                                Aktif
                            </span>

                        <?php else: ?>

                            <span class="badge bg-secondary">
                                Tidak Aktif
                            </span>

                        <?php endif; ?>

                    </p>

                    <?php if (!$isAsync): ?>

                        <hr>

                        <h6>Jadwal Pengerjaan</h6>

                        <p class="mb-1">

                            Pembukaan:

                            <?= $context["schedule_posttest_open_at"]
                                ? htmlspecialchars(
                                    date(
                                        "d/m/Y H:i",
                                        strtotime($context["schedule_posttest_open_at"])
                                    )
                                ) . " WIB"
                                : "-" ?>

                        </p>

                        <p class="mb-0">

                            Penutupan:

                            <?= $context["schedule_posttest_close_at"]
                                ? htmlspecialchars(
                                    date(
                                        "d/m/Y H:i",
                                        strtotime($context["schedule_posttest_close_at"])
                                    )
                                ) . " WIB"
                                : "-" ?>

                        </p>

                    <?php endif; ?>

                </div>

            </div>

            <?php if ($hasPassed): ?>

                <div class="alert alert-success">

                    <h5>Selamat! Anda telah lulus.</h5>

                    Anda telah memenuhi nilai kelulusan posttest.

                    <br>

                    Anda tidak perlu mengerjakan posttest kembali
                    untuk pendaftaran ini.

                    <br>

                    <br>

                    <a
                        href="../certificates/index.php"
                        class="btn btn-success">
                        Sertifikat Saya
                    </a>

                </div>

            <?php elseif ($eligibility["eligible"]): ?>

                <div class="alert alert-success">

                    <?= htmlspecialchars($eligibility["message"]) ?>

                </div>

                <a
                    href="take.php?transaction_id=<?= (int) $transactionId ?>"
                    class="btn btn-primary">

                    <?= count($attempts) > 0
                        ? "Ulangi Posttest"
                        : "Mulai Posttest" ?>

                </a>

            <?php else: ?>

                <div class="alert alert-warning">

                    <strong>Posttest tidak dapat dikerjakan.</strong>

                    <br>

                    <?= htmlspecialchars($eligibility["message"]) ?>

                </div>

            <?php endif; ?>

        <?php endif; ?>

        <?php if (!empty($attempts)): ?>

            <hr>

            <h4>Riwayat Percobaan</h4>

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead class="table-dark">

                        <tr>
                            <th>Percobaan</th>
                            <th>Nilai</th>
                            <th>Hasil</th>
                            <th>Waktu Pengerjaan</th>
                            <th>Detail</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($attempts as $attempt): ?>

                            <tr>

                                <td>
                                    <?= (int) $attempt["attempt_number"] ?>
                                </td>

                                <td>

                                    <?= number_format(
                                        (float) $attempt["attempt_score"],
                                        2,
                                        ",",
                                        "."
                                    ) ?>

                                </td>

                                <td>

                                    <?php if ($attempt["attempt_is_passed"] === "t"): ?>

                                        <span class="badge bg-success">
                                            Lulus
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-danger">
                                            Tidak Lulus
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        date(
                                            "d/m/Y H:i:s",
                                            strtotime($attempt["attempt_submitted_at"])
                                        )
                                    ) ?>

                                </td>

                                <td>

                                    <a
                                        href="result.php?attempt_id=<?= (int) $attempt["attempt_id"] ?>"
                                        class="btn btn-sm btn-outline-primary">
                                        Lihat Hasil
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

    </main>

    <?php require_once __DIR__ . "/../../includes/footer.php"; ?>

</body>

</html>
