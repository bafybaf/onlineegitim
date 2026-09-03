-- Sınav kategorilerine geçiş: eski ders kategorilerini kaldır, kitapları 3 ana kategoriye dağıt

-- 1. Yeni kategorileri ekle (yoksa)
INSERT IGNORE INTO categories (slug, name, sort) VALUES ('dkab-ihl', 'DKAB-İHL (2027)', 1);
INSERT IGNORE INTO categories (slug, name, sort) VALUES ('mbsts', 'MBSTS (2027)', 2);
INSERT IGNORE INTO categories (slug, name, sort) VALUES ('dhbt', 'DHBT YENİ GRUP', 3);

-- 2. Kitapları dağıt (eski kategori → yeni kategori)
-- Tefsir, Akaid, Siyer → DKAB-İHL
UPDATE books SET category_id = (SELECT id FROM categories WHERE slug = 'dkab-ihl'), category = 'DKAB-İHL (2027)'
WHERE category_id IN (SELECT id FROM categories WHERE slug IN ('tefsir', 'akaid', 'siyer'));

-- Hadis, Fıkıh → MBSTS
UPDATE books SET category_id = (SELECT id FROM categories WHERE slug = 'mbsts'), category = 'MBSTS (2027)'
WHERE category_id IN (SELECT id FROM categories WHERE slug IN ('hadis', 'fikih'));

-- Arapça, Kıraat → DHBT
UPDATE books SET category_id = (SELECT id FROM categories WHERE slug = 'dhbt'), category = 'DHBT YENİ GRUP'
WHERE category_id IN (SELECT id FROM categories WHERE slug IN ('arapca', 'kiraat'));

-- Kategorisiz kitapları DKAB-İHL'ye ata
UPDATE books SET category_id = (SELECT id FROM categories WHERE slug = 'dkab-ihl'), category = 'DKAB-İHL (2027)'
WHERE category_id IS NULL OR category_id NOT IN (SELECT id FROM categories WHERE slug IN ('dkab-ihl', 'mbsts', 'dhbt'));

-- 3. Eski kategorileri sil
DELETE FROM categories WHERE slug NOT IN ('dkab-ihl', 'mbsts', 'dhbt');
