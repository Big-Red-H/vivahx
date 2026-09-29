<?php
/*
 * Copy this file OUTSIDE the website folder, to:
 *   ~/editor-config/vivahx.com.php
 * and fill in the values. It holds secrets, so it must never be inside vivahx.com/.
 * (The Hotline Wiki's editor keeps its settings in the same folder.)
 */
return [
    'site_url' => 'http://vivahx.com',
    // The editor itself, over HTTPS (it signs people in).
    'editor_url' => 'https://vivahx.com/editor',

    // "Sign in with GitHub": an OAuth App owned by the Big-Red-H organization.
    // github.com/organizations/Big-Red-H/settings/applications > New OAuth App
    //   Homepage URL:               https://vivahx.com
    //   Authorization callback URL: https://vivahx.com/editor/callback-github.php
    'github_login' => [
        'client_id' => '',
        'client_secret' => '',
    ],

    // "Sign in with Discord": the same Discord application and server as the Hotline Wiki's editor.
    // In the Discord Developer Portal > that application > OAuth2 > Redirects, add:
    //   https://vivahx.com/editor/callback.php
    'discord' => [
        'client_id' => '',
        'client_secret' => '',
        'guild_id' => '',
        // Client Dev, Server Dev, Tracker Dev. Anyone with one of these AND listed in a project's
        // discord_editors (projects/<id>/project.toml) can edit that project.
        'role_ids' => ['1485095253873791007', '1485095448858329218', '1485095504390918257'],
        'invite' => 'https://discord.gg/rfRKHy6UR2', // The VivaHX invite (gives the VivaHX Visitor role).
    ],

    // Where edits are sent, as pull requests. A fine-grained token for Big-Red-H/vivahx only, with
    // "Contents: Read and write", "Pull requests: Read and write" and "Issues: Read and write".
    'github' => [
        'token' => '',
        'repo' => 'Big-Red-H/vivahx',
        'branch' => 'main',
    ],

    // Local testing only (php -S): skip login and record GitHub writes instead of sending them.
    // 'dev_user' => ['provider' => 'github', 'id' => '1', 'username' => 'tester', 'name' => 'Tester', 'avatar' => null, 'token' => '', 'push_repos' => ['jhalter/mobius']],
    // 'dry_run' => true,

    'limits' => [
        'edits_per_hour' => 10,
        'screenshots' => 8,
        'image_max_bytes' => 5000000,
        'image_max_width' => 1600,
    ],
];
