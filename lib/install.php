<?php
declare(strict_types=1);

function oi_install_schema_path(): string
{
    return dirname(__DIR__) . '/sql/schema.sql';
}

function oi_install_table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    return $st !== false && $st->fetchColumn() !== false;
}

function oi_install_has_admin(?PDO $pdo = null): bool
{
    try {
        $pdo = $pdo ?? db();
        if (!oi_install_table_exists($pdo, 'users')) {
            return false;
        }
        $n = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        return (int) $n > 0;
    } catch (Throwable) {
        return false;
    }
}

function oi_install_wait_pdo(): PDO
{
    $last = null;
    $tries = PHP_SAPI === 'cli' ? 30 : 5;
    for ($i = 0; $i < $tries; $i++) {
        try {
            return db();
        } catch (Throwable $e) {
            $last = $e;
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, 'Veritabanı bekleniyor (' . ($i + 1) . '/' . $tries . '): ' . $e->getMessage() . PHP_EOL);
            }
            sleep(2);
        }
    }
    throw $last ?? new RuntimeException('Veritabanına bağlanılamadı.');
}

/** @return list<string> */
function oi_install_statements(string $sql): array
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $parts = [];
    $buf = '';
    $len = strlen($sql);
    $inStr = false;
    $quote = '';
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($inStr) {
            $buf .= $c;
            if ($c === $quote && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $inStr = false;
            }
            continue;
        }
        if ($c === "'" || $c === '"') {
            $inStr = true;
            $quote = $c;
            $buf .= $c;
            continue;
        }
        if ($c === ';') {
            $stmt = trim($buf);
            if ($stmt !== '') {
                $parts[] = $stmt;
            }
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    $stmt = trim($buf);
    if ($stmt !== '') {
        $parts[] = $stmt;
    }
    return $parts;
}

function oi_install_run_schema(PDO $pdo): void
{
    $file = oi_install_schema_path();
    if (!is_file($file)) {
        throw new RuntimeException('sql/schema.sql bulunamadı.');
    }
    $sql = (string) file_get_contents($file);
    foreach (oi_install_statements($sql) as $stmt) {
        if (preg_match('/^(DROP\s+DATABASE|CREATE\s+DATABASE|USE\s+)/i', $stmt)) {
            continue;
        }
        $pdo->exec($stmt);
    }
}

function oi_install_create_admin(PDO $pdo, string $name, string $email, string $password): void
{
    $email = strtolower(trim($email));
    $name = trim($name);
    if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Geçerli ad ve e-posta girin.');
    }
    if (strlen($password) < 10) {
        throw new InvalidArgumentException('Yönetici şifresi en az 10 karakter olmalı.');
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $st = $pdo->prepare('INSERT INTO users (name, email, password, role, status) VALUES (?,?,?,?,?)');
    $st->execute([$name, $email, $hash, 'admin', 'aktif']);
}

function oi_install_seed_admin_from_env(PDO $pdo): void
{
    if (oi_install_has_admin($pdo)) {
        return;
    }
    $email = trim(oi_env('ADMIN_EMAIL', 'admin@onlineilahiyat.com'));
    $password = oi_env('ADMIN_PASSWORD', '');
    $name = trim(oi_env('ADMIN_NAME', 'Yönetici'));
    if ($password === '') {
        return;
    }
    if (strlen($password) < 10) {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "ADMIN_PASSWORD en az 10 karakter olmalı; yönetici oluşturulmadı. /kurulum kullanın.\n");
        }
        return;
    }
    oi_install_create_admin($pdo, $name !== '' ? $name : 'Yönetici', $email, $password);
}

function oi_install_skip_admin_redirect(): bool
{
    if (PHP_SAPI === 'cli') {
        return true;
    }
    $uri = str_replace('\\', '/', (string) ($_SERVER['REQUEST_URI'] ?? ''));
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    foreach (['kurulum', 'health.php', '/api/', '/assets/'] as $needle) {
        if (str_contains($uri, $needle) || str_contains($script, $needle)) {
            return true;
        }
    }
    return false;
}

function oi_install_ensure(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $pdo = oi_install_wait_pdo();
    if (!oi_install_table_exists($pdo, 'users')) {
        oi_install_run_schema($pdo);
    }
    oi_install_seed_admin_from_env($pdo);
    if (oi_install_has_admin($pdo) || oi_install_skip_admin_redirect()) {
        return;
    }
    if (function_exists('redirect')) {
        redirect('kurulum');
    }
}
