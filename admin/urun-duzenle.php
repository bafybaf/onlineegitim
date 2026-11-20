<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$panel = 'admin';
require __DIR__ . '/../includes/manage_book.php';
