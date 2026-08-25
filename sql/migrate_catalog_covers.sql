USE online_ilahiyat;

SET @db := DATABASE();

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE books ADD COLUMN cover VARCHAR(255) NULL AFTER color',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'books' AND COLUMN_NAME = 'cover'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE programs ADD COLUMN image VARCHAR(255) NULL AFTER description',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'programs' AND COLUMN_NAME = 'image'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE books SET cover = CONCAT('assets/img/books/', slug, '.jpg')
WHERE (cover IS NULL OR cover = '') AND slug IN ('tefsir-ozet','riyazu','muvatta','akaid-ders','nahiv','usul','tecvid','siyer');

UPDATE programs SET image = CONCAT('assets/img/programs/', slug, '.jpg')
WHERE (image IS NULL OR image = '') AND slug IN ('tefsir','hadis','fikih','akaid','arapca','kiraat','hafizlik','vaizlik');
