<?php
/**
 * Databasekonfigurasjon — fyll inn dine Uniweb-detaljer.
 * Finn disse i kontrollpanelet under "MySQL-databaser".
 */
define('DB_HOST', 'localhost');           // Vanligvis localhost hos Uniweb
define('DB_NAME', 'DIN_DATABASE_NAVN');   // ← Bytt ut
define('DB_USER', 'DIN_DATABASE_BRUKER'); // ← Bytt ut
define('DB_PASS', 'DITT_DATABASE_PASSORD'); // ← Bytt ut

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
        . 'Sjekk at DB_HOST, DB_NAME, DB_USER og DB_PASS i config.php er korrekte.'
        . '</div>');
}
