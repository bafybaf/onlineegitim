<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/lib/db.php';
require_once dirname(__DIR__, 2) . '/lib/install.php';

oi_install_ensure();
echo "Kurulum tamam: şema hazır";
if (oi_install_has_admin()) {
    echo ", yönetici hesabı var.\n";
} else {
    echo ". ADMIN_PASSWORD yok; ilk açılışta /kurulum kullanılacak.\n";
}
