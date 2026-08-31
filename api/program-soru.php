<?php
require_once __DIR__ . '/../lib/bootstrap.php';

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    json_out(['ok' => false, 'error' => 'Yalnızca POST.'], 405);
}

if (security_honeypot_filled()) {
    json_out(['ok' => true]);
}

if (security_contact_blocked()) {
    json_out(['ok' => false, 'error' => 'Çok fazla istek. Bir süre sonra yeniden deneyin.'], 429);
}

$u = current_user();
try {
    program_ask_create(
        (int) post('program_id'),
        post('name') !== '' ? post('name') : (string) ($u['name'] ?? ''),
        post('email') !== '' ? post('email') : (string) ($u['email'] ?? ''),
        post('body'),
        $u ? (int) $u['id'] : null
    );
    json_out(['ok' => true]);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 422);
}
