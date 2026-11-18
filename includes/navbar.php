<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/app_path.php";

$navRoot = htmlspecialchars(
    APP_BASE_PATH . "/",
    ENT_QUOTES,
    "UTF-8"
);

$roleName = $_SESSION["role_name"] ?? null;

?>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">

    <div class="container">

        <a
            class="navbar-brand fw-bold text-primary"
            href="<?= $navRoot ?>index.php">
            PT AJS
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNavbar"
            aria-controls="mainNavbar"
            aria-expanded="false"
            aria-label="Buka navigasi">

            <span class="navbar-toggler-icon"></span>

        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">

            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">

                <?php if ($roleName === "Admin"): ?>

                    <!-- ADMIN NAVIGATION -->

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="<?= $navRoot ?>index.php">
                            Home
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="<?= $navRoot ?>admin.php">
                            Dashboard
                        </a>
                    </li>

                    <li class="nav-item dropdown">

                        <a
                            class="nav-link dropdown-toggle"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false">
                            Kelola Data
                        </a>

                        <ul class="dropdown-menu">

                            <li>
                                <a
                                    class="dropdown-item"
                                    href="<?= $navRoot ?>admin/trainings/index.php">
                                    Program Pelatihan
                                </a>
                            </li>

                            <li>
                                <a
                                    class="dropdown-item"
                                    href="<?= $navRoot ?>admin/schedules/index.php">
                                    Jadwal Pelatihan
                                </a>
                            </li>

                            <li>
                                <a
                                    class="dropdown-item"
                                    href="<?= $navRoot ?>admin/inquiries/index.php">
                                    Pertanyaan Masuk
                                </a>
                            </li>

                        </ul>

                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="<?= $navRoot ?>catalog.php">
                            Katalog
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="<?= $navRoot ?>contact.php">
                            Kontak
                        </a>
                    </li>

                <?php elseif ($roleName === "Member"): ?>

                    <!-- MEMBER NAVIGATION -->

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="<?= $navRoot ?>index.php">
                            Home
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="<?= $navRoot ?>member/index.php">
                            Dashboard
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="<?= $navRoot ?>catalog.php">
                            Katalog
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="<?= $navRoot ?>contact.php">
                            Kontak
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="<?= $navRoot ?>member/transactions/index.php">
                            Transaksi
                        </a>
                    </li>

                    <li class="nav-item dropdown">

                        <a
                            class="nav-link dropdown-toggle"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false">
                            Aktivitas Saya
                        </a>

                        <ul class="dropdown-menu">

                            <li>
                                <a
                                    class="dropdown-item"
                                    href="<?= $navRoot ?>member/learning/index.php">
                                    Pembelajaran
                                </a>
                            </li>

                            <li>
                                <a
                                    class="dropdown-item"
                                    href="<?= $navRoot ?>member/certificates/index.php">
                                    Sertifikat
                                </a>
                            </li>

                        </ul>

                    </li>

                <?php else: ?>

                    <!-- GUEST NAVIGATION -->

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="<?= $navRoot ?>index.php">
                            Home
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="<?= $navRoot ?>catalog.php">
                            Program Pelatihan
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="<?= $navRoot ?>contact.php">
                            Kontak
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="<?= $navRoot ?>register.php">
                            Buat Akun
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="btn btn-primary px-4"
                            href="<?= $navRoot ?>login.php">
                            Login
                        </a>
                    </li>

                <?php endif; ?>


                <?php if ($roleName === "Admin" || $roleName === "Member"): ?>

                    <li class="nav-item ms-lg-2">

                        <a
                            class="btn btn-outline-danger btn-sm"
                            href="<?= $navRoot ?>logout.php">
                            Keluar
                        </a>

                    </li>

                <?php endif; ?>

            </ul>

        </div>

    </div>

</nav>