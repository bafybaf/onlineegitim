USE online_ilahiyat;

SET NAMES utf8mb4;

SET @db := DATABASE();

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE class_groups ADD COLUMN description TEXT NULL AFTER days',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'class_groups' AND COLUMN_NAME = 'description'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
