<?php

require_once __DIR__ . "/../../includes/app_path.php";


require_once __DIR__ . "/guard.php";

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

// Ambil data pelatihan.
$training = getTraining($conn, $trainingId);

if (!$training) {
    http_response_code(404);
    exit("Pelatihan daring asinkron tidak ditemukan.");
}

// Ambil data posttest dan jumlah pertanyaan.
$queryPosttest = "
    SELECT
        p.posttest_id,
        p.posttest_title,
        p.posttest_passing_score,
        p.posttest_is_active,

        COUNT(q.question_id) AS total_questions

    FROM posttests p

    LEFT JOIN posttest_questions q
        ON p.posttest_id = q.posttest_id

    WHERE p.training_id = $1

    GROUP BY p.posttest_id
";

$resultPosttest = pg_query_params(
    $conn,
    $queryPosttest,
    [$trainingId]
);

if (!$resultPosttest) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil data posttest.");
}

$posttest = pg_fetch_assoc($resultPosttest);

$questionLockInfo = null;

if ($posttest) {

    $questionLockInfo = getPosttestQuestionLockInfo(
        $conn,
        $posttest
    );
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kelola Posttest</title>

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

            <h2>Kelola Posttest</h2>

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

            <a
                href="../trainings/index.php"
                class="btn btn-secondary mb-3">
                Kembali ke Pelatihan
            </a>

            <?php if (isset($_GET["created"])): ?>

                <div class="alert alert-success">
                    Posttest berhasil dibuat.
                </div>

            <?php endif; ?>

            <?php if (isset($_GET["updated"])): ?>

                <div class="alert alert-success">
                    Informasi posttest berhasil diperbarui.
                </div>

            <?php endif; ?>

            <?php if (isset($_GET["toggled"])): ?>

                <div class="alert alert-success">
                    Status posttest berhasil diperbarui.
                </div>

            <?php endif; ?>

            <?php if (isset($_GET["invalid_questions"])): ?>

                <div class="alert alert-warning">
                    Posttest belum dapat diaktifkan.
                    Pastikan setiap pertanyaan memiliki empat pilihan
                    dan tepat satu jawaban benar.
                </div>

            <?php endif; ?>

            <?php if (!$posttest): ?>

                <div class="alert alert-info">
                    Belum ada posttest untuk program pelatihan ini.
                </div>

                <a
                    href="create.php?training_id=<?= (int) $trainingId ?>"
                    class="btn btn-primary">
                    Buat Posttest
                </a>

            <?php else: ?>

                <div class="card">

                    <div class="card-body">

                        <h4>
                            <?= htmlspecialchars($posttest["posttest_title"]) ?>
                        </h4>

                        <p>
                            Nilai kelulusan:
                            <strong>
                                <?= htmlspecialchars($posttest["posttest_passing_score"]) ?>%
                            </strong>
                        </p>

                        <p>
                            Jumlah pertanyaan:
                            <?= (int) $posttest["total_questions"] ?>
                        </p>

                        <p>
                            Status:

                            <?php if ($posttest["posttest_is_active"] === "t"): ?>

                                <span class="badge bg-success">
                                    Aktif
                                </span>

                            <?php else: ?>

                                <span class="badge bg-secondary">
                                    Tidak Aktif
                                </span>

                            <?php endif; ?>

                        </p>

                        <?php if ($questionLockInfo["locked"]): ?>

                            <div class="alert alert-warning mt-3">

                                <strong>Pengelolaan pertanyaan dibatasi.</strong>

                                <p class="mb-2">
                                    Pertanyaan tidak dapat ditambah, diubah, atau dihapus
                                    karena alasan berikut:
                                </p>

                                <ul class="mb-2">

                                    <?php foreach ($questionLockInfo["reasons"] as $reason): ?>

                                        <li>
                                            <?= htmlspecialchars(
                                                $reason,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                        </li>

                                    <?php endforeach; ?>

                                </ul>

                                <small>
                                    Anda masih dapat melihat daftar pertanyaan,
                                    tetapi perubahan tidak tersedia.
                                </small>

                            </div>

                        <?php endif; ?>

                        <div class="d-flex gap-2 flex-wrap">

                            <a
                                href="edit.php?posttest_id=<?= (int) $posttest["posttest_id"] ?>"
                                class="btn btn-warning">
                                Edit Posttest
                            </a>

                            <a
                                href="questions.php?posttest_id=<?= (int) $posttest["posttest_id"] ?>"
                                class="btn <?= $questionLockInfo["locked"]
                                                ? "btn-outline-primary"
                                                : "btn-primary" ?>">

                                <?= $questionLockInfo["locked"]
                                    ? "Lihat Pertanyaan"
                                    : "Kelola Pertanyaan" ?>

                            </a>

                            <form
                                method="POST"
                                action="toggle.php"
                                onsubmit="return confirm('Apakah Anda yakin ingin mengubah status posttest?');">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">

                                <input
                                    type="hidden"
                                    name="posttest_id"
                                    value="<?= (int) $posttest["posttest_id"] ?>">

                                <button
                                    type="submit"
                                    class="btn <?= $posttest["posttest_is_active"] === "t" ? "btn-danger" : "btn-success" ?>">

                                    <?= $posttest["posttest_is_active"] === "t"
                                        ? "Nonaktifkan"
                                        : "Aktifkan" ?>

                                </button>

                            </form>

                        </div>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </main>

    <?php require_once __DIR__ . "/../../includes/footer.php"; ?>

</body>

</html>