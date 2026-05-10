<?php
/*
 * check_email_domain.php  — PrepHub
 * Always calls Abstract API for real mailbox verification.
 * DNS fallback ONLY when API is completely unreachable (no response at all).
 */

header('Content-Type: application/json');

define('ABSTRACT_API_KEY', '58c72b06d34b4e6d8570caa1556141d1');

$email  = isset($_GET['email']) ? trim($_GET['email']) : '';
$domain = '';

/* ── Helper: DNS-only check (last-resort fallback only) ── */
function dnsCheck(string $domain): bool {
    return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
}

/* ── Step 1: Basic format check ── */
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['valid' => false, 'reason' => 'Invalid email format.', 'domain' => '']);
    exit;
}

$parts  = explode('@', $email);
$domain = strtolower($parts[1]);

/* ── Step 2: Call Abstract API via cURL (always, even on localhost) ── */
$url = 'https://emailvalidation.abstractapi.com/v1/?api_key=' . ABSTRACT_API_KEY
     . '&email=' . urlencode($email);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_CONNECTTIMEOUT => 6,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_USERAGENT      => 'PrepHub/1.0',
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
]);

$response  = curl_exec($ch);
$httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

/* ── Step 3: cURL completely failed (no internet) → DNS fallback ── */
if ($response === false || !empty($curlError) || $httpCode === 0) {
    $ok = dnsCheck($domain);
    echo json_encode([
        'valid'  => $ok,
        'reason' => $ok
            ? 'Domain verified (offline fallback). Mailbox not confirmed.'
            : "Domain <b>$domain</b> does not exist.",
        'domain' => $domain,
        'source' => 'dns_fallback',
    ]);
    exit;
}

/* ── Step 4: API key invalid ── */
if ($httpCode === 401) {
    echo json_encode([
        'valid'  => false,
        'reason' => 'Email verification service error. Please contact support.',
        'domain' => $domain,
        'source' => 'api_401',
    ]);
    exit;
}

/* ── Step 5: Rate limit hit ── */
if ($httpCode === 429) {
    $ok = dnsCheck($domain);
    echo json_encode([
        'valid'  => $ok,
        'reason' => $ok
            ? 'Verification limit reached. Domain looks valid — server will verify on submit.'
            : "Domain <b>$domain</b> does not appear to exist.",
        'domain' => $domain,
        'source' => 'api_429',
    ]);
    exit;
}

/* ── Step 6: Any other non-200 ── */
if ($httpCode !== 200) {
    echo json_encode([
        'valid'  => false,
        'reason' => 'Email verification failed (HTTP ' . $httpCode . '). Please try again.',
        'domain' => $domain,
        'source' => 'api_http_' . $httpCode,
    ]);
    exit;
}

/* ── Step 7: Parse JSON ── */
$data = json_decode($response, true);

if (!$data || !is_array($data)) {
    echo json_encode([
        'valid'  => false,
        'reason' => 'Email verification returned an unexpected response. Please try again.',
        'domain' => $domain,
        'source' => 'bad_json',
    ]);
    exit;
}

$format      = !empty($data['is_valid_format']['value']);
$mx          = !empty($data['is_mx_found']['value']);
$smtp        = !empty($data['is_smtp_valid']['value']);
$disposable  = !empty($data['is_disposable_email']['value']);
$deliverable = isset($data['deliverability']) ? strtoupper(trim($data['deliverability'])) : 'UNKNOWN';
$quality     = isset($data['quality_score']) ? (float) $data['quality_score'] : 0.0;

/* ── Step 8: Strict decision logic ── */

if (!$format) {
    echo json_encode(['valid' => false, 'reason' => 'Invalid email format.', 'domain' => $domain]);
    exit;
}

if ($disposable) {
    echo json_encode([
        'valid'  => false,
        'reason' => 'Disposable/temporary emails are not allowed.',
        'domain' => $domain,
    ]);
    exit;
}

if (!$mx) {
    echo json_encode([
        'valid'  => false,
        'reason' => "The domain <b>$domain</b> has no mail server. This email cannot exist.",
        'domain' => $domain,
    ]);
    exit;
}

if ($deliverable === 'UNDELIVERABLE') {
    echo json_encode([
        'valid'  => false,
        'reason' => 'This email address does not exist or cannot receive mail.',
        'domain' => $domain,
    ]);
    exit;
}

if ($deliverable === 'DELIVERABLE') {
    echo json_encode([
        'valid'  => true,
        'reason' => 'Email address verified ✓',
        'domain' => $domain,
        'source' => 'abstract_api',
    ]);
    exit;
}

/*
  UNKNOWN deliverability:
  Gmail, Yahoo, Outlook etc. ALWAYS return UNKNOWN because they block SMTP probing.
  For these trusted providers, trust MX + quality score instead.
  For unknown/custom domains, require SMTP to be valid too.
*/
$trustedDomains = [
    'gmail.com', 'googlemail.com',
    'yahoo.com', 'yahoo.in', 'yahoo.co.in', 'ymail.com',
    'outlook.com', 'hotmail.com', 'live.com', 'msn.com',
    'icloud.com', 'me.com', 'mac.com',
    'protonmail.com', 'proton.me',
    'rediffmail.com',
];

if (in_array($domain, $trustedDomains, true)) {
    // Big providers block SMTP probing — trust MX + quality score
    if ($mx && $quality >= 0.50) {
        echo json_encode([
            'valid'  => true,
            'reason' => 'Email address verified ✓',
            'domain' => $domain,
            'source' => 'trusted_domain',
        ]);
    } else {
        echo json_encode([
            'valid'  => false,
            'reason' => 'This email address appears to be invalid.',
            'domain' => $domain,
            'source' => 'trusted_domain_low_quality',
        ]);
    }
    exit;
}

// Custom / unknown domain — require SMTP + MX + quality
if ($smtp && $mx && $quality >= 0.70) {
    echo json_encode([
        'valid'  => true,
        'reason' => 'Email address verified ✓',
        'domain' => $domain,
        'source' => 'abstract_api_quality',
    ]);
} else {
    echo json_encode([
        'valid'  => false,
        'reason' => 'This email address appears invalid or does not exist.',
        'domain' => $domain,
        'source' => 'abstract_api_strict',
    ]);
}
?>