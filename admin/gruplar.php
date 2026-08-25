<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
group_handle_admin_post(0);
$rows = group_list();
$programs = group_programs();
$teachers = group_teachers();
panel_head('admin', 'gruplar', 'Gruplar | Admin', $u);
group_flash_html();
?>
<p class="mb-5 text-sm text-muted">Tüm sınıf grupları. Ada tıklayınca yoklama, takvim, kontenjan ve öğrenci listesi açılır.</p>

<div class="card mb-6 overflow-hidden">
  <div class="px-5 py-4">
    <p class="stat-label">Sınıflar</p>
    <h2 class="font-display mt-1 text-xl">Grup listesi</h2>
  </div>
  <?php if (!$rows): ?>
    <p class="dash-empty px-5 pb-5">Henüz grup yok. Aşağıdan oluşturun.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th></th>
          <th>Grup</th>
          <th>Program</th>
          <th>Hoca</th>
          <th>Günler</th>
          <th>Kontenjan</th>
          <th>Ödev / Test</th>
          <th></th>
        </tr>
      </thead>
      <tbody data-sort-table="class_groups">
        <?php foreach ($rows as $r): ?>
          <tr data-sort-id="<?= (int) $r['id'] ?>">
            <td class="sort-handle">⋮⋮</td>
            <td>
              <a class="font-extrabold text-navy" href="<?= e(grup_url((int) $r['id'])) ?>"><?= e((string) $r['name']) ?></a>
              <?php if (!empty($r['live_room'])): ?>
                <div class="mt-1"><?= live_pill($r['live_room']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= e((string) $r['program_title']) ?></td>
            <td><?= e((string) $r['teacher_name']) ?></td>
            <td><?= e((string) $r['days']) ?></td>
            <td><?= group_cap_html((int) $r['n'], (int) $r['cap']) ?></td>
            <td><?= (int) $r['hw_n'] ?> / <?= (int) $r['test_n'] ?></td>
            <td><a class="text-sm font-extrabold text-navy" href="<?= e(grup_url((int) $r['id'])) ?>">Detay →</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<section class="card p-5">
  <p class="stat-label">Yeni sınıf</p>
  <h2 class="font-display mt-1 text-xl">Grup oluştur</h2>
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
    <label class="text-sm font-bold">Hoca
      <select name="teacher_id" required class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
        <option value="">Seçin</option>
        <?php foreach ($teachers as $t): ?>
          <option value="<?= (int) $t['id'] ?>"><?= e((string) $t['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="text-sm font-bold">Kontenjan
      <input type="number" name="cap" min="1" max="80" value="10" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
    </label>
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
</section>
<?php panel_foot();
