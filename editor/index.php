<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';

$user = current_user();

if (!$user) {
    $buttons = [];
    if (github_login_enabled()) {
        $buttons[] = '<a class="button" href="login.php?with=github">Sign in with GitHub</a>';
    }
    if (discord_enabled()) {
        $buttons[] = '<a class="button secondary" href="login.php?with=discord">Sign in with Discord</a>';
    }
    render('Edit your project on VivaHX', '<p>Developers can keep their project\'s page on VivaHX up to date: '
        . 'its description and links, screenshots, news, and a write-up for each release.</p>'
        . '<p>Every change is looked over by a VivaHX maintainer before it appears on the site.</p>'
        . '<p>' . implode(' ', $buttons) . '</p>'
        . '<ul class="note">'
        . '<li><b>GitHub</b> is quickest: if you can push to your project\'s GitHub repo, you can edit its page. '
        . 'GitHub only shares your public profile and which public repos you can push to.</li>'
        . '<li><b>Discord</b> works for any project: you need the Client, Server or Tracker Dev role on the Hotline Discord, '
        . 'and a maintainer links your Discord account to your project. Discord only shares your username and your roles in that server.</li>'
        . '</ul>');
}

$user = require_login();
$mine = editable_projects($user);

try {
    $pending = github()->openPullRequests(branch_prefix($user));
} catch (HttpError $e) {
    $pending = [];
}

ob_start();
?>
<?php if ($mine): ?>
<p>Hi <?= e($user['name']) ?>! These are the projects you can edit. Changes are sent for review and appear on VivaHX once a maintainer approves them.</p>
<table class="projects">
  <?php foreach ($mine as $p): ?>
  <tr>
    <td><b><a href="<?= e(site_url('/software/' . $p['id'] . '.html')) ?>"><?= e($p['name']) ?></a></b> <span class="note"><?= e($p['category']) ?></span></td>
    <td>
      <a class="button small" href="project.php?id=<?= e($p['id']) ?>">Edit page</a>
      <a class="button small secondary" href="post.php?id=<?= e($p['id']) ?>">Post news</a>
      <?php if ($p['releases']): ?><a class="button small secondary" href="highlight.php?id=<?= e($p['id']) ?>">Release write-up</a><?php endif ?>
    </td>
  </tr>
  <?php endforeach ?>
</table>
<?php else: ?>
<p>Hi <?= e($user['name']) ?>! You aren't linked to a project on VivaHX yet.</p>
<?php endif ?>

<?php if ($user['provider'] === 'discord' && empty($user['developer'])): ?>
<p class="notice">Editing with Discord needs the <b>Client Dev</b>, <b>Server Dev</b> or <b>Tracker Dev</b> role on the Hotline Discord<?= empty($user['member']) ? ', and you aren\'t in that server yet' : '' ?>.
<?php if (!empty(config()['discord']['invite'])): ?><a href="<?= e(config()['discord']['invite']) ?>">Join the Hotline Discord</a>.<?php endif ?>
Or, if your project is on GitHub, <a href="login.php?with=github">sign in with GitHub</a> instead.</p>
<?php endif ?>

<?php if ($pending): ?>
<h2>Waiting for review</h2>
<ul>
  <?php foreach ($pending as $pr): ?>
  <li><a href="<?= e($pr['html_url']) ?>"><?= e($pr['title']) ?></a> <span class="note">(sent <?= e(date('M j, Y', strtotime($pr['created_at']))) ?>)</span></li>
  <?php endforeach ?>
</ul>
<?php endif ?>

<?php if (can_recommend($user)): ?>
<h2>Know a project we're missing?</h2>
<p><a class="button secondary" href="recommend.php">Recommend a project</a></p>
<?php endif ?>

<h2>Ask to be linked to a project</h2>
<p>Pick your project and a maintainer will link your account to it. <?php if ($user['provider'] === 'discord'): ?>Your Discord user ID is <code><?= e($user['id']) ?></code>.<?php else: ?>If you can push to the project's GitHub repo you don't need to ask: it's linked already.<?php endif ?></p>
<form method="post" action="request.php" class="editform">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <label for="project">Project</label>
  <select id="project" name="project" required>
    <option value="">Choose...</option>
    <?php foreach (projects() as $p): if (isset($mine[$p['id']])) continue; ?>
    <option value="<?= e($p['id']) ?>"><?= e($p['name']) ?> (<?= e($p['category']) ?>)</option>
    <?php endforeach ?>
  </select>
  <label for="note">How are you involved? <span class="note">(for example: "I wrote it", with a link that shows it)</span></label>
  <input type="text" id="note" name="note" maxlength="300" required>
  <p class="actions"><button type="submit" class="primary">Ask to be linked</button></p>
</form>
<p class="note">Not listed? VivaHX follows every Hotline project it knows of. Ask on the Hotline Discord to have yours added.</p>
<?php
render('Your projects', (string) ob_get_clean());
