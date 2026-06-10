<?php
/**
 * Applikasjonskonfigurasjon.
 *
 * Databasedetaljene ligger i config.local.php — en fil som IKKE er i git.
 * Dermed kan koden oppdateres automatisk fra GitHub uten at passord
 * overskrives eller havner i repoet.
 *
 * Oppsett på serveren (én gang):
 *   1. Kopier config.local.example.php til config.local.php
 *   2. Fyll inn dine Uniweb-databasedetaljer
 */

$local_config = __DIR__ . '/config.local.php';
if (is_file($local_config)) {
    require $local_config;
} else {
    die('<div style="font-family:sans-serif;padding:2rem;color:#c00">'
        . '<strong>config.local.php mangler.</strong><br>'
        . 'Kopier <code>config.local.example.php</code> til <code>config.local.php</code> '
        . 'på serveren og fyll inn databasedetaljene fra Uniweb-kontrollpanelet.'
        . '</div>');
}

define('APP_NAME', 'Lagerstyring – Arbeidsbil');

// Bildeopplasting
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB maks pr. bilde

// -------------------------------------------------------------------
// PDO-tilkobling — ikke endre nedenfor
// -------------------------------------------------------------------
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:2rem;color:#c00">'
        . 'Kunne ikke koble til databasen.<br>'
        . 'Sjekk at DB_HOST, DB_NAME, DB_USER og DB_PASS i config.local.php er korrekte.'
        . '</div>');
}

// Kjør eventuelle ventende databasemigreringer automatisk
require_once __DIR__ . '/includes/migrations.php';
run_migrations($pdo);
