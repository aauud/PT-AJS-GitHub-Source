<?php

require_once __DIR__ . "/../../includes/app_path.php";


require_once __DIR__ . "/../guard.php";

// Buat token CSRF untuk melindungi penyimpanan jadwal.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION["csrf_token"], $submittedToken)
    ) {
        http_response_code(403);
        exit("Permintaan tidak valid. Silakan muat ulang halaman.");
    }
}

// Siapkan nilai awal form.
$error = "";
$form = $_POST;
$posttestOpenAt = null;
$posttestCloseAt = null;

// Ambil daftar pelatihan yang tersedia.
$queryTrainings = "
    SELECT
        training_id,
        training_code,
        training_topic,
        training_interaction,
        training_online_type
    FROM trainings
    ORDER BY training_code
";

$resultTrainings = pg_query($conn, $queryTrainings);

if (!$resultTrainings) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil data pelatihan.");
}

$trainings = [];

while ($row = pg_fetch_assoc($resultTrainings)) {
    $trainings[] = $row;
}

// 5. Proses pengiriman formulir.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $trainingId = $_POST["training_id"] ?? "";
    $startDate = trim($_POST["schedule_start_date"] ?? "");
    $duration = $_POST["schedule_duration"] ?? "";

    // Ambil data pelatihan yang dipilih dari basis data.
    $querySelectedTraining = "
        SELECT training_interaction, training_online_type
        FROM trainings
        WHERE training_id = $1
    ";

    $resultSelectedTraining = false;

    if (filter_var($trainingId, FILTER_VALIDATE_INT)) {

        $resultSelectedTraining = pg_query_params(
            $conn,
            $querySelectedTraining,
            [$trainingId]
        );
    }

    $selectedTraining = $resultSelectedTraining
        ? pg_fetch_assoc($resultSelectedTraining)
        : false;

    // Nilai awal untuk kolom opsional.
    $startTime = null;
    $endTime = null;
    $meetingLink = null;
    $location = null;

    if (!$selectedTraining) {

        $error = "Silakan pilih pelatihan yang valid.";
    } elseif ($startDate === "") {

        $error = "Tanggal mulai wajib diisi.";
    } else {

        // Validasi tanggal.
        $dateObject = DateTime::createFromFormat("!Y-m-d", $startDate);

        if (
            !$dateObject ||
            $dateObject->format("Y-m-d") !== $startDate
        ) {

            $error = "Format tanggal tidak valid.";
        } elseif (
            !filter_var($duration, FILTER_VALIDATE_INT) ||
            (int) $duration <= 0
        ) {

            $error = "Durasi harus berupa angka lebih dari 0.";
        } else {

            $method = $selectedTraining["training_interaction"];
            $onlineType = $selectedTraining["training_online_type"];

            // Daring Asinkron.
            // Tidak memerlukan waktu pertemuan tetap.
            if (
                $method === "Daring" &&
                $onlineType === "Asinkron"
            ) {

                // Biarkan seluruh informasi jadwal opsional bernilai NULL.

            } else {

                // Daring Sinkron atau Luring.
                $startTime = $_POST["schedule_start_time"] ?? "";
                $endTime = $_POST["schedule_end_time"] ?? "";

                if (
                    !preg_match(
                        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                        $startTime
                    ) ||
                    !preg_match(
                        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                        $endTime
                    )
                ) {

                    $error = "Waktu mulai dan selesai wajib diisi.";
                } elseif ($endTime <= $startTime) {

                    $error = "Waktu selesai harus setelah waktu mulai.";
                } else {

                    // Daring Sinkron.
                    if (
                        $method === "Daring" &&
                        $onlineType === "Sinkron"
                    ) {

                        $meetingLink = trim(
                            $_POST["schedule_link"] ?? ""
                        );

                        if (
                            !filter_var($meetingLink, FILTER_VALIDATE_URL) ||
                            strlen($meetingLink) > 255
                        ) {

                            $error = "Link pertemuan tidak valid.";
                        }
                    }

                    // Luring.
                    elseif ($method === "Luring") {

                        $location = trim(
                            $_POST["schedule_location"] ?? ""
                        );

                        if ($location === "") {

                            $error = "Lokasi pelaksanaan wajib diisi.";
                        } elseif (strlen($location) > 100) {

                            $error = "Lokasi maksimal 100 karakter.";
                        }
                    }
                }
            }
        }
    }

    //// Validasi rentang waktu pelaksanaan posttest.
    // Pelatihan Daring Asinkron tidak menggunakan rentang waktu ujian tetap.
    if (
        $error === "" &&
        !(
            $selectedTraining["training_interaction"] === "Daring" &&
            $selectedTraining["training_online_type"] === "Asinkron"
        )
    ) {

        $openInput = trim(
            (string) ($_POST["schedule_posttest_open_at"] ?? "")
        );

        $closeInput = trim(
            (string) ($_POST["schedule_posttest_close_at"] ?? "")
        );

        $timezone = new DateTimeZone("Asia/Jakarta");

        $openDate = DateTime::createFromFormat(
            '!Y-m-d\TH:i',
            $openInput,
            $timezone
        );

        $closeDate = DateTime::createFromFormat(
            '!Y-m-d\TH:i',
            $closeInput,
            $timezone
        );

        // Validasi masukan tanggal dan waktu.
        if (
            !$openDate ||
            !$closeDate ||
            $openDate->format('Y-m-d\TH:i') !== $openInput ||
            $closeDate->format('Y-m-d\TH:i') !== $closeInput
        ) {

            $error = "Waktu pembukaan dan penutupan posttest wajib diisi dengan format yang valid.";
        } else {

            // Tentukan rentang tanggal keseluruhan jadwal.
            // Contoh mamsukkan nilainya:
            // Tanggal mulai: 10 November
            // Durasi: 2 hari
            // Hari terakhir jadwal: 11 November
            // Batas akhir: 12 November pukul 00.00

            $scheduleStart = DateTime::createFromFormat(
                '!Y-m-d',
                $startDate,
                $timezone
            );

            $scheduleEnd = clone $scheduleStart;

            $scheduleEnd->modify(
                "+" . (int) $duration . " days"
            );

            if (
                $openDate < $scheduleStart ||
                $closeDate > $scheduleEnd ||
                $closeDate <= $openDate
            ) {

                $error = "Waktu posttest harus berada dalam periode jadwal dan waktu penutupan harus setelah waktu pembukaan.";
            } else {

                // Simpan menggunakan waktu lokal Jakarta.
                $posttestOpenAt = $openDate->format("Y-m-d H:i:s");
                $posttestCloseAt = $closeDate->format("Y-m-d H:i:s");
            }
        }
    }

    // Simpan jadwal.
    if ($error === "") {

        $queryInsert = "
    INSERT INTO schedules (
        schedule_start_date,
        schedule_duration,
        schedule_start_time,
        schedule_end_time,
        schedule_link,
        schedule_location,
        training_id,
        schedule_posttest_open_at,
        schedule_posttest_close_at
    )

    VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)
";

        $params = [
            $startDate,
            $duration,
            $startTime,
            $endTime,
            $meetingLink,
            $location,
            $trainingId,
            $posttestOpenAt,
            $posttestCloseAt
        ];

        $resultInsert = pg_query_params(
            $conn,
            $queryInsert,
            $params
        );

        if ($resultInsert) {

            header("Location: index.php?created=1");
            exit;
        } else {

            error_log(pg_last_error($conn));
            $error = "Gagal menyimpan jadwal pelatihan.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Jadwal</title>

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

            <h2>Tambah Jadwal Pelatihan</h2>

            <p>Masukkan informasi jadwal pelaksanaan pelatihan.</p>

            <a href="index.php" class="btn btn-secondary mb-3">
                Kembali
            </a>

            <?php if ($error !== ""): ?>

                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>

            <?php if (count($trainings) === 0): ?>

                <div class="alert alert-warning">
                    Belum ada data pelatihan. Tambahkan pelatihan terlebih dahulu.
                </div>

            <?php else: ?>

                <form method="POST" action="create.php">

                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">

                    <div class="mb-3">

                        <label class="form-label">Program Pelatihan</label>

                        <select
                            name="training_id"
                            id="training_id"
                            class="form-select"
                            required>

                            <option value="">-- Pilih Pelatihan --</option>

                            <?php foreach ($trainings as $training): ?>

                                <?php
                                $deliveryType = $training["training_interaction"];

                                if ($deliveryType === "Daring") {
                                    $deliveryType .= " - " . $training["training_online_type"];
                                }
                                ?>

                                <option
                                    value="<?= $training["training_id"] ?>"
                                    data-method="<?= htmlspecialchars($training["training_interaction"]) ?>"
                                    data-online-type="<?= htmlspecialchars($training["training_online_type"] ?? "") ?>"
                                    <?= (
                                        ($form["training_id"] ?? "") == $training["training_id"]
                                    ) ? "selected" : "" ?>>

                                    <?= htmlspecialchars($training["training_code"]) ?>
                                    -
                                    <?= htmlspecialchars($training["training_topic"]) ?>
                                    (<?= htmlspecialchars($deliveryType) ?>)

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">Tanggal Mulai</label>

                        <input
                            type="date"
                            name="schedule_start_date"
                            class="form-control"
                            value="<?= htmlspecialchars($form["schedule_start_date"] ?? "") ?>"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">Durasi (Hari)</label>

                        <input
                            type="number"
                            name="schedule_duration"
                            class="form-control"
                            min="1"
                            value="<?= htmlspecialchars($form["schedule_duration"] ?? "") ?>"
                            required>

                    </div>

                    <div id="timeFields">

                        <div class="mb-3">

                            <label class="form-label">Waktu Mulai</label>

                            <input
                                type="time"
                                name="schedule_start_time"
                                id="schedule_start_time"
                                class="form-control"
                                value="<?= htmlspecialchars($form["schedule_start_time"] ?? "") ?>">

                        </div>

                        <div class="mb-3">

                            <label class="form-label">Waktu Selesai</label>

                            <input
                                type="time"
                                name="schedule_end_time"
                                id="schedule_end_time"
                                class="form-control"
                                value="<?= htmlspecialchars($form["schedule_end_time"] ?? "") ?>">

                        </div>

                    </div>

                    <div class="mb-3" id="locationField">

                        <label class="form-label">Lokasi Pelaksanaan</label>

                        <input
                            type="text"
                            name="schedule_location"
                            id="schedule_location"
                            class="form-control"
                            maxlength="100"
                            value="<?= htmlspecialchars($form["schedule_location"] ?? "") ?>">

                    </div>

                    <div class="mb-3" id="meetingLinkField">

                        <label class="form-label">Link Pertemuan</label>

                        <input
                            type="url"
                            name="schedule_link"
                            id="schedule_link"
                            class="form-control"
                            maxlength="255"
                            value="<?= htmlspecialchars($form["schedule_link"] ?? "") ?>">

                    </div>

                    <div id="posttestWindowFields" style="display: none;">

                        <hr>

                        <h5>Jadwal Posttest</h5>

                        <p class="text-muted">
                            Tentukan periode waktu ketika peserta dapat mengerjakan posttest.
                        </p>

                        <div class="mb-3">

                            <label class="form-label">
                                Waktu Pembukaan Posttest
                            </label>

                            <input
                                type="datetime-local"
                                name="schedule_posttest_open_at"
                                id="schedule_posttest_open_at"
                                class="form-control"
                                value="<?= htmlspecialchars($form["schedule_posttest_open_at"] ?? "") ?>">

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Waktu Penutupan Posttest
                            </label>

                            <input
                                type="datetime-local"
                                name="schedule_posttest_close_at"
                                id="schedule_posttest_close_at"
                                class="form-control"
                                value="<?= htmlspecialchars($form["schedule_posttest_close_at"] ?? "") ?>">

                        </div>

                    </div>

                    <button type="submit" class="btn btn-primary">
                        Simpan Jadwal
                    </button>

                </form>

            <?php endif; ?>

        </div>

        <script>
            const trainingSelect = document.getElementById("training_id");

            const timeFields = document.getElementById("timeFields");
            const meetingLinkField = document.getElementById("meetingLinkField");
            const locationField = document.getElementById("locationField");

            const startTime = document.getElementById("schedule_start_time");
            const endTime = document.getElementById("schedule_end_time");
            const meetingLink = document.getElementById("schedule_link");
            const locationInput = document.getElementById("schedule_location");

            const posttestWindowFields =
                document.getElementById("posttestWindowFields");

            const posttestOpen =
                document.getElementById("schedule_posttest_open_at");

            const posttestClose =
                document.getElementById("schedule_posttest_close_at");

            function updateScheduleFields() {

                const selectedOption =
                    trainingSelect.options[trainingSelect.selectedIndex];

                const method = selectedOption?.dataset.method;
                const onlineType = selectedOption?.dataset.onlineType;

                // Sembunyikan seluruh kolom bersyarat terlebih dahulu.
                timeFields.style.display = "none";
                meetingLinkField.style.display = "none";
                locationField.style.display = "none";
                posttestWindowFields.style.display = "none";

                // Atur ulang kolom wajib.
                startTime.required = false;
                endTime.required = false;
                meetingLink.required = false;
                locationInput.required = false;

                posttestOpen.required = false;
                posttestClose.required = false;

                if (!method) {
                    return;
                }

                if (method === "Daring" && onlineType === "Asinkron") {
                    return;
                }

                if (method === "Daring" && onlineType === "Sinkron") {

                    timeFields.style.display = "block";
                    meetingLinkField.style.display = "block";
                    posttestWindowFields.style.display = "block";

                    startTime.required = true;
                    endTime.required = true;
                    meetingLink.required = true;

                    posttestOpen.required = true;
                    posttestClose.required = true;
                }

                // Offline.
                else if (method === "Luring") {

                    timeFields.style.display = "block";
                    locationField.style.display = "block";
                    posttestWindowFields.style.display = "block";

                    startTime.required = true;
                    endTime.required = true;
                    locationInput.required = true;

                    posttestOpen.required = true;
                    posttestClose.required = true;
                }
            }

            trainingSelect.addEventListener(
                "change",
                updateScheduleFields
            );

            // Jalankan satu kali saat halaman pertama kali dimuat.
            updateScheduleFields();
        </script>

    </main>

    <?php
    require_once __DIR__ . "/../../includes/footer.php";
    ?>

</body>

</html>