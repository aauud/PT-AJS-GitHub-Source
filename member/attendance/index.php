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

// 2. Verifikasi kepemilikan transaksi dan status pendaftaran.
$training = getAttendanceTransaction(
    $conn,
    $transactionId,
    $_SESSION["account_id"]
);

if (!$training) {
    http_response_code(404);
    exit("Pendaftaran tidak ditemukan atau belum terdaftar.");
}

// 3. Tentukan metode pelaksanaan pelatihan.
$isAsync = (
    $training["training_interaction"] === "Daring" &&
    $training["training_online_type"] === "Asinkron"
);

$isSynchronous = (
    $training["training_interaction"] === "Daring" &&
    $training["training_online_type"] === "Sinkron"
);

$isOffline = (
    $training["training_interaction"] === "Luring"
);

// 4. Buat token CSRF.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// 5. Ambil data kehadiran yang sudah ada.
$queryAttendance = "
    SELECT *
    FROM attendance_records
    WHERE transaction_id = $1
";

$resultAttendance = pg_query_params(
    $conn,
    $queryAttendance,
    [$transactionId]
);

if (!$resultAttendance) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil data kehadiran.");
}

$attendance = pg_fetch_assoc($resultAttendance);

$status = $attendance
    ? $attendance["attendance_status"]
    : "Belum Dikirim";

$error = "";

// 6. Periksa apakah jadwal sudah dimulai.
$timezone = new DateTimeZone("Asia/Jakarta");

$scheduleStart = DateTime::createFromFormat(
    "Y-m-d H:i:s",
    $training["schedule_start_date"] . " " .
        ($training["schedule_start_time"] ?? "00:00:00"),
    $timezone
);

$currentTime = new DateTime("now", $timezone);

$canSubmit = (
    $scheduleStart &&
    $currentTime >= $scheduleStart &&
    !in_array($status, ["Hadir", "Tidak Hadir"], true)
);

// 7. Proses pengiriman bukti kehadiran.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if ($isAsync) {

        $error = "Pelatihan daring asinkron tidak memerlukan pengumpulan bukti kehadiran.";
    } elseif (!verifyAttendanceCsrf()) {

        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";
    } elseif (!$canSubmit) {

        $error = "Pengumpulan bukti kehadiran belum tersedia.";
    } else {

        $file = $_FILES["attendance_evidence"] ?? null;

        if (!$file || $file["error"] !== UPLOAD_ERR_OK) {

            $error = "Silakan pilih foto atau screenshot yang valid.";
        } elseif ($file["size"] > 5 * 1024 * 1024) {

            $error = "Ukuran gambar maksimal 5 MB.";
        } else {

            // Verifikasi tipe MIME berkas yang sebenarnya.
            $finfo = new finfo(FILEINFO_MIME_TYPE);

            $mime = $finfo->file($file["tmp_name"]);

            $allowedTypes = [
                "image/jpeg" => "jpg",
                "image/png" => "png",
                "image/webp" => "webp"
            ];

            $imageInfo = @getimagesize($file["tmp_name"]);

            if (
                !isset($allowedTypes[$mime]) ||
                !$imageInfo ||
                $imageInfo["mime"] !== $mime
            ) {

                $error = "Format gambar harus JPG, PNG, atau WEBP.";
            } else {

                // Buat nama berkas secara acak.
                $extension = $allowedTypes[$mime];

                $fileName = bin2hex(random_bytes(16))
                    . "."
                    . $extension;

                // Simpan di folder uploads/attendance pada direktori utama proyek.
                $projectRoot = dirname(__DIR__, 2);

                $uploadDirectory = $projectRoot
                    . "/uploads/attendance";

                if (
                    !is_dir($uploadDirectory) &&
                    !mkdir($uploadDirectory, 0755, true)
                ) {

                    $error = "Gagal menyiapkan folder penyimpanan.";
                } elseif (!is_writable($uploadDirectory)) {

                    $error = "Folder penyimpanan tidak dapat digunakan.";
                } else {

                    $destination = $uploadDirectory
                        . "/"
                        . $fileName;

                    $relativePath = "uploads/attendance/"
                        . $fileName;

                    if (!move_uploaded_file(
                        $file["tmp_name"],
                        $destination
                    )) {

                        $error = "Gagal mengunggah gambar.";
                    } else {

                        $evidenceType = $isOffline
                            ? "Foto Kelas"
                            : "Screenshot Meeting";

                        $oldPath = $attendance["attendance_evidence_path"] ?? null;

                        // Simpan atau perbarui bukti yang masih menunggu verifikasi.
                        // Jangan timpa keputusan kehadiran yang sudah final.
                        $querySave = "
                            INSERT INTO attendance_records (
                                transaction_id,
                                attendance_status,
                                attendance_evidence_type,
                                attendance_evidence_path,
                                attendance_submitted_at
                            )

                            VALUES (
                                $1,
                                'Menunggu Verifikasi',
                                $2,
                                $3,
                                CURRENT_TIMESTAMP
                            )

                            ON CONFLICT (transaction_id)

                            DO UPDATE SET
                                attendance_status = 'Menunggu Verifikasi',
                                attendance_evidence_type = EXCLUDED.attendance_evidence_type,
                                attendance_evidence_path = EXCLUDED.attendance_evidence_path,
                                attendance_submitted_at = CURRENT_TIMESTAMP,
                                attendance_verified_by = NULL,
                                attendance_verified_at = NULL,
                                attendance_notes = NULL

                            WHERE attendance_records.attendance_status
                                NOT IN ('Hadir', 'Tidak Hadir')

                            RETURNING attendance_id
                        ";

                        $resultSave = pg_query_params(
                            $conn,
                            $querySave,
                            [
                                $transactionId,
                                $evidenceType,
                                $relativePath
                            ]
                        );

                        if (!$resultSave) {

                            unlink($destination);

                            error_log(pg_last_error($conn));

                            $error = "Gagal menyimpan bukti kehadiran.";
                        } else {

                            $saved = pg_fetch_assoc($resultSave);

                            if (!$saved) {

                                unlink($destination);

                                $error = "Status kehadiran sudah ditetapkan oleh Admin.";
                            } else {

                                // Hapus berkas sebelumnya setelah penggantian berhasil.
                                if (
                                    $oldPath &&
                                    $oldPath !== $relativePath &&
                                    strpos($oldPath, "uploads/attendance/") === 0
                                ) {

                                    $oldFile = $projectRoot
                                        . "/"
                                        . $oldPath;

                                    if (is_file($oldFile)) {
                                        unlink($oldFile);
                                    }
                                }

                                header(
                                    "Location: index.php?transaction_id="
                                        . $transactionId
                                        . "&submitted=1"
                                );

                                exit;
                            }
                        }
                    }
                }
            }
        }
    }
}

$success = (
    isset($_GET["submitted"]) &&
    $_GET["submitted"] === "1"
);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Informasi Kehadiran</title>

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

            <h2>Informasi Kehadiran</h2>

            <h4>
                <?= htmlspecialchars($training["training_topic"]) ?>
            </h4>

            <p>
                Kode:
                <?= htmlspecialchars($training["training_code"]) ?>
            </p>

            <a
                href="../transactions/detail.php?id=<?= (int) $transactionId ?>"
                class="btn btn-secondary mb-3">
                Kembali ke Transaksi
            </a>

            <?php if ($success): ?>

                <div class="alert alert-success">
                    Bukti kehadiran berhasil dikirim.
                    Silakan menunggu verifikasi Admin.
                </div>

            <?php endif; ?>

            <?php if ($error !== ""): ?>

                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>

            <?php if ($isAsync): ?>

                <div class="alert alert-info">
                    Pelatihan daring asinkron tidak memerlukan pengumpulan
                    bukti kehadiran. Selesaikan seluruh modul pembelajaran
                    untuk memenuhi persyaratan posttest.
                </div>

                <a
                    href="../learning/index.php"
                    class="btn btn-primary">
                    My Learning
                </a>

            <?php else: ?>

                <div class="card">

                    <div class="card-body">

                        <h5>Persyaratan Kehadiran</h5>

                        <?php if ($isOffline): ?>

                            <p>
                                Unggah foto kelas bersama sebagai bukti
                                keikutsertaan dalam pelatihan tatap muka.
                                Kehadiran tetap akan diperiksa oleh Admin.
                            </p>

                        <?php else: ?>

                            <p>
                                Unggah screenshot yang menunjukkan
                                keikutsertaan Anda dalam pertemuan daring.
                                Admin akan memeriksa bukti tersebut.
                            </p>

                        <?php endif; ?>

                        <hr>

                        <p>
                            <strong>Status:</strong>

                            <?php if ($status === "Hadir"): ?>

                                <span class="badge bg-success">Hadir</span>

                            <?php elseif ($status === "Tidak Hadir"): ?>

                                <span class="badge bg-danger">Tidak Hadir</span>

                            <?php elseif ($status === "Menunggu Verifikasi"): ?>

                                <span class="badge bg-warning text-dark">
                                    Menunggu Verifikasi
                                </span>

                            <?php else: ?>

                                <span class="badge bg-secondary">
                                    Belum Dikirim
                                </span>

                            <?php endif; ?>

                        </p>

                        <?php if ($attendance && !empty($attendance["attendance_evidence_path"])): ?>

                            <p>
                                Bukti yang telah dikirim:
                            </p>

                            <a
                                href="../../<?= htmlspecialchars($attendance["attendance_evidence_path"]) ?>"
                                target="_blank">
                                Lihat Bukti Kehadiran
                            </a>

                        <?php endif; ?>

                        <?php if (
                            $status === "Hadir" ||
                            $status === "Tidak Hadir"
                        ): ?>

                            <div class="alert alert-info mt-3 mb-0">
                                Status kehadiran Anda sudah ditetapkan oleh Admin.
                            </div>

                        <?php elseif (!$canSubmit): ?>

                            <div class="alert alert-info mt-3 mb-0">
                                Pengumpulan bukti tersedia setelah jadwal pelatihan dimulai.

                            </div>

                        <?php else: ?>

                            <form
                                method="POST"
                                enctype="multipart/form-data"
                                class="mt-3">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">

                                <div class="mb-3">

                                    <label
                                        for="attendance_evidence"
                                        class="form-label">
                                        <?= $isOffline
                                            ? "Foto Kelas"
                                            : "Screenshot Pertemuan Daring" ?>
                                    </label>

                                    <input
                                        type="file"
                                        name="attendance_evidence"
                                        id="attendance_evidence"
                                        class="form-control"
                                        accept="image/jpeg,image/png,image/webp"
                                        required>

                                    <div class="form-text">
                                        Format JPG, PNG, atau WEBP. Maksimal 5 MB.
                                    </div>

                                </div>

                                <button
                                    type="submit"
                                    class="btn btn-primary">
                                    Kirim Bukti Kehadiran
                                </button>

                            </form>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </main>

    <?php require_once __DIR__ . "/../../includes/footer.php"; ?>

</body>

</html>