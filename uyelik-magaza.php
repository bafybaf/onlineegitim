<?php
require_once __DIR__ . '/lib/bootstrap.php';
$u = current_user();
if ($u && is_shop_role($u['role'])) {
    redirect(panel_home($u['role']));
}
redirect('kayit-magaza.php');
