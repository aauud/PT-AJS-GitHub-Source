<?php

require_once __DIR__ . "/../../includes/app_path.php";

require_once __DIR__ . "/guard.php";

$posttestId = filter_var(
    $_GET["posttest_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($posttestId === false || $posttestId === null) {
    http_response_code(400);
    exit("ID posttest tidak valid.");
}

$posttest = getPosttest($conn, $posttestId);

if (!$posttest) {
    http_response_code(404);
    exit("Posttest tidak ditemukan.");
}

$title = $posttest["posttest_title"];
$passingScore = $posttest["posttest_passing_score"];

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

        $queryUpdate = "
            UPDATE posttests
            SET
                posttest_title = $1,
                posttest_passing_score = $2
            WHERE posttest_id = $3
        ";

        $resultUpdate = pg_query_params(
            $conn,
            $queryUpdate,
            [
                $title,
                $passingScore,
                $posttestId
            ]
        );

        if (!$resultUpdate) {

            error_log(pg_last_error($conn));
            $errors[] = "Gagal memperbarui posttest.";
        } else {

            header(
                "Location: index.php?training_id="
                    . $posttest["training_id"]
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

    <title>Edit Posttest</title>

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

            <h2>Edit Posttest</h2>

            <p>
                Program:
                <strong><?= htmlspecialchars($posttest["training_topic"]) ?></strong>
            </p>

            <a
                href="index.php?training_id=<?= (int) $posttest["training_id"] ?>"
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