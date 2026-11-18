<?php

require_once __DIR__ . "/../guard.php";

require_once __DIR__ . "/../../includes/app_path.php";

// Ambil daftar transaksi milik Member ini.
$query = "
    SELECT
        tr.transaction_id,
        tr.transaction_date,
        tr.transaction_status,
        tr.transaction_amount,
        t.training_code,
        t.training_topic,
        s.schedule_start_date,

        (
            SELECT p.payment_status
            FROM payments p
            WHERE p.transaction_id = tr.transaction_id
            ORDER BY p.payment_created_at DESC, p.payment_id DESC
            LIMIT 1
        ) AS latest_payment_status

    FROM transactions tr

    JOIN schedules s
        ON tr.schedule_id = s.schedule_id

    JOIN trainings t
        ON s.training_id = t.training_id

    WHERE tr.account_id = $1

    ORDER BY tr.transaction_id DESC
";

$result = pg_query_params(
    $conn,
    $query,
    [$_SESSION["account_id"]]
);

if (!$result) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil riwayat transaksi.");
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Riwayat Pendaftaran</title>

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

            <h2>Riwayat Pendaftaran Pelatihan</h2>

            <p>
                Selamat datang,
                <?= htmlspecialchars($_SESSION["username"]) ?>.
            </p>

            <a href="../../catalog.php" class="btn btn-secondary mb-3">
                Katalog Pelatihan
            </a>

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead class="table-dark">

                        <tr>
                            <th>No. Transaksi</th>
                            <th>Pelatihan</th>
                            <th>Tanggal Jadwal</th>
                            <th>Tanggal Pendaftaran</th>
                            <th>Biaya</th>
                            <th>Status Transaksi</th>
                            <th>Status Pembayaran</th>
                            <th>Aksi</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (pg_num_rows($result) > 0): ?>

                            <?php while ($row = pg_fetch_assoc($result)): ?>

                                <tr>

                                    <td>
                                        <?= htmlspecialchars($row["transaction_id"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row["training_topic"]) ?>
                                    </td>

                                    <td>
                                        <?= date(
                                            "d/m/Y",
                                            strtotime($row["schedule_start_date"])
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= date(
                                            "d/m/Y",
                                            strtotime($row["transaction_date"])
                                        ) ?>
                                    </td>

                                    <td>
                                        Rp <?= number_format(
                                                (float) $row["transaction_amount"],
                                                0,
                                                ",",
                                                "."
                                            ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row["transaction_status"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $row["latest_payment_status"] ?? "Belum dibuat"
                                        ) ?>
                                    </td>

                                    <td>
                                        <a
                                            href="detail.php?id=<?= $row["transaction_id"] ?>"
                                            class="btn btn-primary btn-sm">
                                            Detail
                                        </a>
                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="8" class="text-center">
                                    Belum ada riwayat pendaftaran.
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