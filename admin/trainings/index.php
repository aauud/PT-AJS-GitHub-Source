<?php

require_once __DIR__ . "/../../includes/app_path.php";
require_once __DIR__ . "/../guard.php";

// Ambil data program pelatihan.
$queryTraining = "
    SELECT
        t.training_id,
        t.training_code,
        t.training_topic,
        t.training_category,
        t.training_interaction,
        t.training_online_type,
        t.training_fee,
        tr.trainer_name

    FROM trainings t

    JOIN trainers tr
        ON t.trainer_id = tr.trainer_id

    ORDER BY t.training_id DESC
";

$resultTraining = pg_query($conn, $queryTraining);

if (!$resultTraining) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil data pelatihan.");
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pelatihan</title>

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

            <h2>Kelola Program Pelatihan</h2>
            <p>Daftar program pelatihan PT AJS.</p>

            <div class="mb-3">
                <a href="../../admin.php" class="btn btn-secondary">
                    Kembali ke Dashboard
                </a>

                <a href="create.php" class="btn btn-primary">
                    Tambah Pelatihan
                </a>
            </div>

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead class="table-dark">
                        <tr>
                            <th>Kode</th>
                            <th>Nama Pelatihan</th>
                            <th>Kategori</th>
                            <th>Metode</th>
                            <th>Jenis Daring</th>
                            <th>Biaya</th>
                            <th>Pelatih</th>
                            <th>Modul</th>
                            <th>Posttest</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (pg_num_rows($resultTraining) > 0): ?>

                            <?php while ($row = pg_fetch_assoc($resultTraining)): ?>

                                <tr>
                                    <td>
                                        <?= htmlspecialchars($row["training_code"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row["training_topic"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row["training_category"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row["training_interaction"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $row["training_online_type"] ?? "-"
                                        ) ?>
                                    </td>

                                    <td>
                                        Rp <?= number_format(
                                                (float) $row["training_fee"],
                                                0,
                                                ",",
                                                "."
                                            ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row["trainer_name"]) ?>
                                    </td>

                                    <td>
                                        <?php if (
                                            $row["training_interaction"] === "Daring" &&
                                            $row["training_online_type"] === "Asinkron"
                                        ): ?>

                                            <a
                                                href="../training_modules/index.php?training_id=<?= (int) $row["training_id"] ?>"
                                                class="btn btn-sm btn-primary">
                                                Kelola Modul
                                            </a>

                                        <?php else: ?>

                                            <span class="text-muted">-</span>

                                        <?php endif; ?>
                                    </td>

                                    <td>

                                        <a
                                            href="../posttests/index.php?training_id=<?= (int) $row["training_id"] ?>"
                                            class="btn btn-sm btn-success">
                                            Kelola Posttest
                                        </a>

                                    </td>
                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="9" class="text-center">
                                    Belum ada data pelatihan.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </main>

    <?php
    require_once __DIR__ . "/../../includes/footer.php";
    ?>

</body>

</html>