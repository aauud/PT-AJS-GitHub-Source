<?php

require_once __DIR__ . "/../../includes/app_path.php";

require_once __DIR__ . "/../guard.php";

// Validasi ID pelatihan.
$trainingId = filter_var(
    $_GET["training_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($trainingId === false || $trainingId === null) {
    http_response_code(400);
    exit("ID pelatihan tidak valid.");
}

// Ambil informasi pelatihan.
$queryTraining = "
    SELECT
        training_id,
        training_code,
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

// Modul hanya tersedia untuk pelatihan Daring Asinkron.
if (
    $training["training_interaction"] !== "Daring" ||
    $training["training_online_type"] !== "Asinkron"
) {
    http_response_code(400);
    exit("Modul pembelajaran hanya tersedia untuk pelatihan daring asinkron.");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Ambil seluruh modul yang terkait dengan pelatihan ini.
$queryModules = "
    SELECT
        module_id,
        module_order,
        module_title,
        module_description,
        module_content,
        module_material_url
    FROM training_modules
    WHERE training_id = $1
    ORDER BY module_order ASC
";

$resultModules = pg_query_params(
    $conn,
    $queryModules,
    [$trainingId]
);

if (!$resultModules) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil data modul.");
}

// Siapkan pesan keberhasilan.
$created = isset($_GET["created"]) && $_GET["created"] === "1";
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kelola Modul Pelatihan</title>

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

            <h2>Kelola Modul Pembelajaran</h2>

            <p>
                Program:
                <strong>
                    <?= htmlspecialchars($training["training_topic"]) ?>
                </strong>
            </p>

            <p>
                Kode:
                <?= htmlspecialchars($training["training_code"]) ?>
            </p>

            <?php if ($created): ?>
                <div class="alert alert-success">
                    Modul berhasil ditambahkan.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET["updated"])): ?>
                <div class="alert alert-success">
                    Modul berhasil diperbarui.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET["deleted"])): ?>
                <div class="alert alert-success">
                    Modul berhasil dihapus.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET["delete_blocked"])): ?>
                <div class="alert alert-warning">
                    Modul tidak dapat dihapus karena sudah memiliki data progres pembelajaran Member.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET["delete_error"])): ?>
                <div class="alert alert-danger">
                    Gagal menghapus modul. Silakan periksa kembali.
                </div>
            <?php endif; ?>

            <div class="mb-3">

                <a
                    href="../trainings/index.php"
                    class="btn btn-secondary">
                    Kembali ke Pelatihan
                </a>

                <a
                    href="create.php?training_id=<?= (int) $trainingId ?>"
                    class="btn btn-primary">
                    Tambah Modul
                </a>

            </div>

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead class="table-dark">
                        <tr>
                            <th>Urutan</th>
                            <th>Judul Modul</th>
                            <th>Deskripsi</th>
                            <th>Materi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (pg_num_rows($resultModules) > 0): ?>

                            <?php while ($row = pg_fetch_assoc($resultModules)): ?>

                                <tr>

                                    <td>
                                        <?= (int) $row["module_order"] ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row["module_title"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $row["module_description"] ?? "-"
                                        ) ?>
                                    </td>

                                    <td>

                                        <?php if (!empty($row["module_material_url"])): ?>

                                            <a
                                                href="<?= htmlspecialchars($row["module_material_url"]) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer">
                                                Buka Materi
                                            </a>

                                        <?php else: ?>

                                            <span class="text-muted">-</span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <a
                                            href="edit.php?training_id=<?= (int) $trainingId ?>&module_id=<?= (int) $row["module_id"] ?>"
                                            class="btn btn-sm btn-warning mb-1">
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="delete.php"
                                            class="d-inline"
                                            onsubmit="return confirm('Apakah Anda yakin ingin menghapus modul ini?');">

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">

                                            <input
                                                type="hidden"
                                                name="training_id"
                                                value="<?= (int) $trainingId ?>">

                                            <input
                                                type="hidden"
                                                name="module_id"
                                                value="<?= (int) $row["module_id"] ?>">

                                            <button type="submit" class="btn btn-sm btn-danger mb-1">
                                                Hapus
                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5" class="text-center">
                                    Belum ada modul pembelajaran.
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