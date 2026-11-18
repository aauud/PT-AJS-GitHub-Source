<?php

require_once __DIR__ . "/guard.php";
require_once __DIR__ . "/../includes/app_path.php";

$memberName = htmlspecialchars(
    $_SESSION["username"] ?? "Member",
    ENT_QUOTES,
    "UTF-8"
);

// ==========================
// Statistik dashboard Member
// ==========================

// 1. Inisialisasi / siapkan nilai awal untuk setiap status pendaftaran.
// Dengan itu, status tanpa catatan tetap ditampilkan.

$registrationStats = [
    "Menunggu Pembayaran" => 0,
    "Terdaftar" => 0,
    "Dibatalkan" => 0
];

$queryRegistration = "
    SELECT
        transaction_status,
        COUNT(*) AS total
    FROM transactions
    WHERE account_id = $1
    GROUP BY transaction_status
";

$resultRegistration = pg_query_params(
    $conn,
    $queryRegistration,
    [$_SESSION["account_id"]]
);

if (!$resultRegistration) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil statistik pendaftaran.");
}

while ($row = pg_fetch_assoc($resultRegistration)) {
    $registrationStats[$row["transaction_status"]] =
        (int) $row["total"];
}

// 2. Siapkan jumlah awal untuk setiap status pembayaran.

$paymentStats = [
    "Menunggu" => 0,
    "Lunas" => 0,
    "Gagal" => 0,
    "Kedaluwarsa" => 0,
    "Dibatalkan" => 0
];

$queryPayment = "
    SELECT
        p.payment_status,
        COUNT(*) AS total
    FROM payments p

    JOIN transactions tr
        ON p.transaction_id = tr.transaction_id

    WHERE tr.account_id = $1

    GROUP BY p.payment_status
";

$resultPayment = pg_query_params(
    $conn,
    $queryPayment,
    [$_SESSION["account_id"]]
);

if (!$resultPayment) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil statistik pembayaran.");
}

while ($row = pg_fetch_assoc($resultPayment)) {
    $paymentStats[$row["payment_status"]] =
        (int) $row["total"];
}


// 3. Hitung total pendaftaran dan catatan pembayaran.

$totalRegistrations = array_sum($registrationStats);
$totalPayments = array_sum($paymentStats);

// ======================================
// Progres pembelajaran Member
// ======================================

$queryLearning = "

    WITH eligible AS (

        SELECT
            tr.transaction_id,
            t.training_id

        FROM transactions tr

        JOIN schedules s
            ON tr.schedule_id = s.schedule_id

        JOIN trainings t
            ON s.training_id = t.training_id

        WHERE tr.account_id = $1
        AND tr.transaction_status = 'Terdaftar'
        AND t.training_interaction = 'Daring'
        AND t.training_online_type = 'Asinkron'

    ),

    module_summary AS (

        SELECT
            e.transaction_id,
            e.training_id,

            COUNT(DISTINCT tm.module_id) AS total_modules,

            COUNT(DISTINCT mp.module_id) AS completed_modules

        FROM eligible e

        JOIN training_modules tm
            ON e.training_id = tm.training_id

        LEFT JOIN module_progress mp
            ON mp.transaction_id = e.transaction_id
            AND mp.module_id = tm.module_id

        GROUP BY
            e.transaction_id,
            e.training_id

    ),

    learning_summary AS (

        SELECT
            ms.transaction_id,
            ms.training_id,
            ms.total_modules,
            ms.completed_modules,

            EXISTS (

                SELECT 1

                FROM posttests pt

                JOIN posttest_attempts pa
                    ON pa.posttest_id = pt.posttest_id

                WHERE pt.training_id = ms.training_id
                AND pa.transaction_id = ms.transaction_id
                AND pa.attempt_is_passed = TRUE

            ) AS has_passed_posttest

        FROM module_summary ms

    )

    SELECT

        COUNT(*) AS total_learning,

        COUNT(*) FILTER (
            WHERE completed_modules = total_modules
            AND has_passed_posttest = TRUE
        ) AS completed_learning,

        COUNT(*) FILTER (
            WHERE NOT (
                completed_modules = total_modules
                AND has_passed_posttest = TRUE
            )
        ) AS active_learning,

        COALESCE(SUM(completed_modules), 0) AS completed_modules,

        COALESCE(SUM(total_modules), 0) AS total_modules

    FROM learning_summary
";

$resultLearning = pg_query_params(
    $conn,
    $queryLearning,
    [$_SESSION["account_id"]]
);

if (!$resultLearning) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil statistik pembelajaran.");
}

$learningStats = pg_fetch_assoc($resultLearning);

// Ubah hasil dari basis data menjadi bilangan bulat.

$totalLearning = (int) $learningStats["total_learning"];

$activeLearning = (int) $learningStats["active_learning"];

$completedLearning = (int) $learningStats["completed_learning"];

$completedModules = (int) $learningStats["completed_modules"];

$totalModules = (int) $learningStats["total_modules"];

// Hitung persentase penyelesaian seluruh modul.

$modulePercentage = 0;

if ($totalModules > 0) {
    $modulePercentage = round(
        ($completedModules / $totalModules) * 100
    );
}

// ==========================
// Pendaftaran terbaru Member
// ==========================

$queryRecentTransactions = "

    SELECT
        tr.transaction_id,
        tr.transaction_date,
        tr.transaction_time,
        tr.transaction_amount,
        tr.transaction_status,

        s.schedule_start_date,

        t.training_code,
        t.training_topic,
        t.training_category,

        latest_payment.payment_status

    FROM transactions tr

    JOIN schedules s
        ON tr.schedule_id = s.schedule_id

    JOIN trainings t
        ON s.training_id = t.training_id

    LEFT JOIN LATERAL (

        SELECT
            p.payment_status

        FROM payments p

        WHERE p.transaction_id = tr.transaction_id

        ORDER BY
            p.payment_created_at DESC,
            p.payment_id DESC

        LIMIT 1

    ) latest_payment ON TRUE

    WHERE tr.account_id = $1

    ORDER BY
        tr.transaction_date DESC,
        tr.transaction_time DESC,
        tr.transaction_id DESC

    LIMIT 5
";

$resultRecentTransactions = pg_query_params(
    $conn,
    $queryRecentTransactions,
    [$_SESSION["account_id"]]
);

if (!$resultRecentTransactions) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil data pendaftaran terbaru.");
}

$recentTransactions = [];

while ($row = pg_fetch_assoc($resultRecentTransactions)) {
    $recentTransactions[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Member - PT AJS</title>

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
        ) ?>"
    >
</head>

<body class="bg-light">

    <?php require_once __DIR__ . "/../includes/navbar.php"; ?>



    <main class="container py-5 page-content">

        <div class="mb-4">
            <h2>Halo, <?= $memberName ?>!</h2>

            <!-- Statistik pendaftaran -->
            <div class="row g-3 mb-4">

                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted">Total Pendaftaran</h6>

                            <h2>
                                <?= $totalRegistrations ?>
                            </h2>

                            <!-- Statistik pembelajaran -->
                            <div class="card shadow-sm mb-4">

                                <div class="card-body">

                                    <h5 class="card-title mb-3">
                                        Ringkasan Pembelajaran
                                    </h5>

                                    <div class="row g-3">

                                        <div class="col-md-4">

                                            <div class="border rounded p-3 h-100">

                                                <small class="text-muted">
                                                    Pembelajaran Aktif
                                                </small>

                                                <h2 class="mb-0">
                                                    <?= $activeLearning ?>
                                                </h2>

                                            </div>

                                        </div>


                                        <div class="col-md-4">

                                            <div class="border rounded p-3 h-100">

                                                <small class="text-muted">
                                                    Pelatihan Selesai
                                                </small>

                                                <h2 class="mb-0">
                                                    <?= $completedLearning ?>
                                                </h2>

                                            </div>

                                        </div>


                                        <div class="col-md-4">

                                            <div class="border rounded p-3 h-100">

                                                <small class="text-muted">
                                                    Total Pembelajaran
                                                </small>

                                                <h2 class="mb-0">
                                                    <?= $totalLearning ?>
                                                </h2>

                                            </div>

                                        </div>

                                    </div>


                                    <hr class="my-4">


                                    <div class="d-flex justify-content-between mb-2">

                                        <span>
                                            Penyelesaian Materi
                                        </span>

                                        <strong>
                                            <?= $modulePercentage ?>%
                                        </strong>

                                    </div>

                                    <div
                                        class="progress"
                                        role="progressbar"
                                        aria-label="Penyelesaian materi"
                                        aria-valuenow="<?= $modulePercentage ?>"
                                        aria-valuemin="0"
                                        aria-valuemax="100">

                                        <div
                                            class="progress-bar"
                                            style="width: <?= $modulePercentage ?>%"></div>

                                    </div>

                                    <small class="text-muted d-block mt-2">

                                        <?= $completedModules ?> dari <?= $totalModules ?>
                                        modul telah diselesaikan.

                                    </small>

                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted">Menunggu Pembayaran</h6>

                            <h2>
                                <?= $registrationStats["Menunggu Pembayaran"] ?>
                            </h2>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted">Terdaftar</h6>

                            <h2>
                                <?= $registrationStats["Terdaftar"] ?>
                            </h2>
                        </div>
                    </div>
                </div>

            </div>


            <!-- Statistik pembayaran -->
            <div class="card shadow-sm mb-4">

                <div class="card-body">

                    <h5 class="card-title mb-3">
                        Ringkasan Pembayaran
                    </h5>

                    <p class="text-muted">
                        Total catatan pembayaran: <?= $totalPayments ?>
                    </p>

                    <div class="row g-3">

                        <?php foreach ($paymentStats as $status => $total): ?>

                            <div class="col-6 col-md">
                                <div class="border rounded p-3 h-100">

                                    <small class="text-muted">
                                        <?= htmlspecialchars($status, ENT_QUOTES, "UTF-8") ?>
                                    </small>

                                    <h4 class="mb-0">
                                        <?= $total ?>
                                    </h4>

                                </div>
                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </div>

            <!-- Pendaftaran terbaru -->
            <div class="card shadow-sm mb-4">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center mb-3">

                        <h5 class="card-title mb-0">
                            Pendaftaran Terbaru
                        </h5>

                        <a
                            href="transactions/index.php"
                            class="btn btn-outline-primary btn-sm">
                            Lihat Semua
                        </a>

                    </div>


                    <?php if (empty($recentTransactions)): ?>

                        <div class="text-center text-muted py-4">

                            <p class="mb-1">
                                Belum ada pendaftaran pelatihan.
                            </p>

                            <small>
                                Silakan kunjungi katalog untuk melihat program
                                pelatihan yang tersedia.
                            </small>

                            <div class="mt-3">

                                <a
                                    href="../catalog.php"
                                    class="btn btn-primary btn-sm">
                                    Lihat Katalog
                                </a>

                            </div>

                        </div>

                    <?php else: ?>

                        <div class="table-responsive">

                            <table class="table table-hover align-middle">

                                <thead>
                                    <tr>
                                        <th>Program</th>
                                        <th>Jadwal</th>
                                        <th>Pendaftaran</th>
                                        <th>Pembayaran</th>
                                        <th class="text-end">Biaya</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach ($recentTransactions as $row): ?>

                                        <?php

                                        // Warna lencana status pendaftaran.

                                        $registrationBadge = "secondary";

                                        if ($row["transaction_status"] === "Terdaftar") {
                                            $registrationBadge = "success";
                                        } elseif (
                                            $row["transaction_status"] === "Menunggu Pembayaran"
                                        ) {
                                            $registrationBadge = "warning";
                                        }


                                        // Warna lencana status pembayaran.

                                        $paymentBadge = "secondary";

                                        switch ($row["payment_status"]) {

                                            case "Lunas":
                                                $paymentBadge = "success";
                                                break;

                                            case "Menunggu":
                                                $paymentBadge = "warning";
                                                break;

                                            case "Gagal":
                                                $paymentBadge = "danger";
                                                break;

                                            case "Kedaluwarsa":
                                            case "Dibatalkan":
                                                $paymentBadge = "secondary";
                                                break;
                                        }

                                        ?>

                                        <tr>

                                            <td>

                                                <strong>
                                                    <?= htmlspecialchars(
                                                        $row["training_topic"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>
                                                </strong>

                                                <br>

                                                <small class="text-muted">

                                                    <?= htmlspecialchars(
                                                        $row["training_code"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>

                                                    ·

                                                    <?= htmlspecialchars(
                                                        $row["training_category"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>

                                                </small>

                                            </td>


                                            <td>

                                                <?= date(
                                                    "d/m/Y",
                                                    strtotime($row["schedule_start_date"])
                                                ) ?>

                                            </td>


                                            <td>

                                                <span class="badge text-bg-<?= $registrationBadge ?>">

                                                    <?= htmlspecialchars(
                                                        $row["transaction_status"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>

                                                </span>

                                            </td>


                                            <td>

                                                <?php if ($row["payment_status"] !== null): ?>

                                                    <span class="badge text-bg-<?= $paymentBadge ?>">

                                                        <?= htmlspecialchars(
                                                            $row["payment_status"],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ) ?>

                                                    </span>

                                                <?php else: ?>

                                                    <span class="text-muted small">
                                                        Belum ada catatan pembayaran
                                                    </span>

                                                <?php endif; ?>

                                            </td>


                                            <td class="text-end text-nowrap">

                                                Rp<?= number_format(
                                                        (float) $row["transaction_amount"],
                                                        0,
                                                        ",",
                                                        "."
                                                    ) ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

            <p class="text-muted">
                Selamat datang di halaman Member PT Arta Jaya Sampurna.
                Kelola pendaftaran dan aktivitas pelatihan Anda di sini.
            </p>
        </div>

        <div class="row g-3">

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Katalog Pelatihan</h5>

                        <p class="card-text">
                            Lihat informasi dan jadwal program pelatihan
                            yang tersedia.
                        </p>

                        <a href="../catalog.php" class="btn btn-primary">
                            Lihat Katalog
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Transaksi Saya</h5>

                        <p class="card-text">
                            Periksa pendaftaran, status pembayaran,
                            dan detail transaksi Anda.
                        </p>

                        <a href="transactions/index.php" class="btn btn-primary">
                            Lihat Transaksi
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Pembelajaran</h5>

                        <p class="card-text">
                            Akses materi dan pantau perkembangan
                            pembelajaran Anda.
                        </p>

                        <a href="learning/index.php" class="btn btn-primary">
                            Buka Pembelajaran
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Sertifikat</h5>

                        <p class="card-text">
                            Lihat dan akses sertifikat pelatihan
                            yang telah memenuhi persyaratan.
                        </p>

                        <a href="certificates/index.php" class="btn btn-primary">
                            Lihat Sertifikat
                        </a>
                    </div>
                </div>
            </div>

        </div>

    </main>

    <?php
    require_once __DIR__ . "/../includes/footer.php";
    ?>

</body>

</html>
