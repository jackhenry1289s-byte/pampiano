<?php
declare(strict_types=1);
require __DIR__ . '/../data/config.php';

$slug = trim((string)($_GET['slug'] ?? ''));
if (!is_valid_slug($slug)) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$pdo = db();
$postStmt = $pdo->prepare('SELECT id, slug, title, description, dest_url, thumb_url, display_domain FROM posts WHERE slug = :slug LIMIT 1');
$postStmt->execute([':slug' => $slug]);
$post = $postStmt->fetch();

if (!$post) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$buttonStmt = $pdo->prepare(
    'SELECT position, cta_type, button_url, label_override, is_primary
     FROM post_buttons WHERE post_id = :post_id ORDER BY position ASC'
);
$buttonStmt->execute([':post_id' => $post['id']]);
$buttons = $buttonStmt->fetchAll();

$ogUrl = app_url('/p/' . rawurlencode((string)$post['slug']));
$thumbAbsolute = str_starts_with((string)$post['thumb_url'], 'http')
    ? (string)$post['thumb_url']
    : app_url((string)$post['thumb_url']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= h((string)$post['title']) ?></title>

    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= h((string)$post['title']) ?>">
    <meta property="og:description" content="<?= h((string)$post['description']) ?>">
    <meta property="og:image" content="<?= h($thumbAbsolute) ?>">
    <meta property="og:url" content="<?= h($ogUrl) ?>">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= h((string)$post['title']) ?>">
    <meta name="twitter:description" content="<?= h((string)$post['description']) ?>">
    <meta name="twitter:image" content="<?= h($thumbAbsolute) ?>">

    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<main class="container landing">
    <article class="card">
        <img class="thumb" src="<?= h((string)$post['thumb_url']) ?>" alt="<?= h((string)$post['title']) ?>">
        <h1><?= h((string)$post['title']) ?></h1>
        <p class="description"><?= nl2br(h((string)$post['description'])) ?></p>

        <?php if (!empty($post['display_domain'])): ?>
            <p class="domain-badge"><?= h((string)$post['display_domain']) ?></p>
        <?php endif; ?>

        <div class="cta-list">
            <?php foreach ($buttons as $button): ?>
                <?php
                    $defaultLabel = CTA_LABELS[$button['cta_type']] ?? 'Open';
                    $label = trim((string)($button['label_override'] ?? '')) !== ''
                        ? (string)$button['label_override']
                        : $defaultLabel;
                    $isPrimary = (int)$button['is_primary'] === 1;
                    if ($isPrimary) {
                        $label .= ' ▶';
                    }
                ?>
                <a
                    class="btn <?= $isPrimary ? 'btn-primary' : 'btn-secondary' ?>"
                    href="<?= h((string)$button['button_url']) ?>"
                    target="_blank"
                    rel="noopener"
                ><?= h($label) ?></a>
            <?php endforeach; ?>
        </div>
    </article>
</main>
</body>
</html>
