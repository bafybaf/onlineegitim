<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogretmen');
group_handle_teacher_post(0, (int) $u['id']);
$groups = group_list((int) $u['id']);
$programs = group_programs();
$teachers = group_teachers();
panel_head('ogretmen', 'siniflar', 'Sınıflarım | Öğretmen Paneli', $u);
group_flash_html();
?>
<p class="mb-5 text-sm text-muted">Kendi gruplarınızı ekleyebilir, adını değiştirebilir ve silebilirsiniz. Detaya girince liste, ilerleme ve yaklaşan seanslar görünür.</p>

<?php if (!$groups): ?>
  <p class="dash-empty">Henüz sınıf yok. Aşağıdan grup ekleyin.</p>
<?php endif; ?>

<div class="grid gap-4">
<?php foreach ($groups as $g):
    $next = $g['next_session'];
    $nextStart = $next && function_exists('schedule_parse_datetime') ? schedule_parse_datetime((string) $next['starts_at']) : null;
    ?>
  <article class="card p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <p class="stat-label"><?= e((string) $g['program_title']) ?></p>
        <h2 class="font-display mt-1 text-2xl"><a class="hover:text-navy" href="<?= e(ogretmen_grup_url((int) $g['id'])) ?>"><?= e((string) $g['name']) ?></a></h2>
        <p class="mt-2 text-sm text-muted"><?= e((string) $g['days']) ?><?= !empty($g['teacher_name']) ? ' · ' . e((string) $g['teacher_name']) : '' ?></p>
      </div>
      <div class="text-right">
        <p class="stat-label">Kontenjan</p>
        <p class="mt-1"><?= group_cap_html((int) $g['n'], (int) $g['cap']) ?></p>
        <?php if (!empty($g['live_room'])): ?>
          <div class="mt-2"><?= live_pill($g['live_room']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php if (!empty($g['description'])): ?>
      <p class="mt-3 text-sm"><?= e(mb_strimwidth((string) $g['description'], 0, 180, '…')) ?></p>
    <?php endif; ?>
    <p class="mt-3 text-sm text-muted">
      <?= (int) $g['hw_n'] ?> ödev · <?= (int) $g['test_n'] ?> test
      <?php if ($next): ?>
        · sonraki seans <?= e($nextStart ? $nextStart->format('d.m.Y H:i') : (string) $next['starts_at']) ?>
        <?= !empty($next['title']) ? ' · ' . e((string) $next['title']) : '' ?>
      <?php endif; ?>
    </p>
    <div class="mt-4 flex flex-wrap gap-2">
      <a class="btn-primary text-sm" href="<?= e(ogretmen_grup_url((int) $g['id'])) ?>">Sınıf detayı</a>
      <?= panel_delete_form(ogretmen_grup_url((int) $g['id']), ['action' => 'delete'], 'Grup silinsin mi? Öğrenciler, ödevler, testler ve takvim saatleri de silinir.', 'Sil', 'btn-outline text-sm') ?>
      <a class="btn-outline text-sm" href="<?= e(url('ogretmen/takvim')) ?>">Takvim</a>
      <a class="btn-outline text-sm" href="<?= e(url('ogretmen/canli')) ?>">Canlı</a>
      <a class="btn-outline text-sm" href="<?= e(url('ogretmen/odevler')) ?>">Ödevler</a>
      <a class="btn-outline text-sm" href="<?= e(url('ogretmen/testler')) ?>">Testler</a>
      <?php if (!empty($g['live_room'])): ?>
        <a class="btn-primary text-sm" href="<?= e(canli_url((int) $g['live_room']['id'])) ?>">Odada devam et</a>
      <?php else: ?>
        <form method="post" action="<?= e(url('api/live.php')) ?>">
          <input type="hidden" name="action" value="start"><input type="hidden" name="html" value="1">
          <input type="hidden" name="group_id" value="<?= (int) $g['id'] ?>"><input type="hidden" name="topic" value="Ders">
          <input type="hidden" name="record" value="1"><input type="hidden" name="yoklama" value="1">
          <button class="btn-outline text-sm">Bu grubu canlı aç</button>
        </form>
      <?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
</div>

<section class="card mt-6 p-5">
  <p class="stat-label">Yeni sınıf</p>
  <h2 class="font-display mt-1 text-xl">Grup ekle</h2>
  <?php if (!$programs): ?>
    <p class="mt-3 text-sm text-muted">Henüz program yok. Yönetim program ekledikten sonra grup açabilirsiniz.</p>
  <?php else: ?>
  <form method="post" class="mt-4 grid gap-3 md:grid-cols-2">
    <input type="hidden" name="action" value="create">
    <label class="text-sm font-bold">Grup adı
      <input name="name" required maxlength="80" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" placeholder="Örn. Tefsir A">
    </label>
    <label class="text-sm font-bold">Ders günleri
      <input name="days" required maxlength="80" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" placeholder="Pzt / Çar 20:00">
    </label>
    <label class="text-sm font-bold">Program
      <select name="program_id" required class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
        <option value="">Seçin</option>
        <?php foreach ($programs as $p): ?>
          <option value="<?= (int) $p['id'] ?>"><?= e((string) $p['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="text-sm font-bold">Kontenjan
      <input type="number" name="cap" min="1" max="9999" value="10" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
    </label>
    <?= group_teachers_field($teachers, [(int) $u['id']], 'Siz otomatik eklersiniz. İsterseniz başka hoca da işaretleyin.', (int) $u['id']) ?>
    <label class="text-sm font-bold md:col-span-2">Açıklama (isteğe bağlı)
      <textarea name="description" rows="3" maxlength="4000" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" placeholder="Grup notu, seviye veya özel açıklama"></textarea>
    </label>
    <label class="text-sm font-bold md:col-span-2">WhatsApp grubu (isteğe bağlı)
      <input name="whatsapp_url" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" placeholder="https://chat.whatsapp.com/...">
    </label>
    <div class="md:col-span-2">
      <button class="btn-primary">Grubu kaydet</button>
    </div>
  </form>
  <?php endif; ?>
</section>
<?php panel_foot();
