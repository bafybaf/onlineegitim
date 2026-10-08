<?php
require_once __DIR__ . '/../lib/bootstrap.php';
$u = current_user();
if (!$u) {
    json_out(['ok' => false], 401);
}
if (!in_array($u['role'], ['ogrenci', 'ogretmen', 'admin'], true)) {
    json_out(['ok' => false, 'error' => 'edu_account'], 403);
}
$action = post('action') ?: ($_GET['action'] ?? '');
$pdo = db();
ensure_live_attendance_schema();

if ($action === 'start' && $u['role'] === 'ogretmen') {
    $gid = (int) post('group_id');
    $st = $pdo->prepare('SELECT * FROM class_groups WHERE id = ?');
    $st->execute([$gid]);
    $g = $st->fetch();
    if ($g && !group_has_teacher($gid, (int) $u['id'])) {
        $g = false;
    }
    if (!$g) {
        json_out(['ok' => false, 'error' => 'group']);
    }
    ensure_live_play_mode_schema();
    $mode = live_remember_play_mode('browser');
    $ex = $pdo->prepare("SELECT * FROM live_rooms WHERE group_id = ? AND status = 'live'");
    $ex->execute([$gid]);
    $room = $ex->fetch();
    if (!$room) {
        $key = live_new_stream_key($pdo);
        try {
            $pdo->prepare('INSERT INTO live_rooms (teacher_id, group_id, title, topic, record, yoklama, stream_key, play_mode) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$u['id'], $gid, $g['name'], post('topic') ?: 'Ders', post('record') ? 1 : 0, post('yoklama') ? 1 : 0, $key, $mode]);
        } catch (Throwable $e) {
            $pdo->prepare('INSERT INTO live_rooms (teacher_id, group_id, title, topic, record, yoklama, stream_key) VALUES (?,?,?,?,?,?,?)')
                ->execute([$u['id'], $gid, $g['name'], post('topic') ?: 'Ders', post('record') ? 1 : 0, post('yoklama') ? 1 : 0, $key]);
        }
        $rid = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO live_chat (room_id, user_id, who_label, body) VALUES (?,?,?,?)')
            ->execute([$rid, $u['id'], 'Sistem', live_start_chat_message($mode)]);
        $stu = $pdo->prepare('SELECT student_id FROM enrollments WHERE group_id = ?');
        $stu->execute([$gid]);
        $att = $pdo->prepare('INSERT INTO attendance (room_id, student_id, present) VALUES (?,?,0)');
        foreach ($stu as $row) {
            $att->execute([$rid, $row['student_id']]);
        }
        $room = ['id' => $rid];
    } else {
        live_ensure_stream_key($pdo, $room);
        try {
            $pdo->prepare('UPDATE live_rooms SET play_mode = ? WHERE id = ?')->execute([$mode, (int) $room['id']]);
        } catch (Throwable $e) {
        }
    }
    if (post('html')) {
        redirect(canli_url((int) $room['id']));
    }
    json_out(['ok' => true, 'id' => (int) $room['id'], 'play_mode' => $mode]);
}

if ($action === 'end') {
    $id = (int) post('id');
    $st = $pdo->prepare('SELECT * FROM live_rooms WHERE id = ?');
    $st->execute([$id]);
    $room = $st->fetch();
    if (!$room) {
        json_out(['ok' => false]);
    }
    if ($u['role'] !== 'admin' && (int) $room['teacher_id'] !== (int) $u['id'] && !group_has_teacher((int) $room['group_id'], (int) $u['id'])) {
        json_out(['ok' => false], 403);
    }
    @set_time_limit(180);
    $pdo->prepare("UPDATE live_rooms SET status='ended', ended_at=NOW(), broadcasting=0 WHERE id=?")->execute([$id]);
    try {
        $pdo->prepare('UPDATE live_rooms SET paused = 0, pause_ends_at = NULL WHERE id = ?')->execute([$id]);
    } catch (Throwable $e) {
    }
    $saved = false;
    if (function_exists('vod_commit_live_room')) {
        try {
            $saved = vod_commit_live_room($pdo, $room, (int) post('mins'), (int) post('sec'));
            if (!$saved) {
                usleep(800000);
                $saved = vod_commit_live_room($pdo, $room, (int) post('mins'), (int) post('sec'));
            }
        } catch (Throwable $e) {
            $saved = false;
        }
    }
    if (post('goto')) {
        $chunks = (int) post('rec_chunks');
        if ($saved) {
            flash_ok('Ders kaydı kaydedildi. Aşağıdan izleyebilirsiniz.');
        } elseif ($chunks < 1) {
            flash_error('Ders kapandı ama video gelmedi. Odada “Kayıt”a basın, 3 saniye geri sayım bitsin, düğme kırmızı “● Kayıt” olsun; sonra Bitir’de “Kaydediliyor” yazısı geçene kadar bekleyin.');
        } else {
            flash_error('Ders kapandı. Kayıt parçaları gitti ama video birleşmedi. Bu sayfayı bir kez yenileyin. Yine yoksa hostingde storage/vod yazılabilir olmalı.');
        }
        redirect(post('goto'));
    }
    json_out(['ok' => true, 'saved' => $saved]);
}

if ($action === 'pause') {
    $id = (int) post('id');
    $st = $pdo->prepare('SELECT * FROM live_rooms WHERE id = ?');
    $st->execute([$id]);
    $room = $st->fetch();
    if (!$room || !live_user_can_publish($u, $room)) {
        json_out(['ok' => false], 403);
    }
    if (($room['status'] ?? '') !== 'live') {
        json_out(['ok' => false, 'error' => 'ended']);
    }
    ensure_live_pause_schema();
    $pdo->prepare('UPDATE live_rooms SET paused = 1, pause_ends_at = NULL WHERE id = ?')->execute([$id]);
    $pdo->prepare('INSERT INTO live_chat (room_id, user_id, who_label, body) VALUES (?,?,?,?)')
        ->execute([$id, $u['id'], 'Sistem', 'Mola başladı.']);
    $st->execute([$id]);
    $room = $st->fetch() ?: $room;
    json_out(['ok' => true, 'room' => live_public_room($room)]);
}

if ($action === 'resume') {
    $id = (int) post('id');
    $st = $pdo->prepare('SELECT * FROM live_rooms WHERE id = ?');
    $st->execute([$id]);
    $room = $st->fetch();
    if (!$room || !live_user_can_publish($u, $room)) {
        json_out(['ok' => false], 403);
    }
    ensure_live_pause_schema();
    $pdo->prepare('UPDATE live_rooms SET paused = 0, pause_ends_at = NULL WHERE id = ?')->execute([$id]);
    $pdo->prepare('INSERT INTO live_chat (room_id, user_id, who_label, body) VALUES (?,?,?,?)')
        ->execute([$id, $u['id'], 'Sistem', 'Ders devam ediyor.']);
    $st->execute([$id]);
    $room = $st->fetch() ?: $room;
    json_out(['ok' => true, 'room' => live_public_room($room)]);
}

if ($action === 'chat') {
    $id = (int) post('room_id');
    $body = post('body');
    if ($body === '') {
        json_out(['ok' => false]);
    }
    $st = $pdo->prepare('SELECT * FROM live_rooms WHERE id = ?');
    $st->execute([$id]);
    $room = $st->fetch();
    if (!$room || !live_user_can_access($u, $room)) {
        json_out(['ok' => false], 403);
    }
    $label = $u['role'] === 'ogretmen' ? 'Hoca' : ($u['role'] === 'admin' ? 'Yönetici' : $u['name']);
    $pdo->prepare('INSERT INTO live_chat (room_id, user_id, who_label, body) VALUES (?,?,?,?)')
        ->execute([$id, $u['id'], $label, $body]);
    json_out(['ok' => true]);
}

if ($action === 'attend' && in_array($u['role'], ['ogretmen', 'admin'], true)) {
    $id = (int) post('room_id');
    $st = $pdo->prepare('SELECT * FROM live_rooms WHERE id = ?');
    $st->execute([$id]);
    $room = $st->fetch();
    if (!$room || !live_user_can_publish($u, $room)) {
        json_out(['ok' => false], 403);
    }
    $sid = (int) post('student_id');
    if (post('present') === '1') {
        if (!live_student_try_enter($room, $sid)) {
            json_out(['ok' => false, 'error' => 'full', 'message' => live_full_watch_message()], 403);
        }
        json_out(['ok' => true]);
    }
    live_mark_student_absent($id, $sid);
    json_out(['ok' => true]);
}

if ($action === 'join' && $u['role'] === 'ogrenci') {
    $id = (int) post('room_id');
    $st = $pdo->prepare('SELECT * FROM live_rooms WHERE id = ?');
    $st->execute([$id]);
    $room = $st->fetch();
    if (!$room || !live_user_can_access($u, $room)) {
        json_out(['ok' => false], 403);
    }
    $reason = live_student_watch_reason($room, (int) $u['id']);
    if ($reason !== null) {
        json_out(['ok' => false, 'error' => 'full', 'message' => $reason], 403);
    }
    if (!live_student_try_enter($room, (int) $u['id'])) {
        json_out(['ok' => false, 'error' => 'full', 'message' => live_full_watch_message()], 403);
    }
    json_out(['ok' => true]);
}

if ($action === 'poll') {
    $id = (int) ($_GET['id'] ?? post('id'));
    $st = $pdo->prepare('SELECT * FROM live_rooms WHERE id = ?');
    $st->execute([$id]);
    $room = $st->fetch();
    if (!$room) {
        json_out(['ok' => false], 404);
    }
    if (!live_user_can_access($u, $room)) {
        json_out(['ok' => false], 403);
    }
    if (function_exists('session_write_close')) {
        @session_write_close();
    }
    $key = live_ensure_stream_key($pdo, $room);
    $chat = $pdo->prepare('SELECT id, who_label, body FROM live_chat WHERE room_id = ? ORDER BY id DESC LIMIT 50');
    $chat->execute([$id]);
    $payload = [
        'ok' => true,
        'room' => live_public_room($room),
        'chat' => array_reverse($chat->fetchAll()),
        'mins' => live_mins($room['started_at']),
        'hls_url' => live_hls_url($key),
        'hls_url_alt' => live_hls_url($key, 1),
        'whep_url' => live_whep_url($key),
        'whep_url_alt' => live_whep_url($key, 1),
        'health_url' => live_health_url(),
    ];
    if (in_array($u['role'], ['ogretmen', 'admin'], true)) {
        $payload['present'] = live_present_students($id);
        $payload['present_n'] = count($payload['present']);
    }
    if (live_user_can_publish($u, $room)) {
        $payload['stream_key'] = $key;
        $payload['whip_url'] = live_whip_url($key);
        $payload['whip_url_alt'] = live_whip_url($key, 1);
    }
    json_out($payload);
}

if ($action === 'board') {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $body = [];
    if ($method !== 'GET' && post('op') === '') {
        $parsed = json_decode((string) file_get_contents('php://input'), true);
        $body = is_array($parsed) ? $parsed : [];
    }
    $id = (int) (post('id') ?: ($_GET['id'] ?? ($body['id'] ?? 0)));
    $st = $pdo->prepare('SELECT * FROM live_rooms WHERE id = ?');
    $st->execute([$id]);
    $room = $st->fetch();
    if (!$room) {
        json_out(['ok' => false], 404);
    }
    if (!live_user_can_access($u, $room)) {
        json_out(['ok' => false], 403);
    }
    if (function_exists('session_write_close')) {
        @session_write_close();
    }
    if ($method === 'GET') {
        $row = live_board_row($pdo, $id);
        $since = (int) ($_GET['since'] ?? -1);
        if ($since >= 0 && (int) $row['rev'] <= $since) {
            json_out(['ok' => true, 'rev' => (int) $row['rev'], 'same' => true]);
        }
        json_out(array_merge(['ok' => true], live_board_public($row, $id)));
    }
    if (!live_user_can_publish($u, $room)) {
        json_out(['ok' => false], 403);
    }
    $op = post('op') !== '' ? post('op') : (string) ($body['op'] ?? '');
    $row = live_board_row($pdo, $id);
    $strokes = json_decode((string) $row['strokes'], true);
    if (!is_array($strokes)) {
        $strokes = [];
    }
    $page = max(1, (int) $row['page']);
    $pageKey = (string) $page;
    if (!isset($strokes[$pageKey]) || !is_array($strokes[$pageKey])) {
        $strokes[$pageKey] = [];
    }

    if ($op === 'pdf') {
        try {
            $path = academy_store_upload('file', 'live-board', ['application/pdf' => 'pdf'], 40);
        } catch (Throwable $e) {
            json_out(['ok' => false, 'error' => 'pdf'], 400);
        }
        if (!$path) {
            json_out(['ok' => false, 'error' => 'pdf'], 400);
        }
        $old = trim((string) $row['pdf_path']);
        if ($old !== '' && function_exists('academy_unlink_stored')) {
            academy_unlink_stored($old);
        }
        $saved = live_board_save($pdo, $id, [
            'pdf_path' => $path,
            'page' => 1,
            'pages' => 0,
            'zoom' => 1,
            'pan_x' => 0,
            'pan_y' => 0,
            'strokes' => '{}',
        ]);
        json_out(array_merge(['ok' => true], live_board_public($saved, $id)));
    }

    if ($op === 'pdf_off') {
        $old = trim((string) $row['pdf_path']);
        if ($old !== '' && function_exists('academy_unlink_stored')) {
            academy_unlink_stored($old);
        }
        $saved = live_board_save($pdo, $id, [
            'pdf_path' => '',
            'page' => 1,
            'pages' => 0,
            'zoom' => 1,
            'pan_x' => 0,
            'pan_y' => 0,
            'strokes' => '{}',
        ]);
        json_out(array_merge(['ok' => true], live_board_public($saved, $id)));
    }

    if ($op === 'stroke') {
        $stroke = live_board_parse_stroke($body['stroke'] ?? null);
        if (!$stroke) {
            json_out(['ok' => false], 400);
        }
        if (count($strokes[$pageKey]) >= 800) {
            array_shift($strokes[$pageKey]);
        }
        $strokes[$pageKey][] = $stroke;
        $saved = live_board_save($pdo, $id, ['strokes' => json_encode($strokes, JSON_UNESCAPED_UNICODE)]);
        json_out(array_merge(['ok' => true], live_board_public($saved, $id)));
    }

    if ($op === 'undo') {
        array_pop($strokes[$pageKey]);
        $saved = live_board_save($pdo, $id, ['strokes' => json_encode($strokes, JSON_UNESCAPED_UNICODE)]);
        json_out(array_merge(['ok' => true], live_board_public($saved, $id)));
    }

    if ($op === 'clear') {
        $strokes[$pageKey] = [];
        $saved = live_board_save($pdo, $id, ['strokes' => json_encode($strokes, JSON_UNESCAPED_UNICODE)]);
        json_out(array_merge(['ok' => true], live_board_public($saved, $id)));
    }

    if ($op === 'page') {
        $next = max(1, (int) ($body['page'] ?? $page));
        $pages = max(0, (int) ($body['pages'] ?? $row['pages']));
        if ($pages > 0) {
            $next = min($next, $pages);
        } else {
            $next = 1;
        }
        $saved = live_board_save($pdo, $id, ['page' => $next, 'pages' => $pages]);
        json_out(array_merge(['ok' => true], live_board_public($saved, $id)));
    }

    if ($op === 'view') {
        $saved = live_board_save($pdo, $id, [
            'zoom' => $body['zoom'] ?? 1,
            'pan_x' => $body['panX'] ?? 0,
            'pan_y' => $body['panY'] ?? 0,
        ]);
        json_out(['ok' => true, 'rev' => (int) ($saved['rev'] ?? 0)]);
    }

    if ($op === 'screen') {
        $saved = live_board_save($pdo, $id, [
            'screen' => !empty($body['on']) ? 1 : 0,
        ]);
        json_out(array_merge(['ok' => true], live_board_public($saved, $id)));
    }

    if ($op === 'layout') {
        $saved = live_board_save($pdo, $id, [
            'board_full' => !empty($body['full']) ? 1 : 0,
            'cam_x' => $body['camX'] ?? 0.72,
            'cam_y' => $body['camY'] ?? 0.04,
        ]);
        json_out(array_merge(['ok' => true], live_board_public($saved, $id)));
    }

    json_out(['ok' => false], 400);
}

if ($action === 'record_chunk') {
    $id = (int) post('id');
    $st = $pdo->prepare('SELECT * FROM live_rooms WHERE id = ?');
    $st->execute([$id]);
    $room = $st->fetch();
    if (!$room || !live_user_can_publish($u, $room)) {
        json_out(['ok' => false], 403);
    }
    if (empty($_FILES['chunk']['tmp_name']) || !is_uploaded_file($_FILES['chunk']['tmp_name'])) {
        json_out([
            'ok' => false,
            'error' => 'nofile',
            'len' => (int) ($_SERVER['CONTENT_LENGTH'] ?? 0),
        ], 400);
    }
    $seq = (int) post('seq');
    $dir = academy_storage('vod');
    if (!is_dir($dir) || !is_writable($dir)) {
        json_out(['ok' => false, 'error' => 'storage'], 500);
    }
    if (function_exists('session_write_close')) {
        @session_write_close();
    }
    $abs = $dir . '/live-' . $id . '.webm';
    $resume = post('resume') === '1';
    $existing = function_exists('vod_file_bytes') ? vod_file_bytes($abs) : (is_file($abs) ? (int) filesize($abs) : 0);
    if ($seq === 0 && $existing > 0 && !$resume && $existing < 65536) {
        @unlink($abs);
        $existing = 0;
    }
    $src = fopen($_FILES['chunk']['tmp_name'], 'rb');
    $dst = fopen($abs, 'ab');
    if ($src === false || $dst === false) {
        if ($src) {
            fclose($src);
        }
        if ($dst) {
            fclose($dst);
        }
        json_out(['ok' => false, 'error' => 'write'], 500);
    }
    $wrote = stream_copy_to_stream($src, $dst);
    fclose($src);
    fclose($dst);
    if ($wrote === false) {
        json_out(['ok' => false, 'error' => 'write'], 500);
    }
    @set_time_limit(120);
    $sizeOut = function_exists('vod_file_bytes') ? vod_file_bytes($abs) : (int) @filesize($abs);
    json_out(['ok' => true, 'seq' => $seq, 'bytes' => (int) $wrote, 'size' => (int) $sizeOut]);
}

if ($action === 'record_done') {
    $id = (int) post('id');
    $st = $pdo->prepare('SELECT r.*, t.name teacher_name FROM live_rooms r JOIN users t ON t.id = r.teacher_id WHERE r.id = ?');
    $st->execute([$id]);
    $room = $st->fetch();
    if (!$room || !live_user_can_publish($u, $room)) {
        json_out(['ok' => false], 403);
    }
    @set_time_limit(180);
    if (function_exists('session_write_close')) {
        @session_write_close();
    }
    $ok = vod_commit_live_room($pdo, $room, (int) post('mins'), (int) post('sec'));
    json_out(['ok' => true, 'saved' => $ok]);
}

json_out(['ok' => false], 400);
