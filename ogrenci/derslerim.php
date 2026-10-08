<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogrenci');
$st = db()->prepare(
    "SELECT g.*, t.name teacher_name, e.progress, e.package_id, p.access_type
     FROM enrollments e
     JOIN class_groups g ON g.id=e.group_id
     JOIN users t ON t.id=g.teacher_id
     LEFT JOIN packages p ON p.id=e.package_id
     WHERE e.student_id=?"
);
$st->execute([(int) $u['id']]);
$mineGroups = group_apply_teacher_labels($st->fetchAll());
panel_head('ogrenci', 'dersler', 'Derslerim | Öğrenci Paneli', $u);
membership_panel_banner($u);
foreach ($mineGroups as $g) {
    $live = db()->prepare("SELECT * FROM live_rooms WHERE group_id=? AND status='live' ORDER BY id DESC");
    $live->execute([$g['id']]);
    $roomRows = $live->fetchAll();
    $r = null;
    foreach ($roomRows as $cand) {
        if (live_student_watch_reason($cand, (int) $u['id']) === null) {
            $r = $cand;
            break;
        }
    }
    if (!$r) {
        foreach ($roomRows as $cand) {
            if (!live_room_is_test($cand)) {
                $r = $cand;
                break;
            }
        }
    }
    $videoOnly = ($g['access_type'] ?? 'canli_video') === 'sadece_video';
    $reason = ($r && !$videoOnly) ? live_student_watch_reason($r, (int) $u['id']) : ($videoOnly ? live_video_only_message() : null);
    $open = $r && !$videoOnly && $reason === null;
    echo '<article class="card mb-4 p-5"><p class="text-xs font-extrabold uppercase tracking-[0.16em] text-navy">Grup</p>';
    echo '<h2 class="font-display mt-1 text-2xl">' . e($g['name']) . '</h2>';
    echo '<p class="mt-2 text-sm text-muted">' . e($g['teacher_name']) . ' · ' . e($g['days']) . '</p>';
    echo '<p class="mt-1 text-xs font-extrabold text-navy">' . ($videoOnly ? 'Yalnızca kayıt paketi' : 'Canlı + kayıt') . '</p>';
    if ($videoOnly) {
        echo '<p class="mt-2 text-sm text-muted">' . e(live_video_only_message()) . '</p>';
    } elseif ($r && !$open) {
        echo '<p class="mt-2 text-sm font-bold text-accent">' . e($reason) . '</p>';
        echo live_pill($r);
    } else {
        echo $r ? live_pill($r) : '<p class="mt-2 text-sm text-muted">Şu an kapalı</p>';
    }
    echo '<div class="mt-2 h-2 overflow-hidden rounded-full bg-soft"><div class="h-full bg-navy" style="width:' . (int) $g['progress'] . '%"></div></div>';
    echo '<div class="mt-4 flex flex-wrap gap-2">';
    if ($open) {
        echo '<a class="btn-primary text-sm" href="' . e(canli_url((int) $r['id'])) . '">Derse gir</a>';
        echo '<a class="btn-outline text-sm" href="' . e(url('ogrenci/kayitlar.php?grup=' . (int) $g['id'])) . '">Kayıtlar</a>';
    } elseif ($r || $videoOnly) {
        echo '<a class="btn-outline text-sm" href="' . e(live_watch_recordings_url((int) $g['id'])) . '">Kayıtlardan izle</a>';
    } else {
        echo '<a class="btn-outline text-sm" href="' . e(url('ogrenci/kayitlar.php?grup=' . (int) $g['id'])) . '">Kayıtlar</a>';
    }
    echo '<a class="btn-outline text-sm" href="' . e(url('ogrenci/notlar')) . '">Notlar</a>';
    if (!empty($g['whatsapp_url'])) {
        echo '<a class="btn-outline text-sm" href="' . e((string) $g['whatsapp_url']) . '" target="_blank" rel="noreferrer">WhatsApp grubu</a>';
    }
    echo '</div></article>';
}
panel_foot();
