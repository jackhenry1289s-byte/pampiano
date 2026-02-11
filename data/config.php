<?php
declare(strict_types=1);

// ----- App config -----
const APP_DOMAIN = 'https://sheetpdf.cv';
const SECRET_KEY = 'change-this-to-a-long-random-secret';
const MAX_IMAGE_MB = 3;

// ----- Database config -----
const DB_HOST = 'localhost';
const DB_NAME = 'smartlink';
const DB_USER = 'smartlink_user';
const DB_PASS = 'change-this-password';
const DB_CHARSET = 'utf8mb4';

const ALLOWED_MIME_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

const CTA_LABELS = [
    'WATCH_VIDEO' => 'Watch Video',
    'LISTEN_MUSIC' => 'Listen Music',
    'WATCH_MORE' => 'Watch More',
    'LISTEN_MORE' => 'Listen More',
    'OPEN_YOUTUBE' => 'Open on YouTube',
    'OPEN_SPOTIFY' => 'Open on Spotify',
    'DOWNLOAD' => 'Download',
    'VISIT_SITE' => 'Visit Site',
];


function app_url(string $path = ''): string
{
    $base = rtrim(APP_DOMAIN, '/');
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '' : $path);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function require_secret_key(): void
{
    $provided = (string)($_GET['k'] ?? '');
    if (!hash_equals(SECRET_KEY, $provided)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Forbidden';
        exit;
    }
}

function is_valid_slug(string $slug): bool
{
    return (bool)preg_match('/^[a-zA-Z0-9_-]{3,120}$/', $slug);
}

function is_valid_absolute_url(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return false;
    }

    $scheme = strtolower($parts['scheme'] ?? '');
    return in_array($scheme, ['http', 'https'], true);
}

function is_valid_display_domain(string $domain): bool
{
    if ($domain === '') {
        return true;
    }

    if (strlen($domain) > 120 || str_contains($domain, ' ') || str_contains($domain, '://')) {
        return false;
    }

    return (bool)preg_match('/^(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,63}$/', $domain);
}

function detect_mime_type(string $filePath): ?string
{
    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = finfo_file($finfo, $filePath) ?: null;
            finfo_close($finfo);
        }
    }

    if (!$mime && function_exists('mime_content_type')) {
        $mime = mime_content_type($filePath) ?: null;
    }

    return $mime;
}

function normalize_buttons(array $input): array
{
    $buttons = [];
    for ($position = 1; $position <= 3; $position++) {
        $ctaType = trim((string)($input['cta_type'][$position] ?? ''));
        $buttonUrl = trim((string)($input['button_url'][$position] ?? ''));
        $labelOverride = trim((string)($input['label_override'][$position] ?? ''));
        $isPrimary = ((string)($input['is_primary'] ?? '')) === (string)$position ? 1 : 0;

        if ($ctaType === '' && $buttonUrl === '' && $labelOverride === '') {
            continue;
        }

        $buttons[] = [
            'position' => $position,
            'cta_type' => $ctaType,
            'button_url' => $buttonUrl,
            'label_override' => $labelOverride,
            'is_primary' => $isPrimary,
        ];
    }

    return $buttons;
}

function schema_sql(): string
{
    return <<<'SQL'
CREATE TABLE IF NOT EXISTS posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(120) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  dest_url TEXT NOT NULL,
  thumb_url TEXT NOT NULL,
  display_domain VARCHAR(120) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_buttons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id INT UNSIGNED NOT NULL,
  position TINYINT UNSIGNED NOT NULL,
  cta_type ENUM(
    'WATCH_VIDEO',
    'LISTEN_MUSIC',
    'WATCH_MORE',
    'LISTEN_MORE',
    'OPEN_YOUTUBE',
    'OPEN_SPOTIFY',
    'DOWNLOAD',
    'VISIT_SITE'
  ) NOT NULL,
  button_url TEXT NOT NULL,
  label_override VARCHAR(40) NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_post_buttons_post_id
    FOREIGN KEY (post_id) REFERENCES posts(id)
    ON DELETE CASCADE,
  UNIQUE KEY uq_post_buttons_post_position (post_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
}
