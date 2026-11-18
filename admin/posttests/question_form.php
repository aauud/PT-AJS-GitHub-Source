<?php

require_once __DIR__ . "/../../includes/app_path.php";

require_once __DIR__ . "/guard.php";

$posttestId = filter_var(
    $_GET["posttest_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

$questionId = null;

if (isset($_GET["question_id"])) {

    $questionId = filter_var(
        $_GET["question_id"],
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    if ($questionId === false) {
        http_response_code(400);
        exit("ID pertanyaan tidak valid.");
    }
}

if ($posttestId === false || $posttestId === null) {
    http_response_code(400);
    exit("ID posttest tidak valid.");
}

$posttest = getPosttest($conn, $posttestId);

if (!$posttest) {
    http_response_code(404);
    exit("Posttest tidak ditemukan.");
}

if (areQuestionsLocked($conn, $posttest)) {
    http_response_code(403);
    exit("Pertanyaan tidak dapat diubah.");
}

$isEdit = $questionId !== null;

// Nilai awal.
$questionOrder = "1";
$questionText = "";

$options = [
    "A" => "",
    "B" => "",
    "C" => "",
    "D" => ""
];

$correctOption = "A";

// Ambil urutan pertanyaan berikutnya.
$queryNext = "
    SELECT COALESCE(MAX(question_order), 0) + 1 AS next_order
    FROM posttest_questions
    WHERE posttest_id = $1
";

$resultNext = pg_query_params(
    $conn,
    $queryNext,
    [$posttestId]
);

if (!$resultNext) {
    error_log(pg_last_error($conn));
    exit("Gagal menentukan urutan pertanyaan.");
}

$questionOrder = (string) pg_fetch_result(
    $resultNext,
    0,
    "next_order"
);

// Muat pertanyaan yang sudah ada saat proses penyuntingan.
if ($isEdit) {

    $resultQuestion = pg_query_params(
        $conn,
        "
        SELECT *
        FROM posttest_questions
        WHERE question_id = $1
        AND posttest_id = $2
        ",
        [$questionId, $posttestId]
    );

    if (!$resultQuestion) {
        error_log(pg_last_error($conn));
        exit("Gagal mengambil pertanyaan.");
    }

    $existingQuestion = pg_fetch_assoc($resultQuestion);

    if (!$existingQuestion) {
        http_response_code(404);
        exit("Pertanyaan tidak ditemukan.");
    }

    $questionOrder = (string) $existingQuestion["question_order"];
    $questionText = $existingQuestion["question_text"];

    $resultOptions = pg_query_params(
        $conn,
        "
        SELECT *
        FROM posttest_options
        WHERE question_id = $1
        ORDER BY option_label
        ",
        [$questionId]
    );

    if (!$resultOptions) {
        error_log(pg_last_error($conn));
        exit("Gagal mengambil pilihan jawaban.");
    }

    while ($option = pg_fetch_assoc($resultOptions)) {

        $label = trim($option["option_label"]);

        $options[$label] = $option["option_text"];

        if ($option["option_is_correct"] === "t") {
            $correctOption = $label;
        }
    }
}

$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!verifyPosttestCsrf()) {
        $errors[] = "Permintaan tidak valid.";
    }

    $questionOrder = trim((string) ($_POST["question_order"] ?? ""));
    $questionText = trim((string) ($_POST["question_text"] ?? ""));

    $submittedOptions = $_POST["options"] ?? [];

    if (!is_array($submittedOptions)) {
        $submittedOptions = [];
    }

    foreach (["A", "B", "C", "D"] as $label) {

        $options[$label] = trim(
            (string) ($submittedOptions[$label] ?? "")
        );

        if ($options[$label] === "") {
            $errors[] = "Pilihan jawaban $label wajib diisi.";
        }
    }

    $correctOption = $_POST["correct_option"] ?? "";

    if (!in_array($correctOption, ["A", "B", "C", "D"], true)) {
        $errors[] = "Pilih satu jawaban yang benar.";
    }

    $validatedOrder = filter_var(
        $questionOrder,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    if ($validatedOrder === false) {
        $errors[] = "Urutan pertanyaan harus berupa angka minimal 1.";
    }

    if ($questionText === "") {
        $errors[] = "Pertanyaan wajib diisi.";
    }

    // Cegah penggunaan urutan pertanyaan yang sama.
    if ($validatedOrder !== false) {

        if ($isEdit) {

            $queryDuplicate = "
                SELECT 1
                FROM posttest_questions
                WHERE posttest_id = $1
                AND question_order = $2
                AND question_id <> $3
            ";

            $params = [
                $posttestId,
                $validatedOrder,
                $questionId
            ];
        } else {

            $queryDuplicate = "
                SELECT 1
                FROM posttest_questions
                WHERE posttest_id = $1
                AND question_order = $2
            ";

            $params = [
                $posttestId,
                $validatedOrder
            ];
        }

        $resultDuplicate = pg_query_params(
            $conn,
            $queryDuplicate,
            $params
        );

        if (!$resultDuplicate) {
            error_log(pg_last_error($conn));
            $errors[] = "Gagal memeriksa urutan pertanyaan.";
        } elseif (pg_num_rows($resultDuplicate) > 0) {
            $errors[] = "Urutan pertanyaan sudah digunakan.";
        }
    }

    // Simpan pertanyaan dan pilihan jawabannya.
    if (empty($errors)) {

        if (!pg_query($conn, "BEGIN")) {
            error_log(pg_last_error($conn));
            $errors[] = "Gagal memulai proses penyimpanan.";
        } else {

            // Simpan pertanyaan baru atau perbarui pertanyaan yang ada.
            if ($isEdit) {

                $resultQuestionSave = pg_query_params(
                    $conn,
                    "
                    UPDATE posttest_questions
                    SET
                        question_order = $1,
                        question_text = $2
                    WHERE question_id = $3
                    AND posttest_id = $4
                    ",
                    [
                        $validatedOrder,
                        $questionText,
                        $questionId,
                        $posttestId
                    ]
                );
            } else {

                $resultQuestionSave = pg_query_params(
                    $conn,
                    "
                    INSERT INTO posttest_questions (
                        posttest_id,
                        question_order,
                        question_text
                    )
                    VALUES ($1, $2, $3)
                    RETURNING question_id
                    ",
                    [
                        $posttestId,
                        $validatedOrder,
                        $questionText
                    ]
                );

                if ($resultQuestionSave) {

                    $newQuestion = pg_fetch_assoc(
                        $resultQuestionSave
                    );

                    $questionId = $newQuestion["question_id"];
                }
            }

            if (!$resultQuestionSave) {

                pg_query($conn, "ROLLBACK");

                error_log(pg_last_error($conn));

                $errors[] = "Gagal menyimpan pertanyaan.";
            } else {

                // Hapus penanda jawaban benar sebelumnya.
                $resultClearCorrect = pg_query_params(
                    $conn,
                    "
                    UPDATE posttest_options
                    SET option_is_correct = FALSE
                    WHERE question_id = $1
                    ",
                    [$questionId]
                );

                if (!$resultClearCorrect) {

                    pg_query($conn, "ROLLBACK");

                    error_log(pg_last_error($conn));

                    $errors[] = "Gagal memperbarui pilihan jawaban.";
                } else {

                    $optionError = false;

                    foreach (["A", "B", "C", "D"] as $label) {

                        $isCorrect = (
                            $label === $correctOption
                        );

                        $resultOption = pg_query_params(
                            $conn,
                            "
                            INSERT INTO posttest_options (
                                question_id,
                                option_label,
                                option_text,
                                option_is_correct
                            )
                            VALUES ($1, $2, $3, $4)

                            ON CONFLICT (question_id, option_label)

                            DO UPDATE SET
                                option_text = EXCLUDED.option_text,
                                option_is_correct = EXCLUDED.option_is_correct
                            ",
                            [
                                $questionId,
                                $label,
                                $options[$label],
                                $isCorrect ? "true" : "false"
                            ]
                        );

                        if (!$resultOption) {
                            $optionError = true;
                            break;
                        }
                    }

                    if ($optionError) {

                        pg_query($conn, "ROLLBACK");

                        error_log(pg_last_error($conn));

                        $errors[] = "Gagal menyimpan pilihan jawaban.";
                    } else {

                        if (!pg_query($conn, "COMMIT")) {

                            pg_query($conn, "ROLLBACK");

                            error_log(pg_last_error($conn));

                            $errors[] = "Gagal menyelesaikan penyimpanan.";
                        } else {

                            header(
                                "Location: questions.php?posttest_id="
                                    . $posttestId
                                    . ($isEdit ? "&updated=1" : "&created=1")
                            );

                            exit;
                        }
                    }
                }
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

    <title><?= $isEdit ? "Edit" : "Tambah" ?> Pertanyaan</title>

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

            <h2>
                <?= $isEdit ? "Edit" : "Tambah" ?> Pertanyaan
            </h2>

            <a
                href="questions.php?posttest_id=<?= (int) $posttestId ?>"
                class="btn btn-secondary mb-3">
                Kembali ke Pertanyaan
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

                    <label class="form-label" for="question_order">
                        Urutan Pertanyaan
                    </label>

                    <input
                        type="number"
                        class="form-control"
                        id="question_order"
                        name="question_order"
                        min="1"
                        value="<?= htmlspecialchars($questionOrder) ?>"
                        required>

                </div>

                <div class="mb-3">

                    <label class="form-label" for="question_text">
                        Pertanyaan
                    </label>

                    <textarea
                        class="form-control"
                        id="question_text"
                        name="question_text"
                        rows="3"
                        required><?= htmlspecialchars($questionText) ?></textarea>

                </div>

                <hr>

                <h5>Pilihan Jawaban</h5>

                <p class="text-muted">
                    Isi keempat pilihan dan pilih satu jawaban yang benar.
                </p>

                <?php foreach (["A", "B", "C", "D"] as $label): ?>

                    <div class="mb-3">

                        <label class="form-label">
                            Pilihan <?= $label ?>
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="options[<?= $label ?>]"
                            value="<?= htmlspecialchars($options[$label]) ?>"
                            required>

                        <div class="form-check mt-2">

                            <input
                                class="form-check-input"
                                type="radio"
                                name="correct_option"
                                value="<?= $label ?>"
                                <?= $correctOption === $label ? "checked" : "" ?>
                                required>

                            <label class="form-check-label">
                                Jadikan jawaban benar
                            </label>

                        </div>

                    </div>

                <?php endforeach; ?>

                <button type="submit" class="btn btn-primary">
                    Simpan Pertanyaan
                </button>

            </form>

        </div>

    </main>

    <?php require_once __DIR__ . "/../../includes/footer.php"; ?>

</body>

</html>