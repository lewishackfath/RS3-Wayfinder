<?php
declare(strict_types=1);

function discord_authorise_url(): string
{
    $state = bin2hex(random_bytes(32));
    $_SESSION['discord_oauth_state'] = $state;

    $params = http_build_query([
        'client_id' => (string) env('DISCORD_CLIENT_ID'),
        'redirect_uri' => (string) env('DISCORD_REDIRECT_URI'),
        'response_type' => 'code',
        'scope' => (string) env('DISCORD_SCOPES', 'identify email'),
        'state' => $state,
    ]);

    return 'https://discord.com/oauth2/authorize?' . $params;
}

function discord_exchange_code(string $code): array
{
    $response = http_post_form('https://discord.com/api/oauth2/token', [
        'client_id' => (string) env('DISCORD_CLIENT_ID'),
        'client_secret' => (string) env('DISCORD_CLIENT_SECRET'),
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => (string) env('DISCORD_REDIRECT_URI'),
    ]);

    if (empty($response['access_token'])) {
        throw new RuntimeException('Discord token exchange failed.');
    }
    return $response;
}

function discord_get_current_user(string $accessToken): array
{
    $ch = curl_init('https://discord.com/api/users/@me');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false || $status < 200 || $status >= 300) {
        throw new RuntimeException('Discord user request failed: ' . ($error ?: $body));
    }

    $data = json_decode((string)$body, true);
    if (!is_array($data)) {
        throw new RuntimeException('Discord returned invalid user JSON.');
    }
    return $data;
}

function http_post_form(string $url, array $fields): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false || $status < 200 || $status >= 300) {
        throw new RuntimeException('HTTP request failed: ' . ($error ?: $body));
    }

    $data = json_decode((string)$body, true);
    if (!is_array($data)) {
        throw new RuntimeException('HTTP response was not valid JSON.');
    }
    return $data;
}
