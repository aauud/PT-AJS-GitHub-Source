<?php

require_once __DIR__ . "/includes/app_path.php";

require_once __DIR__ . "/admin/guard.php";

date_default_timezone_set("Asia/Jakarta");

// Fungsi untuk membantu menyiapkan data dashboard.
function getDashboardRows($conn, $query, $params = [])
{

    $result = pg_query_params(
        $conn,
        $query,
        $params
    );

    if (!$result) {
        error_log(pg_last_error($conn));
        exit("Gagal mengambil data dashboard.");
    }

    $rows = [];

    while ($row = pg_fetch_assoc($result)) {
        $rows[] = $row;
    }

    return $rows;
}

function getDashboardValue($value)
{

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function formatRupiah($amount)
{

    return "Rp " . number_format(
        (float) $amount,
        0,
        ",",
        "."
    );
}

function formatDashboardDate($date)
{

    if (!$date) {
        return "-";
    }

    return date("d/m/Y", strtotime($date));
}

function formatDashboardDateTime($date, $time = null)
{

    if (!$date) {
        return "-";
    }

    $value = $date;

    if ($time !== null) {
        $value .= " " . $time;
    }

    return date(
        "d/m/Y H:i",
        strtotime($value)
    );
}

// Tanggal Jakarta saat ini untuk perhitungan jadwal.
$today = date("Y-m-d");


// Ringkasan dashboard

$querySummary = "
    SELECT

        (
            SELECT COUNT(*)
            FROM trainings
        ) AS total_trainings,

        (
            SELECT COUNT(*)

            FROM accounts a

            JOIN roles r
                ON a.role_id = r.role_id

            WHERE r.role_name = 'Member'
        ) AS total_members,

        (
            SELECT COUNT(*)
            FROM transactions
            WHERE transaction_status::TEXT = 'Terdaftar'
        ) AS successful_registrations,

        (
            SELECT COUNT(*)
            FROM transactions
            WHERE transaction_status::TEXT = 'Menunggu Pembayaran'
        ) AS pending_payments,

        (
            SELECT COUNT(*)
            FROM schedules

            WHERE (
                schedule_start_date
                + (schedule_duration - 1)
            ) >= $1::DATE
        ) AS active_schedules,

        (
            SELECT COALESCE(SUM(p.payment_amount), 0)

            FROM payments p

            JOIN transactions tr
                ON p.transaction_id = tr.transaction_id

            WHERE p.payment_status::TEXT = 'Lunas'
            AND tr.transaction_status::TEXT = 'Terdaftar'
        ) AS total_revenue
";

$summaryRows = getDashboardRows(
    $conn,
    $querySummary,
    [$today]
);

$summary = $summaryRows[0];

// Tren pendaftaran bulanan
// Enam bulan terakhir, termasuk bulan berjalan.
// Pendaftaran yang dibatalkan tidak disertakan.

$queryMonthly = "
    WITH months AS (

        SELECT generate_series(
            date_trunc('month', $1::DATE::TIMESTAMP)
                - INTERVAL '5 months',

            date_trunc('month', $1::DATE::TIMESTAMP),

            INTERVAL '1 month'

        ) AS month_start

    )

    SELECT

        TO_CHAR(m.month_start, 'Mon YYYY') AS month_label,

        COUNT(tr.transaction_id)::INTEGER AS total_registrations

    FROM months m

    LEFT JOIN transactions tr

        ON date_trunc(
            'month',
            tr.transaction_date::TIMESTAMP
        ) = m.month_start

        AND tr.transaction_status::TEXT <> 'Dibatalkan'

    GROUP BY m.month_start

    ORDER BY m.month_start
";

$monthlyRows = getDashboardRows(
    $conn,
    $queryMonthly,
    [$today]
);


// Pendaftaran berdasarkan kategori pelatihan

$queryCategory = "
    SELECT

        t.training_category::TEXT AS category,

        COUNT(tr.transaction_id)::INTEGER AS total_registrations

    FROM transactions tr

    JOIN schedules s
        ON tr.schedule_id = s.schedule_id

    JOIN trainings t
        ON s.training_id = t.training_id

    WHERE tr.transaction_status::TEXT <> 'Dibatalkan'

    GROUP BY t.training_category

    ORDER BY t.training_category
";

$categoryRows = getDashboardRows(
    $conn,
    $queryCategory
);

// Distribusi status transaksi
$queryTransactionStatus = "
    SELECT

        transaction_status::TEXT AS status,

        COUNT(*)::INTEGER AS total

    FROM transactions

    GROUP BY transaction_status

    ORDER BY transaction_status
";

$transactionStatusRows = getDashboardRows(
    $conn,
    $queryTransactionStatus
);

// Distribusi status pembayaran
// Menggunakan percobaan pembayaran terbaru untuk setiap transaksi.
// pendaftaran yang berbeda.

$queryPaymentStatus = "
    SELECT

        latest.payment_status AS status,

        COUNT(*)::INTEGER AS total

    FROM (

        SELECT DISTINCT ON (transaction_id)

            transaction_id,
            payment_status::TEXT AS payment_status

        FROM payments

        ORDER BY
            transaction_id,
            payment_created_at DESC,
            payment_id DESC

    ) AS latest

    GROUP BY latest.payment_status

    ORDER BY latest.payment_status
";

$paymentStatusRows = getDashboardRows(
    $conn,
    $queryPaymentStatus
);

// Lima program pelatihan teratas
// Pendaftaran yang dibatalkan tidak disertakan.

$queryPopular = "
    SELECT

        t.training_code,
        t.training_topic,

        COUNT(tr.transaction_id)::INTEGER AS total_registrations

    FROM trainings t

    JOIN schedules s
        ON t.training_id = s.training_id

    JOIN transactions tr
        ON s.schedule_id = tr.schedule_id

    WHERE tr.transaction_status::TEXT <> 'Dibatalkan'

    GROUP BY
        t.training_id,
        t.training_code,
        t.training_topic

    ORDER BY
        total_registrations DESC,
        t.training_topic ASC

    LIMIT 5
";

$popularRows = getDashboardRows(
    $conn,
    $queryPopular
);

// Pendaftaran terbaru
$queryRecentRegistrations = "
    SELECT

        tr.transaction_id,
        tr.transaction_date,
        tr.transaction_time,
        tr.transaction_amount,
        tr.transaction_status::TEXT AS transaction_status,

        COALESCE(
            NULLIF(a.account_full_name, ''),
            a.account_username
        ) AS member_name,

        t.training_topic

    FROM transactions tr

    JOIN accounts a
        ON tr.account_id = a.account_id

    JOIN schedules s
        ON tr.schedule_id = s.schedule_id

    JOIN trainings t
        ON s.training_id = t.training_id

    ORDER BY
        tr.transaction_date DESC,
        tr.transaction_time DESC,
        tr.transaction_id DESC

    LIMIT 8
";

$recentRegistrations = getDashboardRows(
    $conn,
    $queryRecentRegistrations
);


// Percobaan pembayaran terbaru
$queryRecentPayments = "
    SELECT

        p.payment_id,
        p.payment_order_id,
        p.payment_amount,
        p.payment_status::TEXT AS payment_status,
        p.payment_created_at,

        COALESCE(
            NULLIF(a.account_full_name, ''),
            a.account_username
        ) AS member_name,

        t.training_topic

    FROM payments p

    JOIN transactions tr
        ON p.transaction_id = tr.transaction_id

    JOIN accounts a
        ON tr.account_id = a.account_id

    JOIN schedules s
        ON tr.schedule_id = s.schedule_id

    JOIN trainings t
        ON s.training_id = t.training_id

    ORDER BY
        p.payment_created_at DESC,
        p.payment_id DESC

    LIMIT 8
";

$recentPayments = getDashboardRows(
    $conn,
    $queryRecentPayments
);

// Siapkan data grafik
$monthlyLabels = array_column(
    $monthlyRows,
    "month_label"
);

$monthlyValues = array_map(
    "intval",
    array_column(
        $monthlyRows,
        "total_registrations"
    )
);

$categoryLabels = array_column(
    $categoryRows,
    "category"
);

$categoryValues = array_map(
    "intval",
    array_column(
        $categoryRows,
        "total_registrations"
    )
);

$transactionStatusLabels = array_column(
    $transactionStatusRows,
    "status"
);

$transactionStatusValues = array_map(
    "intval",
    array_column(
        $transactionStatusRows,
        "total"
    )
);

$paymentStatusLabels = array_column(
    $paymentStatusRows,
    "status"
);

$paymentStatusValues = array_map(
    "intval",
    array_column(
        $paymentStatusRows,
        "total"
    )
);

$popularLabels = array_map(
    function ($row) {
        return $row["training_topic"];
    },
    $popularRows
);

$popularValues = array_map(
    "intval",
    array_column(
        $popularRows,
        "total_registrations"
    )
);

// Pengodean JSON agar data dapat disisipkan dengan aman ke JavaScript.
$jsonFlags =
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_AMP |
    JSON_HEX_QUOT |
    JSON_UNESCAPED_UNICODE;

$chartMonthlyLabels = json_encode($monthlyLabels, $jsonFlags);
$chartMonthlyValues = json_encode($monthlyValues, $jsonFlags);

$chartCategoryLabels = json_encode($categoryLabels, $jsonFlags);
$chartCategoryValues = json_encode($categoryValues, $jsonFlags);

$chartTransactionLabels = json_encode($transactionStatusLabels, $jsonFlags);
$chartTransactionValues = json_encode($transactionStatusValues, $jsonFlags);

$chartPaymentLabels = json_encode($paymentStatusLabels, $jsonFlags);
$chartPaymentValues = json_encode($paymentStatusValues, $jsonFlags);

$chartPopularLabels = json_encode($popularLabels, $jsonFlags);
$chartPopularValues = json_encode($popularValues, $jsonFlags);

// Nama tampilan Admin
$adminName = $_SESSION["full_name"]
    ?? $_SESSION["username"]
    ?? "Admin";

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Admin - PT AJS</title>

    <!-- CSS Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.6.1/dist/chart.umd.min.js"></script>

    <style>
        body {
            background-color: #f5f6fa;
        }

        .dashboard-header {
            background: #ffffff;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .summary-card {
            border: none;
            border-radius: 12px;
            height: 100%;
        }

        .summary-label {
            color: #6c757d;
            font-size: 14px;
        }

        .summary-value {
            font-size: 27px;
            font-weight: 700;
            margin-top: 8px;
            margin-bottom: 0;
        }

        .dashboard-card {
            border: none;
            border-radius: 12px;
            height: 100%;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .section-title {
            font-weight: 600;
            margin-bottom: 16px;
        }

        .dashboard-link {
            text-decoration: none;
            color: inherit;
        }

        .dashboard-link:hover {
            transform: translateY(-2px);
            transition: 0.2s;
        }
    </style>


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


        <div class="container-fluid px-4 py-4">

            <!-- Judul dashboard -->
            <div class="dashboard-header shadow-sm">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

                    <div>

                        <h2 class="mb-1">
                            Dashboard PT Arta Jaya Sampurna
                        </h2>

                        <p class="text-muted mb-0">

                            Selamat datang,

                            <strong>
                                <?= getDashboardValue($adminName) ?>
                            </strong>

                            !

                        </p>

                        <small class="text-muted">
                            Data diperbarui:
                            <?= date("d/m/Y H:i") ?> WIB
                        </small>

                    </div>

                    <div>

                        <a
                            href="catalog.php"
                            target="_blank"
                            class="btn btn-outline-primary">
                            Lihat Katalog
                        </a>

                    </div>

                </div>

            </div>

            <!-- Kartu ringkasan -->
            <h5 class="section-title">
                Ringkasan Sistem
            </h5>

            <div class="row g-3 mb-4">

                <div class="col-12 col-sm-6 col-xl-2">

                    <div class="card summary-card shadow-sm">

                        <div class="card-body">

                            <div class="summary-label">
                                Program Pelatihan
                            </div>

                            <p class="summary-value">

                                <?= number_format(
                                    (int) $summary["total_trainings"]
                                ) ?>

                            </p>

                        </div>

                    </div>

                </div>

                <div class="col-12 col-sm-6 col-xl-2">

                    <div class="card summary-card shadow-sm">

                        <div class="card-body">

                            <div class="summary-label">
                                Total Member
                            </div>

                            <p class="summary-value">

                                <?= number_format(
                                    (int) $summary["total_members"]
                                ) ?>

                            </p>

                        </div>

                    </div>

                </div>

                <div class="col-12 col-sm-6 col-xl-2">

                    <div class="card summary-card shadow-sm">

                        <div class="card-body">

                            <div class="summary-label">
                                Pendaftaran Berhasil
                            </div>

                            <p class="summary-value">

                                <?= number_format(
                                    (int) $summary["successful_registrations"]
                                ) ?>

                            </p>

                        </div>

                    </div>

                </div>

                <div class="col-12 col-sm-6 col-xl-2">

                    <div class="card summary-card shadow-sm">

                        <div class="card-body">

                            <div class="summary-label">
                                Menunggu Pembayaran
                            </div>

                            <p class="summary-value">

                                <?= number_format(
                                    (int) $summary["pending_payments"]
                                ) ?>

                            </p>

                        </div>

                    </div>

                </div>

                <div class="col-12 col-sm-6 col-xl-2">

                    <div class="card summary-card shadow-sm">

                        <div class="card-body">

                            <div class="summary-label">
                                Jadwal Aktif & Mendatang
                            </div>

                            <p class="summary-value">

                                <?= number_format(
                                    (int) $summary["active_schedules"]
                                ) ?>

                            </p>

                        </div>

                    </div>

                </div>

                <div class="col-12 col-sm-6 col-xl-2">

                    <div class="card summary-card shadow-sm">

                        <div class="card-body">

                            <div class="summary-label">
                                Total Pendapatan
                            </div>

                            <p class="summary-value fs-5">

                                <?= formatRupiah(
                                    $summary["total_revenue"]
                                ) ?>

                            </p>

                        </div>

                    </div>

                </div>

            </div>

            <!-- Grafik -->
            <h5 class="section-title">
                Visualisasi Data
            </h5>

            <div class="row g-3 mb-4">

                <!-- Pendaftaran bulanan -->

                <div class="col-12 col-xl-8">

                    <div class="card dashboard-card shadow-sm">

                        <div class="card-body">

                            <h5 class="card-title">
                                Tren Pendaftaran Bulanan
                            </h5>

                            <p class="text-muted small">
                                Jumlah pendaftaran selama enam bulan terakhir,
                                tidak termasuk transaksi yang dibatalkan.
                            </p>

                            <div class="chart-container">

                                <canvas id="monthlyChart"></canvas>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- Kategori -->

                <div class="col-12 col-xl-4">

                    <div class="card dashboard-card shadow-sm">

                        <div class="card-body">

                            <h5 class="card-title">
                                Pendaftaran Berdasarkan Kategori
                            </h5>

                            <p class="text-muted small">
                                Technical dan Nontechnical.
                            </p>

                            <div class="chart-container">

                                <canvas id="categoryChart"></canvas>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- Status transaksi -->

                <div class="col-12 col-xl-6">

                    <div class="card dashboard-card shadow-sm">

                        <div class="card-body">

                            <h5 class="card-title">
                                Status Pendaftaran
                            </h5>

                            <div class="chart-container">

                                <canvas id="transactionStatusChart"></canvas>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- Status pembayaran -->

                <div class="col-12 col-xl-6">

                    <div class="card dashboard-card shadow-sm">

                        <div class="card-body">

                            <h5 class="card-title">
                                Status Pembayaran
                            </h5>

                            <p class="text-muted small">
                                Berdasarkan percobaan pembayaran terakhir
                                pada setiap transaksi.
                            </p>

                            <div class="chart-container">

                                <canvas id="paymentStatusChart"></canvas>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- Pelatihan populer -->

                <div class="col-12">

                    <div class="card dashboard-card shadow-sm">

                        <div class="card-body">

                            <h5 class="card-title">
                                Lima Program dengan Pendaftaran Terbanyak
                            </h5>

                            <div class="chart-container">

                                <canvas id="popularChart"></canvas>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <!-- Pendaftaran terbaru -->

            <div class="row g-3 mb-4">

                <div class="col-12 col-xl-7">

                    <div class="card dashboard-card shadow-sm">

                        <div class="card-body">

                            <h5 class="card-title">
                                Pendaftaran Terbaru
                            </h5>

                            <div class="table-responsive">

                                <table class="table table-sm table-striped table-hover">

                                    <thead>

                                        <tr>
                                            <th>Member</th>
                                            <th>Program</th>
                                            <th>Tanggal</th>
                                            <th>Status</th>
                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php if (count($recentRegistrations) > 0): ?>

                                            <?php foreach ($recentRegistrations as $row): ?>

                                                <tr>

                                                    <td>
                                                        <?= getDashboardValue($row["member_name"]) ?>
                                                    </td>

                                                    <td>
                                                        <?= getDashboardValue($row["training_topic"]) ?>
                                                    </td>

                                                    <td>
                                                        <?= formatDashboardDateTime(
                                                            $row["transaction_date"],
                                                            $row["transaction_time"]
                                                        ) ?>
                                                    </td>

                                                    <td>

                                                        <?php if ($row["transaction_status"] === "Terdaftar"): ?>

                                                            <span class="badge bg-success">
                                                                Terdaftar
                                                            </span>

                                                        <?php elseif ($row["transaction_status"] === "Menunggu Pembayaran"): ?>

                                                            <span class="badge bg-warning text-dark">
                                                                Menunggu Pembayaran
                                                            </span>

                                                        <?php else: ?>

                                                            <span class="badge bg-secondary">
                                                                <?= getDashboardValue($row["transaction_status"]) ?>
                                                            </span>

                                                        <?php endif; ?>

                                                    </td>

                                                </tr>

                                            <?php endforeach; ?>

                                        <?php else: ?>

                                            <tr>

                                                <td colspan="4" class="text-center text-muted">
                                                    Belum ada data pendaftaran.
                                                </td>

                                            </tr>

                                        <?php endif; ?>

                                    </tbody>

                                </table>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- Pembayaran terbaru -->

                <div class="col-12 col-xl-5">

                    <div class="card dashboard-card shadow-sm">

                        <div class="card-body">

                            <h5 class="card-title">
                                Pembayaran Terbaru
                            </h5>

                            <div class="table-responsive">

                                <table class="table table-sm table-striped table-hover">

                                    <thead>

                                        <tr>
                                            <th>Member</th>
                                            <th>Nominal</th>
                                            <th>Status</th>
                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php if (count($recentPayments) > 0): ?>

                                            <?php foreach ($recentPayments as $row): ?>

                                                <tr>

                                                    <td>
                                                        <?= getDashboardValue($row["member_name"]) ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?= getDashboardValue($row["training_topic"]) ?>
                                                        </small>
                                                    </td>

                                                    <td>
                                                        <?= formatRupiah($row["payment_amount"]) ?>
                                                    </td>

                                                    <td>

                                                        <?php if ($row["payment_status"] === "Lunas"): ?>

                                                            <span class="badge bg-success">
                                                                Lunas
                                                            </span>

                                                        <?php elseif ($row["payment_status"] === "Menunggu"): ?>

                                                            <span class="badge bg-warning text-dark">
                                                                Menunggu
                                                            </span>

                                                        <?php else: ?>

                                                            <span class="badge bg-secondary">
                                                                <?= getDashboardValue($row["payment_status"]) ?>
                                                            </span>

                                                        <?php endif; ?>

                                                    </td>

                                                </tr>

                                            <?php endforeach; ?>

                                        <?php else: ?>

                                            <tr>

                                                <td colspan="3" class="text-center text-muted">
                                                    Belum ada data pembayaran.
                                                </td>

                                            </tr>

                                        <?php endif; ?>

                                    </tbody>

                                </table>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <!-- Navigasi Admin -->

            <div class="card dashboard-card shadow-sm mb-4">

                <div class="card-body">

                    <h5 class="card-title mb-3">
                        Pengelolaan Sistem
                    </h5>

                    <div class="d-flex flex-wrap gap-2">

                        <a
                            href="admin/trainings/index.php"
                            class="btn btn-primary">
                            Kelola Pelatihan
                        </a>

                        <a
                            href="admin/schedules/index.php"
                            class="btn btn-primary">
                            Kelola Jadwal
                        </a>

                        <a
                            href="admin/trainings/index.php"
                            class="btn btn-outline-primary">
                            Kelola Modul dan Posttest
                        </a>

                        <a
                            href="admin/schedules/index.php"
                            class="btn btn-outline-primary">
                            Kelola Kehadiran
                        </a>

                        <a
                            href="admin/inquiries/index.php"
                            class="btn btn-outline-primary">
                            Pertanyaan Masuk
                        </a>

                    </div>

                </div>

            </div>

            <div class="text-center text-muted small mb-3">

                Sistem Informasi Program Pelatihan PT Arta Jaya Sampurna

            </div>

        </div>

        <!-- Konfigurasi Chart.js -->

        <script>
            const chartFont = {
                family: "Arial, sans-serif"
            };

            // Pendaftaran bulanan.
            new Chart(
                document.getElementById("monthlyChart"), {
                    type: "line",

                    data: {
                        labels: <?= $chartMonthlyLabels ?>,

                        datasets: [{
                            label: "Jumlah Pendaftaran",
                            data: <?= $chartMonthlyValues ?>,
                            borderColor: "#0d6efd",
                            backgroundColor: "rgba(13, 110, 253, 0.12)",
                            fill: true,
                            tension: 0.3
                        }]
                    },

                    options: {
                        responsive: true,
                        maintainAspectRatio: false,

                        plugins: {
                            legend: {
                                display: true
                            }
                        },

                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                }
            );

            // Kategori pelatihan.
            new Chart(
                document.getElementById("categoryChart"), {
                    type: "doughnut",

                    data: {
                        labels: <?= $chartCategoryLabels ?>,

                        datasets: [{
                            data: <?= $chartCategoryValues ?>,
                            backgroundColor: [
                                "#0d6efd",
                                "#20c997"
                            ]
                        }]
                    },

                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                }
            );

            // Status transaksi.
            new Chart(
                document.getElementById("transactionStatusChart"), {
                    type: "pie",

                    data: {
                        labels: <?= $chartTransactionLabels ?>,

                        datasets: [{
                            data: <?= $chartTransactionValues ?>,
                            backgroundColor: [
                                "#198754",
                                "#ffc107",
                                "#dc3545"
                            ]
                        }]
                    },

                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                }
            );

            // Status pembayaran.
            new Chart(
                document.getElementById("paymentStatusChart"), {
                    type: "doughnut",

                    data: {
                        labels: <?= $chartPaymentLabels ?>,

                        datasets: [{
                            data: <?= $chartPaymentValues ?>,
                            backgroundColor: [
                                "#198754",
                                "#ffc107",
                                "#dc3545",
                                "#6c757d",
                                "#0d6efd"
                            ]
                        }]
                    },

                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                }
            );

            // Program pelatihan terpopuler.
            new Chart(
                document.getElementById("popularChart"), {
                    type: "bar",

                    data: {
                        labels: <?= $chartPopularLabels ?>,

                        datasets: [{
                            label: "Jumlah Pendaftaran",
                            data: <?= $chartPopularValues ?>,
                            backgroundColor: "#0d6efd"
                        }]
                    },

                    options: {
                        indexAxis: "y",

                        responsive: true,
                        maintainAspectRatio: false,

                        scales: {
                            x: {
                                beginAtZero: true,

                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                }
            );
        </script>

    </main>

    <?php
    require_once __DIR__ . "/includes/footer.php";
    ?>

</body>

</html>