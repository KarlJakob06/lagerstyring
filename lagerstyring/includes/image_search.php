<?php
/**
 * Bildesøk via Google Custom Search API + nedlasting av valgt bilde.
 *
 * Krever to nøkler i config.local.php (gratis inntil 100 søk/dag):
 *
 *   define('GOOGLE_API_KEY', '...');  // console.cloud.google.com → aktiver
 *                                     // "Custom Search API" → lag API-nøkkel
 *   define('GOOGLE_CSE_ID',  '...');  // programmablesearchengine.google.com →
 *                                     // ny søkemotor → "Søk på hele nettet" +
 *                                     // bildesøk PÅ → kopier søkemotor-ID-en
 */

if (!defined('GOOGLE_API_KEY')) define('GOOGLE_API_KEY', '');
if (!defined('GOOGLE_CSE_ID'))  define('GOOGLE_CSE_ID', '');

/** Søk etter produktbilder. Returnerer ['ok', 'results' => [{url, thumb, title}], 'error']. */
function google_image_search(string $query): array {
    $query = trim($query);
    if ($query === '') {
        return ['ok' => false, 'error' => 'Fyll inn varenavn eller elnummer først.'];
    }
    if (GOOGLE_API_KEY === '' || GOOGLE_CSE_ID === '') {
        return ['ok' => false, 'error' => 'Bildesøk er ikke satt opp ennå: legg GOOGLE_API_KEY og '
            . 'GOOGLE_CSE_ID inn i config.local.php (se config.local.example.php).'];
    }

    $url = 'https://www.googleapis.com/customsearch/v1?' . http_build_query([
        'key'        => GOOGLE_API_KEY,
        'cx'         => GOOGLE_CSE_ID,
        'q'          => $query,
        'searchType' => 'image',
        'num'        => 8,
        'safe'       => 'active',
    ]);

    $raw = image_search_http_get($url);
    if ($raw === null) {
        return ['ok' => false, 'error' => 'Fikk ikke kontakt med Google-API-et. Sjekk nøklene i config.local.php.'];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Uventet svar fra Google-API-et.'];
    }
    if (isset($data['error']['message'])) {
        return ['ok' => false, 'error' => 'Google-API-et avviste søket: ' . $data['error']['message']];
    }

    $results = [];
    foreach ($data['items'] ?? [] as $item) {
        if (empty($item['link'])) continue;
        $results[] = [
            'url'   => $item['link'],
            'thumb' => $item['image']['thumbnailLink'] ?? $item['link'],
            'title' => $item['title'] ?? '',
        ];
    }
    if (!$results) {
        return ['ok' => false, 'error' => 'Ingen bilder funnet for «' . $query . '».'];
    }
    return ['ok' => true, 'results' => $results];
}

/** Last ned valgt bilde til uploads/. Returnerer ['ok', 'filename', 'error']. */
function download_image_to_uploads(string $url): array {
    if (!url_is_safe($url)) {
        return ['ok' => false, 'error' => 'Ugyldig bilde-URL.'];
    }
    $bytes = image_search_http_get($url, false);
    if ($bytes === null) {
        return ['ok' => false, 'error' => 'Kunne ikke laste ned bildet — prøv et annet treff.'];
    }
    $filename = save_image_bytes($bytes);
    if (!$filename) {
        return ['ok' => false, 'error' => 'Treffet var ikke et gyldig bilde (eller for stort) — prøv et annet.'];
    }
    return ['ok' => true, 'filename' => $filename];
}

/** Kun http(s)-URL-er mot offentlige adresser — hindrer at serveren
 *  lures til å hente interne ressurser (SSRF). */
function url_is_safe(string $url): bool {
    $parts = parse_url($url);
    if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
        return false;
    }
    $ip = gethostbyname($parts['host']);
    return (bool)filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    );
}

function image_search_http_get(string $url, bool $follow_redirects = true): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $follow_redirects,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (lagerstyring-arbeidsbil)',
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ($data !== false && $code === 200) ? $data : null;
}

/** Lagre bildebytes til uploads/ med tilfeldig filnavn (mime-validert). */
function save_image_bytes(string $bytes): ?string {
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
