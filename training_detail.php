<?php

require_once __DIR__ . "/includes/app_path.php";

session_start();

require_once __DIR__ . "/config/database.php";

$trainingId = $_GET["id"] ?? "";

if (!filter_var($trainingId, FILTER_VALIDATE_INT)) {
    http_response_code(400);
    exit("ID pelatihan tidak valid.");
}

$queryTraining = "
    SELECT
        t.*,
        tr.trainer_name
    FROM trainings t
    JOIN trainers tr
        ON t.trainer_id = tr.trainer_id
    WHERE t.training_id = $1
";

$resultTraining = pg_query_params(
    $conn,
    $queryTraining,
    [$trainingId]
);

if (!$resultTraining) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil informasi pelatihan.");
}

$training = pg_fetch_assoc($resultTraining);

if (!$training) {
    http_response_code(404);
    exit("Pelatihan tidak ditemukan.");
}

$querySchedules = "
    SELECT *
    FROM schedules
    WHERE training_id = $1
    AND schedule_start_date >= CURRENT_DATE
    ORDER BY schedule_start_date
";

$resultSchedules = pg_query_params(
    $conn,
    $querySchedules,
    [$trainingId]
);

if (!$resultSchedules) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil jadwal pelatihan.");
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pelatihan</title>

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
    require_once __DIR__ . "/includes/navbar.php";
    ?>

    <main class="page-content">


        <div class="container mt-5">

            <a href="catalog.php" class="btn btn-secondary mb-3">
                Kembali ke Katalog
            </a>

            <h1><?= htmlspecialchars($training["training_topic"]) ?></h1>

            <p>
                <strong>Kode:</strong>
                <?= htmlspecialchars($training["training_code"]) ?>
            </p>

            <p>
                <strong>Kategori:</strong>
                <?= htmlspecialchars($training["training_category"]) ?>
            </p>

            <p>
                <strong>Metode:</strong>
                <?= htmlspecialchars($training["training_interaction"]) ?>

                <?php if ($training["training_interaction"] === "Daring"): ?>
                    -
                    <?= htmlspecialchars($training["training_online_type"]) ?>
                <?php endif; ?>
            </p>

            <p>
                <strong>Target peserta:</strong>
                <?= htmlspecialchars($training["training_target_audience"]) ?>
            </p>

            <p>
                <strong>Pelatih:</strong>
                <?= htmlspecialchars($training["trainer_name"]) ?>
            </p>

            <p>
                <strong>Manfaat:</strong><br>
                <?= nl2br(htmlspecialchars($training["training_benefit"])) ?>
            </p>

            <p>
                <strong>Biaya:</strong>
                Rp <?= number_format(
                        (float) $training["training_fee"],
                        0,
                        ",",
                        "."
                    ) ?>
            </p>

            <hr>

            <h3>Jadwal Tersedia</h3>

            <?php if (pg_num_rows($resultSchedules) > 0): ?>

                <div class="table-responsive">

                    <table class="table table-bordered">

                        <thead>
                            <tr>
                                <th>Tanggal Mulai</th>
                                <th>Durasi</th>
                                <th>Waktu</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php while ($schedule = pg_fetch_assoc($resultSchedules)): ?>

                                <?php

                                $time = "-";

                                if ($schedule["schedule_start_time"] !== null) {
                                    $time =
                                        substr($schedule["schedule_start_time"], 0, 5)
                                        . " - "
                                        . substr($schedule["schedule_end_time"], 0, 5);
                                }

                                // Tujuan halaman setelah login.
                                $nextPage =
                                    "member/register.php?schedule_id="
                                    . $schedule["schedule_id"];

                                if (isset($_SESSION["account_id"])) {
                                    $registerUrl = $nextPage;
                                } else {
                                    $registerUrl =
                                        "login.php?next=" . urlencode($nextPage);
                                }

                                ?>

                                <tr>

                                    <td>
                                        <?= date(
                                            "d/m/Y",
                                            strtotime($schedule["schedule_start_date"])
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($schedule["schedule_duration"]) ?>
                                        hari
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($time) ?>
                                    </td>

                                    <td>
                                        <a
                                            href="<?= htmlspecialchars($registerUrl) ?>"
                                            class="btn btn-primary btn-sm">
                                            Daftar
                                        </a>
                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <p>Belum ada jadwal yang tersedia untuk pelatihan ini.</p>

            <?php endif; ?>

        </div>

    </main>

    <?php
    require_once __DIR__ . "/includes/footer.php";
    ?>

</body>

</html>