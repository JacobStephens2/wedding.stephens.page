<?php
/**
 * Site Up Checker (CLI)
 *
 * Checks whether https://wedding.stephens.page is reachable and returning HTTP 200.
 * Sends an alert email when the site is down and a recovery email when it comes back.
 *
 * Run:
 *   php private/cron/site_up_checker.php
 *
 * Env (in private/.env):
 * - SITE_CHECK_URL: string, default "https://wedding.stephens.page/"
 * - SITE_CHECK_TIMEOUT_SECONDS: int, default 15
 * - SITE_CHECK_COOLDOWN_MINUTES: int, default 30 (min interval between repeated down alerts)
 * - SITE_CHECK_RECIPIENTS: comma-separated emails (optional, falls back to RSVP_EMAIL + SMTP_FROM_EMAIL)
 * - SITE_CHECK_DRY_RUN: "1" to avoid sending email
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden\n";
    exit(1);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../email_handler.php';

function parseEmailList(?string $raw): array {
    if (!$raw) return [];
    $parts = preg_split('/[,\s]+/', $raw) ?: [];
    $emails = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        if (filter_var($p, FILTER_VALIDATE_EMAIL)) {
            $emails[] = strtolower($p);
        }
    }
    return array_values(array_unique($emails));
}

function isoNowUtc(): string {
    $dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    return $dt->format('c');
}

function minutesSince(?string $isoTimestamp): ?float {
    if (!$isoTimestamp) return null;
    try {
        $then = new DateTimeImmutable($isoTimestamp);
    } catch (Exception $e) {
        return null;
    }
    $now = new DateTimeImmutable('now', $then->getTimezone());
    $seconds = $now->getTimestamp() - $then->getTimestamp();
    if ($seconds < 0) return 0.0;
    return $seconds / 60.0;
}

// --- Config ---
$url = $_ENV['SITE_CHECK_URL'] ?? 'https://wedding.stephens.page/';
$timeoutSeconds = (int)($_ENV['SITE_CHECK_TIMEOUT_SECONDS'] ?? 15);
if ($timeoutSeconds < 1) $timeoutSeconds = 15;

$cooldownMinutes = (int)($_ENV['SITE_CHECK_COOLDOWN_MINUTES'] ?? 30);
if ($cooldownMinutes < 0) $cooldownMinutes = 0;

$dryRun = (string)($_ENV['SITE_CHECK_DRY_RUN'] ?? '') === '1';

// --- Recipients ---
$recipients = parseEmailList($_ENV['SITE_CHECK_RECIPIENTS'] ?? null);
if (empty($recipients)) {
    $fallback = [];
    $fallback[] = $_ENV['RSVP_EMAIL'] ?? null;
    $fallback[] = $_ENV['CONTACT_EMAIL'] ?? null;
    $fallback[] = $_ENV['MANDRILL_FROM_EMAIL'] ?? null;
    $fallback[] = $_ENV['SMTP_FROM_EMAIL'] ?? null;
    $recipients = parseEmailList(implode(',', array_filter($fallback)));
}

if (empty($recipients)) {
    fwrite(STDERR, "Site up checker: no valid recipients. Set SITE_CHECK_RECIPIENTS in private/.env.\n");
    exit(2);
}

// --- State + lock ---
$stateDir = __DIR__ . '/../cron_state';
if (!is_dir($stateDir)) {
    @mkdir($stateDir, 0750, true);
}
$lockPath  = $stateDir . '/site_up_checker.lock';
$statePath = $stateDir . '/site_up_checker_state.json';

$lockFp = @fopen($lockPath, 'c+');
if (!$lockFp) {
    fwrite(STDERR, "Site up checker: cannot open lock file at $lockPath\n");
    exit(3);
}
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo "Site up checker: already running, exiting.\n";
    exit(0);
}

$prevState = [];
if (is_file($statePath)) {
    $raw = @file_get_contents($statePath);
    $decoded = json_decode($raw ?: '', true);
    if (is_array($decoded)) $prevState = $decoded;
}

// --- Perform check ---
$httpCode = 0;
$error = null;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_TIMEOUT        => $timeoutSeconds,
    CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
    CURLOPT_NOBODY         => true,
    CURLOPT_USERAGENT      => 'WeddingSiteUpChecker/1.0',
]);
$result = curl_exec($ch);
if ($result === false) {
    $error = curl_error($ch);
} else {
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
}
curl_close($ch);

$isUp = ($error === null && $httpCode >= 200 && $httpCode < 400);
$wasDown = (bool)($prevState['is_down'] ?? false);
$lastAlertAt = $prevState['last_alert_at'] ?? null;
$minSinceAlert = minutesSince($lastAlertAt);
$cooldownPassed = ($minSinceAlert === null) ? true : ($minSinceAlert >= $cooldownMinutes);

// --- Decide action ---
$sendDownAlert = false;
$sendRecoveryAlert = false;

if (!$isUp) {
    // Site is down: alert if this is a new outage or cooldown has passed
    if (!$wasDown || $cooldownPassed) {
        $sendDownAlert = true;
    }
}

if ($isUp && $wasDown) {
    $sendRecoveryAlert = true;
}

// --- Compose and send ---
if ($sendDownAlert) {
    $statusDetail = $error ? "Connection error: {$error}" : "HTTP {$httpCode}";
    $subject = "[Wedding Site DOWN] {$url} is not responding";
    $bodyLines = [
        "The wedding website appears to be DOWN.",
        "",
        "URL:    {$url}",
        "Status: {$statusDetail}",
        "Time:   " . isoNowUtc() . " (UTC)",
        "",
        "This check runs every 5 minutes. You will receive another",
        "alert in {$cooldownMinutes} minutes if the site remains down.",
        "",
        "You may want to SSH into the server and check Apache:",
        "  systemctl status apache2",
        "  tail /var/log/apache2/wedding_error.log",
    ];
    $body = implode("\n", $bodyLines);

    if ($dryRun) {
        echo "DRY RUN: would send DOWN alert to: " . implode(', ', $recipients) . "\n";
        echo "Subject: $subject\n";
        echo $body . "\n";
    } else {
        $allOk = true;
        foreach ($recipients as $to) {
            if (!sendEmail($to, $subject, $body)) $allOk = false;
        }
        if ($allOk) {
            echo "Site up checker: DOWN alert sent (" . implode(', ', $recipients) . ").\n";
        } else {
            fwrite(STDERR, "Site up checker: failed to send one or more DOWN alert emails.\n");
        }
    }
}

if ($sendRecoveryAlert) {
    $downSince = $prevState['down_since'] ?? 'unknown';
    $subject = "[Wedding Site RECOVERED] {$url} is back online";
    $bodyLines = [
        "The wedding website is back UP.",
        "",
        "URL:            {$url}",
        "HTTP status:    {$httpCode}",
        "Recovered at:   " . isoNowUtc() . " (UTC)",
        "Down since:     {$downSince} (UTC)",
    ];
    $body = implode("\n", $bodyLines);

    if ($dryRun) {
        echo "DRY RUN: would send RECOVERY alert to: " . implode(', ', $recipients) . "\n";
        echo "Subject: $subject\n";
        echo $body . "\n";
    } else {
        $allOk = true;
        foreach ($recipients as $to) {
            if (!sendEmail($to, $subject, $body)) $allOk = false;
        }
        if ($allOk) {
            echo "Site up checker: RECOVERY alert sent (" . implode(', ', $recipients) . ").\n";
        } else {
            fwrite(STDERR, "Site up checker: failed to send one or more RECOVERY alert emails.\n");
        }
    }
}

if (!$sendDownAlert && !$sendRecoveryAlert) {
    if ($isUp) {
        echo "Site up checker: OK (HTTP {$httpCode}).\n";
    } else {
        $statusDetail = $error ? "error: {$error}" : "HTTP {$httpCode}";
        echo "Site up checker: still down ({$statusDetail}), cooldown active.\n";
    }
}

// --- Update state ---
$newState = [
    'last_checked_at' => isoNowUtc(),
    'is_down'         => !$isUp,
    'last_http_code'  => $httpCode,
    'last_error'      => $error,
];

if (!$isUp) {
    $newState['down_since'] = $wasDown ? ($prevState['down_since'] ?? isoNowUtc()) : isoNowUtc();
    $newState['last_alert_at'] = $sendDownAlert
        ? isoNowUtc()
        : ($prevState['last_alert_at'] ?? null);
} else {
    $newState['down_since'] = null;
    $newState['last_alert_at'] = null;
}

@file_put_contents($statePath, json_encode($newState, JSON_PRETTY_PRINT) . "\n", LOCK_EX);

exit($isUp ? 0 : 1);
