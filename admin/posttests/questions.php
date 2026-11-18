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

$questionLockInfo = getPosttestQuestionLockInfo(
    $conn,
    $posttest
);

$locked = $questionLockInfo["locked"];

$queryQuestions = "
    SELECT
        q.question_id,
        q.question_order,
        q.question_text,

        COUNT(o.option_id) AS option_count,

        COALESCE(
            STRING_AGG(
                o.option_label::TEXT
                || '. '
                || o.option_text
                || CASE
                    WHEN o.option_is_correct THEN ' [Jawaban benar]'
                    ELSE ''
                END,
                E'\n'
                ORDER BY o.option_label
            ),
            ''
        ) AS options_text

    FROM posttest_questions q

    LEFT JOIN posttest_options o
        ON q.question_id = o.question_id

    WHERE q.posttest_id = $1

    GROUP BY q.question_id

    ORDER BY q.question_order ASC
";

$resultQuestions = pg_query_params(
    $conn,
    $queryQuestions,
    [$posttestId]
);

if (!$resultQuestions) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil data pertanyaan.");
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kelola Pertanyaan</title>

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

            <h2>Kelola Pertanyaan Posttest</h2>

            <p>
                <?= htmlspecialchars($posttest["posttest_title"]) ?>
            </p>

            <a
                href="index.php?training_id=<?= (int) $posttest["training_id"] ?>"
                class="btn btn-secondary mb-3">
                Kembali ke Posttest
            </a>

            <?php if (isset($_GET["created"])): ?>

                <div class="alert alert-success">
                    Pertanyaan berhasil ditambahkan.
                </div>

            <?php endif; ?>

            <?php if (isset($_GET["updated"])): ?>

                <div class="alert alert-success">
                    Pertanyaan berhasil diperbarui.
                </div>

            <?php endif; ?>

            <?php if (isset($_GET["deleted"])): ?>

                <div class="alert alert-success">
                    Pertanyaan berhasil dihapus.
                </div>

            <?php endif; ?>

            <?php if ($locked): ?>

                <div class="alert alert-warning">

                    <strong>Mode hanya lihat: pertanyaan tidak dapat diubah.</strong>

                    <p class="mb-2">
                        Perubahan pertanyaan dibatasi karena:
                    </p>

                    <ul class="mb-0">

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

                </div>

            <?php else: ?>

                <a
                    href="question_form.php?posttest_id=<?= (int) $posttestId ?>"
                    class="btn btn-primary mb-3">
                    Tambah Pertanyaan
                </a>

            <?php endif; ?>

            <?php if (pg_num_rows($resultQuestions) > 0): ?>

                <?php while ($row = pg_fetch_assoc($resultQuestions)): ?>

                    <div class="card mb-3">

                        <div class="card-body">

                            <h5>
                                <?= (int) $row["question_order"] ?>.
                                <?= htmlspecialchars($row["question_text"]) ?>
                            </h5>

                            <p class="text-muted mb-2">
                                <?= nl2br(htmlspecialchars($row["options_text"])) ?>
                            </p>

                            <?php if (!$locked): ?>

                                <a
                                    href="question_form.php?posttest_id=<?= (int) $posttestId ?>&question_id=<?= (int) $row["question_id"] ?>"
                                    class="btn btn-sm btn-warning">
                                    Edit
                                </a>

                                <form
                                    method="POST"
                                    action="question_delete.php"
                                    class="d-inline"
                                    onsubmit="return confirm('Hapus pertanyaan ini?');">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">

                                    <input
                                        type="hidden"
                                        name="posttest_id"
                                        value="<?= (int) $posttestId ?>">

                                    <input
                                        type="hidden"
                                        name="question_id"
                                        value="<?= (int) $row["question_id"] ?>">

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-danger">
                                        Hapus
                                    </button>

                                </form>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="alert alert-info">
                    Belum ada pertanyaan posttest.
                </div>

            <?php endif; ?>

        </div>

    </main>

    <?php require_once __DIR__ . "/../../includes/footer.php"; ?>

</body>

</html>