<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';

// "Recommend a project": adds a program as a new projects/<id>/project.toml, in a pull request a maintainer
// approves. Open to anyone who signed in with GitHub, and to Discord members with a Dev role.
$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$action = $isPost ? (string) ($_POST['do'] ?? '') : '';
$user = require_login($action === 'submit');
if (!can_recommend($user)) {
    render_message('Recommending needs a Dev role', '<p>Recommending a project from here needs the Client Dev, Server Dev or Tracker Dev role on the Hotline Discord, or signing in with GitHub.</p>'
        . '<p>Anyone can also <a href="' . e(suggest_on_github_url()) . '">suggest it on GitHub</a> instead.</p>');
}
$github = github();
$categories = ['Clients', 'Servers', 'Trackers', 'Bots', 'Misc'];

$state = ['name' => '', 'category' => '', 'repo' => '', 'website' => '', 'description' => '', 'platforms' => '', 'mine' => false];
if ($isPost) {
    require_post_with_csrf();
    foreach (['name', 'category', 'repo', 'website', 'description', 'platforms'] as $f) {
        $state[$f] = trim(str_replace(["\r", "\n"], ' ', (string) ($_POST[$f] ?? '')));
    }
    $state['mine'] = !empty($_POST['mine']);
}

$errors = [];
if ($action === 'submit') {
    // "owner/repo", or a github.com address.
    $repo = '';
    if ($state['repo'] !== '') {
        if (preg_match('#^(?:https?://github\.com/)?([A-Za-z0-9-]+/[A-Za-z0-9._-]+?)(?:\.git)?/?$#', $state['repo'], $m)) {
            $repo = $m[1];
        } else {
            $errors[] = 'The GitHub repo should look like <code>owner/repo</code> or <code>https://github.com/owner/repo</code>.';
        }
    }
    $website = clean_url($state['website']);
    if ($state['website'] !== '' && $website === '') {
        $errors[] = 'The website must be a web address starting with http:// or https://.';
    }
    if ($repo === '' && $website === '' && !$errors) {
        $errors[] = 'Please give its GitHub repo or its website, so people can find it.';
    }
    if (mb_strlen($state['name']) < 2 || mb_strlen($state['name']) > 60) {
        $errors[] = 'Please give its name (up to 60 characters).';
    }
    if (!in_array($state['category'], $categories, true)) {
        $errors[] = 'Please choose a category.';
    }
    if (mb_strlen($state['description']) > 200 || mb_strlen($state['platforms']) > 80) {
        $errors[] = 'Keep the description to one line (200 characters) and the platforms to 80.';
    }

    // The repo has to exist, and the project can't already be on VivaHX.
    if ($repo !== '' && !$errors) {
        [$status, $meta] = http_request('GET', "https://api.github.com/repos/$repo", [
            'Accept: application/vnd.github+json', 'Authorization: Bearer ' . config()['github']['token'],
        ]);
        if ($status === 404) {
            $errors[] = 'GitHub has no public repo called <code>' . e($repo) . '</code>.';
        } elseif ($status !== 200) {
            render_error(new HttpError($status, json_encode($meta), 'Could not look up that repo on GitHub.'));
        } else {
            $repo = (string) $meta['full_name'];
            if ($state['description'] === '') {
                $state['description'] = trim((string) ($meta['description'] ?? ''));
            }
        }
    }
    foreach (projects() as $p) {
        if (($repo !== '' && strcasecmp($p['github'], $repo) === 0) || strcasecmp($p['name'], $state['name']) === 0) {
            $errors[] = e($p['name']) . ' is already on VivaHX: <a href="' . e(site_url('/software/' . $p['id'] . '.html')) . '">see its page</a>.';
            break;
        }
    }
    if (!$errors && !within_rate_limit($user)) {
        $errors[] = "You've sent a lot in the last hour. Please wait a bit before sending more.";
    }

    if (!$errors) {
        // A folder name that isn't taken, on the site or by a recommendation still waiting.
        $id = slug($state['name']);
        try {
            for ($n = 2, $base = $id; isset(projects()[$id]) || $github->file("projects/$id/project.toml") !== null; $n++) {
                $id = "$base-$n";
            }
        } catch (HttpError $e) {
            render_error($e);
        }
        $lines = ['name = ' . toml_string($state['name']), 'category = ' . toml_string($state['category'])];
        if ($repo !== '') {
            $lines[] = 'github = ' . toml_string($repo);
        }
        $lines[] = 'description = ' . toml_string($state['description'] !== '' ? $state['description'] : $state['name']);
        if ($website !== '') {
            $lines[] = 'homepage = ' . toml_string($website);
        }
        if ($state['platforms'] !== '') {
            $lines[] = 'platforms = ' . toml_string($state['platforms']);
        }
        $mineDiscord = $state['mine'] && $user['provider'] === 'discord';
        $mineGitHub = $state['mine'] && $user['provider'] === 'github';
        $lines[] = '';
        $lines[] = "# Who may edit this project's page on VivaHX (every change is still reviewed):";
        $lines[] = '# Discord user IDs (they also need a Client, Server or Tracker Dev role), and GitHub';
        $lines[] = '# usernames (anyone who can push to the GitHub repo above already can).';
        $lines[] = 'discord_editors = [' . ($mineDiscord ? toml_string($user['id']) : '') . ']';
        $lines[] = 'github_editors = [' . ($mineGitHub ? toml_string($user['username']) : '') . ']';
        $text = implode("\n", $lines) . "\n";

        $body = "Recommended from the VivaHX editor by " . identity($user) . ".\n\n"
            . "Adds **" . str_replace(['*', '`'], '', $state['name']) . "** to " . $state['category']
            . ($repo !== '' ? ", https://github.com/$repo" : '') . ($website !== '' ? ", $website" : '') . ".\n\n"
            . ($state['mine'] ? "They say they work on it, so this also lets them edit its page. Check that before merging.\n\n" : '')
            . "Once merged, the next release check (every 6 hours) picks up its releases and GitHub info, and it gets its own page. "
            . 'Check **Files changed**, then merge to add it or close to decline.';
        try {
            $url = $github->proposeEdit(branch_prefix($user) . gmdate('YmdHis'),
                [['path' => "projects/$id/project.toml", 'content' => $text, 'sha' => null]],
                "Add {$state['name']} ({$state['category']})", $body, commit_author($user));
        } catch (EditConflict $ex) {
            $errors[] = 'Someone recommended a project with the same name a moment ago. Please send it again.';
        } catch (HttpError $ex) {
            render_error($ex);
        }
        if (!$errors) {
            $log = !empty($github->dryRunLog) ? '<h2>Dry run</h2><pre>' . e(json_encode($github->dryRunLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre><pre>' . e(implode("\n", $lines)) . '</pre>' : '';
            render('Thanks for the recommendation', '<p>It\'s been sent to the VivaHX maintainers. Once one of them approves it, '
                . e($state['name']) . ' gets its own page on VivaHX.</p><p><a href="' . e($url) . '">See it on GitHub</a> &middot; <a href="./">Your projects</a> &middot; <a href="'
                . e(site_url('/software/')) . '">All software</a></p>' . $log);
        }
    }
}

ob_start();
?>
<?= messages($errors) ?>
<p>Know a Hotline client, server, tracker, bot or tool that isn't on <a href="<?= e(site_url('/software/')) ?>">VivaHX</a>? Tell us about it. A maintainer looks it over before it's added.</p>
<form method="post" action="recommend.php" class="editform">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <label for="name">Name</label>
  <input type="text" id="name" name="name" value="<?= e($state['name']) ?>" maxlength="60" required>
  <label for="category">Category</label>
  <select id="category" name="category" required>
    <option value="">Choose...</option>
    <?php foreach ($categories as $c): ?>
    <option<?= $state['category'] === $c ? ' selected' : '' ?>><?= e($c) ?></option>
    <?php endforeach ?>
  </select>
  <label for="repo">GitHub repo <span class="note">(owner/repo, if it's on GitHub: its releases are then followed by themselves)</span></label>
  <input type="text" id="repo" name="repo" value="<?= e($state['repo']) ?>" placeholder="jhalter/mobius">
  <label for="website">Website <span class="note">(if it isn't on GitHub, or has a home page of its own)</span></label>
  <input type="text" id="website" name="website" value="<?= e($state['website']) ?>" placeholder="http://">
  <label for="description">What it is <span class="note">(one line; left empty, the GitHub description is used)</span></label>
  <input type="text" id="description" name="description" value="<?= e($state['description']) ?>" maxlength="200">
  <label for="platforms">Runs on <span class="note">(optional, like "Mac OS 9, Windows")</span></label>
  <input type="text" id="platforms" name="platforms" value="<?= e($state['platforms']) ?>" maxlength="80">
  <p><label class="inline"><input type="checkbox" name="mine" value="1"<?= $state['mine'] ? ' checked' : '' ?>> I work on this project; let me edit its page on VivaHX</label></p>
  <p class="actions"><button type="submit" name="do" value="submit" class="primary">Send recommendation</button></p>
</form>
<p class="note">Rather not sign in? <a href="<?= e(suggest_on_github_url()) ?>">Suggest it on GitHub</a> instead.</p>
<?php
render('Recommend a project', (string) ob_get_clean());
