<?php
require_once __DIR__ . '/lib/bootstrap.php';
try {
    db()->query('SELECT 1');
    echo 'OK';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Veritabanı hatası';
}
