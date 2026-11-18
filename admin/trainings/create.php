<?php

require_once __DIR__ . "/../../includes/app_path.php";

require_once __DIR__ . "/../guard.php";

// Buat token CSRF untuk melindungi penyimpanan program pelatihan.
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

// Siapkan pesan kesalahan awal.
$error = "";

// Proses pengiriman formulir.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $trainingCode = trim($_POST["training_code"] ?? "");
    $trainingTopic = trim($_POST["training_topic"] ?? "");
    $trainingMaterial = trim($_POST["training_material"] ?? "");
    $trainingTarget = trim($_POST["training_target_audience"] ?? "");
    $trainingCategory = $_POST["training_category"] ?? "";
    $trainingFee = $_POST["training_fee"] ?? "";
    $trainingBenefit = trim($_POST["training_benefit"] ?? "");
    $trainingInteraction = $_POST["training_interaction"] ?? "";
    $trainerId = $_POST["trainer_id"] ?? "";

    // Jenis pelaksanaan daring hanya berlaku untuk metode Daring.
    $trainingOnlineType = null;

    if ($trainingInteraction === "Daring") {
        $trainingOnlineType = $_POST["training_online_type"] ?? "";
    }

    // Validasi masukan.
    if (
        $trainingCode === "" ||
        $trainingTopic === "" ||
        $trainingMaterial === "" ||
        $trainingTarget === "" ||
        $trainingCategory === "" ||
        $trainingFee === "" ||
        $trainingBenefit === "" ||
        $trainingInteraction === "" ||
        $trainerId === ""
    ) {
        $error = "Semua informasi wajib diisi.";
    } elseif (!is_numeric($trainingFee) || $trainingFee < 0) {
        $error = "Biaya pelatihan harus berupa angka dan tidak boleh negatif.";
    } elseif (!filter_var($trainerId, FILTER_VALIDATE_INT)) {
        $error = "Pelatih tidak valid.";
    } elseif (!in_array(
        $trainingCategory,
        ["Technical", "Nontechnical"],
        true
    )) {
        $error = "Kategori pelatihan tidak valid.";
    } elseif (!in_array(
        $trainingInteraction,
        ["Daring", "Luring"],
        true
    )) {
        $error = "Metode pelaksanaan tidak valid.";
    } elseif (
        $trainingInteraction === "Daring" &&
        !in_array($trainingOnlineType, ["Asinkron", "Sinkron"], true)
    ) {
        $error = "Jenis pelatihan daring harus dipilih.";
    }

    // Simpan ke basis data.
    if ($error === "") {

        $queryInsert = "
            INSERT INTO trainings (
                training_code,
                training_topic,
                training_material,
                training_target_audience,
                training_category,
                training_fee,
                training_benefit,
                training_interaction,
                training_online_type,
                trainer_id
            )
            VALUES (
                $1, $2, $3, $4, $5,
                $6, $7, $8, $9, $10
            )
        ";

        $params = [
            $trainingCode,
            $trainingTopic,
            $trainingMaterial,
            $trainingTarget,
            $trainingCategory,
            $trainingFee,
            $trainingBenefit,
            $trainingInteraction,
            $trainingOnlineType,
            $trainerId
        ];

        $resultInsert = @pg_query_params(
            $conn,
            $queryInsert . "
                ON CONFLICT (training_code) DO NOTHING
                RETURNING training_id
            ",
            $params
        );

        if ($resultInsert === false) {

            error_log("Training insert error: " . pg_last_error($conn));

            $error = "Terjadi kesalahan saat menyimpan data.";
        } elseif (pg_num_rows($resultInsert) === 0) {

            $error = "Kode pelatihan sudah digunakan. Gunakan kode lain.";
        } else {

            header("Location: index.php?created=1");
            exit;
        }
    }
}

// Ambil daftar pelatih yang tersedia.
$queryTrainers = "
    SELECT trainer_id, trainer_name
    FROM trainers
    ORDER BY trainer_name
";

$resultTrainers = pg_query($conn, $queryTrainers);

if (!$resultTrainers) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil data pelatih.");
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pelatihan</title>

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

            <h2>Tambah Program Pelatihan</h2>
            <p>Masukkan informasi program pelatihan baru.</p>

            <a href="index.php" class="btn btn-secondary mb-3">
                Kembali
            </a>

            <?php if ($error !== ""): ?>

                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>

            <form method="POST" action="create.php">

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">

                <div class="mb-3">
                    <label class="form-label">Kode Pelatihan</label>
                    <input
                        type="text"
                        name="training_code"
                        class="form-control"
                        maxlength="30"
                        required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nama / Topik Pelatihan</label>
                    <input
                        type="text"
                        name="training_topic"
                        class="form-control"
                        maxlength="50"
                        required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Kategori</label>
                    <select name="training_category" class="form-select" required>
                        <option value="">-- Pilih Kategori --</option>
                        <option value="Technical">Technical</option>
                        <option value="Nontechnical">Nontechnical</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Materi Pelatihan</label>
                    <textarea
                        name="training_material"
                        class="form-control"
                        rows="3"
                        required></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Target Peserta</label>
                    <input
                        type="text"
                        name="training_target_audience"
                        class="form-control"
                        maxlength="50"
                        required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Biaya Pelatihan (Rp)</label>
                    <input
                        type="number"
                        name="training_fee"
                        class="form-control"
                        min="0"
                        step="0.01"
                        required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Manfaat Pelatihan</label>
                    <textarea
                        name="training_benefit"
                        class="form-control"
                        rows="3"
                        required></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Metode Pelaksanaan</label>
                    <select
                        name="training_interaction"
                        id="training_interaction"
                        class="form-select"
                        required>
                        <option value="">-- Pilih Metode --</option>
                        <option value="Daring">Daring</option>
                        <option value="Luring">Luring</option>
                    </select>
                </div>

                <div class="mb-3" id="onlineTypeContainer" style="display: none;">

                    <label class="form-label">Jenis Pelatihan Daring</label>

                    <select
                        name="training_online_type"
                        id="training_online_type"
                        class="form-select">
                        <option value="">-- Pilih Jenis --</option>
                        <option value="Asinkron">Asinkron</option>
                        <option value="Sinkron">Sinkron</option>
                    </select>

                </div>

                <div class="mb-3">
                    <label class="form-label">Pelatih</label>

                    <select name="trainer_id" class="form-select" required>

                        <option value="">-- Pilih Pelatih --</option>

                        <?php while ($trainer = pg_fetch_assoc($resultTrainers)): ?>

                            <option value="<?= $trainer["trainer_id"] ?>">
                                <?= htmlspecialchars($trainer["trainer_name"]) ?>
                            </option>

                        <?php endwhile; ?>

                    </select>
                </div>

                <button type="submit" class="btn btn-primary">
                    Simpan Pelatihan
                </button>

            </form>

        </div>

        <script>
            const interactionSelect = document.getElementById("training_interaction");
            const onlineContainer = document.getElementById("onlineTypeContainer");
            const onlineTypeSelect = document.getElementById("training_online_type");

            function updateOnlineType() {

                if (interactionSelect.value === "Daring") {

                    onlineContainer.style.display = "block";
                    onlineTypeSelect.required = true;

                } else {

                    onlineContainer.style.display = "none";
                    onlineTypeSelect.required = false;
                    onlineTypeSelect.value = "";

                }
            }

            interactionSelect.addEventListener("change", updateOnlineType);

            updateOnlineType();
        </script>

    </main>

    <?php
    require_once __DIR__ . "/../../includes/footer.php";
    ?>

</body>

</html>