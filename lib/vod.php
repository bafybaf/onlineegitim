<?php
declare(strict_types=1);

function vod_secret(): string
{
    $pass = defined('DB_PASS') ? (string) DB_PASS : '';
    $name = defined('DB_NAME') ? (string) DB_NAME : 'oi';
    return hash('sha256', $pass . '|' . $name . '|vod-play');
}

function vod_play_token(int $recId, int $userId): string
{
    $slot = intdiv(time(), 3600);
    $sig = hash_hmac('sha256', $recId . '|' . $userId . '|' . $slot, vod_secret());
    return $slot . '.' . substr($sig, 0, 32);
}

function vod_play_token_ok(string $token, int $recId, int $userId): bool
{
    if (!preg_match('/^(\d{6,12})\.([a-f0-9]{32})$/', $token, $m)) {
        return false;
    }
    $slot = (int) $m[1];
    $now = intdiv(time(), 3600);
    if ($slot < $now - 3 || $slot > $now + 1) {
        return false;
    }
    $sig = substr(hash_hmac('sha256', $recId . '|' . $userId . '|' . $slot, vod_secret()), 0, 32);
    return hash_equals($sig, $m[2]);
}

function vod_play_url(int $recId, int $userId): string
{
    return url('api/dosya.php?tur=video&id=' . $recId . '&t=' . rawurlencode(vod_play_token($recId, $userId)));
}

function vod_is_remote_path(string $rel): bool
{
    return str_starts_with($rel, 'cf/');
}

function vod_is_live_capture_path(string $rel): bool
{
    $rel = str_replace('\\', '/', trim($rel));
    return (bool) preg_match('#(?:^|/)vod/(?:oda-\d+-|live-\d+\.webm$)#i', $rel);
}

function vod_is_manual_upload_path(string $rel): bool
{
    $rel = str_replace('\\', '/', trim($rel));
    if ($rel === '' || vod_is_remote_path($rel) || vod_is_live_capture_path($rel)) {
        return false;
    }
    return (bool) preg_match('#(?:^|/)vod/[^/]+\.(mp4|webm|mov)$#i', $rel);
}

function vod_cf_iframe_url(string $url): string
{
    if (!preg_match('#^(https://customer-[a-z0-9]+\.cloudflarestream\.com/[A-Za-z0-9_-]+)#i', $url, $m)) {
        return '';
    }
    return $m[1] . '/iframe';
}

function vod_remote_player_html(string $src): string
{
    $iframe = vod_cf_iframe_url($src);
    if ($iframe === '') {
        return '<p class="p-5"><a class="btn-primary" href="' . e($src) . '" target="_blank" rel="noreferrer">Videoyu aç</a></p>';
    }
    return '<iframe src="' . e($iframe) . '" class="block w-full bg-black" style="aspect-ratio:16/9;min-height:240px;border:0" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>';
}

function vod_player_src(array $rec, int $userId): array
{
    $js = '';
    $ext = '';
    $url = trim((string) ($rec['video_url'] ?? ''));
    if ($url !== '' && vod_cf_iframe_url($url) !== '') {
        return ['', $url];
    }
    $rel = trim((string) ($rec['video_path'] ?? ''));
    if ($rel !== '' && !vod_is_remote_path($rel)) {
        $js = vod_play_url((int) $rec['id'], $userId);
        $abs = vod_abs_from_rec($rec);
        if ($abs !== '') {
            $js .= '&v=' . (int) @filemtime($abs);
        }
    } elseif ($url !== '') {
        $ext = $url;
    }
    return [$js, $ext];
}

function vod_is_direct_navigation(): bool
{
    $dest = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? ''));
    if (in_array($dest, ['document', 'iframe', 'embed', 'object', 'frame'], true)) {
        return true;
    }
    $mode = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_MODE'] ?? ''));
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    if ($dest === '' && ($mode === 'navigate' || str_starts_with($accept, 'text/html'))) {
        return true;
    }
    return false;
}

function vod_ffmpeg_bin(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = '';
    $cands = [
        '/usr/bin/ffmpeg',
        '/usr/local/bin/ffmpeg',
        'C:\\ffmpeg\\bin\\ffmpeg.exe',
        'C:\\xampp\\ffmpeg\\bin\\ffmpeg.exe',
        'C:\\xampp\\ffmpeg\\ffmpeg.exe',
    ];
    foreach ($cands as $c) {
        if (is_file($c)) {
            $cached = $c;
            return $cached;
        }
    }
    $cmd = PHP_OS_FAMILY === 'Windows' ? 'where ffmpeg 2>NUL' : 'command -v ffmpeg 2>/dev/null';
    $out = [];
    $code = 1;
    @exec($cmd, $out, $code);
    $found = trim((string) ($out[0] ?? ''));
    if ($code === 0 && $found !== '' && is_file($found)) {
        $cached = $found;
    }
    return $cached;
}

function vod_marker(string $abs): string
{
    return $abs . '.ok';
}

function vod_ensure_playable(string $abs, float $hintMs = 0): bool
{
    if (!is_file($abs) || !is_readable($abs)) {
        return false;
    }
    $ext = strtolower((string) pathinfo($abs, PATHINFO_EXTENSION));
    if ($ext !== 'webm') {
        return true;
    }
    if (is_file(vod_marker($abs))) {
        return true;
    }
    @set_time_limit(180);
    $lockPath = $abs . '.lock';
    $lock = @fopen($lockPath, 'c');
    if ($lock === false) {
        return false;
    }
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        return is_file($abs);
    }
    $ok = false;
    try {
        $ok = vod_stamp_duration($abs, $hintMs);
        if ($ok) {
            @file_put_contents(vod_marker($abs), '1');
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
        @unlink($lockPath);
    }
    return $ok || is_file($abs);
}

function vod_webm_duration_known(string $abs): bool
{
    if (vod_ffprobe_ms($abs) > 1000) {
        return true;
    }
    $fp = @fopen($abs, 'rb');
    if ($fp === false) {
        return false;
    }
    $buf = (string) fread($fp, 65536);
    fclose($fp);
    return strpos($buf, "\x44\x89") !== false;
}

function vod_stamp_duration(string $abs, float $hintMs = 0): bool
{
    $remuxed = vod_remux_webm($abs);
    $ms = vod_probe_ms($abs, $hintMs);
    $patched = vod_webm_patch_duration($abs, $ms > 1000 ? $ms : $hintMs, true);
    return $remuxed || $patched;
}

function vod_ffprobe_bin(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = '';
    $ff = vod_ffmpeg_bin();
    if ($ff !== '') {
        $p = (string) preg_replace('/ffmpeg(\.exe)?$/i', 'ffprobe$1', $ff);
        if ($p !== '' && is_file($p)) {
            $cached = $p;
            return $cached;
        }
    }
    $cands = [
        '/usr/bin/ffprobe',
        '/usr/local/bin/ffprobe',
        'C:\\ffmpeg\\bin\\ffprobe.exe',
        'C:\\xampp\\ffmpeg\\bin\\ffprobe.exe',
        'C:\\xampp\\ffmpeg\\ffprobe.exe',
    ];
    foreach ($cands as $c) {
        if (is_file($c)) {
            $cached = $c;
            return $cached;
        }
    }
    $cmd = PHP_OS_FAMILY === 'Windows' ? 'where ffprobe 2>NUL' : 'command -v ffprobe 2>/dev/null';
    $out = [];
    $code = 1;
    @exec($cmd, $out, $code);
    $found = trim((string) ($out[0] ?? ''));
    if ($code === 0 && $found !== '' && is_file($found)) {
        $cached = $found;
    }
    return $cached;
}

function vod_ffprobe_ms(string $abs): float
{
    $probe = vod_ffprobe_bin();
    if ($probe === '' || !is_file($abs)) {
        return 0.0;
    }
    $cmd = escapeshellarg($probe)
        . ' -v error -analyzeduration 200M -probesize 50M -show_entries format=duration -of default=nk=1:nw=1 '
        . escapeshellarg($abs);
    $out = [];
    $code = 1;
    @exec($cmd . (PHP_OS_FAMILY === 'Windows' ? ' 2>NUL' : ' 2>/dev/null'), $out, $code);
    $raw = strtolower(trim((string) ($out[0] ?? '')));
    if ($raw === '' || $raw === 'n/a' || $raw === 'nan') {
        return 0.0;
    }
    $sec = (float) str_replace(',', '.', $raw);
    if ($code === 0 && $sec > 1 && $sec < 86400) {
        return $sec * 1000.0;
    }
    return 0.0;
}

function vod_probe_ms(string $abs, float $hintMs = 0): float
{
    $ff = vod_ffprobe_ms($abs);
    if ($ff > 1000) {
        return $ff;
    }
    $cluster = vod_webm_last_timecode_ms($abs);
    if ($cluster > 800 && ($hintMs < 2000 || $cluster > $hintMs * 0.25)) {
        return $cluster;
    }
    return $hintMs > 1000 ? $hintMs : $cluster;
}

function vod_length_label(array $rec): string
{
    $sec = (int) ($rec['duration_sec'] ?? 0);
    if ($sec < 1) {
        $sec = max(0, (int) ($rec['mins'] ?? 0)) * 60;
    }
    if ($sec < 1) {
        return '1 dk';
    }
    if ($sec < 60) {
        return $sec . ' sn';
    }
    $m = intdiv($sec, 60);
    $s = $sec % 60;
    return $s > 0 ? ($m . ' dk ' . $s . ' sn') : ($m . ' dk');
}

function ensure_recordings_duration_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec('ALTER TABLE recordings ADD COLUMN duration_sec INT UNSIGNED NULL');
    } catch (Throwable) {
    }
    ensure_recordings_test_schema();
    ensure_recordings_lesson_cat_schema();
}

function ensure_recordings_test_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec('ALTER TABLE recordings ADD COLUMN is_test TINYINT(1) NOT NULL DEFAULT 0');
    } catch (Throwable) {
    }
    try {
        db()->exec("UPDATE recordings SET is_test = 1 WHERE is_test = 0 AND title LIKE 'Test ·%'");
    } catch (Throwable) {
    }
    try {
        db()->exec(
            "UPDATE recordings rec
             JOIN live_rooms r ON rec.video_path LIKE CONCAT('vod/oda-', r.id, '-%')
             SET rec.is_test = 1
             WHERE rec.is_test = 0 AND r.access_mode = 'allow'"
        );
    } catch (Throwable) {
    }
    ensure_recordings_lesson_cat_schema();
}

function ensure_recordings_lesson_cat_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec("ALTER TABLE recordings ADD COLUMN lesson_cat VARCHAR(160) NULL");
    } catch (Throwable) {
    }
    try {
        db()->exec("ALTER TABLE live_rooms ADD COLUMN lesson_cat VARCHAR(160) NULL");
    } catch (Throwable) {
    }
    try {
        $rows = db()->query(
            "SELECT rec.id, rec.title, rec.lesson_cat, g.name gname, p.slug program_slug
             FROM recordings rec
             JOIN class_groups g ON g.id = rec.group_id
             LEFT JOIN programs p ON p.id = g.program_id
             WHERE rec.lesson_cat IS NULL OR rec.lesson_cat = ''"
        )->fetchAll();
        if ($rows) {
            $up = db()->prepare('UPDATE recordings SET lesson_cat = ? WHERE id = ?');
            foreach ($rows as $r) {
                $fam = recording_program_family((string) ($r['program_slug'] ?? ''), (string) ($r['gname'] ?? ''));
                if ($fam === '') {
                    continue;
                }
                $hit = recording_match_subject((string) ($r['title'] ?? ''), $fam)
                    ?? recording_match_subject((string) ($r['gname'] ?? ''), $fam);
                if ($hit !== null) {
                    $up->execute([$hit, (int) $r['id']]);
                }
            }
        }
    } catch (Throwable) {
    }
}

function recording_program_family(string $slug, string $gname = ''): string
{
    $s = recording_fold_tr($slug);
    $g = recording_fold_tr($gname);
    if (str_contains($s, 'dkab') || $s === 'tefsir' || str_contains($g, 'dkab') || str_contains($g, 'oabt')) {
        return 'dkab';
    }
    if (str_contains($s, 'dhbt') || $s === 'hadis' || str_contains($g, 'dhbt')) {
        return 'dhbt';
    }
    return '';
}

function recording_family_for_group_id(int $gid): string
{
    if ($gid < 1) {
        return '';
    }
    try {
        $st = db()->prepare(
            'SELECT g.name, p.slug AS program_slug
             FROM class_groups g
             LEFT JOIN programs p ON p.id = g.program_id
             WHERE g.id = ? LIMIT 1'
        );
        $st->execute([$gid]);
        $row = $st->fetch();
        if (!$row) {
            return '';
        }
        return recording_program_family((string) ($row['program_slug'] ?? ''), (string) ($row['name'] ?? ''));
    } catch (Throwable) {
        return '';
    }
}

/** @return list<string> */
function recording_subject_catalog(string $family): array
{
    if ($family === 'dhbt') {
        return [
            'Kur’ân Bilgisi - Tefsir ve Tecvid Soru Çözümü',
            'İslam İnanç Esasları - Kelam Soru Çözümü',
            'Siyer - İslam Tarihi - Kültür ve Medeniyet Soru Çözümü',
            'İslam İbadet Esasları - Fıkıh Soru Çözümü',
            'Hadis Usûlü ve Tarihi Soru Çözümü',
            'Mezhepler Tarihi Soru Çözümü',
            'Dinler Tarihi Soru Çözümü',
        ];
    }
    if ($family === 'dkab') {
        return [
            'Kur’ân Bilgisi - Tefsir - Tecvid',
            'İslam İnanç Esasları - Kelam',
            'Hadis Usûlü ve Tarihi',
            'İslam Mezhepleri Tarihi',
            'İslam İbadet Esasları - Fıkıh',
            'Dinler Tarihi',
            'Siyer ve İslam Tarihi - İslam Kültür ve Medeniyeti',
            'İslam Felsefesi',
            'Din Felsefesi',
            'Din Psikolojisi',
            'Din Sosyolojisi',
            'Din Eğitimi',
        ];
    }
    return recording_lesson_catalog();
}

function recording_normalize_subject(string $raw, string $family): string
{
    $raw = trim($raw);
    if ($raw === '' || $family === '') {
        return '';
    }
    $hit = recording_match_subject($raw, $family);
    return $hit ?? '';
}

function recording_match_subject(string $text, string $family): ?string
{
    $fold = recording_fold_tr($text);
    if ($fold === '') {
        return null;
    }
    $cats = recording_subject_catalog($family);
    $ranked = $cats;
    usort($ranked, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
    foreach ($ranked as $name) {
        $n = recording_fold_tr($name);
        if ($n !== '' && (str_contains($fold, $n) || str_contains($n, $fold))) {
            return $name;
        }
    }
    foreach (recording_subject_keywords($family) as $needle => $label) {
        if ($needle !== '' && str_contains($fold, $needle)) {
            return $label;
        }
    }
    return null;
}

/** @return array<string,string> */
function recording_subject_keywords(string $family): array
{
    if ($family === 'dhbt') {
        return [
            'kuran bilgisi' => 'Kur’ân Bilgisi - Tefsir ve Tecvid Soru Çözümü',
            'tefsir ve tecvid' => 'Kur’ân Bilgisi - Tefsir ve Tecvid Soru Çözümü',
            'inanc esas' => 'İslam İnanç Esasları - Kelam Soru Çözümü',
            'kultur ve medeniyet' => 'Siyer - İslam Tarihi - Kültür ve Medeniyet Soru Çözümü',
            'ibadet esas' => 'İslam İbadet Esasları - Fıkıh Soru Çözümü',
            'hadis usul' => 'Hadis Usûlü ve Tarihi Soru Çözümü',
            'mezhepler tarihi' => 'Mezhepler Tarihi Soru Çözümü',
            'dinler tarihi' => 'Dinler Tarihi Soru Çözümü',
            'tecvid' => 'Kur’ân Bilgisi - Tefsir ve Tecvid Soru Çözümü',
            'tefsir' => 'Kur’ân Bilgisi - Tefsir ve Tecvid Soru Çözümü',
            'kuran' => 'Kur’ân Bilgisi - Tefsir ve Tecvid Soru Çözümü',
            'kelam' => 'İslam İnanç Esasları - Kelam Soru Çözümü',
            'akaid' => 'İslam İnanç Esasları - Kelam Soru Çözümü',
            'siyer' => 'Siyer - İslam Tarihi - Kültür ve Medeniyet Soru Çözümü',
            'medeniyet' => 'Siyer - İslam Tarihi - Kültür ve Medeniyet Soru Çözümü',
            'fikih' => 'İslam İbadet Esasları - Fıkıh Soru Çözümü',
            'hadis' => 'Hadis Usûlü ve Tarihi Soru Çözümü',
            'mezhep' => 'Mezhepler Tarihi Soru Çözümü',
            'dinler' => 'Dinler Tarihi Soru Çözümü',
        ];
    }
    if ($family === 'dkab') {
        return [
            'kuran bilgisi' => 'Kur’ân Bilgisi - Tefsir - Tecvid',
            'inanc esas' => 'İslam İnanç Esasları - Kelam',
            'hadis usul' => 'Hadis Usûlü ve Tarihi',
            'mezhepleri tarihi' => 'İslam Mezhepleri Tarihi',
            'ibadet esas' => 'İslam İbadet Esasları - Fıkıh',
            'dinler tarihi' => 'Dinler Tarihi',
            'kultur ve medeniyet' => 'Siyer ve İslam Tarihi - İslam Kültür ve Medeniyeti',
            'islam felsefe' => 'İslam Felsefesi',
            'din felsefe' => 'Din Felsefesi',
            'din psikoloji' => 'Din Psikolojisi',
            'din sosyoloji' => 'Din Sosyolojisi',
            'din egitim' => 'Din Eğitimi',
            'tecvid' => 'Kur’ân Bilgisi - Tefsir - Tecvid',
            'tefsir' => 'Kur’ân Bilgisi - Tefsir - Tecvid',
            'kuran' => 'Kur’ân Bilgisi - Tefsir - Tecvid',
            'kelam' => 'İslam İnanç Esasları - Kelam',
            'akaid' => 'İslam İnanç Esasları - Kelam',
            'hadis' => 'Hadis Usûlü ve Tarihi',
            'mezhep' => 'İslam Mezhepleri Tarihi',
            'fikih' => 'İslam İbadet Esasları - Fıkıh',
            'dinler' => 'Dinler Tarihi',
            'siyer' => 'Siyer ve İslam Tarihi - İslam Kültür ve Medeniyeti',
            'medeniyet' => 'Siyer ve İslam Tarihi - İslam Kültür ve Medeniyeti',
            'psikoloji' => 'Din Psikolojisi',
            'sosyoloji' => 'Din Sosyolojisi',
        ];
    }
    return [];
}

function recording_apply_lesson_cat(PDO $pdo, int $id, string $cat): void
{
    if ($id < 1) {
        return;
    }
    $cat = trim($cat);
    ensure_recordings_lesson_cat_schema();
    try {
        if ($cat === '') {
            $pdo->prepare('UPDATE recordings SET lesson_cat = NULL WHERE id = ?')->execute([$id]);
            return;
        }
        $pdo->prepare('UPDATE recordings SET lesson_cat = ? WHERE id = ?')->execute([$cat, $id]);
    } catch (Throwable) {
    }
}

function recording_cat_from_room(array $room): string
{
    $cat = trim((string) ($room['lesson_cat'] ?? ''));
    $gid = (int) ($room['group_id'] ?? 0);
    $fam = $gid > 0 ? recording_family_for_group_id($gid) : '';
    if ($cat !== '' && $fam !== '') {
        $ok = recording_normalize_subject($cat, $fam);
        if ($ok !== '') {
            return $ok;
        }
    }
    if ($fam === '') {
        return '';
    }
    return recording_normalize_subject((string) ($room['topic'] ?? ''), $fam);
}

/** @param list<array<string,mixed>> $groups */
function recording_subject_picker_html(array $groups, string $keep = ''): void
{
    $map = [];
    foreach ($groups as $g) {
        $map[(string) (int) ($g['id'] ?? 0)] = recording_program_family(
            (string) ($g['program_slug'] ?? ''),
            (string) ($g['name'] ?? '')
        );
    }
    $lists = [
        'dkab' => recording_subject_catalog('dkab'),
        'dhbt' => recording_subject_catalog('dhbt'),
    ];
    ?>
  <label class="mt-3 text-sm font-bold md:col-span-2" data-cat-wrap hidden>
    Kategori
    <select name="lesson_cat" data-cat-select data-keep="<?= e($keep) ?>" class="mt-1 w-full rounded-xl border px-3 py-2">
      <option value="">Kategori seçin</option>
    </select>
    <span class="mt-1 block text-xs font-normal text-muted">DHBT ve DKAB gruplarında zorunlu. Ders kaydı bu başlığın altında listelenir.</span>
  </label>
  <script>
  window.OI_REC_CATS = <?= json_encode(['map' => $map, 'lists' => $lists], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  (function () {
    var data = window.OI_REC_CATS || {};
    document.querySelectorAll("[data-cat-wrap]").forEach(function (wrap) {
      var catSel = wrap.querySelector("[data-cat-select]");
      var form = wrap.closest("form");
      var groupSel = form && form.querySelector("[data-cat-group]");
      if (!groupSel || !catSel) return;
      if (wrap.getAttribute("data-cat-bound") === "1") return;
      wrap.setAttribute("data-cat-bound", "1");
      var keep = catSel.getAttribute("data-keep") || "";
      function fill() {
        var gid = String(groupSel.value || "");
        var fam = (data.map && data.map[gid]) || "";
        var list = (data.lists && data.lists[fam]) || [];
        catSel.innerHTML = "";
        if (!fam || !list.length) {
          wrap.hidden = true;
          catSel.required = false;
          return;
        }
        wrap.hidden = false;
        var optional = !!(form && form.querySelector('input[name="test"]'));
        catSel.required = !optional;
        var blank = document.createElement("option");
        blank.value = "";
        blank.textContent = "Kategori seçin";
        catSel.appendChild(blank);
        list.forEach(function (name) {
          var o = document.createElement("option");
          o.value = name;
          o.textContent = name;
          if (keep && keep === name) o.selected = true;
          catSel.appendChild(o);
        });
      }
      groupSel.addEventListener("change", fill);
      fill();
    });
  })();
  </script>
    <?php
}

function recording_is_test(array $rec): bool
{
    if ((int) ($rec['is_test'] ?? 0) === 1) {
        return true;
    }
    $title = (string) ($rec['title'] ?? '');
    return str_starts_with($title, 'Test ·');
}

/** Groups stay together; oldest first, newest lesson at the bottom. */
function recordings_by_group(array $rows): array
{
    $buckets = [];
    $names = [];
    $latest = [];
    foreach ($rows as $r) {
        $gid = (int) ($r['group_id'] ?? 0);
        if (!isset($buckets[$gid])) {
            $buckets[$gid] = [];
            $names[$gid] = (string) ($r['gname'] ?? '');
            $latest[$gid] = ['d' => '', 'id' => 0];
        }
        $buckets[$gid][] = $r;
        $d = (string) ($r['recorded_on'] ?? '');
        $id = (int) ($r['id'] ?? 0);
        if ($d > $latest[$gid]['d'] || ($d === $latest[$gid]['d'] && $id > $latest[$gid]['id'])) {
            $latest[$gid] = ['d' => $d, 'id' => $id];
        }
    }
    foreach ($buckets as &$list) {
        usort($list, static function (array $a, array $b): int {
            $ad = (string) ($a['recorded_on'] ?? '');
            $bd = (string) ($b['recorded_on'] ?? '');
            if ($ad !== $bd) {
                return $ad <=> $bd;
            }
            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });
    }
    unset($list);
    $order = array_keys($buckets);
    usort($order, static function (int $a, int $b) use ($latest): int {
        if ($latest[$a]['d'] !== $latest[$b]['d']) {
            return $latest[$a]['d'] <=> $latest[$b]['d'];
        }
        return $latest[$a]['id'] <=> $latest[$b]['id'];
    });
    $flat = [];
    $outNames = [];
    foreach ($order as $gid) {
        $outNames[$gid] = $names[$gid];
        foreach ($buckets[$gid] as $r) {
            $flat[] = $r;
        }
    }
    return [$flat, $outNames];
}

/** ÖABT DKAB / ilahiyat ana ders başlıkları; uzun olanlar önce. */
function recording_lesson_catalog(): array
{
    return [
        'Kur\'an-ı Kerim ve Tecvid',
        'İslam Tarihi, Kültür ve Medeniyeti',
        'İslam Mezhepleri ve Akımları',
        'Akaid ve Kelam',
        'Akaid & Kelam',
        'İslam Mezhepleri',
        'İslam Tarihi',
        'Din Sosyolojisi',
        'Din Psikolojisi',
        'Din Felsefesi',
        'İslam Felsefesi',
        'Dinler Tarihi',
        'Kur\'an-ı Kerim',
        'Din Eğitimi',
        'Fıkıh Usulü',
        'Tecvid',
        'Tefsir',
        'Hadis',
        'Fıkıh',
        'Kelam',
        'Akaid',
        'Siyer',
        'Nahiv',
        'Sarf',
        'Kıraat',
        'Arapça',
        'Belagat',
    ];
}

function recording_fold_tr(string $s): string
{
    $s = function_exists('mb_strtolower') ? mb_strtolower(trim($s), 'UTF-8') : strtolower(trim($s));
    return strtr($s, [
        'ı' => 'i', 'İ' => 'i', 'ş' => 's', 'Ş' => 's', 'ğ' => 'g', 'Ğ' => 'g',
        'ü' => 'u', 'Ü' => 'u', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c',
        'â' => 'a', 'û' => 'u', 'î' => 'i',
    ]);
}

function recording_part_is_meta(string $part, string $gname = '', string $tname = ''): bool
{
    $p = trim($part);
    if ($p === '') {
        return true;
    }
    $fold = recording_fold_tr($p);
    if ($gname !== '' && $fold === recording_fold_tr($gname)) {
        return true;
    }
    if ($tname !== '') {
        $tf = recording_fold_tr($tname);
        if ($tf !== '' && ($fold === $tf || str_ends_with($fold, ' ' . $tf) || str_starts_with($fold, $tf . ' '))) {
            return true;
        }
    }
    if (preg_match('/^\d{1,2}[.\-\/]\d{1,2}[.\-\/]\d{2,4}(?:\s+\d{1,2}[:.]\d{2})?$/u', $p)) {
        return true;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/u', $p)) {
        return true;
    }
    return false;
}

function recording_match_catalog(string $text): ?string
{
    $fold = recording_fold_tr($text);
    if ($fold === '') {
        return null;
    }
    foreach (recording_lesson_catalog() as $name) {
        $n = recording_fold_tr($name);
        if ($n !== '' && str_contains($fold, $n)) {
            return $name === 'Akaid & Kelam' ? 'Akaid ve Kelam' : $name;
        }
    }
    return null;
}

/** @return array{heading:string,sub:string} */
function recording_lesson_heading(array $rec): array
{
    $raw = trim((string) ($rec['title'] ?? ''));
    $test = (bool) preg_match('/^Test\s*·\s*/u', $raw);
    $raw = trim((string) preg_replace('/^Test\s*·\s*/u', '', $raw));
    $gname = trim((string) ($rec['gname'] ?? ''));
    $tname = trim((string) ($rec['tname'] ?? ''));
    $family = recording_program_family((string) ($rec['program_slug'] ?? ''), $gname);
    $parts = preg_split('/\s+[—–|-]+\s+/u', $raw) ?: [$raw];
    $core = [];
    foreach ($parts as $p) {
        $p = trim((string) $p);
        if ($p === '' || recording_part_is_meta($p, $gname, $tname)) {
            continue;
        }
        $core[] = $p;
    }
    $joined = $core !== [] ? implode(' — ', $core) : $raw;
    $saved = trim((string) ($rec['lesson_cat'] ?? ''));
    $heading = null;
    if ($saved !== '') {
        $heading = $family !== '' ? (recording_normalize_subject($saved, $family) ?: $saved) : $saved;
    }
    if ($heading === null && $family !== '') {
        $heading = recording_match_subject($joined, $family)
            ?? recording_match_subject($raw, $family)
            ?? recording_match_subject($gname, $family);
    }
    if ($heading === null) {
        $heading = recording_match_catalog($joined) ?? recording_match_catalog($raw);
    }
    if ($heading === null) {
        $heading = $core[0] ?? ($raw !== '' ? $raw : 'Ders');
        $heading = preg_replace('/^(?:unite|ünite|ders)\s*\d+\s*[-–:.)]?\s*/iu', '', $heading) ?: $heading;
        $heading = trim($heading);
        if ($heading === '') {
            $heading = 'Ders';
        }
    }
    $sub = $joined;
    $hf = recording_fold_tr($heading);
    if ($hf !== '' && str_starts_with(recording_fold_tr($sub), $hf)) {
        $sub = trim((string) preg_replace('/^' . preg_quote($heading, '/') . '\s*[-–:.)]?\s*/iu', '', $sub));
    }
    $sub = preg_replace('/^(?:unite|ünite|ders)?\s*\d+\s*(?:\.?\s*ders)?\s*[-–:.)]?\s*/iu', '', $sub) ?? $sub;
    $sub = trim((string) $sub);
    if ($tname !== '') {
        $sub = trim((string) preg_replace('/\s*[—–|-]\s*' . preg_quote($tname, '/') . '\s*$/iu', '', $sub));
    }
    if ($sub === '' || recording_fold_tr($sub) === $hf) {
        $sub = trim((string) ($rec['recorded_on'] ?? ''));
    }
    if ($test) {
        $sub = $sub !== '' ? 'Test · ' . $sub : 'Test';
    }
    return ['heading' => $heading, 'sub' => $sub];
}

function recording_edit_title(array $rec): string
{
    $raw = trim((string) ($rec['title'] ?? ''));
    if (!function_exists('recording_lesson_heading')) {
        return $raw;
    }
    $info = recording_lesson_heading($rec);
    $sub = trim((string) ($info['sub'] ?? ''));
    if ($sub !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/u', $sub)) {
        return $sub;
    }
    return $raw !== '' ? $raw : $sub;
}

function recording_card_meta(array $r): string
{
    $bits = [];
    $on = trim((string) ($r['recorded_on'] ?? ''));
    if ($on !== '') {
        $bits[] = $on;
    }
    $len = function_exists('vod_length_label') ? vod_length_label($r) : (((int) ($r['mins'] ?? 0)) . ' dk');
    $len = trim((string) $len);
    if ($len !== '') {
        $bits[] = $len;
    }
    return implode(' · ', $bits);
}

/**
 * Group → lesson accordion. Newest lesson last; newest session at the bottom of its lesson.
 *
 * @return array{0: list<array{id:int,name:string,lessons:list<array{heading:string,rows:list<array>}>}>, 1: array<int,string>}
 */
function recordings_lesson_tree(array $rows): array
{
    [$flat, $groupNames] = recordings_by_group($rows);
    $byGroup = [];
    foreach ($flat as $r) {
        $byGroup[(int) ($r['group_id'] ?? 0)][] = $r;
    }
    $groups = [];
    foreach ($groupNames as $gid => $gname) {
        $list = $byGroup[(int) $gid] ?? [];
        $family = recording_program_family(
            (string) (($list[0]['program_slug'] ?? '')),
            $gname
        );
        $lessons = [];
        $latest = [];
        foreach ($list as $r) {
            $info = recording_lesson_heading($r);
            $key = recording_fold_tr($info['heading']);
            if ($key === '') {
                $key = 'ders';
            }
            if (!isset($lessons[$key])) {
                $lessons[$key] = ['heading' => $info['heading'], 'rows' => []];
                $latest[$key] = ['d' => '', 'id' => 0];
            }
            $r['_lesson_sub'] = $info['sub'];
            $r['_family'] = $family;
            $r['_heading'] = $info['heading'];
            $lessons[$key]['rows'][] = $r;
            $d = (string) ($r['recorded_on'] ?? '');
            $id = (int) ($r['id'] ?? 0);
            if ($d > $latest[$key]['d'] || ($d === $latest[$key]['d'] && $id > $latest[$key]['id'])) {
                $latest[$key] = ['d' => $d, 'id' => $id];
            }
        }
        $order = array_keys($lessons);
        $rank = [];
        foreach (recording_subject_catalog($family) as $i => $name) {
            $rank[recording_fold_tr($name)] = $i;
        }
        usort($order, static function (string $a, string $b) use ($latest, $rank): int {
            $ra = $rank[$a] ?? 1000;
            $rb = $rank[$b] ?? 1000;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }
            if ($latest[$a]['d'] !== $latest[$b]['d']) {
                return $latest[$a]['d'] <=> $latest[$b]['d'];
            }
            return $latest[$a]['id'] <=> $latest[$b]['id'];
        });
        $openKey = '';
        $maxD = '';
        $maxId = 0;
        foreach ($latest as $k => $info) {
            if ($info['d'] > $maxD || ($info['d'] === $maxD && $info['id'] > $maxId)) {
                $openKey = $k;
                $maxD = $info['d'];
                $maxId = $info['id'];
            }
        }
        $outLessons = [];
        foreach ($order as $key) {
            $row = $lessons[$key];
            $row['key'] = $key;
            $outLessons[] = $row;
        }
        $groups[] = [
            'id' => (int) $gid,
            'name' => $gname,
            'family' => $family,
            'open_key' => $openKey,
            'lessons' => $outLessons,
        ];
    }
    return [$groups, $groupNames];
}

/** DHBT/DKAB katalog başlıklarını boş olsa da gösterir; eşleşmeyen eski başlıklar sonda kalır. */
function recordings_pad_catalog_tree(array $tree): array
{
    foreach ($tree as &$g) {
        $fam = (string) ($g['family'] ?? '');
        if ($fam === '') {
            continue;
        }
        $byKey = [];
        foreach ($g['lessons'] as $lesson) {
            $byKey[(string) ($lesson['key'] ?? '')] = $lesson;
        }
        $out = [];
        foreach (recording_subject_catalog($fam) as $name) {
            $key = recording_fold_tr($name);
            if (isset($byKey[$key])) {
                $out[] = $byKey[$key];
                unset($byKey[$key]);
            } else {
                $out[] = ['heading' => $name, 'key' => $key, 'rows' => []];
            }
        }
        foreach ($byKey as $lesson) {
            if (($lesson['key'] ?? '') === '') {
                continue;
            }
            $out[] = $lesson;
        }
        $g['lessons'] = $out;
    }
    unset($g);
    return $tree;
}

function recordings_move_select_html(string $family, string $current, int $recId): string
{
    if ($family === '' || $recId < 1) {
        return '';
    }
    $cats = recording_subject_catalog($family);
    if ($cats === []) {
        return '';
    }
    $cur = recording_fold_tr($current);
    $opts = '';
    $matched = false;
    foreach ($cats as $name) {
        $sel = recording_fold_tr($name) === $cur ? ' selected' : '';
        if ($sel !== '') {
            $matched = true;
        }
        $opts .= '<option value="' . e($name) . '"' . $sel . '>' . e($name) . '</option>';
    }
    $html = '<label class="vod-move"><span>Taşı</span><select data-vod-move-sel>';
    if (!$matched) {
        $html .= '<option value="" disabled selected>Kategori seçin</option>';
    }
    $html .= $opts . '</select></label>';
    return $html;
}

function recordings_render_lesson_tree(array $tree, callable $card, array $opt = []): void
{
    $splitGroups = !empty($opt['split_groups']);
    $movable = !empty($opt['movable']);
    $first = true;
    foreach ($tree as $g) {
        $fam = (string) ($g['family'] ?? '');
        $canMove = $movable && $fam !== '';
        if ($splitGroups) {
            echo '<div class="mb-3 ' . ($first ? 'mt-0' : 'mt-8') . ' flex flex-wrap items-end justify-between gap-2">';
            echo '<div><p class="text-xs font-extrabold uppercase tracking-[0.16em] text-navy">Grup</p>';
            echo '<h2 class="font-display text-2xl">' . e((string) $g['name']) . '</h2></div>';
            if (!empty($opt['group_extra']) && is_callable($opt['group_extra'])) {
                echo $opt['group_extra']($g);
            }
            echo '</div>';
        }
        $first = false;
        if ($canMove) {
            echo '<div data-vod-move data-vod-group="' . (int) ($g['id'] ?? 0) . '">';
        }
        foreach ($g['lessons'] as $lesson) {
            $n = count($lesson['rows']);
            $heading = (string) ($lesson['heading'] ?? '');
            $catalogCat = $fam !== '' ? recording_normalize_subject($heading, $fam) : '';
            $open = (($lesson['key'] ?? '') !== '' && ($lesson['key'] ?? '') === ($g['open_key'] ?? '')) ? ' open' : '';
            echo '<details class="vod-lesson"' . $open;
            if ($canMove && $catalogCat !== '') {
                echo ' data-vod-cat="' . e($catalogCat) . '"';
            }
            echo '>';
            echo '<summary><span class="vod-lesson-title">' . e($heading) . '</span>';
            echo '<span class="vod-lesson-count">' . $n . ' kayıt</span></summary>';
            echo '<div class="vod-lesson-body">';
            if ($canMove) {
                echo '<p class="vod-drop-hint"' . ($n > 0 ? ' hidden' : '') . '>Kayıt yok. Tutamacından sürükleyip buraya bırakın.</p>';
            }
            foreach ($lesson['rows'] as $r) {
                if ($canMove) {
                    echo '<div class="vod-rec" data-vod-rec="' . (int) ($r['id'] ?? 0) . '" data-rec-on="' . e((string) ($r['recorded_on'] ?? '')) . '">';
                    echo '<span class="vod-rec-handle sort-handle" title="Sürükleyerek taşı" aria-label="Sürükleyerek taşı">⋮⋮</span>';
                    echo '<div class="vod-rec-main">';
                }
                $card($r);
                if ($canMove) {
                    echo recordings_move_select_html($fam, (string) ($r['_heading'] ?? $heading), (int) ($r['id'] ?? 0));
                    echo '</div></div>';
                }
            }
            echo '</div></details>';
        }
        if ($canMove) {
            echo '</div>';
        }
    }
}

function vod_abs_from_rec(array $rec): string
{
    $rel = trim((string) ($rec['video_path'] ?? ''));
    if ($rel === '') {
        return '';
    }
    if (function_exists('academy_file_readable')) {
        $p = academy_file_readable($rel);
        if ($p) {
            return $p;
        }
    }
    if (function_exists('academy_abs_file')) {
        $p = academy_abs_file($rel);
        if (is_file($p)) {
            return $p;
        }
    }
    return '';
}

function vod_hint_ms(array $rec): float
{
    $sec = (int) ($rec['duration_sec'] ?? 0);
    if ($sec > 1) {
        return $sec * 1000.0;
    }
    return max(0, (int) ($rec['mins'] ?? 0)) * 60 * 1000.0;
}

function vod_sync_recording_length(array $rec): array
{
    $id = (int) ($rec['id'] ?? 0);
    $abs = vod_abs_from_rec($rec);
    if ($id < 1 || $abs === '') {
        return $rec;
    }
    $ms = vod_probe_ms($abs, vod_hint_ms($rec));
    $sec = $ms > 400 ? (int) max(1, (int) round($ms / 1000)) : 0;
    if ($sec < 1) {
        return $rec;
    }
    $mins = max(1, min(480, (int) round($sec / 60)));
    if ((int) ($rec['duration_sec'] ?? 0) === $sec && (int) ($rec['mins'] ?? 0) === $mins) {
        return $rec;
    }
    ensure_recordings_duration_schema();
    try {
        db()->prepare('UPDATE recordings SET duration_sec = ?, mins = ? WHERE id = ?')->execute([$sec, $mins, $id]);
    } catch (Throwable) {
        try {
            db()->prepare('UPDATE recordings SET mins = ? WHERE id = ?')->execute([$mins, $id]);
        } catch (Throwable) {
        }
    }
    $rec['duration_sec'] = $sec;
    $rec['mins'] = $mins;
    return $rec;
}

function vod_prepare_recording(array $rec): array
{
    return vod_sync_recording_length($rec);
}

function vod_last_committed_id(): int
{
    return (int) ($GLOBALS['_vod_last_commit_id'] ?? 0);
}

function vod_after_response(callable $fn): void
{
    ignore_user_abort(true);
    @set_time_limit(320);
    if (!headers_sent()) {
        header('Connection: close');
    }
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    @flush();
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    }
    try {
        $fn();
    } catch (Throwable $e) {
    }
}

function vod_redirect_then(string $path, callable $after): void
{
    ignore_user_abort(true);
    @set_time_limit(320);
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        $to = $path;
    } else {
        $base = rtrim(BASE_URL, '/');
        $to = ($base !== '' && (str_starts_with($path, $base . '/') || $path === $base || $path === $base . '/'))
            ? $path
            : url($path);
    }
    if (!headers_sent()) {
        header('Location: ' . $to);
        header('Content-Length: 0');
        header('Connection: close');
    }
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    @flush();
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    }
    try {
        $after();
    } catch (Throwable $e) {
    }
    exit;
}

function vod_file_bytes(string $path): int
{
    if (!is_file($path)) {
        return 0;
    }
    clearstatcache(true, $path);
    $sz = @filesize($path);
    if ($sz === false) {
        return PHP_INT_MAX;
    }
    return (int) $sz;
}

function vod_file_ready(string $path, int $min = 200): bool
{
    return is_file($path) && vod_file_bytes($path) >= $min;
}

function vod_clip_title(string $s, int $n = 160): string
{
    $s = trim($s);
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $n);
    }
    return substr($s, 0, $n);
}

function vod_commit_live_room(PDO $pdo, array $room, int $mins = 0, int $sec = 0): bool
{
    $id = (int) ($room['id'] ?? 0);
    if ($id < 1) {
        return false;
    }
    $lockPath = academy_storage('vod') . '/live-' . $id . '.commit.lock';
    $lock = @fopen($lockPath, 'c');
    if ($lock === false) {
        return false;
    }
    if (!flock($lock, LOCK_EX)) {
        fclose($lock);
        return false;
    }
    try {
        return vod_commit_live_room_locked($pdo, $room, $mins, $id, $sec);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
        @unlink($lockPath);
    }
}

function vod_commit_live_room_locked(PDO $pdo, array $room, int $mins, int $id, int $sec = 0): bool
{
    $GLOBALS['_vod_last_commit_id'] = 0;
    @set_time_limit(180);
    $like = 'vod/oda-' . $id . '-%';
    $liveName = 'vod/live-' . $id . '.webm';
    $dup = $pdo->prepare('SELECT id FROM recordings WHERE video_path LIKE ? OR video_path = ? LIMIT 1');
    $dup->execute([$like, $liveName]);
    if ($dup->fetch()) {
        return true;
    }
    $tmp = academy_storage('vod') . '/live-' . $id . '.webm';
    $name = '';
    $dest = '';
    if (vod_file_ready($tmp)) {
        $name = 'vod/oda-' . $id . '-' . date('Ymd-His') . '.webm';
        $dest = academy_storage() . '/' . $name;
        @set_time_limit(180);
        if (!@rename($tmp, $dest)) {
            $dest = $tmp;
            $name = 'vod/' . basename($tmp);
        } elseif (is_file($tmp) && @realpath($tmp) !== @realpath($dest)) {
            @unlink($tmp);
        }
    } else {
        $found = glob(academy_storage() . '/vod/oda-' . $id . '-*.webm') ?: [];
        rsort($found);
        if (!$found || !vod_file_ready($found[0])) {
            return false;
        }
        $dest = $found[0];
        $name = 'vod/' . basename($dest);
    }
    $topic = trim((string) ($room['topic'] ?? ''));
    $title = trim((string) ($room['title'] ?? 'Ders'));
    $when = date('d.m.Y H:i');
    $label = $topic !== '' && $topic !== 'Ders' ? $topic . ' — ' . $title : $title;
    $label .= ' — ' . $when;
    $bytes = vod_file_bytes($dest);
    $hintMs = $sec > 1 ? $sec * 1000.0 : max(0, $mins) * 60 * 1000.0;
    $stamped = false;
    if ($bytes > 0) {
        try {
            $stamped = vod_webm_patch_duration($dest, $hintMs, false);
        } catch (Throwable $e) {
            $stamped = false;
        }
        @file_put_contents(vod_marker($dest), '1');
    }
    $fileMs = $bytes > 0 ? vod_probe_ms($dest, $hintMs) : 0.0;
    $jsSec = $sec > 1 ? $sec : ($mins > 0 ? $mins * 60 : 0);
    $sec = $fileMs > 400 ? (int) max(1, (int) round($fileMs / 1000)) : max(1, $jsSec);
    $sec = min(28800, $sec);
    $mins = max(1, (int) round($sec / 60));
    $mins = min(480, $mins);
    $isTest = function_exists('live_room_is_test') && live_room_is_test($room);
    if ($isTest && !str_starts_with($label, 'Test ·')) {
        $label = 'Test · ' . $label;
    }
    ensure_recordings_duration_schema();
    $title = vod_clip_title($label);
    $lessonCat = recording_cat_from_room($room);
    $okRow = false;
    try {
        $pdo->prepare('INSERT INTO recordings (group_id, teacher_id, title, mins, duration_sec, recorded_on, video_url, video_path, is_test, lesson_cat) VALUES (?,?,?,?,?,CURDATE(),NULL,?,?,?)')
            ->execute([(int) $room['group_id'], (int) $room['teacher_id'], $title, $mins, $sec, $name, $isTest ? 1 : 0, $lessonCat !== '' ? $lessonCat : null]);
        $okRow = true;
    } catch (Throwable $e) {
        try {
            $pdo->prepare('INSERT INTO recordings (group_id, teacher_id, title, mins, duration_sec, recorded_on, video_url, video_path) VALUES (?,?,?,?,?,CURDATE(),NULL,?)')
                ->execute([(int) $room['group_id'], (int) $room['teacher_id'], $title, $mins, $sec, $name]);
            $okRow = true;
        } catch (Throwable $e2) {
            try {
                $pdo->prepare('INSERT INTO recordings (group_id, teacher_id, title, mins, recorded_on, video_url, video_path) VALUES (?,?,?,?,CURDATE(),NULL,?)')
                    ->execute([(int) $room['group_id'], (int) $room['teacher_id'], $title, $mins, $name]);
                $okRow = true;
            } catch (Throwable $e3) {
                return is_file($dest);
            }
        }
    }
    if ($okRow && !$isTest && function_exists('notify_group_students')) {
        notify_group_students((int) $room['group_id'], 'Ders kaydı hazır', $label, url('ogrenci/kayitlar'));
    }
    $GLOBALS['_vod_last_commit_id'] = 0;
    if ($okRow) {
        $recId = (int) $pdo->lastInsertId();
        if ($recId < 1) {
            $q = $pdo->prepare('SELECT id FROM recordings WHERE video_path = ? ORDER BY id DESC LIMIT 1');
            $q->execute([$name]);
            $recId = (int) $q->fetchColumn();
        }
        $GLOBALS['_vod_last_commit_id'] = $recId;
        if ($recId > 0 && $lessonCat !== '') {
            recording_apply_lesson_cat($pdo, $recId, $lessonCat);
        }
    }
    return true;
}

function vod_upload_recording_to_cf(PDO $pdo, int $recId, string $absPath = '', string $title = ''): bool
{
    return false;
}

function vod_recover_pending_rooms(PDO $pdo, ?int $teacherId = null): int
{
    $n = 0;
    $files = array_merge(
        glob(academy_storage('vod') . '/live-*.webm') ?: [],
        glob(academy_storage('vod') . '/oda-*.webm') ?: []
    );
    foreach ($files as $file) {
        $base = str_replace('\\', '/', (string) $file);
        if (!preg_match('/(?:live-|oda-)(\d+)/', basename($base), $m)) {
            continue;
        }
        if (!vod_file_ready($file)) {
            continue;
        }
        $st = $pdo->prepare('SELECT * FROM live_rooms WHERE id = ?');
        $st->execute([(int) $m[1]]);
        $room = $st->fetch();
        if (!$room) {
            continue;
        }
        if ($teacherId !== null) {
            $tid = (int) $room['teacher_id'];
            $gid = (int) $room['group_id'];
            if ($tid !== $teacherId && !(function_exists('group_has_teacher') && group_has_teacher($gid, $teacherId))) {
                continue;
            }
        }
        if (vod_commit_live_room($pdo, $room, 0)) {
            $n++;
        }
    }
    return $n;
}

function vod_promote_local_recordings_to_cf(PDO $pdo, ?int $teacherId = null): int
{
    return 0;
}

function vod_recover_teacher_pending(PDO $pdo, int $teacherId): int
{
    return vod_recover_pending_rooms($pdo, $teacherId);
}

function vod_manual_cf_recordings(PDO $pdo): array
{
    try {
        $rows = $pdo->query(
            "SELECT * FROM recordings
             WHERE video_url LIKE '%cloudflarestream%'
               AND video_path LIKE 'vod/%'
               AND video_path NOT LIKE 'vod/oda-%'
               AND video_path NOT LIKE 'vod/live-%'
             ORDER BY id ASC"
        )->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
    return is_array($rows) ? $rows : [];
}

function vod_clear_recording_cf_url(PDO $pdo, int $id): void
{
    try {
        $pdo->prepare('UPDATE recordings SET video_url = NULL WHERE id = ?')->execute([$id]);
    } catch (Throwable $e) {
    }
}

function vod_restore_manual_recordings_from_cf(PDO $pdo, int $limit = 3): array
{
    $done = 0;
    $wait = 0;
    $rows = vod_manual_cf_recordings($pdo);
    foreach ($rows as $rec) {
        $id = (int) ($rec['id'] ?? 0);
        $rel = trim((string) ($rec['video_path'] ?? ''));
        $url = trim((string) ($rec['video_url'] ?? ''));
        if ($id < 1 || !vod_is_manual_upload_path($rel)) {
            continue;
        }
        $uid = function_exists('live_cf_uid_from_ref') ? live_cf_uid_from_ref($url) : '';
        if ($uid === '') {
            $uid = live_cf_uid_from_ref($rel);
        }
        $abs = vod_abs_from_rec($rec);
        if ($abs !== '' && is_file($abs) && vod_file_bytes($abs) >= 8000) {
            vod_clear_recording_cf_url($pdo, $id);
            if ($uid !== '' && function_exists('live_cf_delete_video')) {
                try {
                    live_cf_delete_video($uid);
                } catch (Throwable $e) {
                }
            }
            $done++;
            continue;
        }
        if ($limit < 1) {
            $wait++;
            continue;
        }
        if ($uid === '' || !function_exists('live_cf_download_video_file')) {
            $wait++;
            continue;
        }
        if (preg_match('/\.webm$/i', $rel)) {
            $rel = (string) preg_replace('/\.webm$/i', '.mp4', $rel);
        }
        $dest = function_exists('academy_abs_file') ? academy_abs_file($rel) : '';
        if ($dest === '') {
            $wait++;
            continue;
        }
        @set_time_limit(360);
        if (!live_cf_download_video_file($uid, $dest)) {
            $wait++;
            continue;
        }
        try {
            $pdo->prepare('UPDATE recordings SET video_url = NULL, video_path = ? WHERE id = ?')->execute([$rel, $id]);
        } catch (Throwable $e) {
            vod_clear_recording_cf_url($pdo, $id);
        }
        if (function_exists('live_cf_delete_video')) {
            try {
                live_cf_delete_video($uid);
            } catch (Throwable $e) {
            }
        }
        $done++;
        $limit--;
    }
    return ['done' => $done, 'left' => $wait];
}

function vod_optimize_upload(string $rel): string
{
    if ($rel === '' || !function_exists('academy_abs_file')) {
        return $rel;
    }
    $abs = academy_abs_file($rel);
    if (!is_file($abs) || !is_readable($abs)) {
        return $rel;
    }
    $orig = vod_file_bytes($abs);
    if ($orig < 8 * 1024 * 1024) {
        return $rel;
    }
    $ff = vod_ffmpeg_bin();
    if ($ff === '') {
        return $rel;
    }
    @set_time_limit(900);
    $tmp = $abs . '.opt.mp4';
    @unlink($tmp);
    $scale = PHP_OS_FAMILY === 'Windows'
        ? 'scale=-2:min(1080\\,ih)'
        : "scale=-2:'min(1080,ih)'";
    $cmd = escapeshellarg($ff)
        . ' -y -hide_banner -loglevel error -i ' . escapeshellarg($abs)
        . ' -map 0:v:0 -map 0:a:0?'
        . ' -c:v libx264 -preset veryfast -crf 20 -pix_fmt yuv420p'
        . ' -vf ' . escapeshellarg($scale)
        . ' -c:a aac -b:a 160k -ac 2 -ar 48000 -movflags +faststart '
        . escapeshellarg($tmp);
    $out = [];
    $code = 1;
    @exec($cmd . (PHP_OS_FAMILY === 'Windows' ? ' 2>NUL' : ' 2>/dev/null'), $out, $code);
    $fresh = is_file($tmp) ? vod_file_bytes($tmp) : 0;
    if ($code !== 0 || $fresh < 80000 || $fresh >= (int) ($orig * 0.97)) {
        @unlink($tmp);
        return $rel;
    }
    $dir = str_replace('\\', '/', dirname($rel));
    $base = pathinfo($rel, PATHINFO_FILENAME);
    $newRel = ($dir === '.' ? '' : $dir . '/') . $base . '.mp4';
    $newAbs = academy_abs_file($newRel);
    if (!@rename($tmp, $newAbs)) {
        @copy($tmp, $newAbs);
        @unlink($tmp);
    }
    if ($newAbs !== $abs && is_file($newAbs)) {
        @unlink($abs);
        return $newRel;
    }
    return is_file($newAbs) ? $newRel : $rel;
}

function vod_remux_webm(string $abs): bool
{
    $ff = vod_ffmpeg_bin();
    if ($ff === '') {
        return false;
    }
    $tmp = $abs . '.tmp.webm';
    @unlink($tmp);
    $cmd = escapeshellarg($ff)
        . ' -y -hide_banner -loglevel error -analyzeduration 200M -probesize 50M -fflags +genpts -i '
        . escapeshellarg($abs)
        . ' -c copy -avoid_negative_ts make_zero ' . escapeshellarg($tmp);
    $out = [];
    $code = 1;
    @exec($cmd . (PHP_OS_FAMILY === 'Windows' ? ' 2>NUL' : ' 2>/dev/null'), $out, $code);
    $orig = (int) filesize($abs);
    $fresh = is_file($tmp) ? (int) filesize($tmp) : 0;
    if ($code === 0 && $fresh > 800 && $fresh >= (int) ($orig * 0.5)) {
        if (!@rename($tmp, $abs)) {
            @copy($tmp, $abs);
            @unlink($tmp);
        }
        return is_file($abs) && filesize($abs) > 800;
    }
    @unlink($tmp);
    return false;
}

function vod_webm_vint_width(int $first): int
{
    $width = 1;
    $mask = 0x80;
    while ($width <= 8 && ($first & $mask) === 0) {
        $width++;
        $mask >>= 1;
    }
    return $width <= 8 ? $width : 0;
}

function vod_webm_read_vint(string $buf, int &$o, int $len): ?int
{
    if ($o >= $len) {
        return null;
    }
    $first = ord($buf[$o]);
    $width = vod_webm_vint_width($first);
    if ($width < 1 || $o + $width > $len) {
        return null;
    }
    $mask = 0x80 >> ($width - 1);
    $unknown = true;
    $val = $first & ($mask - 1);
    if (($first & $mask) === 0) {
        return null;
    }
    for ($i = 1; $i < $width; $i++) {
        $b = ord($buf[$o + $i]);
        $val = ($val << 8) | $b;
        if ($b !== 0xFF) {
            $unknown = false;
        }
    }
    if ($width === 1 && $first === 0xFF) {
        $unknown = true;
    } elseif ($val !== (1 << (7 * $width)) - 1) {
        $unknown = false;
    }
    $o += $width;
    return $unknown ? -1 : $val;
}

function vod_webm_read_id(string $buf, int &$o, int $len): ?string
{
    if ($o >= $len) {
        return null;
    }
    $width = vod_webm_vint_width(ord($buf[$o]));
    if ($width < 1 || $width > 4 || $o + $width > $len) {
        return null;
    }
    $id = substr($buf, $o, $width);
    $o += $width;
    return $id;
}

function vod_pack_be_float(float $value, int $bytes): string
{
    $fmt = $bytes === 4 ? 'f' : 'd';
    $raw = pack($fmt, $value);
    if (pack('S', 1) === pack('v', 1)) {
        $raw = strrev($raw);
    }
    return $raw;
}

function vod_webm_last_timecode_ms(string $abs): float
{
    $size = filesize($abs);
    if ($size === false || $size < 32) {
        return 0;
    }
    $tail = min(12 * 1024 * 1024, (int) $size);
    $fp = @fopen($abs, 'rb');
    if ($fp === false) {
        return 0;
    }
    fseek($fp, (int) $size - $tail);
    $buf = (string) fread($fp, $tail);
    fclose($fp);
    $last = 0.0;
    $len = strlen($buf);
    $needle = "\x1F\x43\xB6\x75";
    $pos = 0;
    while (($i = strpos($buf, $needle, $pos)) !== false) {
        $o = $i + 4;
        $clSize = vod_webm_read_vint($buf, $o, $len);
        if ($clSize === null) {
            $pos = $i + 1;
            continue;
        }
        $end = $clSize < 0 ? min($len, $o + 64) : min($len, $o + min($clSize, 64));
        $id = vod_webm_read_id($buf, $o, $end);
        if ($id === "\xE7") {
            $n = vod_webm_read_vint($buf, $o, $end);
            if ($n !== null && $n > 0 && $o + $n <= $end) {
                $tc = 0;
                for ($k = 0; $k < $n; $k++) {
                    $tc = ($tc << 8) | ord($buf[$o + $k]);
                }
                if ($tc > $last) {
                    $last = (float) $tc;
                }
            }
        }
        $pos = $i + 1;
    }
    return $last;
}

function vod_webm_patch_duration(string $abs, float $hintMs, bool $allowRewrite = true): bool
{
    $size = filesize($abs);
    if ($size === false || $size < 64) {
        return false;
    }
    $headLen = min(262144, (int) $size);
    $fp = @fopen($abs, 'rb');
    if ($fp === false) {
        return false;
    }
    $buf = (string) fread($fp, $headLen);
    fclose($fp);
    $len = strlen($buf);
    $o = 0;
    $id = vod_webm_read_id($buf, $o, $len);
    if ($id !== "\x1A\x45\xDF\xA3") {
        return false;
    }
    $ebmlSize = vod_webm_read_vint($buf, $o, $len);
    if ($ebmlSize === null || $ebmlSize < 0) {
        return false;
    }
    $o += $ebmlSize;
    $segId = vod_webm_read_id($buf, $o, $len);
    if ($segId !== "\x18\x53\x80\x67") {
        return false;
    }
    vod_webm_read_vint($buf, $o, $len);
    $durOff = -1;
    $durBytes = 0;
    $infoSizeOff = -1;
    $infoSizeWidth = 0;
    $infoSize = -1;
    $infoPayload = -1;
    $guard = 0;
    while ($o + 3 < $len && $guard++ < 400) {
        $elId = vod_webm_read_id($buf, $o, $len);
        $sizeOff = $o;
        $elSize = vod_webm_read_vint($buf, $o, $len);
        if ($elId === null || $elSize === null) {
            break;
        }
        if ($elId === "\x15\x49\xA9\x66") {
            $infoSizeOff = $sizeOff;
            $infoSizeWidth = $o - $sizeOff;
            $infoSize = $elSize;
            $infoPayload = $o;
            $infoEnd = $elSize < 0 ? $len : min($len, $o + $elSize);
            $io = $o;
            $ig = 0;
            while ($io + 3 < $infoEnd && $ig++ < 80) {
                $cid = vod_webm_read_id($buf, $io, $infoEnd);
                $csz = vod_webm_read_vint($buf, $io, $infoEnd);
                if ($cid === null || $csz === null || $csz < 0) {
                    break;
                }
                if ($cid === "\x44\x89" && ($csz === 4 || $csz === 8)) {
                    $durOff = $io;
                    $durBytes = $csz;
                    break;
                }
                $io += $csz;
            }
            break;
        }
        if ($elSize < 0) {
            break;
        }
        $o += $elSize;
    }
    $clusterMs = vod_webm_last_timecode_ms($abs);
    $ms = max($clusterMs, $hintMs);
    if ($clusterMs > 800 && $ms < $clusterMs + 250) {
        $ms = $clusterMs + 250.0;
    }
    if ($ms < 2000) {
        return false;
    }
    if ($durOff >= 0 && $durBytes >= 4) {
        $packed = vod_pack_be_float($ms, $durBytes);
        if (strlen($packed) !== $durBytes) {
            return false;
        }
        $out = @fopen($abs, 'r+b');
        if ($out === false) {
            return false;
        }
        fseek($out, $durOff);
        $wrote = fwrite($out, $packed);
        fclose($out);
        return $wrote === $durBytes;
    }
    if (!$allowRewrite) {
        return false;
    }
    if ($infoSizeOff < 0 || $infoPayload < 0 || $infoSize < 1 || $infoSizeWidth < 1) {
        return false;
    }
    $newSize = $infoSize + 11;
    $maxSameWidth = (1 << (7 * $infoSizeWidth)) - 2;
    if ($newSize > $maxSameWidth) {
        return false;
    }
    $insert = "\x44\x89\x88" . vod_pack_be_float($ms, 8);
    if (strlen($insert) !== 11) {
        return false;
    }
    $sizeByte = vod_webm_encode_vint($newSize, $infoSizeWidth);
    if ($sizeByte === '') {
        return false;
    }
    $tmp = $abs . '.dur.webm';
    $src = @fopen($abs, 'rb');
    $dst = @fopen($tmp, 'wb');
    if ($src === false || $dst === false) {
        if ($src) {
            fclose($src);
        }
        if ($dst) {
            fclose($dst);
        }
        return false;
    }
    fwrite($dst, substr($buf, 0, $infoSizeOff));
    fwrite($dst, $sizeByte);
    $payloadOff = $infoPayload;
    $copyFrom = $payloadOff + $infoSize;
    fwrite($dst, substr($buf, $payloadOff, $infoSize));
    fwrite($dst, $insert);
    if ($copyFrom < $len) {
        fwrite($dst, substr($buf, $copyFrom));
    }
    $fileSize = (int) $size;
    if ($fileSize > $headLen) {
        fseek($src, $headLen);
        while (!feof($src)) {
            $chunk = fread($src, 1024 * 256);
            if ($chunk === false || $chunk === '') {
                break;
            }
            fwrite($dst, $chunk);
        }
    }
    fclose($src);
    fclose($dst);
    if (!is_file($tmp) || filesize($tmp) < $fileSize) {
        @unlink($tmp);
        return false;
    }
    if (!@rename($tmp, $abs)) {
        @copy($tmp, $abs);
        @unlink($tmp);
    }
    return is_file($abs) && filesize($abs) >= $fileSize;
}

function vod_webm_encode_vint(int $value, int $width): string
{
    if ($width < 1 || $width > 8 || $value < 0) {
        return '';
    }
    $max = (1 << (7 * $width)) - 2;
    if ($value > $max) {
        return '';
    }
    $bytes = [];
    $v = $value;
    for ($i = 0; $i < $width; $i++) {
        $bytes[] = $v & 0xFF;
        $v >>= 8;
    }
    $bytes = array_reverse($bytes);
    $bytes[0] |= 1 << (8 - $width);
    $out = '';
    foreach ($bytes as $b) {
        $out .= chr($b);
    }
    return $out;
}

function vod_send_file(string $abs, string $mime): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $size = filesize($abs);
    if ($size === false || $size < 1) {
        http_response_code(404);
        exit('Dosya yok.');
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $start = 0;
    $end = $size - 1;
    $code = 200;
    $range = (string) ($_SERVER['HTTP_RANGE'] ?? '');
    if ($range !== '' && preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
        if ($m[1] === '' && $m[2] !== '') {
            $len = (int) $m[2];
            $start = max(0, $size - $len);
        } else {
            $start = (int) $m[1];
            if ($m[2] !== '') {
                $end = (int) $m[2];
            }
        }
        if ($start > $end || $start >= $size || $end >= $size) {
            header('HTTP/1.1 416 Range Not Satisfiable');
            header('Content-Range: bytes */' . $size);
            exit;
        }
        $code = 206;
    }
    header('Accept-Ranges: bytes');
    header('Content-Type: ' . $mime);
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline');
    header('Content-Length: ' . (string) ($end - $start + 1));
    if ($code === 206) {
        header('HTTP/1.1 206 Partial Content');
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }
    $fp = fopen($abs, 'rb');
    if ($fp === false) {
        http_response_code(500);
        exit;
    }
    fseek($fp, $start);
    $left = $end - $start + 1;
    while ($left > 0 && !feof($fp)) {
        if (connection_aborted()) {
            break;
        }
        $chunk = fread($fp, min(65536, $left));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        $left -= strlen($chunk);
    }
    fclose($fp);
    exit;
}
