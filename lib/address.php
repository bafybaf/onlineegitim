<?php

function ensure_address_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $pdo = db();
    try {
        $tables = [];
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM) as $row) {
            $tables[(string) $row[0]] = true;
        }
        if (!isset($tables['addresses'])) {
            $pdo->exec(
                "CREATE TABLE addresses (
                  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  user_id INT UNSIGNED NOT NULL,
                  title VARCHAR(40) NOT NULL DEFAULT 'Ev',
                  name VARCHAR(120) NOT NULL,
                  phone VARCHAR(30) NOT NULL,
                  city VARCHAR(80) NOT NULL,
                  district VARCHAR(80) DEFAULT NULL,
                  `line` VARCHAR(255) NOT NULL,
                  is_default TINYINT(1) NOT NULL DEFAULT 0,
                  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  KEY idx_addr_user (user_id),
                  CONSTRAINT fk_addr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        $orderCols = table_columns('orders');
        foreach ([
            'address_id' => 'INT UNSIGNED NULL',
            'ship_name' => 'VARCHAR(120) NULL',
            'ship_phone' => 'VARCHAR(30) NULL',
            'ship_city' => 'VARCHAR(80) NULL',
            'ship_district' => 'VARCHAR(80) NULL',
            'ship_line' => 'VARCHAR(255) NULL',
        ] as $col => $def) {
            if (!isset($orderCols[$col])) {
                $pdo->exec('ALTER TABLE orders ADD COLUMN `' . $col . '` ' . $def);
            }
        }
        $payCols = table_columns('payments');
        if (!isset($payCols['address_id'])) {
            $pdo->exec('ALTER TABLE payments ADD COLUMN address_id INT UNSIGNED NULL');
        }
        if (!isset($payCols['address_json'])) {
            $pdo->exec('ALTER TABLE payments ADD COLUMN address_json TEXT NULL');
        }
        try {
            $pdo->exec('ALTER TABLE orders ADD CONSTRAINT fk_o_addr FOREIGN KEY (address_id) REFERENCES addresses(id) ON DELETE SET NULL');
        } catch (Throwable) {
        }
        try {
            $pdo->exec('ALTER TABLE payments ADD CONSTRAINT fk_pay_addr FOREIGN KEY (address_id) REFERENCES addresses(id) ON DELETE SET NULL');
        } catch (Throwable) {
        }
    } catch (Throwable) {
        // Şema yoksa veya yetki kısıtlıysa sessizce geçilir.
    }
}

function table_columns(string $table): array
{
    $cols = [];
    try {
        foreach (db()->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`')->fetchAll() as $row) {
            $cols[(string) $row['Field']] = true;
        }
    } catch (Throwable) {
    }
    return $cols;
}

function address_validate(array $in): array
{
    $title = mb_substr(trim((string) ($in['title'] ?? '')), 0, 40);
    if ($title === '') {
        $title = 'Ev';
    }
    $name = trim((string) ($in['name'] ?? ''));
    $phone = trim((string) ($in['phone'] ?? ''));
    $city = trim((string) ($in['city'] ?? ''));
    $district = trim((string) ($in['district'] ?? ''));
    $line = trim((string) ($in['line'] ?? ''));
    $default = !empty($in['is_default']);

    $err = '';
    if (mb_strlen($name) < 2) {
        $err = 'Teslimat adı en az 2 karakter olmalı.';
    } elseif (preg_replace('/\D+/', '', $phone) === '' || mb_strlen(preg_replace('/\D+/', '', $phone)) < 10) {
        $err = 'Geçerli bir telefon girin.';
    } elseif (mb_strlen($city) < 2) {
        $err = 'Şehir zorunludur.';
    } elseif (mb_strlen($line) < 5) {
        $err = 'Açık adres (sokak, no) zorunludur.';
    }

    return [
        $err,
        [
            'title' => $title,
            'name' => mb_substr($name, 0, 120),
            'phone' => mb_substr($phone, 0, 30),
            'city' => mb_substr($city, 0, 80),
            'district' => $district !== '' ? mb_substr($district, 0, 80) : null,
            'line' => mb_substr($line, 0, 255),
            'is_default' => $default,
        ],
    ];
}

function user_addresses(int $userId): array
{
    $st = db()->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC');
    $st->execute([$userId]);
    return $st->fetchAll();
}

function user_address(int $userId, int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM addresses WHERE id = ? AND user_id = ?');
    $st->execute([$id, $userId]);
    $row = $st->fetch();
    return $row ?: null;
}

function user_default_address(int $userId): ?array
{
    $st = db()->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC LIMIT 1');
    $st->execute([$userId]);
    $row = $st->fetch();
    return $row ?: null;
}

function address_count(int $userId): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM addresses WHERE user_id = ?');
    $st->execute([$userId]);
    return (int) $st->fetchColumn();
}

function address_save(int $userId, array $data, ?int $id = null): int
{
    $pdo = db();
    $makeDefault = !empty($data['is_default']) || address_count($userId) < 1 || ($id && address_count($userId) === 1);
    if ($id) {
        $cur = user_address($userId, $id);
        if (!$cur) {
            throw new RuntimeException('Adres bulunamadı.');
        }
        $pdo->prepare(
            'UPDATE addresses SET title=?, name=?, phone=?, city=?, district=?, `line`=?, is_default=? WHERE id=? AND user_id=?'
        )->execute([
            $data['title'],
            $data['name'],
            $data['phone'],
            $data['city'],
            $data['district'],
            $data['line'],
            $makeDefault ? 1 : (int) $cur['is_default'],
            $id,
            $userId,
        ]);
    } else {
        $pdo->prepare(
            'INSERT INTO addresses (user_id, title, name, phone, city, district, `line`, is_default) VALUES (?,?,?,?,?,?,?,?)'
        )->execute([
            $userId,
            $data['title'],
            $data['name'],
            $data['phone'],
            $data['city'],
            $data['district'],
            $data['line'],
            $makeDefault ? 1 : 0,
        ]);
        $id = (int) $pdo->lastInsertId();
    }
    if ($makeDefault) {
        address_set_default($userId, $id);
    }
    return $id;
}

function address_delete(int $userId, int $id): bool
{
    $row = user_address($userId, $id);
    if (!$row) {
        return false;
    }
    db()->prepare('DELETE FROM addresses WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
    if ((int) $row['is_default'] === 1) {
        $next = user_default_address($userId);
        if ($next) {
            address_set_default($userId, (int) $next['id']);
        }
    }
    return true;
}

function address_set_default(int $userId, int $id): bool
{
    if (!user_address($userId, $id)) {
        return false;
    }
    $pdo = db();
    $pdo->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare('UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
    return true;
}

function address_snapshot(array $addr): array
{
    return [
        'id' => (int) ($addr['id'] ?? 0),
        'title' => (string) ($addr['title'] ?? ''),
        'name' => (string) ($addr['name'] ?? ''),
        'phone' => (string) ($addr['phone'] ?? ''),
        'city' => (string) ($addr['city'] ?? ''),
        'district' => (string) ($addr['district'] ?? ''),
        'line' => (string) ($addr['line'] ?? ''),
    ];
}

function address_format(array $row): string
{
    $orderSnap = array_key_exists('ship_name', $row) || array_key_exists('ship_line', $row);
    $name = trim((string) ($orderSnap ? ($row['ship_name'] ?? '') : ($row['name'] ?? '')));
    $phone = trim((string) ($orderSnap ? ($row['ship_phone'] ?? '') : ($row['phone'] ?? '')));
    $line = trim((string) ($orderSnap ? ($row['ship_line'] ?? '') : ($row['line'] ?? '')));
    $district = trim((string) ($orderSnap ? ($row['ship_district'] ?? '') : ($row['district'] ?? '')));
    $city = trim((string) ($orderSnap ? ($row['ship_city'] ?? '') : ($row['city'] ?? '')));
    $place = trim($district . ($district !== '' && $city !== '' ? ' / ' : '') . $city);
    $parts = array_values(array_filter([$name, $phone, $line, $place], static fn(string $s): bool => $s !== ''));
    return implode(' · ', $parts);
}

function address_paytr_line(array $snap): string
{
    $line = trim((string) ($snap['line'] ?? ''));
    $district = trim((string) ($snap['district'] ?? ''));
    $city = trim((string) ($snap['city'] ?? ''));
    $out = trim($line . ($line !== '' && ($district !== '' || $city !== '') ? ', ' : '') . trim($district . ' ' . $city));
    return mb_substr($out !== '' ? $out : 'Türkiye', 0, 400);
}

function checkout_resolve_address(array $user): array
{
    $userId = (int) $user['id'];
    $addrId = (int) post('address_id');
    if ($addrId > 0) {
        $row = user_address($userId, $addrId);
        if (!$row) {
            return ['error' => 'address', 'snap' => null];
        }
        return ['error' => '', 'snap' => address_snapshot($row)];
    }

    [$err, $clean] = address_validate([
        'title' => post('addr_title'),
        'name' => post('addr_name'),
        'phone' => post('addr_phone'),
        'city' => post('addr_city'),
        'district' => post('addr_district'),
        'line' => post('addr_line'),
        'is_default' => post('addr_default') === '1' || address_count($userId) < 1,
    ]);
    if ($err !== '') {
        return ['error' => 'address', 'snap' => null, 'message' => $err];
    }
    $id = address_save($userId, $clean);
    $row = user_address($userId, $id);
    return ['error' => '', 'snap' => $row ? address_snapshot($row) : $clean + ['id' => $id]];
}

function payment_address_snapshot(array $payment): array
{
    $json = json_decode((string) ($payment['address_json'] ?? ''), true);
    return is_array($json) ? $json : [];
}
