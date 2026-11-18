<?php

require_once __DIR__ . "/../../includes/app_path.php";
require_once __DIR__ . "/../guard.php";

// 1. Token CSRF
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$errors = [];

$allowedStatuses = [
    "Baru",
    "Diproses",
    "Dijawab",
    "Ditutup"
];

// 2. Perbarui status pertanyaan
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Verifikasi token CSRF.
    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION["csrf_token"], $submittedToken)
    ) {

        $errors[] = "Permintaan tidak valid.";
    } else {

        $inquiryId = filter_var(
            $_POST["inquiry_id"] ?? null,
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]]
        );

        $newStatus = $_POST["inquiry_status"] ?? "";

        // Validasi ID pertanyaan.
        if (!$inquiryId) {
            $errors[] = "ID pertanyaan tidak valid.";
        }

        // Validasi status.
        if (!in_array($newStatus, $allowedStatuses, true)) {
            $errors[] = "Status pertanyaan tidak valid.";
        }

        // Perbarui data.
        if (empty($errors)) {

            $queryUpdate = "
                UPDATE contact_inquiries

                SET
                    inquiry_status = $1,
                    inquiry_updated_at = CURRENT_TIMESTAMP

                WHERE inquiry_id = $2

                RETURNING inquiry_id
            ";

            $resultUpdate = pg_query_params(
                $conn,
                $queryUpdate,
                [$newStatus, $inquiryId]
            );

            if (!$resultUpdate) {

                error_log(pg_last_error($conn));

                $errors[] = "Gagal memperbarui status pertanyaan.";
            } elseif (!pg_fetch_assoc($resultUpdate)) {

                $errors[] = "Pertanyaan tidak ditemukan.";
            } else {

                // Cegah pengiriman ulang formulir.

                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

                header("Location: index.php?updated=1");
                exit;
            }
        }
    }
}

// 3. Ambil daftar pertanyaan
$queryInquiries = "

    SELECT

        i.inquiry_id,
        i.account_id,
        i.inquiry_email,
        i.inquiry_message,
        i.inquiry_status,
        i.inquiry_created_at,
        i.inquiry_updated_at,

        CASE
            WHEN i.account_id IS NULL THEN 'Guest'
            ELSE 'Member'
        END AS sender_type,

        COALESCE(
            NULLIF(a.account_full_name, ''),
            a.account_username,
            'Guest'
        ) AS sender_name

    FROM contact_inquiries i

    LEFT JOIN accounts a
        ON i.account_id = a.account_id

    ORDER BY

        CASE i.inquiry_status
            WHEN 'Baru' THEN 1
            WHEN 'Diproses' THEN 2
            WHEN 'Dijawab' THEN 3
            WHEN 'Ditutup' THEN 4
        END,

        i.inquiry_created_at DESC

";

$resultInquiries = pg_query($conn, $queryInquiries);

if (!$resultInquiries) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil data pertanyaan.");
}

$inquiries = [];

while ($row = pg_fetch_assoc($resultInquiries)) {
    $inquiries[] = $row;
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pertanyaan Masuk - Admin PT AJS</title>

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


    <main class="container-fluid px-4 py-4 page-content">

        <div class="mb-4">

            <h2>
                Pertanyaan Masuk
            </h2>

            <p class="text-muted">
                Daftar pertanyaan yang dikirim melalui halaman Kontak.
            </p>

        </div>


        <?php if (isset($_GET["updated"])): ?>

            <div class="alert alert-success">
                Status pertanyaan berhasil diperbarui.
            </div>

        <?php endif; ?>


        <?php if (!empty($errors)): ?>

            <div class="alert alert-danger">

                <ul class="mb-0">

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= htmlspecialchars(
                                $error,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <div class="card shadow-sm border-0">

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>

                            <tr>
                                <th>ID</th>
                                <th>Waktu</th>
                                <th>Pengirim</th>
                                <th>Email</th>
                                <th>Pertanyaan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>

                        </thead>


                        <tbody>

                            <?php if (!empty($inquiries)): ?>

                                <?php foreach ($inquiries as $row): ?>

                                    <tr>

                                        <td>
                                            <?= (int) $row["inquiry_id"] ?>
                                        </td>


                                        <td class="text-nowrap">

                                            <?= date(
                                                "d/m/Y H:i",
                                                strtotime($row["inquiry_created_at"])
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $row["sender_name"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>

                                            <br>

                                            <small class="text-muted">

                                                <?= htmlspecialchars(
                                                    $row["sender_type"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>

                                            </small>

                                        </td>


                                        <td>

                                            <a href="mailto:<?= htmlspecialchars(
                                                                $row["inquiry_email"],
                                                                ENT_QUOTES,
                                                                "UTF-8"
                                                            ) ?>">

                                                <?= htmlspecialchars(
                                                    $row["inquiry_email"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>

                                            </a>

                                        </td>


                                        <td style="min-width: 250px;">

                                            <?= nl2br(
                                                htmlspecialchars(
                                                    $row["inquiry_message"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                )
                                            ) ?>

                                        </td>


                                        <td>

                                            <?php

                                            $statusBadge = "secondary";

                                            if ($row["inquiry_status"] === "Baru") {
                                                $statusBadge = "danger";
                                            } elseif ($row["inquiry_status"] === "Diproses") {
                                                $statusBadge = "warning";
                                            } elseif ($row["inquiry_status"] === "Dijawab") {
                                                $statusBadge = "success";
                                            }

                                            ?>

                                            <span class="badge text-bg-<?= $statusBadge ?>">

                                                <?= htmlspecialchars(
                                                    $row["inquiry_status"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>

                                            </span>

                                        </td>


                                        <td style="min-width: 190px;">

                                            <form
                                                method="POST"
                                                action="index.php">

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= htmlspecialchars(
                                                                $_SESSION["csrf_token"],
                                                                ENT_QUOTES,
                                                                "UTF-8"
                                                            ) ?>">


                                                <input
                                                    type="hidden"
                                                    name="inquiry_id"
                                                    value="<?= (int) $row["inquiry_id"] ?>">


                                                <select
                                                    name="inquiry_status"
                                                    class="form-select form-select-sm mb-2"
                                                    required>

                                                    <?php foreach ($allowedStatuses as $status): ?>

                                                        <option
                                                            value="<?= htmlspecialchars(
                                                                        $status,
                                                                        ENT_QUOTES,
                                                                        "UTF-8"
                                                                    ) ?>"

                                                            <?= $row["inquiry_status"] === $status
                                                                ? "selected"
                                                                : "" ?>>

                                                            <?= htmlspecialchars(
                                                                $status,
                                                                ENT_QUOTES,
                                                                "UTF-8"
                                                            ) ?>

                                                        </option>

                                                    <?php endforeach; ?>

                                                </select>


                                                <button
                                                    type="submit"
                                                    class="btn btn-primary btn-sm w-100">
                                                    Simpan Status
                                                </button>

                                            </form>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>

                                    <td
                                        colspan="7"
                                        class="text-center text-muted py-4">
                                        Belum ada pertanyaan masuk.
                                    </td>

                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </main>

    <?php
    require_once __DIR__ . "/../../includes/footer.php";
    ?>

</body>

</html>