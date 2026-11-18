<?php

require_once __DIR__ . "/../../includes/app_path.php";

require_once __DIR__ . "/guard.php";

// 1. Validasi ID jadwal.
$scheduleId = filter_var(
    $_GET["schedule_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($scheduleId === false || $scheduleId === null) {
    http_response_code(400);
    exit("ID jadwal tidak valid.");
}

// 2. Ambil informasi jadwal.
$querySchedule = "
    SELECT
        s.schedule_id,
        s.schedule_start_date,
        s.schedule_duration,

        t.training_code,
        t.training_topic,
        t.training_interaction,
        t.training_online_type

    FROM schedules s

    JOIN trainings t
        ON s.training_id = t.training_id

    WHERE s.schedule_id = $1
";

$resultSchedule = pg_query_params(
    $conn,
    $querySchedule,
    [$scheduleId]
);

if (!$resultSchedule) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil data jadwal.");
}

$schedule = pg_fetch_assoc($resultSchedule);

if (!$schedule) {
    http_response_code(404);
    exit("Jadwal tidak ditemukan.");
}

// 3. Pencatatan kehadiran hanya berlaku untuk pelatihan Luring dan Daring Sinkron.
$isAsync = (
    $schedule["training_interaction"] === "Daring" &&
    $schedule["training_online_type"] === "Asinkron"
);

if ($isAsync) {

    http_response_code(400);

    exit("Pelatihan daring asinkron menggunakan progres modul sebagai persyaratan pembelajaran.");
}

// 4. Ambil daftar Member terdaftar beserta status kehadirannya.
$queryMembers = "
    SELECT
        tr.transaction_id,

        a.account_full_name,
        a.account_username,
        a.account_email,

        ar.attendance_id,
        ar.attendance_status,
        ar.attendance_evidence_type,
        ar.attendance_evidence_path,
        ar.attendance_submitted_at,
        ar.attendance_verified_at,
        ar.attendance_notes

    FROM transactions tr

    JOIN accounts a
        ON tr.account_id = a.account_id

    LEFT JOIN attendance_records ar
        ON tr.transaction_id = ar.transaction_id

    WHERE tr.schedule_id = $1
    AND tr.transaction_status = 'Terdaftar'

    ORDER BY tr.transaction_id ASC
";

$resultMembers = pg_query_params(
    $conn,
    $queryMembers,
    [$scheduleId]
);

if (!$resultMembers) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil data peserta.");
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kelola Kehadiran</title>

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

    <?php require_once __DIR__ . "/../../includes/navbar.php"; ?>

    <main class="page-content">

        <div class="container mt-5 mb-5">

            <h2>Kelola Kehadiran Peserta</h2>

            <p>
                Program:
                <strong>
                    <?= htmlspecialchars($schedule["training_topic"]) ?>
                </strong>
            </p>

            <p>
                Kode:
                <?= htmlspecialchars($schedule["training_code"]) ?>
            </p>

            <p>
                Metode:

                <?= htmlspecialchars($schedule["training_interaction"]) ?>

                <?= $schedule["training_online_type"]
                    ? " - " . htmlspecialchars($schedule["training_online_type"])
                    : "" ?>
            </p>

            <a
                href="../schedules/index.php"
                class="btn btn-secondary mb-3">
                Kembali ke Jadwal
            </a>

            <?php if (isset($_GET["updated"])): ?>

                <div class="alert alert-success">
                    Status kehadiran berhasil diperbarui.
                </div>

            <?php endif; ?>

            <div class="table-responsive">

                <table class="table table-bordered table-striped align-middle">

                    <thead class="table-dark">

                        <tr>
                            <th>Peserta</th>
                            <th>Status</th>
                            <th>Bukti Kehadiran</th>
                            <th>Waktu Pengiriman</th>
                            <th>Catatan</th>
                            <th>Verifikasi</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (pg_num_rows($resultMembers) > 0): ?>

                            <?php while ($row = pg_fetch_assoc($resultMembers)): ?>

                                <?php

                                $status = $row["attendance_status"]
                                    ?? "Belum Dikirim";

                                ?>

                                <tr>

                                    <td>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $row["account_full_name"]
                                                    ?: $row["account_username"]
                                            ) ?>

                                        </strong>

                                        <br>

                                        <small class="text-muted">

                                            <?= htmlspecialchars($row["account_email"]) ?>

                                        </small>

                                        <br>

                                        <small class="text-muted">
                                            Transaksi #<?= (int) $row["transaction_id"] ?>
                                        </small>

                                    </td>

                                    <td>

                                        <?php if ($status === "Hadir"): ?>

                                            <span class="badge bg-success">
                                                Hadir
                                            </span>

                                        <?php elseif ($status === "Tidak Hadir"): ?>

                                            <span class="badge bg-danger">
                                                Tidak Hadir
                                            </span>

                                        <?php elseif ($status === "Menunggu Verifikasi"): ?>

                                            <span class="badge bg-warning text-dark">
                                                Menunggu Verifikasi
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-secondary">
                                                Belum Dikirim
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <?php if (!empty($row["attendance_evidence_path"])): ?>

                                            <p class="mb-1">

                                                <?= htmlspecialchars(
                                                    $row["attendance_evidence_type"]
                                                ) ?>

                                            </p>

                                            <a
                                                href="../../<?= htmlspecialchars($row["attendance_evidence_path"]) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer">
                                                Lihat Bukti
                                            </a>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                Tidak ada file
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <?= $row["attendance_submitted_at"]
                                            ? htmlspecialchars($row["attendance_submitted_at"])
                                            : "-" ?>

                                    </td>

                                    <td>

                                        <?= htmlspecialchars(
                                            $row["attendance_notes"] ?? "-"
                                        ) ?>

                                    </td>

                                    <td>

                                        <form
                                            method="POST"
                                            action="update.php">

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">

                                            <input
                                                type="hidden"
                                                name="schedule_id"
                                                value="<?= (int) $scheduleId ?>">

                                            <input
                                                type="hidden"
                                                name="transaction_id"
                                                value="<?= (int) $row["transaction_id"] ?>">

                                            <div class="mb-2">

                                                <select
                                                    name="attendance_status"
                                                    class="form-select form-select-sm"
                                                    required>

                                                    <option value="Hadir">
                                                        Hadir
                                                    </option>

                                                    <option value="Tidak Hadir">
                                                        Tidak Hadir
                                                    </option>

                                                </select>

                                            </div>

                                            <div class="mb-2">

                                                <textarea
                                                    name="attendance_notes"
                                                    class="form-control form-control-sm"
                                                    rows="2"
                                                    placeholder="Catatan verifikasi (opsional)"></textarea>

                                            </div>

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-primary w-100">
                                                Simpan Verifikasi
                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="6" class="text-center">

                                    Belum ada peserta dengan status Terdaftar.

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </main>

    <?php require_once __DIR__ . "/../../includes/footer.php"; ?>

</body>

</html>