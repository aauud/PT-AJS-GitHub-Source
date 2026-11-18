<?php

require_once __DIR__ . "/../../includes/app_path.php";

require_once __DIR__ . "/guard.php";

// 1. Buat sertifikat yang belum tersedia untuk percobaan yang sudah lulus.

// Ini karena sebelumnya belum implementasi pembuatan sertifikat
// padahal sudah ada Member yang dinyatakan lulus. 
// Jadi, ini mendukung pembuatan sertifikat untuk Member yang sudah lulus
// sebelum implementasi sertifikat otomatis.
// Implementasi sertifikat tidak jadi "Pengembangan Lanjut"

// DISTINCT ON untuk memilih hasil transaksi / posttest yang pertama kali lulus
// dengan tujuan apabila ada kasus anomali di mana terbaca dua kali lulus, 
// tetap hanya akan mencetak setifikat sekali 

$queryGenerate = "
    WITH passed_attempts AS (

        SELECT DISTINCT ON (pa.transaction_id)

            pa.transaction_id,
            pa.attempt_id,

            a.account_full_name,
            t.training_topic

        FROM posttest_attempts pa

        JOIN transactions tr
            ON pa.transaction_id = tr.transaction_id

        JOIN accounts a
            ON tr.account_id = a.account_id

        JOIN schedules s
            ON tr.schedule_id = s.schedule_id

        JOIN trainings t
            ON s.training_id = t.training_id

        WHERE tr.account_id = $1
        AND pa.attempt_is_passed = TRUE

        AND NULLIF(
            BTRIM(a.account_full_name),
            ''
        ) IS NOT NULL

        ORDER BY
            pa.transaction_id,
            pa.attempt_submitted_at ASC,
            pa.attempt_id ASC
    )

    INSERT INTO certificates (
        transaction_id,
        attempt_id,
        certificate_number,
        certificate_name,
        certificate_training_title,
        certificate_issued_at
    )

    SELECT

        transaction_id,
        attempt_id,

        'AJS/'
        || TO_CHAR(
            CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Jakarta',
            'YYYY'
        )
        || '/'
        || LPAD(
            transaction_id::TEXT,
            GREATEST(6, LENGTH(transaction_id::TEXT)),
            '0'
        ),

        BTRIM(account_full_name),

        LEFT(training_topic, 200),

        CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Jakarta'

    FROM passed_attempts

    ON CONFLICT (transaction_id)
    DO NOTHING
";

$resultGenerate = pg_query_params(
    $conn,
    $queryGenerate,
    [$_SESSION["account_id"]]
);

if (!$resultGenerate) {
    error_log(pg_last_error($conn));
    exit("Gagal memproses penerbitan sertifikat.");
}

// 2. Ambil seluruh sertifikat milik Member ini.
$queryCertificates = "
    SELECT
        c.certificate_id,
        c.transaction_id,
        c.certificate_number,
        c.certificate_name,
        c.certificate_training_title,
        c.certificate_issued_at

    FROM certificates c

    JOIN transactions tr
        ON c.transaction_id = tr.transaction_id

    WHERE tr.account_id = $1

    ORDER BY c.certificate_issued_at DESC
";

$resultCertificates = pg_query_params(
    $conn,
    $queryCertificates,
    [$_SESSION["account_id"]]
);

if (!$resultCertificates) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil data sertifikat.");
}

$certificates = [];

while ($row = pg_fetch_assoc($resultCertificates)) {
    $certificates[] = $row;
}

// Periksa apakah ada percobaan yang lulus tetapi belum memiliki nama lengkap.
$queryMissingName = "
    SELECT COUNT(DISTINCT pa.transaction_id) AS total

    FROM posttest_attempts pa

    JOIN transactions tr
        ON pa.transaction_id = tr.transaction_id

    JOIN accounts a
        ON tr.account_id = a.account_id

    LEFT JOIN certificates c
        ON pa.transaction_id = c.transaction_id

    WHERE tr.account_id = $1
    AND pa.attempt_is_passed = TRUE
    AND c.certificate_id IS NULL

    AND NULLIF(
        BTRIM(a.account_full_name),
        ''
    ) IS NULL
";

$resultMissingName = pg_query_params(
    $conn,
    $queryMissingName,
    [$_SESSION["account_id"]]
);

if (!$resultMissingName) {
    error_log(pg_last_error($conn));
    exit("Gagal memeriksa kelengkapan data sertifikat.");
}

$missingNameCount = (int) pg_fetch_result(
    $resultMissingName,
    0,
    "total"
);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sertifikat Saya</title>

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


        <div class="container mt-5 mb-5">

            <h2>Sertifikat Saya</h2>

            <p class="text-muted">
                Daftar sertifikat dari program pelatihan
                yang telah Anda selesaikan.
            </p>

            <a
                href="../learning/index.php"
                class="btn btn-secondary mb-3">
                Kembali ke My Learning
            </a>

            <?php if ($missingNameCount > 0): ?>

                <div class="alert alert-warning">

                    Anda memiliki hasil posttest yang telah lulus,
                    tetapi nama lengkap pada akun belum tersedia.

                    Silakan hubungi Admin untuk melengkapi data
                    sebelum sertifikat diterbitkan.

                </div>

            <?php endif; ?>

            <?php if (count($certificates) > 0): ?>

                <div class="table-responsive">

                    <table class="table table-bordered table-striped">

                        <thead class="table-dark">

                            <tr>
                                <th>Nomor Sertifikat</th>
                                <th>Nama Peserta</th>
                                <th>Program Pelatihan</th>
                                <th>Tanggal Terbit</th>
                                <th>Aksi</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($certificates as $row): ?>

                                <?php

                                $timezone = new DateTimeZone("Asia/Jakarta");

                                $issueDate = new DateTimeImmutable(
                                    $row["certificate_issued_at"],
                                    $timezone
                                );

                                ?>

                                <tr>

                                    <td>
                                        <?= htmlspecialchars($row["certificate_number"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row["certificate_name"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row["certificate_training_title"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($issueDate->format("d/m/Y")) ?>
                                    </td>

                                    <td>

                                        <a
                                            href="view.php?certificate_id=<?= (int) $row["certificate_id"] ?>"
                                            class="btn btn-sm btn-primary">
                                            Lihat Sertifikat
                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="alert alert-info">

                    Belum ada sertifikat yang diterbitkan
                    untuk akun Anda.

                </div>

            <?php endif; ?>

        </div>

    </main>

    <?php
    require_once __DIR__ . "/../../includes/footer.php";
    ?>

</body>

</html>