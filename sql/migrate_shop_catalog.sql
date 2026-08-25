USE online_ilahiyat;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @db := DATABASE();

CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(80) NOT NULL,
  sort SMALLINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (slug, name, sort)
SELECT 'tefsir', 'Tefsir', 10 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'tefsir' OR name = 'Tefsir');
INSERT INTO categories (slug, name, sort)
SELECT 'hadis', 'Hadis', 20 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'hadis' OR name = 'Hadis');
INSERT INTO categories (slug, name, sort)
SELECT 'fikih', 'Fıkıh', 30 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'fikih' OR name = 'Fıkıh');
INSERT INTO categories (slug, name, sort)
SELECT 'akaid', 'Akaid', 40 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'akaid' OR name = 'Akaid');
INSERT INTO categories (slug, name, sort)
SELECT 'arapca', 'Arapça', 50 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'arapca' OR name = 'Arapça');
INSERT INTO categories (slug, name, sort)
SELECT 'kiraat', 'Kıraat', 60 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'kiraat' OR name = 'Kıraat');
INSERT INTO categories (slug, name, sort)
SELECT 'siyer', 'Siyer', 70 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'siyer' OR name = 'Siyer');

INSERT INTO categories (slug, name, sort)
SELECT LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(b.category, 'ı', 'i'), 'İ', 'i'), 'ş', 's'), 'ğ', 'g'), 'ü', 'u'), 'ö', 'o')),
       b.category,
       80
FROM (SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category <> '') b
WHERE NOT EXISTS (SELECT 1 FROM categories c WHERE c.name = b.category OR c.slug = LOWER(b.category));

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE books ADD COLUMN category_id INT UNSIGNED NULL AFTER category',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'books' AND COLUMN_NAME = 'category_id'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE books ADD COLUMN description TEXT NULL',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'books' AND COLUMN_NAME = 'description'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE books ADD COLUMN cover VARCHAR(255) NULL',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'books' AND COLUMN_NAME = 'cover'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE books ADD COLUMN stock INT NOT NULL DEFAULT 0',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'books' AND COLUMN_NAME = 'stock'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE books ADD COLUMN is_digital TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'books' AND COLUMN_NAME = 'is_digital'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE books ADD COLUMN price INT UNSIGNED NOT NULL DEFAULT 1',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'books' AND COLUMN_NAME = 'price'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE books ADD COLUMN price_old INT UNSIGNED NOT NULL DEFAULT 0',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'books' AND COLUMN_NAME = 'price_old'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE books ADD CONSTRAINT fk_book_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL',
    'SELECT 1'
  )
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'books' AND CONSTRAINT_NAME = 'fk_book_cat'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE books b
JOIN categories c ON c.name = b.category
SET b.category_id = c.id
WHERE b.category_id IS NULL AND b.category IS NOT NULL AND b.category <> '';

CREATE TABLE IF NOT EXISTS campaigns (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(160) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  description TEXT NULL,
  type ENUM('yuzde','tutar','kargo') NOT NULL DEFAULT 'yuzde',
  discount_value INT UNSIGNED NOT NULL DEFAULT 0,
  code VARCHAR(40) NULL,
  applies_to ENUM('all','category','book') NOT NULL DEFAULT 'all',
  category_id INT UNSIGNED NULL,
  book_id INT UNSIGNED NULL,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  KEY idx_camp_code (code),
  KEY idx_camp_active (active, starts_at, ends_at),
  CONSTRAINT fk_camp_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_camp_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO campaigns (title, slug, description, type, discount_value, code, applies_to, starts_at, ends_at, active)
SELECT 'Mağaza kuponu', 'ilh10',
       'Sepette ILH10 kodunu girince tüm kitaplarda yüzde 10 indirim.',
       'yuzde', 10, 'ILH10', 'all', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM campaigns WHERE slug = 'ilh10' OR code = 'ILH10');

INSERT INTO campaigns (title, slug, description, type, discount_value, code, applies_to, category_id, starts_at, ends_at, active)
SELECT 'Erken kayıt kitap', 'erken-kayit-kitap',
       'Tefsir kitaplarında erken kayıt indirimi. Sepete ekleyince otomatik uygulanır.',
       'yuzde', 15, NULL, 'category',
       (SELECT id FROM categories WHERE slug = 'tefsir' LIMIT 1),
       NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM campaigns WHERE slug = 'erken-kayit-kitap');
