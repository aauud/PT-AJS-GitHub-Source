
<?php

require_once __DIR__ . "/guard.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Permintaan tidak dapat dilakukan.");
}

if (!verifyPosttestCsrf()) {
    http_response_code(403);
    exit("Permintaan tidak valid.");
}

$posttestId = filter_var(
    $_POST["posttest_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

$questionId = filter_var(
    $_POST["question_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (
    $posttestId === false || $posttestId === null ||
    $questionId === false || $questionId === null
) {
    http_response_code(400);
    exit("ID tidak valid.");
}

$posttest = getPosttest($conn, $posttestId);

if (!$posttest) {
    http_response_code(404);
    exit("Posttest tidak ditemukan.");
}

if (areQuestionsLocked($conn, $posttest)) {
    http_response_code(403);
    exit("Pertanyaan tidak dapat dihapus.");
}

// Periksa kepemilikan data.
$resultQuestion = pg_query_params(
    $conn,
    "
    SELECT question_id
    FROM posttest_questions
    WHERE question_id = $1
    AND posttest_id = $2
    ",
    [$questionId, $posttestId]
);

if (!$resultQuestion || pg_num_rows($resultQuestion) === 0) {
    http_response_code(404);
    exit("Pertanyaan tidak ditemukan.");
}

if (!pg_query($conn, "BEGIN")) {
    error_log(pg_last_error($conn));
    exit("Gagal memulai proses penghapusan.");
}

// Hapus pilihan jawaban terlebih dahulu.
$resultOptions = pg_query_params(
    $conn,
    "
    DELETE FROM posttest_options
    WHERE question_id = $1
    ",
    [$questionId]
);

if (!$resultOptions) {

    pg_query($conn, "ROLLBACK");

    error_log(pg_last_error($conn));

    exit("Gagal menghapus pilihan jawaban.");
}

// Setelah itu, baru dpaat menghapus pertanyaan.
$resultDelete = pg_query_params(
    $conn,
    "
    DELETE FROM posttest_questions
    WHERE question_id = $1
    AND posttest_id = $2
    ",
    [$questionId, $posttestId]
);

if (!$resultDelete) {

    pg_query($conn, "ROLLBACK");

    error_log(pg_last_error($conn));

    exit("Gagal menghapus pertanyaan.");
}

if (!pg_query($conn, "COMMIT")) {

    pg_query($conn, "ROLLBACK");

    error_log(pg_last_error($conn));

    exit("Gagal menyelesaikan penghapusan.");
}

header(
    "Location: questions.php?posttest_id="
        . $posttestId
        . "&deleted=1"
);

exit;

?>
