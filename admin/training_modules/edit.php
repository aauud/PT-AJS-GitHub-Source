<?php

require_once __DIR__ . "/../../includes/app_path.php";

require_once __DIR__ . "/../guard.php";

// Validasi ID.
$trainingId = filter_var(
    $_GET["training_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

$moduleId = filter_var(
    $_GET["module_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (
    $trainingId === false || $trainingId === null ||
    $moduleId === false || $moduleId === null
) {
    http_response_code(400);
    exit("ID pelatihan atau modul tidak valid.");
}

// Ambil informasi pelatihan.
$queryTraining = "
    SELECT
        training_id,
        training_topic,
        training_interaction,
        training_online_type
    FROM trainings
    WHERE training_id = $1
";

$resultTraining = pg_query_params(
    $conn,
    $queryTraining,
    [$trainingId]
);

if (!$resultTraining) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil data pelatihan.");
}

$training = pg_fetch_assoc($resultTraining);

if (!$training) {
    http_response_code(404);
    exit("Pelatihan tidak ditemukan.");
}

// Izinkan hanya pelatihan Daring Asinkron.
if (
    $training["training_interaction"] !== "Daring" ||
    $training["training_online_type"] !== "Asinkron"
) {
    http_response_code(400);
    exit("Modul hanya tersedia untuk pelatihan daring asinkron.");
}

// Ambil data modul yang dipilih.
$queryModule = "
    SELECT
        module_id,
        module_order,
        module_title,
        module_description,
        module_content,
        module_material_url
    FROM training_modules
    WHERE module_id = $1
    AND training_id = $2
";

$resultModule = pg_query_params(
    $conn,
    $queryModule,
    [$moduleId, $trainingId]
);

if (!$resultModule) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil data modul.");
}

$module = pg_fetch_assoc($resultModule);

if (!$module) {
    http_response_code(404);
    exit("Modul tidak ditemukan.");
}

// Buat token CSRF.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Siapkan nilai awal formulir.
$moduleOrder = (string) $module["module_order"];
$moduleTitle = $module["module_title"];
$moduleDescription = $module["module_description"] ?? "";
$moduleContent = $module["module_content"] ?? "";
$moduleMaterialUrl = $module["module_material_url"] ?? "";

$errors = [];

// Proses pengiriman formulir.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION["csrf_token"], $submittedToken)
    ) {
        $errors[] = "Permintaan tidak valid. Silakan muat ulang halaman.";
    }

    $moduleOrder = trim((string) ($_POST["module_order"] ?? ""));
    $moduleTitle = trim((string) ($_POST["module_title"] ?? ""));
    $moduleDescription = trim((string) ($_POST["module_description"] ?? ""));
    $moduleContent = trim((string) ($_POST["module_content"] ?? ""));
    $moduleMaterialUrl = trim((string) ($_POST["module_material_url"] ?? ""));

    // Validasi urutan.
    $validatedOrder = filter_var(
        $moduleOrder,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    if ($validatedOrder === false) {
        $errors[] = "Urutan modul harus berupa angka minimal 1.";
    }

    // Validasi judul.
    if ($moduleTitle === "") {
        $errors[] = "Judul modul wajib diisi.";
    }

    // Wajib mengisi konten atau URL materi.
    if ($moduleContent === "" && $moduleMaterialUrl === "") {
        $errors[] = "Isi konten modul atau URL materi wajib disediakan.";
    }

    // Validasi URL.
    if ($moduleMaterialUrl !== "") {

        $scheme = strtolower(
            (string) parse_url(
                $moduleMaterialUrl,
                PHP_URL_SCHEME
            )
        );

        if (
            !filter_var($moduleMaterialUrl, FILTER_VALIDATE_URL) ||
            !in_array($scheme, ["http", "https"], true)
        ) {
            $errors[] = "URL materi harus berupa alamat HTTP atau HTTPS yang valid.";
        }
    }

    // 1Periksa apakah urutan yang dipilih sudah digunakan.
    if (
        $validatedOrder !== false &&
        $validatedOrder !== (int) $module["module_order"]
    ) {

        $queryDuplicate = "
            SELECT module_id
            FROM training_modules
            WHERE training_id = $1
            AND module_order = $2
            AND module_id <> $3
        ";

        $resultDuplicate = pg_query_params(
            $conn,
            $queryDuplicate,
            [$trainingId, $validatedOrder, $moduleId]
        );

        if (!$resultDuplicate) {
            error_log(pg_last_error($conn));
            $errors[] = "Gagal memeriksa urutan modul.";
        } elseif (pg_num_rows($resultDuplicate) > 0) {
            $errors[] = "Urutan modul tersebut sudah digunakan.";
        }
    }

    // Perbarui modul jika seluruh validasi berhasil.
    if (empty($errors)) {

        $queryUpdate = "
            UPDATE training_modules
            SET
                module_order = $1,
                module_title = $2,
                module_description = $3,
                module_content = $4,
                module_material_url = $5
            WHERE module_id = $6
            AND training_id = $7
        ";

        $resultUpdate = pg_query_params(
            $conn,
            $queryUpdate,
            [
                $validatedOrder,
                $moduleTitle,
                $moduleDescription,
                $moduleContent,
                $moduleMaterialUrl,
                $moduleId,
                $trainingId
            ]
        );

        if (!$resultUpdate) {

            error_log(pg_last_error($conn));

            $errors[] = "Gagal memperbarui modul. Periksa kembali urutannya.";
        } else {

            header(
                "Location: index.php?training_id="
                    . $trainingId
                    . "&updated=1"
            );

            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Modul</title>

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



        <div class="container mt-5">

            <h2>Edit Modul Pembelajaran</h2>

            <p>
                Program:
                <strong>
                    <?= htmlspecialchars($training["training_topic"]) ?>
                </strong>
            </p>

            <a
                href="index.php?training_id=<?= (int) $trainingId ?>"
                class="btn btn-secondary mb-3">
                Kembali ke Daftar Modul
            </a>

            <?php if (!empty($errors)): ?>

                <div class="alert alert-danger">
                    <ul class="mb-0">

                        <?php foreach ($errors as $error): ?>

                            <li><?= htmlspecialchars($error) ?></li>

                        <?php endforeach; ?>

                    </ul>
                </div>

            <?php endif; ?>

            <form
                method="POST"
                action="edit.php?training_id=<?= (int) $trainingId ?>&module_id=<?= (int) $moduleId ?>">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">

                <div class="mb-3">

                    <label for="module_order" class="form-label">
                        Urutan Modul
                    </label>

                    <input
                        type="number"
                        class="form-control"
                        id="module_order"
                        name="module_order"
                        min="1"
                        value="<?= htmlspecialchars($moduleOrder) ?>"
                        required>

                </div>

                <div class="mb-3">

                    <label for="module_title" class="form-label">
                        Judul Modul
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="module_title"
                        name="module_title"
                        value="<?= htmlspecialchars($moduleTitle) ?>"
                        required>

                </div>

                <div class="mb-3">

                    <label for="module_description" class="form-label">
                        Deskripsi Modul
                    </label>

                    <textarea
                        class="form-control"
                        id="module_description"
                        name="module_description"
                        rows="3"><?= htmlspecialchars($moduleDescription) ?></textarea>

                </div>

                <div class="mb-3">

                    <label for="module_content" class="form-label">
                        Konten Modul
                    </label>

                    <textarea
                        class="form-control"
                        id="module_content"
                        name="module_content"
                        rows="8"><?= htmlspecialchars($moduleContent) ?></textarea>

                </div>

                <div class="mb-3">

                    <label for="module_material_url" class="form-label">
                        URL Materi (Opsional)
                    </label>

                    <input
                        type="url"
                        class="form-control"
                        id="module_material_url"
                        name="module_material_url"
                        value="<?= htmlspecialchars($moduleMaterialUrl) ?>"
                        placeholder="https://...">

                </div>

                <button type="submit" class="btn btn-primary">
                    Simpan Perubahan
                </button>

            </form>

        </div>

    </main>

    <?php require_once __DIR__ . "/../../includes/footer.php"; ?>

</body>

</html>