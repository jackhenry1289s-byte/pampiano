<?php
declare(strict_types=1);
require __DIR__ . '/data/config.php';
require_secret_key();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Method Not Allowed';
    exit;
}

$errors = [];
$slug = trim((string)($_POST['slug'] ?? ''));
$title = trim((string)($_POST['title'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$destUrl = trim((string)($_POST['dest_url'] ?? ''));
$displayDomain = trim((string)($_POST['display_domain'] ?? ''));
$buttons = normalize_buttons($_POST);

if (!is_valid_slug($slug)) {
    $errors[] = 'Invalid slug. Use 3..120 chars: letters, numbers, _ or -.';
}
if ($title === '' || mb_strlen($title) > 255) {
    $errors[] = 'Title is required and must be 1..255 characters.';
}
if ($description === '' || mb_strlen($description) > 2000) {
    $errors[] = 'Description is required and must be 1..2000 characters.';
}
if (!is_valid_absolute_url($destUrl)) {
    $errors[] = 'dest_url must be a valid absolute URL.';
}
if (!is_valid_display_domain($displayDomain)) {
    $errors[] = 'display_domain must be domain-like, no spaces, no scheme.';
}

if (count($buttons) < 1 || count($buttons) > 3) {
    $errors[] = 'You must configure between 1 and 3 buttons.';
}

$primaryCount = 0;
foreach ($buttons as $idx => &$button) {
    if (!isset(CTA_LABELS[$button['cta_type']])) {
        $errors[] = sprintf('Button %d has invalid cta_type.', $button['position']);
    }

    // Convenience: if primary button URL is empty, fallback to dest_url.
    if ($button['is_primary'] === 1 && $button['button_url'] === '') {
        $button['button_url'] = $destUrl;
    }

    if (!is_valid_absolute_url($button['button_url'])) {
        $errors[] = sprintf('Button %d URL is invalid.', $button['position']);
    }
    if ($button['label_override'] !== '' && mb_strlen($button['label_override']) > 40) {
        $errors[] = sprintf('Button %d label_override exceeds 40 chars.', $button['position']);
    }
    $primaryCount += (int)$button['is_primary'];
}
unset($button);
if ($primaryCount !== 1) {
    $errors[] = 'Exactly one primary button is required.';
}

if (!isset($_FILES['thumb_file']) || ($_FILES['thumb_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $errors[] = 'Thumbnail upload failed or missing.';
}

$thumbWebPath = '';
if (!$errors) {
    $tmpPath = (string)$_FILES['thumb_file']['tmp_name'];
    if (!is_uploaded_file($tmpPath)) {
        $errors[] = 'Invalid upload source.';
    }
    $sizeBytes = (int)($_FILES['thumb_file']['size'] ?? 0);
    $maxBytes = MAX_IMAGE_MB * 1024 * 1024;

    if ($sizeBytes < 1 || $sizeBytes > $maxBytes) {
        $errors[] = sprintf('Thumbnail size must be between 1 byte and %d MB.', MAX_IMAGE_MB);
    }

    $mime = detect_mime_type($tmpPath);
    if (!is_string($mime) || !isset(ALLOWED_MIME_TYPES[$mime])) {
        $errors[] = 'Thumbnail MIME type is not allowed. Use jpg/jpeg/png/webp.';
    }

    if (!$errors) {
        $ext = ALLOWED_MIME_TYPES[$mime];
        $newName = bin2hex(random_bytes(16)) . '.' . $ext;
        $targetDir = __DIR__ . '/uploads';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            $errors[] = 'Upload directory is not available.';
        } else {
            $targetPath = $targetDir . '/' . $newName;
            if (!move_uploaded_file($tmpPath, $targetPath)) {
                $errors[] = 'Failed to save thumbnail image.';
            } else {
                $thumbWebPath = '/uploads/' . $newName;
            }
        }
    }
}

if ($errors) {
    http_response_code(422);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h1>Validation errors</h1><ul>';
    foreach ($errors as $error) {
        echo '<li>' . h($error) . '</li>';
    }
    echo '</ul><p><a href="javascript:history.back()">Go back</a></p>';
    exit;
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    $insertPost = $pdo->prepare(
        'INSERT INTO posts (slug, title, description, dest_url, thumb_url, display_domain)
         VALUES (:slug, :title, :description, :dest_url, :thumb_url, :display_domain)'
    );
    $insertPost->execute([
        ':slug' => $slug,
        ':title' => $title,
        ':description' => $description,
        ':dest_url' => $destUrl,
        ':thumb_url' => $thumbWebPath,
        ':display_domain' => $displayDomain !== '' ? $displayDomain : null,
    ]);

    $postId = (int)$pdo->lastInsertId();

    $insertButton = $pdo->prepare(
        'INSERT INTO post_buttons (post_id, position, cta_type, button_url, label_override, is_primary)
         VALUES (:post_id, :position, :cta_type, :button_url, :label_override, :is_primary)'
    );

    foreach ($buttons as $button) {
        $insertButton->execute([
            ':post_id' => $postId,
            ':position' => $button['position'],
            ':cta_type' => $button['cta_type'],
            ':button_url' => $button['button_url'],
            ':label_override' => $button['label_override'] !== '' ? $button['label_override'] : null,
            ':is_primary' => $button['is_primary'],
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($thumbWebPath !== '') {
        $uploaded = __DIR__ . $thumbWebPath;
        if (is_file($uploaded)) {
            @unlink($uploaded);
        }
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Failed to save post. ';
    if (stripos($e->getMessage(), 'Duplicate entry') !== false) {
        echo 'Slug already exists.';
    } else {
        echo 'Please check server logs.';
    }
    exit;
}

header('Location: /p/' . rawurlencode($slug), true, 302);
exit;
