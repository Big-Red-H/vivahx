<?php
declare(strict_types=1);

final class Discord
{
    private const API = 'https://discord.com/api/v10';

    public function __construct(private array $cfg, private string $redirectUri)
    {
    }

    public function authorizeUrl(string $state): string
    {
        return 'https://discord.com/oauth2/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->cfg['client_id'],
            // Only the user's identity and their membership in our server. No messages, no server list.
            'scope' => 'identify guilds.members.read',
            'state' => $state,
            'redirect_uri' => $this->redirectUri,
            'prompt' => 'none',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(string $code): array
    {
        [$status, $data] = http_request('POST', self::API . '/oauth2/token', [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode($this->cfg['client_id'] . ':' . $this->cfg['client_secret']),
        ], http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
        ]));
        if ($status !== 200 || empty($data['access_token'])) {
            throw new HttpError($status, json_encode($data), 'Discord login failed.');
        }
        return $data;
    }

    public function user(string $token): array
    {
        return $this->get('/users/@me', $token);
    }

    /** The user's role IDs in the configured server, or null if they aren't a member. */
    public function memberRoles(string $token): ?array
    {
        [$status, $data] = http_request('GET', self::API . '/users/@me/guilds/' . $this->cfg['guild_id'] . '/member', [
            'Authorization: Bearer ' . $token,
        ]);
        if ($status === 404) {
            return null;
        }
        if ($status !== 200) {
            throw new HttpError($status, json_encode($data), 'Could not check your Discord server membership.');
        }
        return $data['roles'] ?? [];
    }

    private function get(string $path, string $token): array
    {
        [$status, $data] = http_request('GET', self::API . $path, ['Authorization: Bearer ' . $token]);
        if ($status !== 200 || !is_array($data)) {
            throw new HttpError($status, json_encode($data), 'Discord request failed.');
        }
        return $data;
    }
}
