USE online_ilahiyat;

SET @db := DATABASE();

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE books ADD COLUMN description TEXT NULL AFTER is_digital',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'books' AND COLUMN_NAME = 'description'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE books ADD COLUMN pages SMALLINT UNSIGNED NULL AFTER description',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'books' AND COLUMN_NAME = 'pages'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE books ADD COLUMN publisher VARCHAR(160) NULL AFTER pages',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'books' AND COLUMN_NAME = 'publisher'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE programs SET description = 'Kur’an-ı Kerim’i meal, usul ve klasik şerh üzerinden okuyan yıllık ihtisas. Canlı ders, kayıt, koçluk ve küçük grup.' WHERE slug = 'tefsir';
UPDATE programs SET description = 'Hadis usulü, ricâl ve külliyat. Riyazü’s-Salihin şerhi, ezber ve metin okuma.' WHERE slug = 'hadis';
UPDATE programs SET description = 'İbadet, aile ve muamelat fıkhı. Mezhep mukayesesi, usul ve güncel meseleler.' WHERE slug = 'fikih';
UPDATE programs SET description = 'Ehl-i sünnet itikadı, kelam tarihi ve güncel inanç soruları. Küçük grup, ödev ve koçluk.' WHERE slug = 'akaid';
UPDATE programs SET description = 'Sarf, nahiv ve metin okuma. İlahiyat Arapçasına giden yoğun dil programı.' WHERE slug = 'arapca';
UPDATE programs SET description = 'Tecvid, mahreç ve kıraat usulü. En fazla 8 kişilik canlı sınıf.' WHERE slug = 'kiraat';
UPDATE programs SET description = 'Ezber planı, dinletme seansları ve tekrar takvimi. Birebir + grup koçluk.' WHERE slug = 'hafizlik';
UPDATE programs SET description = 'Hutbe, vaaz ve hitabet. Metin yazımı, sahne pratiği ve süre yönetimi.' WHERE slug = 'vaizlik';

UPDATE books SET description = 'Meal-tefsir farkı, nüzul ve klasik müfessir hatları. Tefsir programı için el kitabı.', pages = 248, publisher = 'Online İlahiyat Yayınları' WHERE slug = 'tefsir-ozet';
UPDATE books SET description = 'Riyazü’s-Salihin’den seçme metin ve kısa şerh. Niyet, ihlas, ilim ve edep.', pages = 312, publisher = 'Nevevî Külliyatı / Online İlahiyat' WHERE slug = 'riyazu';
UPDATE books SET description = 'Muvatta’dan seçme bablar. Medine ameli ve hadis-fıkıh ilişkisi.', pages = 276, publisher = 'Online İlahiyat Yayınları' WHERE slug = 'muvatta';
UPDATE books SET description = 'İman esasları ve kelam ıstılahları. Dijital PDF, panele düşer.', pages = 164, publisher = 'Online İlahiyat Kadrosu' WHERE slug = 'akaid-ders';
UPDATE books SET description = 'Mübteda-haber, fâil-mef‘ûl ve i‘rab alıştırmaları. Arapça program kaynağı.', pages = 220, publisher = 'Dil Atölyesi' WHERE slug = 'nahiv';
UPDATE books SET description = 'Delil türleri, emir-nehiy, umum-husus ve kıyas. Fıkıh programı omurgası.', pages = 198, publisher = 'Usul Serisi' WHERE slug = 'usul';
UPDATE books SET description = 'Mahreç, sıfat, med ve idğam şemaları. Kıraat ve hafızlık için atlas.', pages = 144, publisher = 'Kıraat Birimi' WHERE slug = 'tecvid';
UPDATE books SET description = 'Mekke-Medine siyer özeti. Hutbe ve vaaz için işaretli başlıklar.', pages = 188, publisher = 'Siyer Okulu' WHERE slug = 'siyer';
