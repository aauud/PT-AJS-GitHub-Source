<?php
require_once __DIR__ . "/app_path.php";
?>

<footer class="site-footer">

    <div class="container">

        <div class="row align-items-center g-3">

            <div class="col-md-6">

                <h5 class="text-white fw-bold">
                    PT Arta Jaya Sampurna
                </h5>

                <p class="small">
                    Sistem Informasi Program Pelatihan
                </p>

            </div>

            <div class="col-md-6 text-md-end">

                <a
                    href="<?= htmlspecialchars(
                                app_url("catalog.php"),
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                    class="text-white text-decoration-none me-3">
                    Program Pelatihan
                </a>

            </div>

        </div>

        <hr class="border-secondary my-3">

        <p class="small text-center mb-0">

            &copy; <?= date("Y") ?>
            PT Arta Jaya Sampurna. All rights reserved.

        </p>

    </div>

</footer>

<!-- Bootstrap JavaScript Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>