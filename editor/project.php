<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';

$cfg = config();
$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$action = $isPost ? (string) ($_POST['do'] ?? '') : '';
$user = require_login($action === 'submit');
$project = require_project($user, (string) ($_POST['id'] ?? $_GET['id'] ?? ''));
$pid = $project['id'];
$github = github();
$dir = "projects/$pid";
$maxShots = (int) ($cfg['limits']['screenshots'] ?? 8);

// Screenshots uploaded while editing wait on the server until the change is sent. Each open
// form keeps its own list, so two tabs don't mix them up.
$formId = $isPost ? (string) preg_replace('/[^0-9a-f]/', '', (string) ($_POST['form_id'] ?? '')) : bin2hex(random_bytes(8));
if (!$isPost) {
    $_SESSION['uploads'] = array_slice($_SESSION['uploads'] ?? [], -9, null, true);
}
$uploads = $_SESSION['uploads'][$formId] ?? [];

// --- Load --------------------------------------------------------------------

$state = ['tagline' => '', 'website' => '', 'discord' => '', 'donate' => '', 'about' => '', 'summary' => '', 'shots' => []];
if ($isPost) {
    require_post_with_csrf();
    foreach (['tagline', 'website', 'discord', 'donate', 'about', 'summary'] as $f) {
        $state[$f] = str_replace("\r\n", "\n", (string) ($_POST[$f] ?? ''));
    }
    // Existing screenshots, in the order and with the captions on the form.
    foreach ((array) ($_POST['shot'] ?? []) as $file => $row) {
        $state['shots'][] = [
            'file' => (string) $file,
            'caption' => trim((string) ($row['caption'] ?? '')),
            'order' => (int) ($row['order'] ?? 99),
            'remove' => !empty($row['remove']),
        ];
    }
} else {
    try {
        $page = $github->file("$dir/page.json");
        $about = $github->file("$dir/about.md");
    } catch (HttpError $e) {
        render_error($e);
    }
    $data = $page ? (json_decode($page['text'], true) ?: []) : [];
    foreach (['tagline', 'website', 'discord', 'donate'] as $f) {
        $state[$f] = (string) ($data[$f] ?? '');
    }
    $state['about'] = $about['text'] ?? '';
    foreach ($data['screenshots'] ?? [] as $n => $shot) {
        $state['shots'][] = ['file' => (string) $shot['file'], 'caption' => (string) ($shot['caption'] ?? ''), 'order' => $n + 1, 'remove' => false];
    }
}

$errors = [];
$notice = '';
$previewHtml = null;
$kept = array_values(array_filter($state['shots'], fn ($s) => !$s['remove']));

// --- Actions -----------------------------------------------------------------

if ($action === 'upload') {
    $files = $_FILES['images'] ?? null;
    $count = is_array($files['name'] ?? null) ? count($files['name']) : 0;
    $taken = array_merge(array_column($state['shots'], 'file'), array_column($uploads, 'name'));
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if (count($kept) + count($uploads) >= $maxShots) {
            $errors[] = "A project can have up to $maxShots screenshots. Remove one to add another.";
            break;
        }
        try {
            $image = Images::accept([
                'name' => $files['name'][$i], 'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i], 'size' => $files['size'][$i],
            ], $user['provider'] . '-' . $user['id'], $taken);
            $image['caption'] = '';
            $image['order'] = 100 + count($uploads);
            $taken[] = $image['name'];
            $uploads[$image['id']] = $image;
        } catch (InvalidArgumentException $ex) {
            $errors[] = e($ex->getMessage());
        }
    }
}

// Captions and order for screenshots that haven't been sent yet.
foreach ((array) ($_POST['new'] ?? []) as $id => $row) {
    if (isset($uploads[$id])) {
        $uploads[$id]['caption'] = trim((string) ($row['caption'] ?? ''));
        $uploads[$id]['order'] = (int) ($row['order'] ?? 99);
    }
}
if (str_starts_with($action, 'drop:')) {
    $id = substr($action, 5);
    if (isset($uploads[$id])) {
        @unlink($uploads[$id]['file']);
        unset($uploads[$id]);
    }
}
$_SESSION['uploads'][$formId] = $uploads;

if ($action === 'preview') {
    try {
        $previewHtml = trim($state['about']) !== '' ? preview_html($state['about']) : '<p class="note">(No About text.)</p>';
    } catch (HttpError $ex) {
        $errors[] = 'The preview could not be shown right now, but you can still send your change.';
    }
}

if ($action === 'submit') {
    $summary = trim($state['summary']);
    if (mb_strlen($summary) < 3 || mb_strlen($summary) > 150) {
        $errors[] = 'Please describe your change in a few words (up to 150 characters).';
    }
    foreach (['website' => 'Website', 'discord' => 'Discord invite', 'donate' => 'Donate link'] as $f => $label) {
        if (trim($state[$f]) !== '' && clean_url($state[$f]) === '') {
            $errors[] = "The $label must be a web address starting with http:// or https://.";
        }
    }
    if (mb_strlen($state['tagline']) > 160) {
        $errors[] = 'The tagline can be up to 160 characters.';
    }
    if (mb_strlen($state['about']) > 20000) {
        $errors[] = 'The About text is too long (up to 20,000 characters).';
    }
    foreach (array_merge($kept, array_values($uploads)) as $s) {
        if (mb_strlen($s['caption']) > 120) {
            $errors[] = 'Screenshot captions can be up to 120 characters.';
            break;
        }
    }

    if (!$errors) {
        try {
            $page = $github->file("$dir/page.json");
            $about = $github->file("$dir/about.md");
            $onGitHub = $github->folder("$dir/screenshots");
        } catch (HttpError $e) {
            render_error($e);
        }
        // Screenshots in the final order: the ones kept, and the new ones.
        $all = [];
        foreach ($kept as $s) {
            if (isset($onGitHub[$s['file']])) {
                $all[] = ['file' => $s['file'], 'caption' => $s['caption'], 'order' => $s['order']];
            }
        }
        foreach ($uploads as $u) {
            $all[] = ['file' => $u['name'], 'caption' => $u['caption'], 'order' => $u['order'], 'upload' => $u];
        }
        usort($all, fn ($a, $b) => $a['order'] <=> $b['order']);

        $data = array_filter([
            'tagline' => trim($state['tagline']),
            'website' => clean_url($state['website']),
            'discord' => clean_url($state['discord']),
            'donate' => clean_url($state['donate']),
        ], fn ($v) => $v !== '');
        $data['screenshots'] = array_map(fn ($s) => array_filter(['file' => $s['file'], 'caption' => $s['caption']], fn ($v) => $v !== ''), $all);
        $pageText = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        $aboutText = trim($state['about']) === '' ? '' : rtrim($state['about']) . "\n";

        $files = [];
        if (($page['text'] ?? null) !== $pageText) {
            $files[] = ['path' => "$dir/page.json", 'content' => $pageText, 'sha' => $page['sha'] ?? null];
        }
        if ($aboutText === '' && $about) {
            $files[] = ['path' => "$dir/about.md", 'delete' => true, 'sha' => $about['sha']];
        } elseif ($aboutText !== '' && ($about['text'] ?? null) !== $aboutText) {
            $files[] = ['path' => "$dir/about.md", 'content' => $aboutText, 'sha' => $about['sha'] ?? null];
        }
        $added = [];
        foreach ($all as $s) {
            if (isset($s['upload']) && is_file($s['upload']['file'])) {
                $files[] = ['path' => "$dir/screenshots/{$s['file']}", 'content' => (string) file_get_contents($s['upload']['file']), 'sha' => null];
                $added[] = '`' . $s['file'] . '`';
            }
        }
        $removed = [];
        $stillUsed = array_column($all, 'file');
        foreach ($onGitHub as $name => $sha) {
            if (!in_array($name, $stillUsed, true)) {
                $files[] = ['path' => "$dir/screenshots/$name", 'delete' => true, 'sha' => $sha];
                $removed[] = '`' . $name . '`';
            }
        }

        if (!$files) {
            $errors[] = "You haven't changed anything yet.";
        } elseif (!within_rate_limit($user)) {
            $errors[] = "You've sent a lot in the last hour. Please wait a bit before sending more.";
        } else {
            $body = "Sent from the VivaHX editor by " . identity($user) . ".\n\n"
                . "Project: **" . str_replace(['*', '`'], '', $project['name']) . "**, " . site_url("/software/$pid.html") . "\n"
                . ($added ? "\nScreenshots added: " . implode(', ', $added) . "\n" : '')
                . ($removed ? "\nScreenshots removed: " . implode(', ', $removed) . "\n" : '')
                . "\n> " . str_replace("\n", ' ', $summary) . "\n\n"
                . 'Check **Files changed**, then merge to publish or close to decline.';
            try {
                $url = $github->proposeEdit(branch_prefix($user) . gmdate('YmdHis'), $files,
                    "{$project['name']}: $summary", $body, commit_author($user));
            } catch (EditConflict $ex) {
                $errors[] = 'Someone else changed this page after you opened it. Copy your text somewhere safe, <a href="project.php?id=' . e($pid) . '">reload the page</a>, and make your change again.';
            } catch (HttpError $ex) {
                render_error($ex);
            }
            if (!$errors) {
                foreach ($uploads as $u) {
                    @unlink($u['file']);
                }
                unset($_SESSION['uploads'][$formId]);
                render_sent($url, $project, $github);
            }
        }
    }
}

// --- Form ------------------------------------------------------------------------

$raw = 'https://raw.githubusercontent.com/' . $cfg['github']['repo'] . '/' . ($cfg['github']['branch'] ?? 'main') . "/$dir/screenshots/";
usort($state['shots'], fn ($a, $b) => $a['order'] <=> $b['order']);
ob_start();
?>
<?= messages($errors, $notice) ?>
<?php if ($previewHtml !== null): ?>
<div class="preview"><p class="preview-label">About text preview (not sent yet)</p><?= $previewHtml ?></div>
<?php endif ?>

<form method="post" action="project.php" enctype="multipart/form-data" class="editform">
  <button type="submit" name="do" value="preview" class="hidden-default" tabindex="-1" aria-hidden="true">Preview</button>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="form_id" value="<?= e($formId) ?>">
  <input type="hidden" name="id" value="<?= e($pid) ?>">
  <p class="note">Editing <a href="<?= e(site_url("/software/$pid.html")) ?>"><?= e($project['name']) ?> on VivaHX</a>. Releases and their notes come from <?= $project['github'] ? 'GitHub' : 'VivaHX' ?> by themselves; add your own write-up to one with <a href="highlight.php?id=<?= e($pid) ?>">Release write-up</a>.</p>

  <label for="tagline">Tagline <span class="note">(one line under the name; leave empty to use the description from GitHub)</span></label>
  <input type="text" id="tagline" name="tagline" value="<?= e($state['tagline']) ?>" maxlength="160">

  <label for="about">About <span class="note">(Markdown: **bold**, *italic*, [links](http://example.com), lists. Leave empty to use the start of your README.)</span></label>
  <textarea id="about" name="about" rows="14" spellcheck="true"><?= e($state['about']) ?></textarea>

  <label for="website">Website</label>
  <input type="text" id="website" name="website" value="<?= e($state['website']) ?>" placeholder="http://">
  <label for="discord">Discord invite</label>
  <input type="text" id="discord" name="discord" value="<?= e($state['discord']) ?>" placeholder="https://discord.gg/...">
  <label for="donate">Donate link</label>
  <input type="text" id="donate" name="donate" value="<?= e($state['donate']) ?>" placeholder="https://">

  <fieldset class="images">
    <legend>Screenshots (up to <?= $maxShots ?>)</legend>
    <?php if ($state['shots'] || $uploads): ?>
    <table class="shots">
      <tr><th>Order</th><th></th><th>Caption</th><th></th></tr>
      <?php foreach ($state['shots'] as $s): $f = $s['file']; ?>
      <tr<?= $s['remove'] ? ' class="removed"' : '' ?>>
        <td><input type="number" name="shot[<?= e($f) ?>][order]" value="<?= (int) $s['order'] ?>" min="1" max="99" class="order"></td>
        <td><img src="<?= e($raw . rawurlencode($f)) ?>" alt="" height="48"></td>
        <td><input type="text" name="shot[<?= e($f) ?>][caption]" value="<?= e($s['caption']) ?>" maxlength="120"></td>
        <td><label class="inline"><input type="checkbox" name="shot[<?= e($f) ?>][remove]" value="1"<?= $s['remove'] ? ' checked' : '' ?>> Remove</label></td>
      </tr>
      <?php endforeach ?>
      <?php foreach ($uploads as $id => $u): ?>
      <tr>
        <td><input type="number" name="new[<?= e($id) ?>][order]" value="<?= (int) $u['order'] ?>" min="1" max="199" class="order"></td>
        <td><img src="image.php?id=<?= e($id) ?>" alt="" height="48"></td>
        <td><input type="text" name="new[<?= e($id) ?>][caption]" value="<?= e($u['caption']) ?>" maxlength="120" placeholder="Caption (new)"></td>
        <td><button type="submit" name="do" value="drop:<?= e($id) ?>" class="linkbutton">Remove</button></td>
      </tr>
      <?php endforeach ?>
    </table>
    <p class="note">Screenshots are shown in order, lowest number first.</p>
    <?php endif ?>
    <label for="images">Add PNG, JPG, GIF or WebP images (up to <?= (int) (($cfg['limits']['image_max_bytes'] ?? 5_000_000) / 1_000_000) ?> MB each)</label>
    <input type="file" id="images" name="images[]" accept="image/png,image/jpeg,image/gif,image/webp" multiple>
    <button type="submit" name="do" value="upload">Upload</button>
  </fieldset>

  <label for="summary">What did you change?</label>
  <input type="text" id="summary" name="summary" value="<?= e($state['summary']) ?>" maxlength="150" placeholder="For example: New screenshots for version 2.0">

  <p class="actions">
    <button type="submit" name="do" value="preview">Preview About</button>
    <button type="submit" name="do" value="submit" class="primary">Send for review</button>
  </p>
</form>
<?php
render('Edit ' . $project['name'], (string) ob_get_clean());
