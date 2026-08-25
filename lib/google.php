<?php

function google_configured(): bool
{
    return setting_bool('google_enabled')
        && trim(setting('google_client_id')) !== ''
        && trim(setting('google_client_secret')) !== '';
}

function google_redirect_uri(): string
{
    return rtrim(app_public_url('google-callback'), '/');
}

function google_origin_hint(): string
{
    $uri = google_redirect_uri();
    $p = parse_url($uri);
    if (!is_array($p) || empty($p['scheme']) || empty($p['host'])) {
        return 'http://localhost';
    }
    $origin = $p['scheme'] . '://' . $p['host'];
    if (!empty($p['port'])) {
        $origin .= ':' . $p['port'];
    }
    return $origin;
}

function google_start_url(string $ctx, string $next = ''): string
{
    $q = ['ctx' => $ctx];
    if ($next !== '') {
        $q['next'] = $next;
    }
    return page_url('google-basla') . '?' . http_build_query($q);
}

function google_http_json(string $method, string $url, array $fields = [], array $headers = []): array
{
    $body = $fields !== [] ? http_build_query($fields) : '';
    $hdr = array_merge(['Accept: application/json'], $headers);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Google isteği başlatılamadı.');
        }
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $hdr,
        ];
        if (strtoupper($method) === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $body;
            $hdr[] = 'Content-Type: application/x-www-form-urlencoded';
            $opts[CURLOPT_HTTPHEADER] = $hdr;
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException($err !== '' ? $err : 'Google yanıt vermedi.');
        }
    } else {
        $http = [
            'method' => strtoupper($method),
            'header' => implode("\r\n", $hdr) . ($body !== '' ? "\r\nContent-Type: application/x-www-form-urlencoded" : ''),
            'timeout' => 20,
            'ignore_errors' => true,
        ];
        if ($body !== '') {
            $http['content'] = $body;
        }
        $raw = @file_get_contents($url, false, stream_context_create(['http' => $http]));
        if ($raw === false) {
            throw new RuntimeException('Google yanıt vermedi.');
        }
        $code = 200;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
    }
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Google yanıtı okunamadı.');
    }
    if ($code >= 400 || isset($data['error'])) {
        $msg = (string) ($data['error_description'] ?? $data['error'] ?? 'Google doğrulaması başarısız.');
        throw new RuntimeException($msg);
    }
    return $data;
}

function google_exchange_code(string $code): array
{
    return google_http_json('POST', 'https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => setting('google_client_id'),
        'client_secret' => setting('google_client_secret'),
        'redirect_uri' => google_redirect_uri(),
        'grant_type' => 'authorization_code',
    ]);
}

function google_profile_from_tokens(array $tokens): array
{
    $access = (string) ($tokens['access_token'] ?? '');
    $idToken = (string) ($tokens['id_token'] ?? '');
    $info = [];
    if ($idToken !== '') {
        try {
            $info = google_http_json('GET', 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($idToken));
        } catch (Throwable) {
            $info = [];
        }
    }
    if ($access !== '' && (empty($info['email']) || empty($info['sub']))) {
        $info = google_http_json('GET', 'https://www.googleapis.com/oauth2/v3/userinfo', [], [
            'Authorization: Bearer ' . $access,
        ]);
    }
    $email = strtolower(trim((string) ($info['email'] ?? '')));
    $gid = trim((string) ($info['sub'] ?? $info['id'] ?? ''));
    $name = trim((string) ($info['name'] ?? ''));
    if ($name === '') {
        $name = trim(((string) ($info['given_name'] ?? '')) . ' ' . ((string) ($info['family_name'] ?? '')));
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $gid === '') {
        throw new RuntimeException('Google hesabından e-posta alınamadı.');
    }
    if (isset($info['email_verified']) && !in_array($info['email_verified'], [true, 'true', '1', 1], true)) {
        throw new RuntimeException('Google e-postası doğrulanmamış.');
    }
    return [
        'google_id' => $gid,
        'email' => $email,
        'name' => $name !== '' ? $name : $email,
    ];
}

function google_find_user_by_id(string $googleId): ?array
{
    if (!isset(users_columns()['google_id'])) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM users WHERE google_id = ? LIMIT 1');
    $st->execute([$googleId]);
    $row = $st->fetch();
    return $row ?: null;
}

function google_link_user(int $userId, string $googleId): void
{
    if (!isset(users_columns()['google_id'])) {
        return;
    }
    db()->prepare('UPDATE users SET google_id = ? WHERE id = ?')->execute([$googleId, $userId]);
}

function google_assert_ctx_role(string $ctx, array $u): void
{
    $role = (string) ($u['role'] ?? '');
    if (is_admin_role($role)) {
        throw new RuntimeException('Yönetici Google ile giriş yapamaz. /wiys kullanın.');
    }
    if ($ctx === 'magaza') {
        if (!is_shop_role($role)) {
            throw new RuntimeException('Bu e-posta eğitim hesabı. Mağaza için ayrı e-posta kullanın veya ders girişine gidin.');
        }
        return;
    }
    if ($ctx === 'ders') {
        if (!in_array($role, ['ogrenci', 'ogretmen'], true)) {
            throw new RuntimeException('Bu e-posta mağaza hesabı. Ders için ayrı e-posta kullanın veya mağaza girişine gidin.');
        }
        return;
    }
    throw new RuntimeException('Geçersiz Google oturumu.');
}

function google_login_or_register(string $ctx, array $profile): array
{
    $ctx = $ctx === 'magaza' ? 'magaza' : 'ders';
    $googleId = (string) $profile['google_id'];
    $email = (string) $profile['email'];
    $name = (string) $profile['name'];

    $byGoogle = google_find_user_by_id($googleId);
    if ($byGoogle) {
        if (($byGoogle['status'] ?? '') === 'pasif') {
            throw new RuntimeException('Bu hesap pasif.');
        }
        google_assert_ctx_role($ctx, $byGoogle);
        return $byGoogle;
    }

    $st = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $existing = $st->fetch();
    if ($existing) {
        if (($existing['status'] ?? '') === 'pasif') {
            throw new RuntimeException('Bu hesap pasif.');
        }
        google_assert_ctx_role($ctx, $existing);
        if (empty($existing['google_id'])) {
            google_link_user((int) $existing['id'], $googleId);
            $existing['google_id'] = $googleId;
        } elseif ((string) $existing['google_id'] !== $googleId) {
            throw new RuntimeException('Bu e-posta başka bir Google hesabına bağlı.');
        }
        return $existing;
    }

    $role = $ctx === 'magaza' ? 'musteri' : 'ogrenci';
    $status = $ctx === 'magaza' ? 'aktif' : 'bekliyor';
    $pass = bin2hex(random_bytes(16));
    return register_user_account($name, $email, $pass, $role, '', $status, $googleId);
}

function google_finish(array $user, string $ctx, string $next = ''): void
{
    login_user($user);
    if ($next !== '') {
        redirect($next);
    }
    if ($ctx === 'ders' && ($user['role'] ?? '') === 'ogrenci' && function_exists('membership_needs_pay') && membership_needs_pay($user)) {
        redirect(page_url('uyelik-ders'));
    }
    redirect(panel_home($user['role']));
}

function google_button(string $ctx, string $next = '', string $class = ''): void
{
    if (!google_configured()) {
        return;
    }
    $href = google_start_url($ctx, $next);
    $cls = trim('btn-google ' . $class);
    echo '<a class="' . e($cls) . '" href="' . e($href) . '">';
    echo '<svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 8 3.1l5.7-5.7C34.2 6.1 29.4 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.2-.1-2.3-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16 19 12 24 12c3.1 0 5.8 1.2 8 3.1l5.7-5.7C34.2 6.1 29.4 4 24 4 16.3 4 9.6 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 10-2 13.6-5.2l-6.3-5.3C29.2 35.1 26.7 36 24 36c-5.3 0-9.7-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-1.1 3.2-3.5 5.7-6.6 7.2l.1.1 6.3 5.3C36.7 41.3 44 36 44 24c0-1.2-.1-2.3-.4-3.5z"/></svg>';
    echo '<span>Google ile devam et</span></a>';
}
