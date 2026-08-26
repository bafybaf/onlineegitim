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

if ($action === 'start' && $u['role'] === 'ogretmen') {
    $gid = (int) post('group_id');
    $st = $pdo->prepare('SELECT * FROM class_groups WHERE id = ? AND teacher_id = ?');
    $st->execute([$gid, $u['id']]);
    $g = $st->fetch();
    if (!$g) {
        json_out(['ok' => false, 'error' => 'group']);
    }
    ensure_live_play_mode_schema();
    $mode = live_remember_play_mode(post('play_mode'));
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

if ($action === 'set_mode' && in_array($u['role'], ['ogretmen', 'admin'], true)) {
    $id = (int) post('id');
    $st = $pdo->prepare('SELECT * FROM live_rooms WHERE id = ?');
    $st->execute([$id]);
    $room = $st->fetch();
    if (!$room || !live_user_can_publish($u, $room)) {
        json_out(['ok' => false], 403);
    }
    ensure_live_play_mode_schema();
    $mode = live_remember_play_mode(post('play_mode'));
    try {
        $pdo->prepare('UPDATE live_rooms SET play_mode = ? WHERE id = ?')->execute([$mode, $id]);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => 'schema'], 500);
    }
    if (post('html')) {
        redirect(canli_url($id));
    }
    json_out(['ok' => true, 'play_mode' => $mode]);
}

if ($action === 'end') {
    $id = (int) post('id');
    $st = $pdo->prepare('SELECT * FROM live_rooms WHERE id = ?');
    $st->execute([$id]);
    $room = $st->fetch();
    if (!$room) {
        json_out(['ok' => false]);
    }
    if ($u['role'] !== 'admin' && (int) $room['teacher_id'] !== (int) $u['id']) {
        json_out(['ok' => false], 403);
    }
    $pdo->prepare("UPDATE live_rooms SET status='ended', ended_at=NOW(), broadcasting=0 WHERE id=?")->execute([$id]);
    if (post('goto')) {
        redirect(post('goto'));
    }
    json_out(['ok' => true]);
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
    $pdo->prepare('INSERT INTO attendance (room_id, student_id, present) VALUES (?,?,?) ON DUPLICATE KEY UPDATE present = VALUES(present)')
        ->execute([$id, (int) post('student_id'), post('present') === '1' ? 1 : 0]);
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
    $pdo->prepare('UPDATE attendance SET present = 1 WHERE room_id = ? AND student_id = ?')
        ->execute([$id, $u['id']]);
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
    $key = live_ensure_stream_key($pdo, $room);
    $chat = $pdo->prepare('SELECT who_label, body FROM live_chat WHERE room_id = ? ORDER BY id');
    $chat->execute([$id]);
    $lives = $pdo->query("SELECT r.id, r.title, u.name teacher FROM live_rooms r JOIN users u ON u.id=r.teacher_id WHERE r.status='live' ORDER BY r.id")->fetchAll();
    $payload = [
        'ok' => true,
        'room' => live_public_room($room),
        'chat' => $chat->fetchAll(),
        'lives' => $lives,
        'mins' => live_mins($room['started_at']),
        'hls_url' => live_hls_url($key),
        'hls_url_alt' => live_hls_url($key, 1),
        'whep_url' => live_whep_url($key),
        'whep_url_alt' => live_whep_url($key, 1),
        'health_url' => live_health_url(),
    ];
    if (live_user_can_publish($u, $room)) {
        $payload['rtmp_url'] = live_rtmp_url();
        $payload['stream_key'] = $key;
        $payload['whip_url'] = live_whip_url($key);
        $payload['whip_url_alt'] = live_whip_url($key, 1);
    }
    json_out($payload);
}

json_out(['ok' => false], 400);
