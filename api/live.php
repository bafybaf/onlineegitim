<?php
require_once __DIR__ . '/../lib/bootstrap.php';
$u = current_user();
if (!$u) {
    json_out(['ok' => false], 401);
}
if (!in_array($u['role'], ['ogrenci', 'ogretmen', 'admin'], true)) {
    json_out(['ok' => false, 'error' => 'edu_account'], 403);
}
if (function_exists('live_merge_json_body')) {
    live_merge_json_body();
}
$action = post('action') ?: ($_GET['action'] ?? '');
$pdo = db();
ensure_live_attendance_schema();

if ($action === 'whip') {
    $id = (int) post('room_id');
    $st = $pdo->prepare('SELECT * FROM live_rooms WHERE id = ?');
    $st->execute([$id]);
    $room = $st->fetch();
    if (!$room || !live_user_can_publish($u, $room)) {
        json_out(['ok' => false, 'error' => 'publish'], 403);
    }
    $method = strtoupper(trim((string) post('method')));
    $target = trim((string) post('target'));
    $sdp = function_exists('live_request_sdp') ? live_request_sdp() : (string) ($_POST['sdp'] ?? '');
    $res = live_whip_proxy($method, $target, $sdp);
    if (empty($res['ok']) && $method !== 'DELETE' && function_exists('live_log_event')) {
        $code = (int) ($res['status'] ?? 0);
        live_log_event(
            $id,
            $code === 409 ? 'whip_409' : 'whip_fail',
            $code === 409 ? 'Hoca kamerası Cloudflare’a bağlanamadı (oturum meşgul).' : 'Hoca kamerası Cloudflare’a bağlanamadı.',
            'HTTP ' . $code . ($res['error'] ?? ''),
            (int) $u['id']
        );
    }
    json_out([
        'ok' => !empty($res['ok']),
        'status' => (int) ($res['status'] ?? 0),
        'sdp' => (string) ($res['sdp'] ?? ''),
        'location' => (string) ($res['location'] ?? ''),
        'error' => (string) ($res['error'] ?? ''),
    ]);
}

if ($action === 'log') {
    $id = (int) post('room_id');
    $st = $pdo->prepare('SELECT * FROM live_rooms WHERE id = ?');
    $st->execute([$id]);
    $room = $st->fetch();
    if (!$room || !live_user_can_access($u, $room)) {
        json_out(['ok' => false], 403);
    }
    $kind = preg_replace('/[^a-z0-9_]/', '', strtolower(post('kind'))) ?: 'info';
    live_log_event($id, $kind, post('message'), post('detail'), (int) $u['id']);
    json_out(['ok' => true]);
}

if ($action === 'start' && in_array($u['role'], ['ogretmen', 'admin'], true)) {
    $gid = (int) post('group_id');
    $st = $pdo->prepare('SELECT * FROM class_groups WHERE id = ?');
    $st->execute([$gid]);
    $g = $st->fetch();
    $isTest = $u['role'] === 'admin' && post('test') === '1';
    if ($g && $u['role'] === 'ogretmen' && !group_has_teacher($gid, (int) $u['id'])) {
        $g = false;
    }
    if (!$g) {
        json_out(['ok' => false, 'error' => 'group']);
    }
    ensure_live_play_mode_schema();
    $mode = live_remember_play_mode((string) post('play_mode'));
    $zoomUrl = '';
    $zoomPass = '';
    if ($mode === 'zoom' || $mode === 'zoom_link') {
        $zoomUrl = live_zoom_normalize((string) post('zoom_url'));
        $zoomPass = live_zoom_pass_ok((string) post('zoom_pass'));
        if ($mode === 'zoom' && $zoomUrl === '' && live_zoom_s2s_ready()) {
            $created = live_zoom_create_meeting(trim((string) ($g['name'] ?? '') . ' — ' . (post('topic') ?: 'Ders')));
            if ($created) {
                $zoomUrl = (string) $created['join_url'];
                $zoomPass = live_zoom_pass_ok((string) $created['password']);
            }
        }
        if ($zoomUrl === '') {
            $why = live_zoom_last_error();
            if ($why === '') {
                if ($mode === 'zoom_link') {
                    $why = 'Zoom bağlantısını yapıştırın.';
                } else {
                    $why = live_zoom_s2s_ready()
                        ? 'Zoom toplantısı açılamadı. Admin anahtarlarını kontrol edin.'
                        : 'Zoom için toplantı bağlantısı girin veya Admin’den Server-to-Server anahtarlarını kaydedin.';
                }
            }
            if (post('html')) {
                flash_error($why);
                redirect($u['role'] === 'admin' ? 'admin/canli.php' : 'ogretmen/canli.php');
            }
            json_out(['ok' => false, 'error' => 'zoom_url', 'message' => $why]);
        }
        $_SESSION['live_zoom_url'] = $zoomUrl;
        $_SESSION['live_zoom_pass'] = $zoomPass;
    }
    $recordOn = ($mode === 'zoom' || $mode === 'zoom_link') ? 0 : (post('record') ? 1 : 0);
    $allow = [];
    if ($isTest) {
        $raw = $_POST['student_ids'] ?? [];
        if (!is_array($raw)) {
            $raw = [$raw];
        }
        $chk = $pdo->prepare('SELECT student_id FROM enrollments WHERE group_id = ? AND student_id = ? LIMIT 1');
        foreach ($raw as $sid) {
            $sid = (int) $sid;
            if ($sid < 1) {
                continue;
            }
            $chk->execute([$gid, $sid]);
            if ($chk->fetch()) {
                $allow[] = $sid;
            }
        }
        $allow = array_values(array_unique($allow));
        $ex = $pdo->prepare("SELECT * FROM live_rooms WHERE group_id = ? AND teacher_id = ? AND status = 'live' AND access_mode = 'allow' LIMIT 1");
        $ex->execute([$gid, (int) $u['id']]);
        $room = $ex->fetch();
    } else {
        $ex = $pdo->prepare("SELECT * FROM live_rooms WHERE group_id = ? AND status = 'live' AND (access_mode IS NULL OR access_mode = '' OR access_mode = 'all') LIMIT 1");
        $ex->execute([$gid]);
        $room = $ex->fetch();
    }
    $topic = post('topic') ?: ($isTest ? 'Test yayını' : 'Ders');
    $title = $isTest ? ('Test · ' . (string) $g['name']) : (string) $g['name'];
    $allowJson = $isTest ? json_encode($allow) : null;
    if (!$room) {
        $key = live_new_stream_key($pdo);
        $inserted = false;
        try {
            $pdo->prepare('INSERT INTO live_rooms (teacher_id, group_id, title, topic, record, yoklama, stream_key, play_mode, zoom_url, zoom_pass, access_mode, allow_student_ids) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([(int) $u['id'], $gid, $title, $topic, $recordOn, post('yoklama') ? 1 : 0, $key, $mode, $zoomUrl !== '' ? $zoomUrl : null, $zoomPass !== '' ? $zoomPass : null, $isTest ? 'allow' : 'all', $allowJson]);
            $inserted = true;
        } catch (Throwable $e) {
            try {
                $pdo->prepare('INSERT INTO live_rooms (teacher_id, group_id, title, topic, record, yoklama, stream_key, play_mode, access_mode, allow_student_ids) VALUES (?,?,?,?,?,?,?,?,?,?)')
                    ->execute([(int) $u['id'], $gid, $title, $topic, $recordOn, post('yoklama') ? 1 : 0, $key, $mode, $isTest ? 'allow' : 'all', $allowJson]);
                $inserted = true;
            } catch (Throwable $e2) {
                try {
                    $pdo->prepare('INSERT INTO live_rooms (teacher_id, group_id, title, topic, record, yoklama, stream_key, play_mode) VALUES (?,?,?,?,?,?,?,?)')
                        ->execute([(int) $u['id'], $gid, $title, $topic, $recordOn, post('yoklama') ? 1 : 0, $key, $mode]);
                    $inserted = true;
                } catch (Throwable $e3) {
                    $pdo->prepare('INSERT INTO live_rooms (teacher_id, group_id, title, topic, record, yoklama, stream_key) VALUES (?,?,?,?,?,?,?)')
                        ->execute([(int) $u['id'], $gid, $title, $topic, $recordOn, post('yoklama') ? 1 : 0, $key]);
                    $inserted = true;
                }
            }
        }
        if (!$inserted) {
            json_out(['ok' => false, 'error' => 'room']);
        }
        $rid = (int) $pdo->lastInsertId();
        $hello = $isTest
            ? 'Test yayını açıldı. Yalnız seçilen öğrenciler girebilir.'
            : live_start_chat_message($mode);
        $pdo->prepare('INSERT INTO live_chat (room_id, user_id, who_label, body) VALUES (?,?,?,?)')
            ->execute([$rid, $u['id'], 'Sistem', $hello]);
        $att = $pdo->prepare('INSERT INTO attendance (room_id, student_id, present) VALUES (?,?,0)');
        if ($isTest) {
            foreach ($allow as $sid) {
                $att->execute([$rid, $sid]);
            }
        } else {
            $stu = $pdo->prepare('SELECT student_id FROM enrollments WHERE group_id = ?');
            $stu->execute([$gid]);
            foreach ($stu as $row) {
                $att->execute([$rid, $row['student_id']]);
            }
        }
        $room = ['id' => $rid];
        live_room_save_zoom($pdo, $rid, $mode, $zoomUrl, $zoomPass);
    } else {
        live_ensure_stream_key($pdo, $room);
        try {
            if ($isTest) {
                $pdo->prepare('UPDATE live_rooms SET play_mode = ?, topic = ?, allow_student_ids = ? WHERE id = ?')
                    ->execute([$mode, $topic, $allowJson, (int) $room['id']]);
                $att = $pdo->prepare('INSERT INTO attendance (room_id, student_id, present) VALUES (?,?,0) ON DUPLICATE KEY UPDATE student_id = student_id');
                foreach ($allow as $sid) {
                    $att->execute([(int) $room['id'], $sid]);
                }
            }
        } catch (Throwable $e) {
        }
        live_room_save_zoom($pdo, (int) $room['id'], $mode, $zoomUrl, $zoomPass);
    }
    if (post('html')) {
        redirect(canli_url((int) $room['id']));
    }
    json_out(['ok' => true, 'id' => (int) $room['id'], 'play_mode' => $mode]);
}

if ($action === 'zoom_join') {
    $id = (int) (post('id') ?: ($_GET['id'] ?? 0));
    $st = $pdo->prepare('SELECT * FROM live_rooms WHERE id = ?');
    $st->execute([$id]);
    $room = $st->fetch();
    if (!$room || !live_user_can_access($u, $room) || !live_room_zoom_embed($room)) {
        json_out(['ok' => false, 'error' => 'Zoom odası bulunamadı.'], 403);
    }
    if (!live_zoom_sdk_ready()) {
        json_out(['ok' => false, 'error' => 'Admin henüz Zoom Meeting SDK anahtarlarını girmedi.']);
    }
    $meet = live_zoom_meeting_id(live_room_zoom_url($room));
    if ($meet === '') {
        json_out(['ok' => false, 'error' => 'Toplantı numarası yok.']);
    }
    $observer = function_exists('live_user_is_observer') && live_user_is_observer($u, $room);
    $guest = post('guest') === '1';
    $host = !$guest && !$observer && live_user_can_publish($u, $room);
    $zak = '';
    $zoomEmail = '';
    if (live_zoom_s2s_ready()) {
        $zu = live_zoom_host_user();
        $zoomEmail = trim((string) ($zu['email'] ?? ''));
        $zak = live_zoom_zak();
        if ($host && $zak === '') {
            $host = false;
        }
    }
    $sig = live_zoom_sdk_signature($meet, $host ? 1 : 0);
    if ($sig === '') {
        json_out(['ok' => false, 'error' => 'Zoom imzası üretilemedi.']);
    }
    $email = $host ? $zoomEmail : trim((string) ($u['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = '';
    }
    json_out([
        'ok' => true,
        'signature' => $sig,
        'sdkKey' => live_zoom_setting('sdk_key'),
        'meetingNumber' => $meet,
        'password' => live_room_zoom_pass($room),
        'userName' => (string) ($u['name'] ?? 'Katılımcı'),
        'userEmail' => $email,
        'zak' => $zak,
        'host' => $host ? 1 : 0,
    ]);
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
    $isZoom = function_exists('live_room_is_zoom') && live_room_is_zoom($room);
    $saved = false;
    if (!$isZoom && function_exists('vod_commit_live_room')) {
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
        if ($isZoom) {
            flash_ok('Zoom dersi kapandı. Bulut kaydı Zoom hesabınızda oluşur; indirip Kayıt yükle’den siteye ekleyin.');
            redirect(post('goto'));
        }
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
    if (($u['role'] ?? '') === 'admin' && !live_user_can_publish($u, $room)) {
        json_out(['ok' => false, 'error' => 'observe'], 403);
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
    $screenOn = (int) ($room['screen_on'] ?? 0);
    if ($screenOn === 0) {
        try {
            $board = live_board_row($pdo, $id);
            $screenOn = (int) ($board['screen'] ?? 0);
        } catch (Throwable) {
        }
    }
    $payload = [
        'ok' => true,
        'room' => live_public_room($room),
        'chat' => array_reverse($chat->fetchAll()),
        'mins' => live_mins($room['started_at']),
        'screen' => $screenOn,
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

if ($action === 'screen') {
    $id = (int) (post('id') ?: post('room_id'));
    $st = $pdo->prepare('SELECT * FROM live_rooms WHERE id = ?');
    $st->execute([$id]);
    $room = $st->fetch();
    if (!$room || !live_user_can_publish($u, $room)) {
        json_out(['ok' => false], 403);
    }
    $on = !empty(post('on'));
    live_room_set_screen($pdo, $id, $on);
    json_out(['ok' => true, 'screen' => $on ? 1 : 0]);
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
