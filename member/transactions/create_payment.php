<?php

require_once __DIR__ . "/../../includes/app_path.php";


require_once __DIR__ . "/../guard.php";

$midtransConfig = require __DIR__ . "/../../config/midtrans.php";

$serverKey = $midtransConfig["server_key"];

// Gunakan endpoint Sandbox selama tahap pengembangan dan pengujian.
$midtransUrl = "https://api.sandbox.midtrans.com/v2/charge";

// 3. Tentukan apakah permintaan menggunakan metode POST atau GET.
$paymentId = null;
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // 4. Validasi token CSRF.
    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        empty($_SESSION["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $submittedToken)
    ) {
        exit("Permintaan tidak valid. Silakan kembali dan coba lagi.");
    }

    // 5. Validasi ID transaksi.
    $transactionId = $_POST["transaction_id"] ?? "";

    if (!filter_var($transactionId, FILTER_VALIDATE_INT)) {
        exit("ID transaksi tidak valid.");
    }

    // 6. Ambil transaksi milik Member yang sedang login.
    $queryTransaction = "
        SELECT
            tr.transaction_id,
            tr.transaction_amount,
            tr.transaction_status,
            t.training_topic,
            t.training_code

        FROM transactions tr

        JOIN schedules s
            ON tr.schedule_id = s.schedule_id

        JOIN trainings t
            ON s.training_id = t.training_id

        WHERE tr.transaction_id = $1
        AND tr.account_id = $2
    ";

    $resultTransaction = pg_query_params(
        $conn,
        $queryTransaction,
        [
            $transactionId,
            $_SESSION["account_id"]
        ]
    );

    if (!$resultTransaction) {
        error_log(pg_last_error($conn));
        exit("Gagal mengambil data transaksi.");
    }

    $transaction = pg_fetch_assoc($resultTransaction);

    if (!$transaction) {
        http_response_code(404);
        exit("Transaksi tidak ditemukan.");
    }

    if ($transaction["transaction_status"] !== "Menunggu Pembayaran") {
        exit("Transaksi ini tidak dapat dibayar.");
    }

    // 7. Gunakan kembali pembayaran QRIS yang masih menunggu jika tersedia.
    // Dengan demikian, sistem tidak membuat beberapa kode QRIS yang masih dapat dibayar
    // untuk satu pendaftaran yang sama.
    $queryExistingPayment = "
        SELECT payment_id, payment_qr_url
        FROM payments
        WHERE transaction_id = $1
        AND payment_status = 'Menunggu'
        ORDER BY payment_created_at DESC
        LIMIT 1
    ";

    $resultExistingPayment = pg_query_params(
        $conn,
        $queryExistingPayment,
        [$transactionId]
    );

    if (!$resultExistingPayment) {
        error_log(pg_last_error($conn));
        exit("Gagal memeriksa pembayaran sebelumnya.");
    }

    $existingPayment = pg_fetch_assoc($resultExistingPayment);

    if ($existingPayment) {

        if (!empty($existingPayment["payment_qr_url"])) {

            $paymentId = $existingPayment["payment_id"];
        } else {

            exit("Permintaan pembayaran sebelumnya belum selesai diproses. "
                . "Silakan periksa kembali.");
        }
    } else {

        // 8. Buat ID pesanan yang unik.
        try {

            $orderId = "AJS-TRX-"
                . $transactionId
                . "-"
                . strtoupper(bin2hex(random_bytes(4)));
        } catch (Throwable $e) {

            error_log($e->getMessage());
            exit("Gagal membuat nomor pembayaran.");
        }

        $amount = (float) $transaction["transaction_amount"];

        // 9. Buat catatan pembayaran terlebih dahulu.
        $queryInsertPayment = "
            INSERT INTO payments (
                payment_method,
                payment_status,
                payment_amount,
                payment_order_id,
                transaction_id
            )

            VALUES (
                'QRIS',
                'Menunggu',
                $1,
                $2,
                $3
            )

            RETURNING payment_id
        ";

        $resultInsertPayment = pg_query_params(
            $conn,
            $queryInsertPayment,
            [
                $amount,
                $orderId,
                $transactionId
            ]
        );

        if (!$resultInsertPayment) {
            error_log(pg_last_error($conn));
            exit("Gagal membuat data pembayaran.");
        }

        $newPayment = pg_fetch_assoc($resultInsertPayment);

        $paymentId = $newPayment["payment_id"];

        // 10. Siapkan permintaan ke Midtrans.
        $payload = [
            "payment_type" => "qris",

            "transaction_details" => [
                "order_id" => $orderId,
                "gross_amount" => $amount
            ],

            "item_details" => [
                [
                    "id" => $transaction["training_code"],
                    "price" => $amount,
                    "quantity" => 1,
                    "name" => $transaction["training_topic"]
                ]
            ],

            "qris" => [
                "acquirer" => "gopay"
            ]
        ];

        // 11. Kirim permintaan ke Midtrans.
        $ch = curl_init($midtransUrl);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,

            CURLOPT_USERPWD => $serverKey . ":",
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,

            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "Content-Type: application/json"
            ],

            CURLOPT_POSTFIELDS => json_encode($payload),

            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);

        $httpCode = curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        $curlError = curl_error($ch);

        curl_close($ch);

        // 12. Tangani kegagalan koneksi.
        if ($response === false) {

            error_log("Midtrans cURL error: " . $curlError);

            pg_query_params(
                $conn,
                "
                UPDATE payments
                SET
                    payment_status = 'Gagal',
                    payment_provider_status = 'connection_error'
                WHERE payment_id = $1
                ",
                [$paymentId]
            );

            exit("Tidak dapat terhubung ke layanan pembayaran.");
        }

        // 13. Baca respons.
        $responseData = json_decode($response, true);

        if (
            $httpCode < 200 ||
            $httpCode >= 300 ||
            !is_array($responseData) ||
            ($responseData["status_code"] ?? "") !== "201" ||
            ($responseData["transaction_status"] ?? "") !== "pending"
        ) {

            error_log(
                "Midtrans response: HTTP "
                    . $httpCode
                    . " - "
                    . $response
            );

            $providerStatus = $responseData["status_code"]
                ?? "request_failed";

            pg_query_params(
                $conn,
                "
                UPDATE payments
                SET
                    payment_status = 'Gagal',
                    payment_provider_status = $2
                WHERE payment_id = $1
                ",
                [
                    $paymentId,
                    (string) $providerStatus
                ]
            );

            exit("Midtrans gagal membuat pembayaran QRIS.");
        }

        // 14. Ambil URL gambar QR dari tindakan pada respons.
        $qrUrl = null;

        foreach ($responseData["actions"] ?? [] as $action) {

            if ($action["name"] === "generate-qr-code") {
                $qrUrl = $action["url"];
                break;
            }
        }

        // 15. Validasi URL QR.
        $qrParts = is_string($qrUrl)
            ? parse_url($qrUrl)
            : false;

        $validQrUrl = (
            is_array($qrParts) &&
            ($qrParts["scheme"] ?? "") === "https" &&
            in_array(
                $qrParts["host"] ?? "",
                [
                    "api.midtrans.com",
                    "api.sandbox.midtrans.com"
                ],
                true
            )
        );

        if (!$validQrUrl) {

            error_log("Midtrans QR URL missing or invalid.");

            pg_query_params(
                $conn,
                "
                UPDATE payments
                SET
                    payment_status = 'Gagal',
                    payment_provider_status = 'missing_qr_url'
                WHERE payment_id = $1
                ",
                [$paymentId]
            );

            exit("Informasi kode QR dari Midtrans tidak tersedia.");
        }

        // 16. Simpan informasi respons Midtrans.
        $queryUpdatePayment = "
            UPDATE payments
            SET
                payment_reference = $2,
                payment_provider_status = $3,
                payment_qr_url = $4,
                payment_expiry_at = $5
            WHERE payment_id = $1
        ";

        $resultUpdatePayment = pg_query_params(
            $conn,
            $queryUpdatePayment,
            [
                $paymentId,
                $responseData["transaction_id"],
                $responseData["transaction_status"],
                $qrUrl,
                $responseData["expiry_time"] ?? null
            ]
        );

        if (!$resultUpdatePayment) {

            error_log(
                "Payment update failed: " . pg_last_error($conn)
            );

            exit("Pembayaran dibuat, tetapi data pembayaran gagal diperbarui.");
        }
    }

    // 17. Alihkan ke metode GET untuk mencegah pengiriman formulir berulang.
    header("Location: create_payment.php?id=" . $paymentId);
    exit;
} elseif ($_SERVER["REQUEST_METHOD"] === "GET") {

    // 18. Ambil pembayaran yang sudah ada dan dimiliki Member ini.
    $paymentId = $_GET["id"] ?? "";

    if (!filter_var($paymentId, FILTER_VALIDATE_INT)) {
        http_response_code(400);
        exit("ID pembayaran tidak valid.");
    }
} else {

    http_response_code(405);
    exit("Metode permintaan tidak didukung.");
}

// 19. Ambil informasi pembayaran untuk ditampilkan.
$queryPayment = "
    SELECT
        p.payment_id,
        p.payment_order_id,
        p.payment_amount,
        p.payment_status,
        p.payment_qr_url,

        tr.transaction_id,
        tr.transaction_status,

        t.training_topic

    FROM payments p

    JOIN transactions tr
        ON p.transaction_id = tr.transaction_id

    JOIN schedules s
        ON tr.schedule_id = s.schedule_id

    JOIN trainings t
        ON s.training_id = t.training_id

    WHERE p.payment_id = $1
    AND tr.account_id = $2
";

$resultPayment = pg_query_params(
    $conn,
    $queryPayment,
    [
        $paymentId,
        $_SESSION["account_id"]
    ]
);

if (!$resultPayment) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil informasi pembayaran.");
}

$payment = pg_fetch_assoc($resultPayment);

if (!$payment) {
    http_response_code(404);
    exit("Data pembayaran tidak ditemukan.");
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pembayaran QRIS</title>

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

            <h2>Pembayaran QRIS</h2>

            <a
                href="detail.php?id=<?= $payment["transaction_id"] ?>"
                class="btn btn-secondary mb-3">
                Kembali ke Transaksi
            </a>

            <div class="card" style="max-width: 500px; margin: 0 auto;">

                <div class="card-body text-center">

                    <h4>
                        <?= htmlspecialchars($payment["training_topic"]) ?>
                    </h4>

                    <p>
                        Nomor Pembayaran:
                        <?= htmlspecialchars($payment["payment_order_id"]) ?>
                    </p>

                    <h3>
                        Rp <?= number_format(
                                (float) $payment["payment_amount"],
                                0,
                                ",",
                                "."
                            ) ?>
                    </h3>

                    <hr>

                    <?php if (
                        $payment["payment_status"] === "Menunggu" &&
                        !empty($payment["payment_qr_url"])
                    ): ?>

                        <p>Silakan scan kode QR berikut untuk membayar.</p>

                        <img
                            src="<?= htmlspecialchars($payment["payment_qr_url"], ENT_QUOTES, "UTF-8") ?>"
                            alt="QRIS Payment"
                            style="max-width: 280px; width: 100%;">

                        <p class="text-secondary mt-3">
                            Status pembayaran akan diperbarui setelah sistem
                            menerima konfirmasi dari Midtrans.
                        </p>

                        <a
                            href="https://simulator.sandbox.midtrans.com/v2/qris/index"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn btn-outline-primary">
                            Buka Simulator QRIS (Sandbox)
                        </a>

                    <?php elseif ($payment["payment_status"] === "Lunas"): ?>

                        <div class="alert alert-success">
                            Pembayaran telah berhasil.
                        </div>

                    <?php else: ?>

                        <div class="alert alert-warning">
                            Status pembayaran:
                            <?= htmlspecialchars($payment["payment_status"]) ?>
                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <br>

    </main>

    <?php
    require_once __DIR__ . "/../../includes/footer.php";
    ?>

</body>

</html>