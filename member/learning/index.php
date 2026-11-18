<?php

require_once __DIR__ . "/../../includes/app_path.php";

require_once __DIR__ . "/guard.php";
// Ambil pendaftaran pelatihan Daring Asinkron yang memenuhi syarat.
$queryLearning = "
    SELECT
        tr.transaction_id,
        tr.transaction_amount,

        t.training_code,
        t.training_topic,
        t.training_category,

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
        ) AS completed_modules

    FROM transactions tr

    JOIN schedules s
        ON tr.schedule_id = s.schedule_id

    JOIN trainings t
        ON s.training_id = t.training_id

    WHERE tr.account_id = $1
    AND tr.transaction_status = 'Terdaftar'
    AND t.training_interaction = 'Daring'
    AND t.training_online_type = 'Asinkron'

    ORDER BY tr.transaction_id DESC
";

$resultLearning = pg_query_params(
    $conn,
    $queryLearning,
    [$_SESSION["account_id"]]
);

if (!$resultLearning) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil data pembelajaran.");
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Learning</title>

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

<body>

    <?php
    require_once __DIR__ . "/../../includes/navbar.php";
    ?>

    <main class="page-content">


        <div class="container mt-5">

            <h2>My Learning</h2>

            <p>
                Daftar program pelatihan daring asinkron
                yang telah Anda daftarkan.
            </p>

            <a
                href="../transactions/index.php"
                class="btn btn-secondary mb-3">
                Kembali ke Riwayat Transaksi
            </a>

            <div class="row">

                <?php if (pg_num_rows($resultLearning) > 0): ?>

                    <?php while ($row = pg_fetch_assoc($resultLearning)): ?>

                        <?php

                        $totalModules = (int) $row["total_modules"];
                        $completedModules = (int) $row["completed_modules"];

                        $percentage = $totalModules > 0
                            ? round(($completedModules / $totalModules) * 100)
                            : 0;

                        ?>

                        <div class="col-md-6 mb-4">

                            <div class="card h-100">

                                <div class="card-body">

                                    <h5 class="card-title">

                                        <?= htmlspecialchars(
                                            $row["training_topic"]
                                        ) ?>

                                    </h5>

                                    <p class="text-muted mb-2">

                                        Kode:
                                        <?= htmlspecialchars(
                                            $row["training_code"]
                                        ) ?>

                                    </p>

                                    <p>

                                        Kategori:
                                        <?= htmlspecialchars(
                                            $row["training_category"]
                                        ) ?>

                                    </p>

                                    <p>

                                        Modul selesai:

                                        <?= $completedModules ?>
                                        /
                                        <?= $totalModules ?>

                                    </p>

                                    <div
                                        class="progress mb-3"
                                        role="progressbar"
                                        aria-label="Learning progress"
                                        aria-valuenow="<?= $percentage ?>"
                                        aria-valuemin="0"
                                        aria-valuemax="100">

                                        <div
                                            class="progress-bar"
                                            style="width: <?= $percentage ?>%;">

                                            <?= $percentage ?>%

                                        </div>

                                    </div>

                                    <?php if ($totalModules === 0): ?>

                                        <p class="text-muted">
                                            Belum ada modul pembelajaran.
                                        </p>

                                    <?php endif; ?>

                                    <a
                                        href="detail.php?transaction_id=<?= (int) $row["transaction_id"] ?>"
                                        class="btn btn-primary">

                                        Lihat Pembelajaran

                                    </a>

                                </div>

                            </div>

                        </div>

                    <?php endwhile; ?>

                <?php else: ?>

                    <div class="col-12">

                        <div class="alert alert-info">

                            Anda belum memiliki program pembelajaran
                            daring asinkron yang terdaftar.

                        </div>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </main>

    <?php
    require_once __DIR__ . "/../../includes/footer.php";
    ?>

</body>

</html>