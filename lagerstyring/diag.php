<?php
/**
 * Midlertidig diagnoseside — viser den faktiske feilen når siden gir 500.
 *
 * Åpne https://ditt-domene.no/lagerstyring/diag.php i nettleseren.
 * SLETT denne filen igjen når feilen er løst (den viser teknisk info).
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

echo "PHP-versjon:        " . PHP_VERSION . "\n";
echo "curl tilgjengelig:  " . (function_exists('curl_init') ? 'JA' : 'NEI') . "\n";
echo "allow_url_fopen:    " . (ini_get('allow_url_fopen') ? 'JA' : 'NEI') . "\n";
echo "fileinfo (finfo):   " . (class_exists('finfo') ? 'JA' : 'NEI') . "\n";
echo "pdo_mysql:          " . (extension_loaded('pdo_mysql') ? 'JA' : 'NEI') . "\n";
echo str_repeat('-', 50) . "\n";

echo "1) Sjekker config.local.php ...\n";
$local = __DIR__ . '/config.local.php';
if (!is_file($local)) {
    echo "   FEIL: config.local.php finnes ikke.\n";
    exit;
}
// Syntakssjekk uten å kjøre filen
$out = [];
$rc  = 0;
@exec('php -l ' . escapeshellarg($local) . ' 2>&1', $out, $rc);
echo '   ' . ($out ? implode("\n   ", $out) : '(kunne ikke kjøre php -l — hopper over)') . "\n";
echo str_repeat('-', 50) . "\n";

echo "2) Laster config.php (databasetilkobling + migrering) ...\n";
require __DIR__ . '/config.php';
echo "   OK — databasen er tilkoblet.\n";
echo str_repeat('-', 50) . "\n";

echo "3) Sjekker at nødvendige kolonner finnes ...\n";
foreach ([['items', 'owner_id'], ['items', 'unit'], ['users', 'failed_attempts'], ['users', 'last_login']] as [$t, $c]) {
    echo "   $t.$c: " . (table_has_column($pdo, $t, $c) ? 'OK' : 'MANGLER') . "\n";
}
echo str_repeat('-', 50) . "\n";
echo "Ferdig. Husk å slette diag.php når alt fungerer.\n";
