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
$isFree = (int) $p['price_now'] <= 0;
$kayitLabel = $isFree ? 'Ücretsiz sepete ekle' : 'Sepete ekle';
$askName = (string) ($u['name'] ?? '');
$askEmail = (string) ($u['email'] ?? '');
public_head($p['title'] . ' | Online İlahiyat', catalog_seo_excerpt($body));
?>
<header class="bg-soft">
  <div class="mx-auto max-w-6xl px-4 py-6 lg:px-8 lg:py-8">
    <div class="mb-8 overflow-hidden rounded-[22px]"><?= program_gallery_html($p, 'detail') ?></div>
    <p class="badge"><?= e($p['level']) ?></p>
    <h1 class="font-display mt-3 text-2xl leading-tight md:text-3xl"><?= e($p['title']) ?></h1>
    <p class="mt-4 text-sm font-bold text-navy"><?= e($p['hours']) ?> · <?= e($p['tag']) ?></p>
    <p class="mt-4"><?= program_price_html($p, 'price-now text-3xl md:text-4xl') ?></p>
    <div class="mt-7 flex flex-wrap gap-3">
      <button type="button" class="btn-primary" data-add-program="<?= (int) $p['id'] ?>"><?= e($kayitLabel) ?></button>
      <a href="<?= e(page_url('sepet')) ?>" class="btn-outline">Sepete git</a>
      <button type="button" class="btn-outline" data-open-ask>Soru sor</button>
    </div>
  </div>
</header>
<main class="mx-auto max-w-6xl px-4 py-12 lg:px-8">
  <div class="grid items-start gap-8 lg:grid-cols-[1fr_340px]">
    <div class="grid gap-8">
      <section>
        <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-accent">Eğitim</p>
        <h2 class="font-display mt-2 text-3xl">Eğitim hakkında</h2>
        <div class="mt-4 grid gap-4 text-lg leading-relaxed text-muted">
          <?php foreach ($paras as $para): ?>
            <p><?= e($para) ?></p>
          <?php endforeach; ?>
        </div>
        <?= program_body_gallery_html($p) ?>
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
        <h2 class="font-display text-2xl">Eğitim ve kontenjan</h2>
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
          <p class="mt-4 text-sm text-muted">Bu programda henüz açık grup yok. <button type="button" class="font-extrabold text-navy" data-open-ask>Soru sorun</button>; kayıt sonrası grup açılınca panelden haberdar edilirsiniz.</p>
        <?php endif; ?>
      </section>

      <?php render_installment_table((int) $p['price_now']); ?>
      <?php if (!$isFree): ?>
      <p class="text-sm text-muted">Taksit tablosu bilgilendirme içindir. Ödeme bu sayfada alınmaz; sepete ekleyip <a class="font-extrabold text-navy" href="<?= e(page_url('sepet')) ?>">mağaza hesabıyla</a> güvenli kart ödemesiyle tamamlanır.</p>
      <?php endif; ?>
    </div>

    <aside class="grid gap-4 lg:sticky lg:top-24">
      <div class="card overflow-hidden">
        <div class="bg-navy3 px-6 py-5 text-white">
          <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-white/60"><?= $isFree ? 'Ücret' : 'Yıllık ücret' ?></p>
          <?php if (!$isFree && (int) $p['price_old'] > 0): ?>
          <p class="mt-1"><span class="price-old mr-2 text-white/50"><?= money((int) $p['price_old']) ?></span></p>
          <?php endif; ?>
          <p class="font-display mt-1 text-3xl"><?= e(money_or_free((int) $p['price_now'])) ?></p>
          <p class="mt-1 text-sm text-white/70"><?= e($p['tag']) ?></p>
        </div>
        <div class="grid gap-3 p-6">
          <button type="button" class="btn-primary" data-add-program="<?= (int) $p['id'] ?>"><?= e($kayitLabel) ?></button>
          <button type="button" class="btn-outline" data-open-ask>Soru sor</button>
          <?php if (!$u): ?>
            <a href="<?= e(url('giris-magaza.php?next=sepet')) ?>" class="text-center text-sm font-extrabold text-navy">Hesabınız varsa mağaza girişi</a>
          <?php endif; ?>
          <?php if ($isFree): ?>
          <p class="text-xs text-muted">Bu eğitim ücretsizdir. Sepete ekleyip mağaza hesabıyla kaydı tamamlayın; kart çekilmez.</p>
          <?php else: ?>
          <p class="text-xs text-muted">Kart bu sayfada çekilmez. Eğitimi sepete ekleyin; ödeme mağaza hesabıyla sepette yapılır. Sınıfa admin yerleştirir.</p>
          <?php endif; ?>
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
<div id="ask-modal" class="modal">
  <div class="card w-full max-w-md p-6">
    <h3 class="font-display text-2xl">Soru sor</h3>
    <p class="mt-2 text-sm text-muted"><?= e((string) $p['title']) ?> hakkında sorunuzu yazın; yanıt yönetime ve ilgili hocaya düşer.</p>
    <form class="mt-4 grid gap-3" method="post" action="<?= e(url('api/program-soru.php')) ?>" data-ask-form>
      <?= csrf_field() ?>
      <?= security_honeypot_field() ?>
      <input type="hidden" name="program_id" value="<?= (int) $p['id'] ?>">
      <input required name="name" class="rounded-xl border border-[#e5e5e7] px-3 py-2" placeholder="Ad soyad" autocomplete="name" value="<?= e($askName) ?>">
      <input required type="email" name="email" class="rounded-xl border border-[#e5e5e7] px-3 py-2" placeholder="E-posta" autocomplete="email" value="<?= e($askEmail) ?>">
      <textarea required name="body" minlength="8" maxlength="2000" rows="5" class="rounded-xl border border-[#e5e5e7] px-3 py-2" placeholder="Sorunuz"></textarea>
      <p class="hidden text-sm font-bold text-green-700" data-ask-ok>Sorunuz iletildi.</p>
      <p class="hidden text-sm font-bold text-accent" data-ask-err></p>
      <button class="btn-primary" type="submit">Gönder</button>
      <button type="button" data-close-ask class="btn-outline">Vazgeç</button>
    </form>
  </div>
</div>
<?php public_foot();
