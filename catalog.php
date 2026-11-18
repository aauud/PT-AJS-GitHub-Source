<?php

session_start();

require_once __DIR__ . "/config/database.php";

require_once __DIR__ . "/includes/app_path.php";

$query = "
    SELECT
        t.training_id,
        t.training_code,
        t.training_topic,
        t.training_category,
        t.training_interaction,
        t.training_online_type,
        t.training_target_audience,
        t.training_benefit,
        t.training_fee,
        tr.trainer_name,

        (
            SELECT COUNT(*)
            FROM schedules s
            WHERE s.training_id = t.training_id
            AND s.schedule_start_date >= CURRENT_DATE
        ) AS upcoming_schedules

    FROM trainings t

    JOIN trainers tr
        ON t.trainer_id = tr.trainer_id

    ORDER BY t.training_code
";

$result = pg_query($conn, $query);

if (!$result) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil informasi pelatihan.");
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Program Pelatihan PT AJS</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="assets/css/ajs-theme.css">

</head>

<body class="bg-light">

    <?php

    $basePath = "";

    require_once __DIR__ . "/includes/navbar.php";

    ?>


    <main class="container py-5">

        <div class="mb-4">

            <h1>Program Pelatihan PT AJS</h1>

            <p class="text-muted">
                Temukan program pelatihan yang sesuai dengan kebutuhan Anda.
            </p>

        </div>


        <div class="row g-3">

            <?php if (pg_num_rows($result) > 0): ?>

                <?php while ($training = pg_fetch_assoc($result)): ?>

                    <div class="col-md-6 col-lg-4">

                        <div class="card h-100 shadow-sm">

                            <div class="card-body">

                                <h5 class="card-title">

                                    <?= htmlspecialchars(
                                        $training["training_topic"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </h5>


                                <p class="text-secondary">

                                    <?= htmlspecialchars(
                                        $training["training_code"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </p>


                                <p>

                                    <strong>Kategori:</strong>

                                    <?= htmlspecialchars(
                                        $training["training_category"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </p>


                                <p>

                                    <strong>Metode:</strong>

                                    <?= htmlspecialchars(
                                        $training["training_interaction"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>


                                    <?php if ($training["training_interaction"] === "Daring"): ?>

                                        -

                                        <?= htmlspecialchars(
                                            $training["training_online_type"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    <?php endif; ?>

                                </p>


                                <p>

                                    <strong>Pelatih:</strong>

                                    <?= htmlspecialchars(
                                        $training["trainer_name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

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


                                <p>

                                    <strong>Jadwal tersedia:</strong>

                                    <?= (int) $training["upcoming_schedules"] ?>

                                </p>


                                <a
                                    href="training_detail.php?id=<?= (int) $training["training_id"] ?>"
                                    class="btn btn-primary">
                                    Lihat Detail
                                </a>

                            </div>

                        </div>

                    </div>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="col-12">

                    <div class="alert alert-secondary">
                        Belum ada program pelatihan tersedia.
                    </div>

                </div>

            <?php endif; ?>

        </div>

    </main>

    <?php
    require_once __DIR__ . "/includes/footer.php";
    ?>

</body>

</html>