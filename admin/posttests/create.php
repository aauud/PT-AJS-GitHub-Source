<?php

require_once __DIR__ . "/../../includes/app_path.php";

require_once __DIR__ . "/guard.php";

// validasi.
$trainingId = filter_var(
    $_GET["training_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($trainingId === false || $trainingId === null) {
    http_response_code(400);
    exit("ID pelatihan tidak valid.");
}

$training = getTraining($conn, $trainingId);

if (!$training) {
    http_response_code(404);
    exit("Pelatihan tidak ditemukan.");
}

// mencegah duplicate posttest.
$resultExisting = pg_query_params(
    $conn,
    "SELECT posttest_id FROM posttests WHERE training_id = $1",
    [$trainingId]
);

if (!$resultExisting) {
    error_log(pg_last_error($conn));
    exit("Gagal memeriksa posttest.");
}

if (pg_num_rows($resultExisting) > 0) {
    header("Location: index.php?training_id=" . $trainingId);
    exit;
}

// pembuatan posttest
$title = "";
$passingScore = "70";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!verifyPosttestCsrf()) {
        $errors[] = "Permintaan tidak valid.";
    }

    $title = trim((string) ($_POST["posttest_title"] ?? ""));
    $passingScore = trim((string) ($_POST["posttest_passing_score"] ?? ""));

    if ($title === "" || strlen($title) > 150) {
        $errors[] = "Judul posttest wajib diisi dan maksimal 150 karakter.";
    }

    if (
        !is_numeric($passingScore) ||
        (float) $passingScore < 0 ||
        (float) $passingScore > 100
    ) {
        $errors[] = "Nilai kelulusan harus berada antara 0 sampai 100.";
    }

    if (empty($errors)) {

        $queryInsert = "
            INSERT INTO posttests (
                training_id,
                posttest_title,
                posttest_passing_score
            )

            VALUES ($1, $2, $3)

            ON CONFLICT (training_id)
            DO NOTHING

            RETURNING posttest_id
        ";

        $resultInsert = pg_query_params(
            $conn,
            $queryInsert,
            [
                $trainingId,
                $title,
                $passingScore
            ]
        );

        if (!$resultInsert) {

            error_log(pg_last_error($conn));
            $errors[] = "Gagal membuat posttest.";
        } else {

            $newPosttest = pg_fetch_assoc($resultInsert);

            if ($newPosttest) {

                header(
                    "Location: index.php?training_id="
                        . $trainingId
                        . "&created=1"
                );

                exit;
            } else {

                $errors[] = "Posttest untuk pelatihan ini sudah tersedia.";
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Buat Posttest</title>

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

            <h2>Buat Posttest</h2>

            <p>
                Program:
                <strong><?= htmlspecialchars($training["training_topic"]) ?></strong>
            </p>

            <a
                href="index.php?training_id=<?= (int) $trainingId ?>"
                class="btn btn-secondary mb-3">
                Kembali
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

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">

                <div class="mb-3">

                    <label class="form-label" for="posttest_title">
                        Judul Posttest
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="posttest_title"
                        name="posttest_title"
                        maxlength="150"
                        value="<?= htmlspecialchars($title) ?>"
                        required>

                </div>

                <div class="mb-3">

                    <label class="form-label" for="posttest_passing_score">
                        Nilai Kelulusan (%)
                    </label>

                    <input
                        type="number"
                        class="form-control"
                        id="posttest_passing_score"
                        name="posttest_passing_score"
                        min="0"
                        max="100"
                        step="0.01"
                        value="<?= htmlspecialchars($passingScore) ?>"
                        required>

                    <div class="form-text">
                        Nilai kelulusan awal yang disepakati adalah 70%.
                    </div>

                </div>

                <button type="submit" class="btn btn-primary">
                    Simpan Posttest
                </button>

            </form>

        </div>

    </main>

    <?php require_once __DIR__ . "/../../includes/footer.php"; ?>

</body>

</html>