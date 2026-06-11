<?php
/**
 * Lokale innstillinger — kopier denne filen til config.local.php
 * og fyll inn dine egne verdier. config.local.php skal ALDRI i git.
 */

// ── Database (fra Uniweb-kontrollpanelet under "MySQL-databaser") ──
define('DB_HOST', 'localhost');        // Vanligvis localhost hos Uniweb
define('DB_NAME', 'DIN_DATABASE');     // ← Bytt ut
define('DB_USER', 'DIN_DB_BRUKER');    // ← Bytt ut
define('DB_PASS', 'DITT_DB_PASSORD');  // ← Bytt ut

// ── Google-bildesøk (valgfritt, gratis inntil 100 søk/dag) ─────────
// 1. Gå til https://console.cloud.google.com → opprett prosjekt →
//    søk opp og aktiver "Custom Search API" → Credentials → lag API-nøkkel
// 2. Gå til https://programmablesearchengine.google.com → ny søkemotor →
//    velg "Søk på hele nettet" og slå PÅ bildesøk → kopier søkemotor-ID-en
// Uten disse fungerer alt annet som normalt — bare bildesøket er av.
define('GOOGLE_API_KEY', '');
define('GOOGLE_CSE_ID', '');
