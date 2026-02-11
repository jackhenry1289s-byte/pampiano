<?php
declare(strict_types=1);
require __DIR__ . '/data/config.php';
require_secret_key();

$ctaOptions = array_keys(CTA_LABELS);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Create Smart-Link</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<main class="container admin">
    <h1>Create Smart-Link</h1>
    <p class="hint">Secure endpoint. Keep your secret key private.</p>

    <section class="card sql-help">
        <h2>Run once in MySQL/MariaDB</h2>
        <pre><?= h(schema_sql()) ?></pre>
    </section>

    <form action="/save.php?k=<?= urlencode((string)($_GET['k'] ?? '')) ?>" method="post" enctype="multipart/form-data" class="card form-grid">
        <label>Slug
            <input type="text" name="slug" required minlength="3" maxlength="120" pattern="[a-zA-Z0-9_-]+" placeholder="my-campaign-01">
        </label>

        <label>Title
            <input type="text" name="title" required maxlength="255" placeholder="Song Title / Video Title">
        </label>

        <label>Description
            <textarea name="description" required maxlength="2000" rows="4" placeholder="Short, engaging description for preview and landing page."></textarea>
        </label>

        <label>Primary Destination URL (dest_url)
            <input type="url" name="dest_url" required placeholder="https://example.com/watch">
        </label>

        <label>Display Domain (optional)
            <input type="text" name="display_domain" maxlength="120" placeholder="example.com">
        </label>

        <label>Thumbnail Image (jpg/png/webp)
            <input type="file" name="thumb_file" accept=".jpg,.jpeg,.png,.webp" required>
        </label>

        <fieldset>
            <legend>CTA Buttons (1 to 3, exactly one primary)</legend>
            <?php for ($position = 1; $position <= 3; $position++): ?>
                <div class="button-row">
                    <h3>Button <?= $position ?></h3>
                    <label>CTA Type
                        <select name="cta_type[<?= $position ?>]">
                            <option value="">-- Choose --</option>
                            <?php foreach ($ctaOptions as $type): ?>
                                <option value="<?= h($type) ?>"><?= h($type . ' → ' . CTA_LABELS[$type]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>Button URL
                        <input type="url" name="button_url[<?= $position ?>]" placeholder="https://...">
                    </label>

                    <label>Label Override (optional)
                        <input type="text" name="label_override[<?= $position ?>]" maxlength="40" placeholder="Custom label">
                    </label>

                    <label class="inline">
                        <input type="radio" name="is_primary" value="<?= $position ?>"> Set as primary
                    </label>
                </div>
            <?php endfor; ?>
        </fieldset>

        <button type="submit" class="btn btn-primary">Save Smart-Link</button>
    </form>
</main>
</body>
</html>
