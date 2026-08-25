<?php

use PHPMailer\PHPMailer\PHPMailer;

function mail_log(string $message): void
{
    $safe = preg_replace('/(pass(word)?|smtp_pass)\s*[:=]\s*\S+/i', '$1=***', $message) ?? $message;
    error_log('[mailer] ' . $safe);
    $dir = dirname(__DIR__) . '/storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($dir . '/mail.log', date('c') . ' ' . $safe . PHP_EOL, FILE_APPEND);
}

function admin_inbox(): string
{
    $to = trim(setting('smtp_to_email', 'info@onlineilahiyat.com'));
    return $to !== '' ? $to : 'info@onlineilahiyat.com';
}

function smtp_ready(): bool
{
    return setting_bool('smtp_enabled')
        && setting('smtp_host') !== ''
        && setting('smtp_from_email') !== ''
        && class_exists(PHPMailer::class);
}

function send_mail(string $to, string $subject, string $htmlBody, string $textBody = '', ?string $replyTo = null): bool
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        mail_log('Geçersiz alıcı, gönderilmedi: ' . $subject);
        return false;
    }
    if (!setting_bool('smtp_enabled')) {
        mail_log('SMTP kapalı, gönderilmedi: ' . $subject);
        return false;
    }
    $host = trim(setting('smtp_host'));
    $from = trim(setting('smtp_from_email'));
    if ($host === '' || $from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        mail_log('SMTP host/from eksik, gönderilmedi: ' . $subject);
        return false;
    }
    if (!class_exists(PHPMailer::class)) {
        mail_log('PHPMailer yüklü değil, gönderilmedi: ' . $subject);
        return false;
    }
    try {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = (int) setting('smtp_port', '587') ?: 587;
        $enc = setting('smtp_encryption', 'tls');
        if ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }
        $user = setting('smtp_user');
        if ($user !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = setting('smtp_pass');
        }
        $mail->setFrom($from, setting('smtp_from_name', defined('APP_NAME') ? APP_NAME : 'Online İlahiyat'));
        $mail->addAddress($to);
        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyTo);
        }
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody !== '' ? $textBody : trim(html_entity_decode(strip_tags($htmlBody), ENT_QUOTES, 'UTF-8'));
        $mail->send();
        mail_log('Gönderildi: ' . $subject . ' → ' . $to);
        return true;
    } catch (Throwable $e) {
        mail_log('Gönderim hatası: ' . $e->getMessage());
        return false;
    }
}

function mail_wrap(string $title, string $innerHtml): string
{
    return '<div style="font-family:Nunito,Arial,sans-serif;max-width:560px;margin:0 auto;color:#1a1f36">'
        . '<p style="font-weight:800;font-size:20px;color:#1a3fad;margin:0 0 12px">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>'
        . $innerHtml
        . '<p style="margin-top:24px;font-size:12px;color:#6e6e73">Online İlahiyat</p></div>';
}

function notify_admin(string $subject, string $htmlBody, string $textBody = '', ?string $replyTo = null): bool
{
    return send_mail(admin_inbox(), $subject, $htmlBody, $textBody, $replyTo);
}

function notify_payment_paid(array $payment): void
{
    try {
        if (!smtp_ready()) {
            return;
        }
        $kind = payment_kind_label((string) ($payment['kind'] ?? 'kitap'));
        $oid = (string) ($payment['merchant_oid'] ?? '');
        $total = isset($payment['total']) ? money((int) $payment['total']) : '';
        $html = mail_wrap('Ödeme alındı', '<p>' . htmlspecialchars($kind, ENT_QUOTES, 'UTF-8')
            . ' ödemesi onaylandı.</p><p>Sipariş no: <b>' . htmlspecialchars($oid, ENT_QUOTES, 'UTF-8')
            . '</b><br>Tutar: <b>' . htmlspecialchars($total, ENT_QUOTES, 'UTF-8') . '</b></p>');
        notify_admin('Ödeme alındı · ' . $oid, $html, $kind . ' ödeme ' . $oid . ' ' . $total);
        $uid = (int) ($payment['user_id'] ?? 0);
        if ($uid > 0) {
            $ust = db()->prepare('SELECT * FROM users WHERE id = ?');
            $ust->execute([$uid]);
            $user = $ust->fetch();
            if ($user) {
                $userHtml = mail_wrap('Ödemeniz alındı', '<p>Ödemeniz onaylandı.</p><p>Sipariş no: <b>'
                    . htmlspecialchars($oid, ENT_QUOTES, 'UTF-8') . '</b><br>Tutar: <b>'
                    . htmlspecialchars($total, ENT_QUOTES, 'UTF-8') . '</b><br>Tür: '
                    . htmlspecialchars($kind, ENT_QUOTES, 'UTF-8') . '</p>');
                send_mail((string) $user['email'], 'Ödemeniz alındı · ' . $oid, $userHtml, $kind . ' ' . $oid . ' ' . $total);
                if (function_exists('notify_user')) {
                    notify_user($uid, 'Ödeme alındı', $kind . ' · ' . $oid, page_url('uyelik-ders'));
                }
            }
        }
    } catch (Throwable $e) {
        mail_log('Ödeme bildirimi atlandı: ' . $e->getMessage());
    }
}
