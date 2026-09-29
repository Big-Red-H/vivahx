<?php
/**
 * VivaHX project editor: developers sign in with GitHub or Discord and propose changes to their
 * own project's page. Every change arrives as a GitHub pull request for a maintainer to approve.
 * Built on the Hotline Wiki's Discord editor.
 *
 * Every public page in this folder starts with: require __DIR__ . '/src/bootstrap.php';
 */
declare(strict_types=1);

require __DIR__ . '/http.php';
require __DIR__ . '/Discord.php';
require __DIR__ . '/GitHub.php';
require __DIR__ . '/GitHubLogin.php';
require __DIR__ . '/Images.php';
require __DIR__ . '/view.php';

const PROJECT_ID = '/^[a-z0-9][a-z0-9-]*$/';

function config(): array
{
    static $config = null;
    if ($config === null) {
        // Secrets live outside the website: ~/editor-config/vivahx.com.php (see config.sample.php).
        $siteDir = dirname(__DIR__, 2);
        $path = getenv('VIVAHX_EDITOR_CONFIG') ?: dirname($siteDir) . '/editor-config/' . basename($siteDir) . '.php';
        if (!is_file($path)) {
            http_response_code(503);
            exit('The editor is not set up yet.');
        }
        $config = require $path;
        $unset = fn ($v) => !is_string($v) || $v === '' || str_starts_with($v, 'PASTE_');
        if (PHP_SAPI !== 'cli-server' && ($unset($config['github']['token'] ?? '') || ($unset($config['discord']['client_secret'] ?? '') && $unset($config['github_login']['client_secret'] ?? '')))) {
            http_response_code(503);
            exit('The editor is not set up yet.');
        }
        $config['data_dir'] = $config['data_dir'] ?? dirname($path) . '/' . basename($siteDir) . '-data';
        $config['site_url'] = rtrim($config['site_url'], '/');
        $config['editor_url'] = rtrim($config['editor_url'] ?? $config['site_url'] . '/editor', '/');
    }
    return $config;
}

/** Local testing with `php -S` only. Never true on the real server. */
function is_dev(): bool
{
    return PHP_SAPI === 'cli-server' && !empty(config()['dev_user']);
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function origin(string $url): string
{
    $u = parse_url($url);
    return $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
}

function editor_path(): string
{
    return rtrim((string) parse_url(config()['editor_url'], PHP_URL_PATH), '/') . '/';
}

function site_url(string $path): string
{
    return preg_match('#^https?://#', $path) ? $path : config()['site_url'] . $path;
}

/** A setting that's been filled in (not empty, and not still the sample's PASTE_... text). */
function is_set(mixed $value): bool
{
    return is_string($value) && $value !== '' && !str_starts_with($value, 'PASTE_');
}

function discord_enabled(): bool
{
    return is_set(config()['discord']['client_id'] ?? '') && is_set(config()['discord']['client_secret'] ?? '');
}

function github_login_enabled(): bool
{
    return is_set(config()['github_login']['client_id'] ?? '') && is_set(config()['github_login']['client_secret'] ?? '');
}

function discord(): Discord
{
    return new Discord(config()['discord'], config()['editor_url'] . '/callback.php');
}

function github_login(): GitHubLogin
{
    return new GitHubLogin(config()['github_login'], config()['editor_url'] . '/callback-github.php');
}

function github(): GitHub
{
    $gh = config()['github'];
    return new GitHub($gh['token'], $gh['repo'], $gh['branch'] ?? 'main', !empty(config()['dry_run']));
}

function data_dir(string $sub): string
{
    $dir = config()['data_dir'] . '/' . $sub;
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    return $dir;
}

// --- Request setup ----------------------------------------------------------

if (!is_dev() && !is_https()) {
    header('Location: ' . origin(config()['editor_url']) . ($_SERVER['REQUEST_URI'] ?? editor_path()), true, 301);
    exit;
}

header("Content-Security-Policy: default-src 'self'; img-src 'self' https://cdn.discordapp.com https://avatars.githubusercontent.com https://raw.githubusercontent.com data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

ini_set('session.gc_probability', '1');
ini_set('session.gc_divisor', '100');
ini_set('session.gc_maxlifetime', '28800');
session_save_path(data_dir('sessions'));
session_name('vivahx_editor');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => editor_path(),
    'secure' => !is_dev(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// --- Security helpers -------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function require_post_with_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || !hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        render_message('Please try again', '<p>Your session expired. Go back, reload the page, and try again.</p>');
    }
}

// --- Projects and who may edit them -----------------------------------------

/** Every project, from projects.json (written by the site build from projects/<id>/project.toml). */
function projects(): array
{
    static $projects = null;
    if ($projects === null) {
        $list = json_decode((string) @file_get_contents(dirname(__DIR__) . '/projects.json'), true);
        $projects = [];
        foreach (is_array($list) ? $list : [] as $p) {
            $projects[$p['id']] = $p;
        }
    }
    return $projects;
}

function project(string $id): ?array
{
    return preg_match(PROJECT_ID, $id) ? (projects()[$id] ?? null) : null;
}

/**
 * Whether this person may edit this project:
 *  - GitHub: listed in the project's github_editors, or able to push to its GitHub repo.
 *  - Discord: holds one of the developer roles AND is listed in its discord_editors.
 * Everything they send is still reviewed before it's published.
 */
function can_edit(array $user, array $project): bool
{
    if ($user['provider'] === 'github') {
        $login = strtolower($user['username']);
        if (in_array($login, array_map('strtolower', $project['github_editors']), true)) {
            return true;
        }
        return $project['github'] !== '' && in_array(strtolower($project['github']), $user['push_repos'] ?? [], true);
    }
    return !empty($user['developer']) && in_array($user['id'], $project['discord_editors'], true);
}

/** Anyone signed in with GitHub, or a Discord member with one of the Dev roles. */
function can_recommend(array $user): bool
{
    return $user['provider'] === 'github' || !empty($user['developer']);
}

function suggest_on_github_url(): string
{
    return 'https://github.com/' . config()['github']['repo'] . '/issues/new?template=software.yml';
}

function editable_projects(array $user): array
{
    return array_filter(projects(), fn ($p) => can_edit($user, $p));
}

// --- Login state ------------------------------------------------------------

function current_user(): ?array
{
    if (is_dev() && empty($_SESSION['user'])) {
        $_SESSION['user'] = config()['dev_user'] + ['checked_at' => PHP_INT_MAX, 'token_expires' => PHP_INT_MAX];
    }
    return $_SESSION['user'] ?? null;
}

/**
 * Returns the signed-in person, or sends them to log in. Discord roles and GitHub access are
 * checked again every 15 minutes, so someone who loses them loses the editor soon after.
 */
function require_login(bool $recheckNow = false): array
{
    $user = current_user();
    if (!$user) {
        $_SESSION['return_to'] = $_SERVER['REQUEST_URI'] ?? './';
        redirect('./');
    }
    if (!is_dev() && ($recheckNow || time() - $user['checked_at'] > 900)) {
        try {
            if (time() >= $user['token_expires']) {
                throw new HttpError(401, '', 'Login expired.');
            }
            $user = $user['provider'] === 'github'
                ? github_access($user, github_login()->pushableRepos($user['token']))
                : discord_access($user, discord()->memberRoles($user['token']));
        } catch (HttpError $e) {
            if ($e->status !== 401) {
                render_error($e);
            }
            unset($_SESSION['user']);
            $_SESSION['return_to'] = ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' ? ($_SERVER['REQUEST_URI'] ?? './') : './';
            redirect('./');
        }
        $_SESSION['user'] = $user;
    }
    return $user;
}

/** Stops a page unless the signed-in person may edit this project. */
function require_project(array $user, string $id): array
{
    $project = project($id);
    if (!$project) {
        http_response_code(404);
        render_message('No such project', '<p>That project isn\'t on VivaHX. <a href="./">Back to your projects</a>.</p>');
    }
    if (!can_edit($user, $project)) {
        http_response_code(403);
        render_message('Not your project yet', '<p>You aren\'t linked to <b>' . e($project['name']) . '</b>. '
            . '<a href="./">Ask to be linked</a> from the editor\'s home page.</p>');
    }
    return $project;
}

function discord_access(array $user, ?array $roles): array
{
    $user['member'] = $roles !== null;
    $user['developer'] = $roles !== null && array_intersect($roles, config()['discord']['role_ids']) !== [];
    $user['checked_at'] = time();
    return $user;
}

function github_access(array $user, array $pushRepos): array
{
    $user['push_repos'] = $pushRepos;
    $user['checked_at'] = time();
    return $user;
}

/** A short "who sent this" line for pull requests and issues. */
function identity(array $user): string
{
    $name = str_replace(['`', '*', '_', '[', ']'], '', $user['name']);
    return $user['provider'] === 'github'
        ? "**$name** (GitHub [@{$user['username']}](https://github.com/{$user['username']}))"
        : "**$name** (Discord `@{$user['username']}`, user ID `{$user['id']}`)";
}

function commit_author(array $user): array
{
    return $user['provider'] === 'github'
        ? ['name' => $user['name'], 'email' => $user['id'] . '+' . $user['username'] . '@users.noreply.github.com']
        : ['name' => $user['name'], 'email' => $user['id'] . '+' . $user['username'] . '@users.discord.invalid'];
}

/** Branch names start with this, so the home page can find someone's edits still in review. */
function branch_prefix(array $user): string
{
    return 'edit/' . $user['provider'] . '-' . $user['id'] . '-';
}

/** Stops people from flooding the review queue. */
function within_rate_limit(array $user): bool
{
    $limit = (int) (config()['limits']['edits_per_hour'] ?? 10);
    $file = data_dir('ratelimit') . '/' . $user['provider'] . '-' . preg_replace('/\D/', '', $user['id']) . '.json';
    $recent = array_filter(
        is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [],
        fn ($t) => $t > time() - 3600
    );
    if (count($recent) >= $limit) {
        return false;
    }
    $recent[] = time();
    file_put_contents($file, json_encode(array_values($recent)), LOCK_EX);
    return true;
}

/** A TOML/JSON string: the post headers are TOML, and JSON's escapes are valid TOML. */
function toml_string(string $s): string
{
    return json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function slug(string $text): string
{
    $s = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
    return substr($s !== '' ? $s : 'post', 0, 60);
}

function clean_url(string $url): string
{
    $url = trim($url);
    return $url === '' || preg_match('#^https?://[^\s<>"]+$#i', $url) ? $url : '';
}

/** A Markdown preview through GitHub's renderer, with pending uploads shown from the editor. */
function preview_html(string $markdown): string
{
    return github()->markdown($markdown);
}
