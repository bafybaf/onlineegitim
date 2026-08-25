<?php

function installment_max(): int
{
    $max = (int) setting('paytr_max_installment', '0');
    return $max > 0 ? $max : 12;
}

/** @return list<array{n:int,label:string,monthly:int}> */
function installment_rows(int $price): array
{
    $cap = installment_max();
    $out = [];
    foreach ([1, 3, 6, 9, 12] as $n) {
        if ($n > 1 && $n > $cap) {
            continue;
        }
        $out[] = [
            'n' => $n,
            'label' => $n === 1 ? 'Peşin' : $n . ' taksit',
            'monthly' => (int) ceil($price / max(1, $n)),
        ];
    }
    return $out;
}

function catalog_paragraphs(string $text): array
{
    $parts = preg_split('/\n\s*\n/u', trim($text)) ?: [];
    return array_values(array_filter(array_map('trim', $parts), static fn (string $p): bool => $p !== ''));
}

function catalog_copy(string $kind, string $slug): string
{
    $all = $kind === 'book' ? catalog_book_copy() : catalog_program_copy();
    return $all[$slug] ?? '';
}

function catalog_body(string $kind, string $slug, string $db = ''): string
{
    $long = catalog_copy($kind, $slug);
    $db = trim($db);
    if ($long !== '') {
        return $long;
    }
    return $db;
}

function catalog_book_pages(string $slug, int $db = 0): int
{
    if ($db > 0) {
        return $db;
    }
    $map = [
        'tefsir-ozet' => 248,
        'riyazu' => 312,
        'muvatta' => 276,
        'akaid-ders' => 164,
        'nahiv' => 220,
        'usul' => 198,
        'tecvid' => 144,
        'siyer' => 188,
    ];
    return $map[$slug] ?? 0;
}

function catalog_book_publisher(string $slug, string $author = '', string $db = ''): string
{
    $db = trim($db);
    if ($db !== '') {
        return $db;
    }
    $map = [
        'tefsir-ozet' => 'Online İlahiyat Yayınları',
        'riyazu' => 'Nevevî Külliyatı / Online İlahiyat',
        'muvatta' => 'Online İlahiyat Yayınları',
        'akaid-ders' => 'Online İlahiyat Kadrosu',
        'nahiv' => 'Dil Atölyesi',
        'usul' => 'Usul Serisi',
        'tecvid' => 'Kıraat Birimi',
        'siyer' => 'Siyer Okulu',
    ];
    return $map[$slug] ?? $author;
}

function catalog_related_book_slug(string $programSlug): string
{
    return match ($programSlug) {
        'tefsir' => 'tefsir-ozet',
        'hadis' => 'riyazu',
        'fikih' => 'usul',
        'akaid' => 'akaid-ders',
        'arapca' => 'nahiv',
        'kiraat', 'hafizlik' => 'tecvid',
        'vaizlik' => 'siyer',
        default => '',
    };
}

function catalog_interest_label(string $programSlug): string
{
    return match ($programSlug) {
        'tefsir' => 'Tefsir',
        'hadis' => 'Hadis',
        'fikih' => 'Fıkıh',
        'akaid' => 'Akaid',
        'arapca' => 'Arapça',
        'kiraat' => 'Kıraat',
        'hafizlik' => 'Hafızlık',
        'vaizlik' => 'Vaizlik',
        default => 'Tefsir',
    };
}

function catalog_seo_excerpt(string $text, int $len = 158): string
{
    $one = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if (mb_strlen($one) <= $len) {
        return $one;
    }
    return rtrim(mb_substr($one, 0, $len - 1), " \t\n\r.,;:") . '…';
}

/** @return array<string,string> */
function catalog_program_copy(): array
{
    return [
        'tefsir' => "Kur’an-ı Kerim’i meal, usul ve klasik şerh üzerinden adım adım okuyan yıllık bir ihtisas programıdır. Haftalık canlı derslerde âyetler nüzul bağlamı ve dil incelikleriyle ele alınır; evde kayıt izlenir, ödev ve koçlukla takip sürer.\n\n"
            . "İlk dönemde tefsir usulü, nüzul ortamı, muhkem-müteşabih ve meal-tefsir farkı işlenir. İkinci dönemde Taberî, Zemahşerî ve İbn Kesîr hattından seçme metinler okunur; Bakara ve Âl-i İmrân üzerinden uygulamalı tefsir yapılır.\n\n"
            . "Sınıflar en fazla 10 kişidir. Ders kayıtları öğrenci paneline düşer; takıldığınız yerde hocaya mesaj atabilir, haftalık koçlukta ezber ve okuma planınızı gözden geçirirsiniz. Kaynak kitap önerisi mağazadan sepete eklenir.\n\n"
            . "Hedef meal ezberi değil; âyeti doğru anlamak, tefsir diline alışmak ve ilahiyat okumalarına sağlam bir zemin kurmaktır.",
        'hadis' => "Hadis usulü, ricâl ve temel külliyat üzerinden sünneti tanıyan yıllık bir ihtisas programıdır. Derslerde isnad ve metin birlikte okunur; Riyazü’s-Salihin ve seçme külliyat parçaları şerh edilir.\n\n"
            . "İlk ay usul kavramları — sahih, hasen, zayıf, mütevatir — ve ricâl diline alışılır. Sonra Buhârî, Müslim ve Muvatta hattından ders metinleri işlenir; her hafta ezber ve kısa şerh ödevi verilir.\n\n"
            . "Küçük grupta hoca her öğrencinin okumasını duyar. Kayıtlar paneldedir; koçlukta ezber listeniz ve tekrar takviminiz birlikte planlanır. Hadis programı, tefsir ve fıkıh okumalarına da isnad disiplini kazandırır.\n\n"
            . "Amaç ezber yarışı değil; metni güvenilir şekilde okumak, râvi dilini tanımak ve sünneti günlük hayata ölçü olarak taşımaktır.",
        'fikih' => "İbadet, aile ve muamelat fıkhını mezhep diliyle ve güncel meselelerle okuyan bir programdır. Temel dönemde namaz, oruç, zekât ve hac; ihtisasta alışveriş, nikâh ve çağdaş fıkhî sorular işlenir.\n\n"
            . "Dersler furû ve usulü birlikte yürütür: delil, illet ve ihtilaf adabı gösterilir. Mezhep mukayesesi polemik için değil, hükmü yerli yerinde anlamak içindir. Her hafta kısa mesele çözümü ödevi vardır.\n\n"
            . "Koçlukta takıldığınız bab tekrar edilir; canlı derste en fazla 10 kişilik sınıfta söz alırsınız. Kayıtlar panele düşer, kaynak kitap mağazadan önerilir.\n\n"
            . "Yıllık tempo, ilahiyat öğrencisi ve meslek içi tekrar yapmak isteyenler için kurulmuştur.",
        'akaid' => "Ehl-i sünnet itikadını kelam tarihi ve güncel inanç soruları eşliğinde okuyan temel bir programdır. Tevhid, nübüvvet, âhiret ve kader konuları klasik metinlerle işlenir.\n\n"
            . "Derslerde kavram ezberi değil, soruya cevap verme dili hedeflenir. Şüphe ve itirazlar sınıfta konuşulur; hocanın yönlendirmesiyle kısa okuma listesi verilir. Kelam ıstılahları haftalık ödevle pekiştirilir.\n\n"
            . "Haftada 4 saatlik tempo, çalışan ve üniversite öğrencisine uygundur. Kayıt ve ödev paneldedir; küçük grupta herkes söz alır.\n\n"
            . "Program, inancı sade ve delilli anlatmayı; aşırı tartışmadan uzak, ölçülü bir itikad dili kurmayı amaçlar.",
        'arapca' => "Sarf, nahiv ve metin okumayı ilahiyat Arapçasına götüren yoğun bir dil programıdır. A1’den B2’ye kadar kademeli ilerlenir; her hafta 8 saat canlı ders ve ev ödevi vardır.\n\n"
            . "İlk dönemde vezin, i‘rab ve isim cümlesi yerleşir. İkinci dönemde kısa tefsir ve hadis cümleleri okunur; sözlük kullanma ve i‘rab alışkanlığı kazandırılır. Hocanın düzeltmesi derste yapılır.\n\n"
            . "Sınıflar küçüktür. Kayıt izlenir, koçlukta zayıf vezinler tekrar edilir. Nahiv pratik kitabı mağazadan sepete eklenebilir.\n\n"
            . "Hedef konuşma kursu değil; ilahiyat metnini sözlükle çözebilecek nahiv ve sarf omurgası kurmaktır.",
        'kiraat' => "Tecvid, mahreç ve kıraat usulünü küçük grupta çalıştıran bir programdır. En fazla 8 kişilik canlı sınıfta her öğrenci okur; hoca anında düzeltir.\n\n"
            . "Derslerde harf sıfatları, medler, idğam ve vakıf-ibtida işlenir. İsteyenler için aşere ve takrib yönüne giriş yapılır; hedef düzgün ve güvenli tilavettir.\n\n"
            . "Haftada 3 saatlik tempo, hafızlık ve imam-hatip öğrencisine uygundur. Kayıtlar dinleme için paneldedir; ödev kısa okuma parçalarıdır.\n\n"
            . "Koçlukta mahreç ve med hataları tek tek işaretlenir. Tecvid Atlası, dersin yanında tutulacak görsel kaynaktır.",
        'hafizlik' => "Ezber planı, dinletme ve tekrar takvimini birlikte yürüten birebir + grup takibidir. Yeni sayfa, pekiştirme ve unutulan âyetler haftalık koçlukta ayrılır.\n\n"
            . "Grup seansında ortak okuma ve motivasyon; birebirde dinletme vardır. Hoca tempo ve sayfa hedefini sizin vaktinize göre kurar. Unutulan yerler tekrar listesine alınır.\n\n"
            . "Kayıt ve ödev listesi paneldedir. Kıraat düzeltmesi gerektiğinde Tecvid Atlası ve kıraat programı önerilir.\n\n"
            . "Amaç yarış temposu değil; sürdürülebilir ezber, düzgün tilavet ve düzenli tekrar disiplinidir.",
        'vaizlik' => "Hutbe, vaaz ve hitabeti metin yazımı ve sahne pratiğiyle çalıştıran ileri bir programdır. Konu seçimi, âyet-hadis isnadı, giriş-gelişme-sonuç ve süre yönetimi işlenir.\n\n"
            . "Her öğrenci kısa hutbe taslağı yazar; derste okur, hocadan ve gruptan geri bildirim alır. Dil sade, delil sağlam, üslup ölçülü tutulur.\n\n"
            . "Haftada 3 saatlik tempo, görevli ve gönüllü vaiz adayına uygundur. Kayıtlar paneldedir; ödev metin teslimidir.\n\n"
            . "Siyer ve tefsir okumaları kaynak olarak önerilir. Hedef minberde güvenli duruş ve dinleyiciyi yormayan, delilli bir hitabettir.",
    ];
}

/** @return array<string,string> */
function catalog_book_copy(): array
{
    return [
        'tefsir-ozet' => "Tefsir usulünün temel meselelerini ders temposuna uygun özetleyen bir el kitabıdır. Meal-tefsir farkı, nüzul ortamı, muhkem-müteşabih ve klasik müfessir hatları kısa başlıklarla anlatılır.\n\n"
            . "Tefsir programı öğrencileri için hazırlanmıştır; her bölüm sonunda tekrar soruları vardır. Âyet örnekleri Bakara ve Âl-i İmrân’dan seçilmiştir.\n\n"
            . "Basılı nüsha kargoyla gelir. Notlarınızı derste işaretleyerek kullanın; dijital seçenek sepette ayrıca belirlenebilir.",
        'riyazu' => "Riyazü’s-Salihin’den derslerde okunan hadislerin seçme metin ve kısa şerhidir. Niyet, ihlas, ilim ve edep babları öne çıkarılır.\n\n"
            . "Hadis programı ve ev halkı okuması için sadeleştirilmiştir. İsnad notları dipnottadır; metin ezberi için satır aralığı geniştir.\n\n"
            . "Nevevî’nin tertibi korunmuş, şerh cümleleri ders diline çekilmiştir. Basılı stok sınırlıdır.",
        'muvatta' => "İmam Mâlik’in Muvatta’sından seçme babların ders notu ve şerh denemesidir. Medine ameli, hadis-fıkıh ilişkisi ve amelin delil değeri vurgulanır.\n\n"
            . "Hadis ve fıkıh öğrencisine köprü metindir. Her babın sonunda kısa mesele sorusu bulunur; hoca bunları ödev olarak da kullanabilir.\n\n"
            . "Basılı cilt, ders sırasında işaretlemeye uygundur. 500₺ üzeri siparişlerde kargo sepet kurallarına göre ücretsizdir.",
        'akaid-ders' => "Akaid derslerinin slayt ve notlarının derlenmiş dijital nüshasıdır. İman esasları, kelam ıstılahları ve sık sorulan şüpheler maddeler hâlinde işlenir.\n\n"
            . "PDF ödeme sonrası öğrenci paneline düşer; basılı kargo yoktur. Akaid programıyla birlikte okunması önerilir.\n\n"
            . "Kısa maddeler, ezber kartı gibi kullanılabilir. Güncel sorular dipnotta işaretlenmiştir.",
        'nahiv' => "Nahiv kaidelerini alıştırma ağırlıklı anlatan pratik bir kitaptır. Mübteda-haber, fâil-mef‘ûl ve i‘rab cetvelleri her ünitede tekrar edilir.\n\n"
            . "Klasik Arapça programının ilk dönem kaynağıdır. Cümle çözümleri boşluk doldurma ve i‘rab satırıyla pekiştirilir.\n\n"
            . "Basılı nüsha defter gibi kullanılır. Stok azaldığında yeni baskı bekletilebilir; erken sipariş önerilir.",
        'usul' => "Fıkıh usulüne giriş niteliğindedir. Delil türleri, emir-nehiy, umum-husus, nesh ve kıyas başlıkları sade dilde anlatılır.\n\n"
            . "Fıkıh programının teorik omurgasıdır. Her bölümde kısa mesele uygulaması vardır; furû ile usul birbirine bağlanır.\n\n"
            . "Basılı kitap, ders notunun yanına konmak için tasarlandı. Kavram dizini arka kısımdadır.",
        'tecvid' => "Mahreç, sıfat, med ve idğam konularını şema ve tabloyla gösteren bir atlasdır. Az sayfa, çok görsel; ders sırasında masada açık durur.\n\n"
            . "Kıraat ve hafızlık takibinde yanınıza alın. Harf çıkış yerleri renkli şemayla işaretlenmiştir.\n\n"
            . "Baskı adedi sınırlıdır. Stok ikiye düştüğünde yeni siparişi ertelemeyin.",
        'siyer' => "Siyer-i Nebi’nin Mekke ve Medine dönemini özetleyen ders kitabıdır. Gazve, seriyye ve ahlâk başlıkları vaaz için işaretlenmiştir.\n\n"
            . "Vaizlik programı ve genel okur için sadeleştirilmiştir. Kronoloji tablosu arka kapaktadır; hutbe konusu seçerken işe yarar.\n\n"
            . "Basılı nüsha kargoyla gelir. Siyer okuması tefsir ve hadis derslerine de zemin olur.",
    ];
}

function render_installment_table(int $price): void
{
    $rows = installment_rows($price);
    ?>
    <div class="card overflow-hidden">
      <div class="bg-soft px-6 py-5">
        <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-accent">Ödeme planı</p>
        <h2 class="font-display mt-1 text-2xl">Taksit seçenekleri</h2>
        <p class="mt-2 text-sm text-muted">Örnek taksit; kesin tutar banka kampanyasına göredir.</p>
      </div>
      <table class="table">
        <thead>
          <tr><th>Plan</th><th>Aylık</th><th>Toplam</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
          <tr>
            <td class="font-extrabold"><?= e($row['label']) ?></td>
            <td><?= $row['n'] === 1 ? '—' : money($row['monthly']) ?></td>
            <td class="price-now"><?= money($price) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
}
