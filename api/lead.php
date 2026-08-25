<?php
require_once __DIR__ . '/../lib/bootstrap.php';

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    exit('Yalnızca POST.');
}

if (security_honeypot_filled()) {
    $_SESSION['flash'] = 'Talebiniz alındı. Danışmanımız sizi arayacak.';
    redirect('index.php');
}

$name = post('name');
$phone = post('phone');
$interest = post('interest');
$digits = preg_replace('/\D+/', '', $phone) ?? '';

if ($name === '' || strlen($digits) < 10) {
    flash_error('Ad ve geçerli bir telefon girin.');
    redirect('index.php');
}
if (mb_strlen($name) > 120 || mb_strlen($interest) > 80) {
    flash_error('Form bilgileri geçersiz.');
    redirect('index.php');
}
if (security_lead_blocked()) {
    flash_error('Çok fazla istek. Bir süre sonra yeniden deneyin.');
    redirect('index.php');
}

db()->prepare('INSERT INTO leads (name, phone, interest) VALUES (?,?,?)')
    ->execute([$name, $phone, $interest]);
$html = mail_wrap('Yeni arama talebi', '<p><b>Ad:</b> ' . e($name) . '<br><b>Telefon:</b> ' . e($phone) . '<br><b>İlgi:</b> ' . e($interest) . '</p>');
notify_admin('Sizi arayalım · ' . $name, $html, $name . "\n" . $phone . "\n" . $interest);
$_SESSION['flash'] = 'Talebiniz alındı. Danışmanımız sizi arayacak.';
redirect('index.php');
