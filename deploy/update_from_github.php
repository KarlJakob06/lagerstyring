<?php
/**
 * Automatisk oppdatering fra GitHub — for webhotell UTEN git/SSH.
 *
 * Laster ned siste versjon av repoet som ZIP, pakker ut og synkroniserer
 * app-filene til webroten. config.local.php og uploads/ røres aldri.
 *
 * Oppsett:
 *   1. Last opp denne filen UTENFOR public_html (f.eks. ~/deploy/),
 *      eller sett DEPLOY_SECRET nedenfor hvis den må ligge i webroten.
 *   2. Juster $WEB_DIR nedenfor.
 *   3. Opprett en cron-jobb i Uniweb-kontrollpanelet, f.eks. hvert 15. min:
 *      php /home/DITT_BRUKERNAVN/deploy/update_from_github.php
 */

// ---------- Innstillinger ----------
$GITHUB_REPO   = 'KarlJakob06/lagerstyring';
$BRANCH        = 'main';
$REPO_SUBDIR   = 'lagerstyring';                          // Mappen i repoet som inneholder appen
$WEB_DIR       = dirname(__DIR__) . '/public_html/lagerstyring'; // ← Juster til din webrot
$STATE_FILE    = __DIR__ . '/.last_deployed_commit';
$DEPLOY_SECRET = '';   // Sett en lang, tilfeldig streng hvis skriptet kalles via nettleser/wget
$PRESERVE      = ['config.local.php', 'uploads'];         // Røres aldri i webroten
// -----------------------------------

// Tillat kjøring fra CLI (cron) alltid; via HTTP kun med riktig secret.
if (PHP_SAPI !== 'cli') {
    if ($DEPLOY_SECRET === '' || !hash_equals($DEPLOY_SECRET, $_GET['secret'] ?? '')) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function say(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

function http_get(string $url): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'lagerstyring-deploy',
        CURLOPT_TIMEOUT        => 120,
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($data === false || $code !== 200) {
        throw new RuntimeException("Nedlasting feilet ($code): $url");
    }
    return $data;
}

try {
    // 1. Sjekk siste commit på GitHub — hopp over hvis ingenting er nytt
    $api    = "https://api.github.com/repos/$GITHUB_REPO/commits/$BRANCH";
    $info   = json_decode(http_get($api), true);
    $latest = $info['sha'] ?? null;
    if (!$latest) {
        throw new RuntimeException('Fant ikke siste commit via GitHub-API.');
    }

    $current = is_file($STATE_FILE) ? trim(file_get_contents($STATE_FILE)) : '';
    if ($latest === $current) {
        exit(0); // Ingenting nytt — stille exit så cron-loggen ikke fylles opp
    }
    say("Ny versjon funnet: $latest (var: " . ($current ?: 'ukjent') . ')');

    // 2. Last ned og pakk ut ZIP av repoet
    $zipData = http_get("https://codeload.github.com/$GITHUB_REPO/zip/refs/heads/$BRANCH");
    $tmpZip  = tempnam(sys_get_temp_dir(), 'deploy') . '.zip';
    file_put_contents($tmpZip, $zipData);

    $tmpDir = sys_get_temp_dir() . '/deploy_' . bin2hex(random_bytes(6));
    mkdir($tmpDir, 0755, true);

    $zip = new ZipArchive();
    if ($zip->open($tmpZip) !== true) {
        throw new RuntimeException('Kunne ikke åpne ZIP-filen.');
    }
    $zip->extractTo($tmpDir);
    $zip->close();
    unlink($tmpZip);

    // ZIP-en inneholder én rotmappe: <repo>-<branch>/
    $roots = glob($tmpDir . '/*', GLOB_ONLYDIR);
    $src   = ($roots[0] ?? '') . '/' . $REPO_SUBDIR;
    if (!is_dir($src)) {
        throw new RuntimeException("Fant ikke $REPO_SUBDIR/ i nedlastet ZIP.");
    }

    // 3. Synkroniser filer til webroten (uten å røre $PRESERVE)
    if (!is_dir($WEB_DIR)) {
        mkdir($WEB_DIR, 0755, true);
    }

    $copy = function (string $from, string $to) use (&$copy) {
        foreach (scandir($from) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $f = "$from/$entry";
            $t = "$to/$entry";
            if (is_dir($f)) {
                if (!is_dir($t)) mkdir($t, 0755, true);
                $copy($f, $t);
            } else {
                copy($f, $t);
            }
        }
    };

    foreach (scandir($src) as $entry) {
        if ($entry === '.' || $entry === '..' || in_array($entry, $PRESERVE, true)) continue;
        $f = "$src/$entry";
        $t = "$WEB_DIR/$entry";
        if (is_dir($f)) {
            if (!is_dir($t)) mkdir($t, 0755, true);
            $copy($f, $t);
        } else {
            copy($f, $t);
        }
    }

    // Sørg for at uploads/ finnes med sikkerhetsregler
    if (!is_dir("$WEB_DIR/uploads")) {
        mkdir("$WEB_DIR/uploads", 0755, true);
    }
    if (!is_file("$WEB_DIR/uploads/.htaccess") && is_file("$src/uploads/.htaccess")) {
        copy("$src/uploads/.htaccess", "$WEB_DIR/uploads/.htaccess");
    }

    // 4. Rydd opp og lagre hvilken commit som er deployet
    $rm = function (string $dir) use (&$rm) {
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $p = "$dir/$entry";
            is_dir($p) ? $rm($p) : unlink($p);
        }
        rmdir($dir);
    };
    $rm($tmpDir);

    file_put_contents($STATE_FILE, $latest);
    say('Oppdatering fullført.');
} catch (Throwable $e) {
    say('FEIL: ' . $e->getMessage());
    exit(1);
}
