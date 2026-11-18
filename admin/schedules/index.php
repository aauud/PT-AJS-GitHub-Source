<?php

require_once __DIR__ . "/../../includes/app_path.php";
require_once __DIR__ . "/../guard.php";

// Ambil jadwal beserta informasi pelatihannya.
$querySchedule = "
    SELECT
        s.schedule_id,
        s.schedule_start_date,
        s.schedule_duration,
        s.schedule_start_time,
        s.schedule_end_time,
        s.schedule_link,
        s.schedule_location,

        t.training_code,
        t.training_topic,
        t.training_interaction,
        t.training_online_type

    FROM schedules s

    JOIN trainings t
        ON s.training_id = t.training_id

    ORDER BY s.schedule_start_date DESC, s.schedule_id DESC
";

$resultSchedule = pg_query($conn, $querySchedule);

if (!$resultSchedule) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil data jadwal.");
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kelola Jadwal</title>

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

            <h2>Kelola Jadwal Pelatihan</h2>

            <p>
                Daftar jadwal pelaksanaan program pelatihan PT AJS.
            </p>

            <div class="mb-3">

                <a
                    href="../../admin.php"
                    class="btn btn-secondary">
                    Dashboard
                </a>

                <a
                    href="create.php"
                    class="btn btn-primary">
                    Tambah Jadwal
                </a>

            </div>

            <?php if (isset($_GET["created"])): ?>

                <div class="alert alert-success">
                    Jadwal pelatihan berhasil ditambahkan.
                </div>

            <?php endif; ?>

            <div class="table-responsive">

                <table class="table table-bordered table-striped align-middle">

                    <thead class="table-dark">

                        <tr>
                            <th>Kode Pelatihan</th>
                            <th>Nama Pelatihan</th>
                            <th>Metode</th>
                            <th>Tanggal Mulai</th>
                            <th>Durasi</th>
                            <th>Waktu</th>
                            <th>Informasi Pelaksanaan</th>
                            <th>Kehadiran</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (pg_num_rows($resultSchedule) > 0): ?>

                            <?php while ($row = pg_fetch_assoc($resultSchedule)): ?>

                                <?php

                                $deliveryMethod = $row["training_interaction"];

                                if ($deliveryMethod === "Daring") {
                                    $deliveryMethod .= " - " . $row["training_online_type"];
                                }

                                $scheduleTime = "-";

                                if ($row["schedule_start_time"] !== null) {

                                    $scheduleTime =
                                        substr($row["schedule_start_time"], 0, 5)
                                        . " - "
                                        . substr($row["schedule_end_time"], 0, 5);
                                }

                                $scheduleInfo = "Belum ditentukan";

                                if ($row["training_interaction"] === "Daring") {

                                    if ($row["training_online_type"] === "Asinkron") {
                                        $scheduleInfo = "Materi melalui website";
                                    } else {
                                        $scheduleInfo = $row["schedule_link"];
                                    }

                                } else {

                                    $scheduleInfo = $row["schedule_location"];

                                }

                                $isAsyncTraining = (
                                    $row["training_interaction"] === "Daring" &&
                                    $row["training_online_type"] === "Asinkron"
                                );

                                ?>

                                <tr>

                                    <td>
                                        <?= htmlspecialchars($row["training_code"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row["training_topic"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($deliveryMethod) ?>
                                    </td>

                                    <td>
                                        <?= date(
                                            "d/m/Y",
                                            strtotime($row["schedule_start_date"])
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row["schedule_duration"]) ?>
                                        hari
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($scheduleTime) ?>
                                    </td>

                                    <td>

                                        <?php if (
                                            $row["training_interaction"] === "Daring" &&
                                            $row["training_online_type"] === "Sinkron" &&
                                            !empty($scheduleInfo)
                                        ): ?>

                                            <a
                                                href="<?= htmlspecialchars($scheduleInfo, ENT_QUOTES, "UTF-8") ?>"
                                                target="_blank"
                                                rel="noopener noreferrer">
                                                Link Pertemuan
                                            </a>

                                        <?php else: ?>

                                            <?= htmlspecialchars($scheduleInfo ?? "-") ?>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <?php if ($isAsyncTraining): ?>

                                            <span class="text-muted">
                                                Tidak diperlukan
                                            </span>

                                        <?php else: ?>

                                            <a
                                                href="../attendance/index.php?schedule_id=<?= (int) $row["schedule_id"] ?>"
                                                class="btn btn-sm btn-primary">
                                                Kelola Kehadiran
                                            </a>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="8" class="text-center">
                                    Belum ada jadwal pelatihan.
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