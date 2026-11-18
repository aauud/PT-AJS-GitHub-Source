<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/config/database.php";

require_once __DIR__ . "/includes/app_path.php";

// Mengambil program yang ingin ditunjukkan

$queryFeatured = "
    SELECT
        t.training_id,
        t.training_code,
        t.training_topic,
        t.training_category,
        t.training_interaction,
        t.training_online_type,
        t.training_target_audience,
        t.training_benefit,
        t.training_fee,

        tr.trainer_name,

        (
            SELECT COUNT(*)
            FROM schedules s
            WHERE s.training_id = t.training_id
            AND s.schedule_start_date >= CURRENT_DATE
        ) AS upcoming_schedules,

        (
            SELECT MIN(s.schedule_start_date)
            FROM schedules s
            WHERE s.training_id = t.training_id
            AND s.schedule_start_date >= CURRENT_DATE
        ) AS next_schedule

    FROM trainings t

    JOIN trainers tr
        ON t.trainer_id = tr.trainer_id

    ORDER BY t.training_id DESC

    LIMIT 3
";

$resultFeatured = pg_query($conn, $queryFeatured);

if (!$resultFeatured) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil informasi program pelatihan.");
}

$featuredTrainings = [];

while ($row = pg_fetch_assoc($resultFeatured)) {
    $featuredTrainings[] = $row;
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>PT Arta Jaya Sampurna - Program Pelatihan</title>

    <!-- CSS Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- Tema PT AJS -->
    <style>
        body {
            background-color: #ffffff;
            color: #343a40;
        }

        .hero-carousel {
            overflow: hidden;
            border-radius: 0 0 24px 24px;
        }

        .hero-slide {
            min-height: 430px;
            display: flex;
            align-items: center;
            padding: 65px 30px;
            color: #ffffff;
        }

        .hero-slide-one {
            background:
                radial-gradient(circle at 85% 20%,
                    rgba(255, 255, 255, 0.20),
                    transparent 25%),
                linear-gradient(120deg, #761c2d, #c0394a);
        }

        .hero-slide-two {
            background:
                radial-gradient(circle at 85% 80%,
                    rgba(255, 255, 255, 0.18),
                    transparent 25%),
                linear-gradient(120deg, #8f2639, #d65a4d);
        }

        .hero-slide-three {
            background:
                radial-gradient(circle at 80% 20%,
                    rgba(255, 255, 255, 0.20),
                    transparent 25%),
                linear-gradient(120deg, #681d31, #a84759);
        }

        .hero-description {
            font-size: 1.05rem;
            max-width: 600px;
            opacity: 0.92;
            line-height: 1.7;
        }

        .hero-decoration {
            font-size: 8rem;
            font-weight: 800;
            text-align: center;
            opacity: 0.15;
            user-select: none;
        }

        .hero-carousel .carousel-indicators {
            margin-bottom: 18px;
        }

        .section-padding {
            padding-top: 75px;
            padding-bottom: 75px;
        }

        .section-heading {
            font-weight: 700;
            color: var(--ajs-red-dark);
        }

        .section-subtitle {
            color: #6c757d;
            max-width: 650px;
            margin: auto;
            line-height: 1.7;
        }

        .intro-panel {
            background: #fff5f6;
            border: 1px solid #f4dce0;
            border-radius: 20px;
            padding: 35px;
            height: 100%;
        }

        .intro-icon {
            background: var(--ajs-red-light);
            color: var(--ajs-red);
            width: 55px;
            height: 55px;
            border-radius: 14px;
            background: var(--ajs-red-light);
            color: var(--ajs-red);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .training-card {
            border: none;
            border-radius: 16px;
            overflow: hidden;
            height: 100%;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.07);
            transition: transform 0.2s ease;
        }

        .training-card:hover {
            transform: translateY(-5px);
        }

        .training-card-top {
            background:
                linear-gradient(135deg,
                    #fbecef,
                    #f4d5d9);

            color: var(--ajs-red);
            min-height: 120px;
            /* background: linear-gradient(135deg, #e8f0ff, #d4e5ff); */
            display: flex;
            align-items: center;
            justify-content: center;
            /* color: #1769aa; */
            font-size: 2.5rem;
            font-weight: 800;
        }

        .training-description {
            color: #6c757d;
            line-height: 1.6;
            min-height: 48px;

            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;

            overflow: hidden;
        }

        .cta-panel {
            background:
                linear-gradient(120deg,
                    #761c2d,
                    #b42e43);

            border-radius: 20px;
            padding: 45px 30px;
            color: #ffffff;
        }
    </style>

    <link
        rel="stylesheet"
        href="<?= htmlspecialchars(
                    app_url("assets/css/ajs-theme.css"),
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>">
</head>


<body>

    <?php require_once __DIR__ . "/includes/navbar.php"; ?>

    <main class="page-content">

        <section id="home">

            <!-- CAROUSEL -->

            <div
                id="mainCarousel"
                class="carousel slide hero-carousel"
                data-bs-ride="carousel">

                <div class="carousel-indicators">

                    <button
                        type="button"
                        data-bs-target="#mainCarousel"
                        data-bs-slide-to="0"
                        class="active"
                        aria-current="true"
                        aria-label="Slide 1"></button>

                    <button
                        type="button"
                        data-bs-target="#mainCarousel"
                        data-bs-slide-to="1"
                        aria-label="Slide 2"></button>

                    <button
                        type="button"
                        data-bs-target="#mainCarousel"
                        data-bs-slide-to="2"
                        aria-label="Slide 3"></button>

                </div>

                <div class="carousel-inner">

                    <!-- Slide 1 -->

                    <div class="carousel-item active">

                        <div class="hero-slide hero-slide-one">

                            <div class="container">

                                <div class="row align-items-center">

                                    <div class="col-lg-8">

                                        <h1 class="hero-title">

                                            Kembangkan Kompetensi,
                                            Wujudkan Potensi.

                                        </h1>

                                        <p class="hero-description">

                                            Temukan program pelatihan yang
                                            mendukung pengembangan keterampilan
                                            teknis maupun nonteknis sesuai
                                            kebutuhan Anda.

                                        </p>

                                        <a
                                            href="catalog.php"
                                            class="btn btn-light btn-lg mt-3">
                                            Jelajahi Program
                                        </a>

                                    </div>

                                    <div class="col-lg-4 d-none d-lg-block">

                                        <div class="hero-decoration">
                                            AJS
                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- Slide 2 -->

                    <div class="carousel-item">

                        <div class="hero-slide hero-slide-two">

                            <div class="container">

                                <div class="row align-items-center">

                                    <div class="col-lg-8">

                                        <h2 class="hero-title">

                                            Pilihan Program
                                            untuk Berbagai Kebutuhan.

                                        </h2>

                                        <p class="hero-description">

                                            Kenali topik pelatihan, metode
                                            pelaksanaan, pelatih, dan informasi
                                            jadwal yang tersedia melalui
                                            katalog program PT AJS.

                                        </p>

                                        <a
                                            href="catalog.php"
                                            class="btn btn-light btn-lg mt-3">
                                            Lihat Katalog
                                        </a>

                                    </div>

                                    <div class="col-lg-4 d-none d-lg-block">

                                        <div class="hero-decoration">
                                            LEARN
                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- Slide 3 -->

                    <div class="carousel-item">

                        <div class="hero-slide hero-slide-three">

                            <div class="container">

                                <div class="row align-items-center">

                                    <div class="col-lg-8">

                                        <h2 class="hero-title">

                                            Mulai dari Informasi
                                            yang Tepat.

                                        </h2>

                                        <p class="hero-description">

                                            Pelajari informasi program terlebih
                                            dahulu untuk menemukan pelatihan
                                            yang sesuai dengan kebutuhan
                                            pengembangan Anda.

                                        </p>

                                        <a
                                            href="catalog.php"
                                            class="btn btn-light btn-lg mt-3">
                                            Temukan Program
                                        </a>

                                    </div>

                                    <div class="col-lg-4 d-none d-lg-block">

                                        <div class="hero-decoration">
                                            STUDY
                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- Tanda Panah Carousel -->

                <button
                    class="carousel-control-prev"
                    type="button"
                    data-bs-target="#mainCarousel"
                    data-bs-slide="prev">

                    <span
                        class="carousel-control-prev-icon"
                        aria-hidden="true"></span>

                    <span class="visually-hidden">
                        Sebelumnya
                    </span>

                </button>

                <button
                    class="carousel-control-next"
                    type="button"
                    data-bs-target="#mainCarousel"
                    data-bs-slide="next">

                    <span
                        class="carousel-control-next-icon"
                        aria-hidden="true"></span>

                    <span class="visually-hidden">
                        Berikutnya
                    </span>

                </button>

            </div>

        </section>

        <!-- TENTANG PT AJS -->

        <section class="section-padding">

            <div class="container">

                <div class="row align-items-center g-4">

                    <div class="col-lg-5">

                        <div class="intro-panel">

                            <div class="intro-icon">
                                A
                            </div>

                            <h3 class="section-heading">
                                Tentang PT Arta Jaya Sampurna
                            </h3>

                            <p class="text-muted mb-0">

                                PT Arta Jaya Sampurna merupakan penyedia
                                program pelatihan yang menghadirkan
                                informasi dan layanan pelatihan untuk
                                mendukung kebutuhan pengembangan kompetensi.

                            </p>

                        </div>

                    </div>


                    <div class="col-lg-7">

                        <h2 class="section-heading mb-3">

                            Mengembangkan Keterampilan
                            Melalui Program Pelatihan

                        </h2>

                        <p class="text-muted">

                            Setiap program memiliki topik, tujuan,
                            metode pelaksanaan, serta informasi jadwal
                            yang dapat dipelajari sebelum melakukan
                            pendaftaran.

                        </p>

                        <p class="text-muted">

                            Melalui website ini, calon peserta dapat
                            mengenal program pelatihan PT AJS dan
                            memperoleh informasi awal secara lebih
                            terstruktur.

                        </p>

                        <a
                            href="catalog.php"
                            class="btn btn-primary mt-2">
                            Lihat Seluruh Program
                        </a>

                    </div>

                </div>

            </div>

        </section>

        <!-- INFORMASI WEBSITE -->

        <section class="py-5 bg-light">

            <div class="container">

                <div class="text-center mb-5">

                    <h2 class="section-heading">
                        Temukan Informasi Pelatihan
                    </h2>

                    <p class="section-subtitle">

                        Kenali informasi program yang tersedia
                        untuk membantu Anda menentukan pilihan
                        pelatihan.

                    </p>

                </div>


                <div class="row g-4">

                    <div class="col-md-4">

                        <div class="intro-panel">

                            <div class="intro-icon">
                                01
                            </div>

                            <h5>
                                Informasi Program
                            </h5>

                            <p class="text-muted mb-0">

                                Pelajari topik, kategori, manfaat,
                                dan target peserta setiap program.

                            </p>

                        </div>

                    </div>


                    <div class="col-md-4">

                        <div class="intro-panel">

                            <div class="intro-icon">
                                02
                            </div>

                            <h5>
                                Metode Pelaksanaan
                            </h5>

                            <p class="text-muted mb-0">

                                Ketahui apakah program diselenggarakan
                                secara daring atau luring serta
                                informasi pelaksanaannya.

                            </p>

                        </div>

                    </div>


                    <div class="col-md-4">

                        <div class="intro-panel">

                            <div class="intro-icon">
                                03
                            </div>

                            <h5>
                                Informasi Jadwal
                            </h5>

                            <p class="text-muted mb-0">

                                Periksa jadwal dan biaya program
                                melalui informasi pelatihan
                                yang tersedia.

                            </p>

                        </div>

                    </div>

                </div>

            </div>

        </section>

        <!-- Menampilkan beberapa program -->

        <section class="section-padding">

            <div class="container">

                <div class="text-center mb-5">

                    <h2 class="section-heading">
                        Program Pelatihan
                    </h2>

                    <p class="section-subtitle">

                        Kenali beberapa program pelatihan
                        yang tersedia di PT AJS.

                    </p>

                </div>


                <div class="row g-4">


                    <?php if (count($featuredTrainings) > 0): ?>


                        <?php foreach ($featuredTrainings as $training): ?>


                            <div class="col-md-6 col-lg-4">

                                <div class="card training-card">

                                    <div class="training-card-top">

                                        <?= htmlspecialchars(
                                            $training["training_code"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </div>


                                    <div class="card-body p-4">

                                        <span class="badge text-bg-primary mb-3">

                                            <?= htmlspecialchars(
                                                $training["training_category"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>

                                        </span>


                                        <h5 class="card-title">

                                            <?= htmlspecialchars(
                                                $training["training_topic"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>

                                        </h5>


                                        <p class="training-description">

                                            <?= htmlspecialchars(
                                                $training["training_benefit"] ?? "",
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>

                                        </p>


                                        <p class="small text-muted mb-2">

                                            <strong>Pelatih:</strong>

                                            <?= htmlspecialchars(
                                                $training["trainer_name"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>

                                        </p>


                                        <p class="small text-muted mb-2">

                                            <strong>Metode:</strong>

                                            <?= htmlspecialchars(
                                                $training["training_interaction"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>


                                            <?php if ($training["training_interaction"] === "Daring" && !empty($training["training_online_type"])): ?>

                                                -

                                                <?= htmlspecialchars(
                                                    $training["training_online_type"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>

                                            <?php endif; ?>

                                        </p>


                                        <p class="small text-muted mb-2">

                                            <strong>Biaya:</strong>

                                            Rp <?= number_format(
                                                    (float) $training["training_fee"],
                                                    0,
                                                    ",",
                                                    "."
                                                ) ?>

                                        </p>


                                        <p class="small text-muted">

                                            <strong>Jadwal mendatang:</strong>

                                            <?php if ($training["next_schedule"]): ?>

                                                <?= date(
                                                    "d/m/Y",
                                                    strtotime($training["next_schedule"])
                                                ) ?>

                                                (<?= (int) $training["upcoming_schedules"] ?> jadwal)

                                            <?php else: ?>

                                                Belum tersedia

                                            <?php endif; ?>

                                        </p>


                                        <a
                                            href="training_detail.php?id=<?= (int) $training["training_id"] ?>"
                                            class="btn btn-outline-primary w-100 mt-2">
                                            Lihat Detail
                                        </a>

                                    </div>

                                </div>

                            </div>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <div class="col-12">

                            <div class="alert alert-secondary text-center">

                                Belum ada program pelatihan yang tersedia.

                            </div>

                        </div>


                    <?php endif; ?>


                </div>


                <div class="text-center mt-5">

                    <a
                        href="catalog.php"
                        class="btn btn-primary btn-lg px-5">
                        Lihat Semua Program
                    </a>

                </div>

            </div>

        </section>

        <!-- Section Call To Action (CTA) -->

        <section class="pb-5">

            <div class="container">

                <div class="cta-panel text-center">

                    <h2 class="fw-bold mb-3">

                        Temukan Program Pelatihan
                        yang Sesuai dengan Kebutuhan Anda

                    </h2>

                    <p class="mb-4">

                        Jelajahi katalog program pelatihan
                        untuk mendapatkan informasi lebih lanjut.

                    </p>

                    <a
                        href="catalog.php"
                        class="btn btn-light btn-lg px-4">
                        Jelajahi Katalog
                    </a>

                </div>

            </div>

        </section>


    </main>

    <?php
    require_once __DIR__ . "/includes/footer.php";
    ?>

</body>

</html>