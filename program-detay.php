<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$slug = $_GET['slug'] ?? 'tefsir';
$st = db()->prepare('SELECT * FROM programs WHERE slug = ?');
$st->execute([$slug]);
$p = $st->fetch() ?: programs()[0];
$groups = db()->prepare('SELECT g.*, t.name teacher, (SELECT COUNT(*) FROM enrollments e WHERE e.group_id = g.id) n FROM class_groups g JOIN users t ON t.id = g.teacher_id WHERE g.program_id = ? ORDER BY ' . catalog_order_sql('g', 'class_groups'));
$groups->execute([(int) $p['id']]);
$groups = $groups->fetchAll();
$u = current_user();
$body = catalog_body('program', (string) $p['slug'], (string) $p['description']);
$paras = catalog_paragraphs($body);
$cap = 10;
if ($groups) {
    $caps = array_map(static fn (array $g): int => (int) $g['cap'], $groups);
    $cap = min($caps);
}
$relatedSlug = catalog_related_book_slug((string) $p['slug']);
$related = null;
if ($relatedSlug !== '') {
    $bst = db()->prepare('SELECT * FROM books WHERE slug = ?');
    $bst->execute([$relatedSlug]);
    $related = $bst->fetch() ?: null;
}
$ilgi = catalog_interest_label((string) $p['slug']);
$kayitHref = ($u && $u['role'] === 'ogrenci')
    ? (page_url('uyelik-ders') . '?ilgi=' . rawurlencode($ilgi))
    : (page_url('kayit-ders') . '?ilgi=' . rawurlencode($ilgi));
$eduUser = $u && (is_edu_role($u['role']) || is_admin_role($u['role']));
$panelHref = $eduUser ? url(panel_home($u['role'])) : '';
public_head($p['title'] . ' | Online İlahiyat', catalog_seo_excerpt($body));
?>
<header class="bg-soft">
  <div class="mx-auto max-w-6xl px-4 py-12 lg:px-8 lg:py-16">
    <div class="mb-8 overflow-hidden rounded-[22px]"><?= program_gallery_html($p, 'detail') ?></div>
    <p class="badge"><?= e($p['level']) ?></p>
    <h1 class="font-display mt-4 text-4xl leading-tight md:text-6xl"><?= e($p['title']) ?></h1>
    <p class="mt-4 text-sm font-bold text-navy"><?= e($p['hours']) ?> · <?= e($p['tag']) ?></p>
    <p class="mt-4"><span class="price-old mr-2 text-lg"><?= money((int) $p['price_old']) ?></span><span class="price-now text-3xl md:text-4xl"><?= money((int) $p['price_now']) ?> / yıl</span></p>
    <div class="mt-7 flex flex-wrap gap-3">
      <a href="<?= e($kayitHref) ?>" class="btn-primary">Canlı ders üyeliği al</a>
      <a href="<?= e(page_url('iletisim')) ?>" class="btn-outline">İletişim</a>
      <?php if ($panelHref): ?><a href="<?= e($panelHref) ?>" class="btn-outline">Panelim</a><?php endif; ?>
    </div>
  </div>
</header>
<main class="mx-auto max-w-6xl px-4 py-12 lg:px-8">
  <div class="grid items-start gap-8 lg:grid-cols-[1fr_340px]">
    <div class="grid gap-8">
      <section>
        <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-accent">Program</p>
        <h2 class="font-display mt-2 text-3xl">Program hakkında</h2>
        <div class="mt-4 grid gap-4 text-lg leading-relaxed text-muted">
          <?php foreach ($paras as $para): ?>
            <p><?= e($para) ?></p>
          <?php endforeach; ?>
        </div>
      </section>

      <section>
        <h2 class="font-display text-3xl">Neler dahil?</h2>
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
          <div class="card p-5"><h3 class="font-extrabold">Canlı ders</h3><p class="mt-1 text-sm text-muted">Haftalık <?= e($p['hours']) ?>. Kamera açık küçük sınıf, hocayla birebir söz hakkı.</p></div>
          <div class="card p-5"><h3 class="font-extrabold">Ders kaydı</h3><p class="mt-1 text-sm text-muted">Kaçırdığınız ders öğrenci paneline düşer; tekrar izleme açıktır.</p></div>
          <div class="card p-5"><h3 class="font-extrabold">Koçluk</h3><p class="mt-1 text-sm text-muted">Ezber, okuma ve takıldığınız yer haftalık birebir takip edilir.</p></div>
          <div class="card p-5"><h3 class="font-extrabold">Ödev</h3><p class="mt-1 text-sm text-muted">Kısa yazılı veya okuma ödevi; teslim ve geri bildirim panelden yürür.</p></div>
          <div class="card p-5 sm:col-span-2"><h3 class="font-extrabold">Küçük grup</h3><p class="mt-1 text-sm text-muted">En fazla <?= (int) $cap ?> kişilik sınıf. Kalabalık webinar değil, gerçek takip.</p></div>
        </div>
      </section>

      <section class="card p-6">
        <h2 class="font-display text-2xl">Program ve kontenjan</h2>
        <p class="mt-2 text-sm text-muted">Haftalık tempo: <b class="text-ink"><?= e($p['hours']) ?></b> · Grup üst sınırı: <b class="text-ink"><?= (int) $cap ?> kişi</b></p>
        <?php if ($groups): ?>
          <table class="table mt-4">
            <thead><tr><th>Grup</th><th>Hoca</th><th>Gün</th><th>Kontenjan</th></tr></thead>
            <tbody>
              <?php foreach ($groups as $g): ?>
              <tr>
                <td class="font-extrabold"><?= e($g['name']) ?></td>
                <td><?= e($g['teacher']) ?></td>
                <td><?= e($g['days']) ?></td>
                <td><?= (int) $g['n'] ?> / <?= (int) $g['cap'] ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="mt-4 text-sm text-muted">Bu programda henüz açık grup yok. <a class="font-extrabold text-navy" href="<?= e(page_url('iletisim')) ?>">İletişimden yazın</a>; kayıt sonrası grup açılınca panelden haberdar edilirsiniz.</p>
        <?php endif; ?>
      </section>

      <?php render_installment_table((int) $p['price_now']); ?>
      <p class="text-sm text-muted">Taksit tablosu bilgilendirme içindir. Ödeme bu sayfada alınmaz; <a class="font-extrabold text-navy" href="<?= e($kayitHref) ?>">üyelik satın al</a> sayfasında güvenli kart ödemesiyle tamamlanır.</p>
    </div>

    <aside class="grid gap-4 lg:sticky lg:top-24">
      <div class="card overflow-hidden">
        <div class="bg-navy3 px-6 py-5 text-white">
          <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-white/60">Yıllık ücret</p>
          <p class="mt-1"><span class="price-old mr-2 text-white/50"><?= money((int) $p['price_old']) ?></span></p>
          <p class="font-display mt-1 text-3xl"><?= money((int) $p['price_now']) ?></p>
          <p class="mt-1 text-sm text-white/70"><?= e($p['tag']) ?></p>
        </div>
        <div class="grid gap-3 p-6">
          <a href="<?= e($kayitHref) ?>" class="btn-primary text-center">Canlı ders üyeliği al</a>
          <a href="<?= e(page_url('iletisim')) ?>" class="btn-outline text-center">İletişim</a>
          <?php if ($panelHref): ?>
            <a href="<?= e($panelHref) ?>" class="btn-outline text-center">Panelim</a>
          <?php else: ?>
            <a href="<?= e(page_url('giris-ders')) ?>" class="text-center text-sm font-extrabold text-navy">Hesabınız varsa ders girişi</a>
          <?php endif; ?>
          <p class="text-xs text-muted">Kart bu sayfada çekilmez. Üyelik ücreti kayıt / üyelik satın al formunda ödenir.</p>
        </div>
      </div>
      <?php if ($related): ?>
      <article class="card overflow-hidden hover:border-navy">
        <?= book_gallery_html($related, 'aside', page_url('kitap', (string) $related['slug'])) ?>
        <div class="p-4">
          <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-navy">Kaynak kitap</p>
          <p class="mt-1 font-extrabold"><a href="<?= e(page_url('kitap', (string) $related['slug'])) ?>"><?= e($related['title']) ?></a></p>
          <p class="text-sm text-muted"><?= e($related['author']) ?></p>
          <p class="mt-2"><span class="price-old mr-2"><?= money((int) $related['price_old']) ?></span><span class="price-now"><?= money((int) $related['price']) ?></span></p>
          <button type="button" data-add-book="<?= (int) $related['id'] ?>" class="btn-primary mt-3 w-full text-sm">Sepete ekle</button>
        </div>
      </article>
      <?php endif; ?>
    </aside>
  </div>
</main>
<?php public_foot();
