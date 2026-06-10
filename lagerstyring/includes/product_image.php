<?php
/**
 * Henter produktbilde (og produktnavn) fra EFObasen basert på elnummer.
 *
 * EFObasen (efobasen.efo.no) er elektrobransjens felles produktregister —
 * det er her grossistene (Ahlsell, Onninen, Solar, Sonepar m.fl.) selv
 * henter produktdata og bilder fra. Ett oppslag her dekker derfor alle.
 *
 * Test fra kommandolinjen (f.eks. via SSH på webhotellet):
 *   php includes/product_image.php 1400282
 *
 * Hvis EFObasen endrer API-et sitt: åpne en produktside på
 * efobasen.efo.no med nettleserens utviklerverktøy (Network-fanen)
 * og juster URL-ene nedenfor til det som faktisk kalles.
 */

const EFOBASEN_INFO_URL = 'https://efobasen.efo.no/API/VisProdukt/HentProduktinfo?produktnr=%s';
const EFOBASEN_FILE_URL = 'https://efobasen.efo.no/API/Produktfiler/LastNed?id=%s';

if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', __DIR__ . '/../uploads/');
    define('MAX_FILE_SIZE', 5 * 1024 * 1024);
}

/** Enkel GET med korte tidsavbrudd så lagring av varer ikke henger. */
function efobasen_http_get(string $url): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (lagerstyring-arbeidsbil)',
        CURLOPT_HTTPHEADER     => ['Accept: application/json, */*'],
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ($data !== false && $code === 200) ? $data : null;
}

/**
 * Let rekursivt i API-svaret etter fil-ID-er som hører til produktbilder.
 * Strukturen kan variere, så dette er bevisst tolerant: noder som
 * inneholder teksten "produktbilde" gjennomsøkes for id-felter.
 */
function efobasen_collect_image_ids(array $node, array &$ids): void {
    foreach ($node as $k => $v) {
        // Markør som verdi: {"Type": "Produktbilde", "Filer": [{"Id": 123}]}
        if (is_string($v) && stripos($v, 'produktbilde') !== false) {
            efobasen_collect_ids($node, $ids);
        }
        // Markør som nøkkel: {"Produktbilde": {"FilId": 123}} eller {"Produktbilde": 123}
        if (is_string($k) && stripos($k, 'produktbilde') !== false) {
            if (is_array($v)) {
                efobasen_collect_ids($v, $ids);
            } elseif (is_numeric($v)) {
                $ids[] = (int)$v;
            }
        }
    }
    foreach ($node as $v) {
        if (is_array($v)) {
            efobasen_collect_image_ids($v, $ids);
        }
    }
}

function efobasen_collect_ids(array $node, array &$ids): void {
    foreach ($node as $k => $v) {
        if (is_array($v)) {
            efobasen_collect_ids($v, $ids);
        } elseif (is_numeric($v) && is_string($k) && preg_match('/^(fil)?_?id$/i', $k)) {
            $ids[] = (int)$v;
        }
    }
}

/** Let etter produktnavn/varetekst i API-svaret (til forhåndsutfylling). */
function efobasen_find_name(array $node): ?string {
    foreach ($node as $k => $v) {
        if (is_string($k) && is_string($v) && $v !== ''
            && preg_match('/produktnavn|varetekst|beskrivelse/i', $k)) {
            return mb_substr(trim($v), 0, 120);
        }
    }
    foreach ($node as $v) {
        if (is_array($v) && ($name = efobasen_find_name($v))) {
            return $name;
        }
    }
    return null;
}

/** Lagre bildebytes til uploads/ med tilfeldig filnavn. Returnerer filnavnet. */
function save_fetched_image(string $bytes): ?string {
    if ($bytes === '' || strlen($bytes) > MAX_FILE_SIZE) {
        return null;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->buffer($bytes);
    $ext   = ['image/jpeg' => 'jpg', 'image/png' => 'png',
              'image/gif' => 'gif', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) {
        return null;
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    $filename = bin2hex(random_bytes(12)) . '.' . $ext;
    return file_put_contents(UPLOAD_DIR . $filename, $bytes) !== false ? $filename : null;
}

/**
 * Hovedfunksjon: hent produktbilde for et elnummer.
 * Returnerer ['ok' => bool, 'filename' => ?string, 'name' => ?string, 'error' => ?string]
 */
function fetch_product_image(string $elnummer): array {
    $el = preg_replace('/\D/', '', $elnummer);
    if (strlen($el) < 6 || strlen($el) > 8) {
        return ['ok' => false, 'error' => 'Ugyldig elnummer (forventer 6–8 siffer).'];
    }

    $raw = efobasen_http_get(sprintf(EFOBASEN_INFO_URL, $el));
    if ($raw === null) {
        return ['ok' => false, 'error' => 'Fikk ikke kontakt med EFObasen, eller produktet finnes ikke.'];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Uventet svar fra EFObasen (ikke JSON).'];
    }

    $ids = [];
    efobasen_collect_image_ids($data, $ids);
    $ids = array_slice(array_unique($ids), 0, 3);
    if (!$ids) {
        return ['ok' => false, 'error' => 'Produktet ble funnet, men har ikke noe bilde i EFObasen.'];
    }

    foreach ($ids as $id) {
        $bytes = efobasen_http_get(sprintf(EFOBASEN_FILE_URL, $id));
        if ($bytes !== null && ($filename = save_fetched_image($bytes))) {
            return [
                'ok'       => true,
                'filename' => $filename,
                'name'     => efobasen_find_name($data),
            ];
        }
    }
    return ['ok' => false, 'error' => 'Fant bildereferanser, men klarte ikke å laste ned et gyldig bilde.'];
}

// Kjørbar direkte fra CLI for testing: php includes/product_image.php 1400282
if (PHP_SAPI === 'cli' && isset($argv[0]) && basename($argv[0]) === basename(__FILE__)) {
    $result = fetch_product_image($argv[1] ?? '');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($result['ok'] ? 0 : 1);
}
