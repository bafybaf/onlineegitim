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
    $to = trim(setting('smtp_to_email', 'info@onlineilahiyat.com.tr'));
    return $to !== '' ? $to : 'info@onlineilahiyat.com.tr';
}

function mail_last_error(?string $set = null): string
{
    static $last = '';
    if ($set !== null) {
        $last = $set;
    }
    return $last;
}

function mail_is_gmail_host(string $host): bool
{
    $h = strtolower($host);
    return str_contains($h, 'gmail.com') || str_contains($h, 'googlemail.com') || str_contains($h, 'google.com');
}

function smtp_ready(): bool
{
    return setting_bool('smtp_enabled')
        && setting('smtp_host') !== ''
        && setting('smtp_user') !== ''
        && setting('smtp_pass') !== ''
        && setting('smtp_from_email') !== ''
        && class_exists(PHPMailer::class);
}

function send_mail(string $to, string $subject, string $htmlBody, string $textBody = '', ?string $replyTo = null): bool
{
    mail_last_error('');
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        mail_last_error('Alıcı e-posta geçersiz.');
        mail_log('Geçersiz alıcı, gönderilmedi: ' . $subject);
        return false;
    }
    if (!setting_bool('smtp_enabled')) {
        mail_last_error('SMTP kapalı. Önce kaydedin ve kutuyu işaretleyin.');
        mail_log('SMTP kapalı, gönderilmedi: ' . $subject);
        return false;
    }
    $host = trim(setting('smtp_host'));
    $from = trim(setting('smtp_from_email'));
    $user = trim(setting('smtp_user'));
    $pass = setting('smtp_pass');
    if ($host === '' || $from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        mail_last_error('Sunucu veya gönderen e-posta eksik.');
        mail_log('SMTP host/from eksik, gönderilmedi: ' . $subject);
        return false;
    }
    if ($user === '' || $pass === '') {
        mail_last_error('Kullanıcı adı ve şifre gerekli. Gmail için hesap şifresi değil, Uygulama şifresi yazın.');
        mail_log('SMTP kullanıcı/şifre eksik, gönderilmedi: ' . $subject);
        return false;
    }
    if (!class_exists(PHPMailer::class)) {
        mail_last_error('PHPMailer yüklü değil.');
        mail_log('PHPMailer yüklü değil, gönderilmedi: ' . $subject);
        return false;
    }
    $gmail = mail_is_gmail_host($host);
    if ($gmail) {
        $pass = preg_replace('/\s+/', '', $pass) ?? $pass;
        $host = 'smtp.gmail.com';
        if (!filter_var($user, FILTER_VALIDATE_EMAIL)) {
            mail_last_error('Gmail kullanıcı adı tam e-posta olmalı (ornek@gmail.com).');
            return false;
        }
        if (strcasecmp($from, $user) !== 0) {
            $from = $user;
        }
    }
    $port = (int) setting('smtp_port', '587') ?: 587;
    $enc = setting('smtp_encryption', 'tls');
    if ($gmail) {
        if ($port === 465) {
            $enc = 'ssl';
        } else {
            $port = 587;
            $enc = 'tls';
        }
    }
    $fromName = setting('smtp_from_name', defined('APP_NAME') ? APP_NAME : 'Online İlahiyat');
    $alt = $textBody !== '' ? $textBody : trim(html_entity_decode(strip_tags($htmlBody), ENT_QUOTES, 'UTF-8'));
    $cfg = compact('host', 'port', 'enc', 'user', 'pass', 'from', 'fromName', 'to', 'replyTo', 'subject', 'htmlBody', 'alt');
    try {
        mail_phpmailer_send($cfg, false);
        mail_last_error('');
        mail_log('Gönderildi: ' . $subject . ' → ' . $to);
        return true;
    } catch (Throwable $first) {
        $msg = $first->getMessage();
        $sslFail = str_contains(strtolower($msg), 'ssl') || str_contains(strtolower($msg), 'certificate') || str_contains($msg, 'stream_socket_enable_crypto');
        if ($sslFail) {
            try {
                mail_phpmailer_send($cfg, true);
                mail_last_error('');
                mail_log('Gönderildi (SSL gevşek): ' . $subject . ' → ' . $to);
                return true;
            } catch (Throwable $e) {
                $first = $e;
            }
        }
        $raw = $first->getMessage();
        mail_last_error(mail_explain_error($raw, $gmail));
        mail_log('Gönderim hatası: ' . $raw);
        return false;
    }
}

function mail_phpmailer_send(array $cfg, bool $relaxSsl): void
{
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->Timeout = 20;
    $lang = dirname(__DIR__) . '/vendor/phpmailer/phpmailer/language/';
    if (is_file($lang . 'phpmailer.lang-tr.php')) {
        $mail->setLanguage('tr', $lang);
    }
    $mail->isSMTP();
    $mail->Host = (string) $cfg['host'];
    $mail->Port = (int) $cfg['port'];
    $mail->SMTPAuth = true;
    $mail->Username = (string) $cfg['user'];
    $mail->Password = (string) $cfg['pass'];
    $mail->AuthType = 'LOGIN';
    $enc = (string) $cfg['enc'];
    if ($enc === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($enc === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mail->SMTPSecure = '';
        $mail->SMTPAutoTLS = false;
    }
    if ($relaxSsl) {
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
    }
    $mail->setFrom((string) $cfg['from'], (string) $cfg['fromName']);
    $mail->addAddress((string) $cfg['to']);
    $replyTo = (string) ($cfg['replyTo'] ?? '');
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $mail->addReplyTo($replyTo);
    }
    $mail->Subject = (string) $cfg['subject'];
    $mail->isHTML(true);
    $mail->Body = (string) $cfg['htmlBody'];
    $mail->AltBody = (string) $cfg['alt'];
    $mail->send();
}

function mail_explain_error(string $raw, bool $gmail): string
{
    $low = strtolower($raw);
    if (str_contains($low, 'authenticate') || str_contains($low, 'username and password') || str_contains($low, '5.7.8') || str_contains($low, '5.7.9') || str_contains($low, '535')) {
        return 'Gmail giriş reddetti. Hesap şifresi değil, Google Uygulama şifresi kullanın (2 adımlı doğrulama açık olmalı). ' . $raw;
    }
    if (str_contains($low, 'connect') || str_contains($low, 'timed out') || str_contains($low, 'connection refused') || str_contains($low, '10060') || str_contains($low, '10061')) {
        return 'Sunucu smtp.gmail.com adresine bağlanamadı (port 587/465 kapalı olabilir). Hostinger e-postası varsa smtp.hostinger.com kullanın. ' . $raw;
    }
    if ($gmail && (str_contains($low, 'from') || str_contains($low, 'sender') || str_contains($low, '5.7.60'))) {
        return 'Gönderen e-posta, Gmail kullanıcı adıyla aynı olmalı. ' . $raw;
    }
    return $raw;
}

function mail_wrap(string $title, string $innerHtml): string
{
    return '<div style="font-family:Nunito,Arial,sans-serif;max-width:560px;margin:0 auto;color:#1a1a1a">'
        . '<p style="font-weight:800;font-size:20px;color:#111111;margin:0 0 12px">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>'
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
