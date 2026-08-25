USE online_ilahiyat;

SET @db := DATABASE();

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE users ADD COLUMN google_id VARCHAR(64) NULL',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'google_id'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'CREATE UNIQUE INDEX uq_users_google ON users (google_id)',
    'SELECT 1'
  )
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND INDEX_NAME = 'uq_users_google'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE packages SET active = 0 WHERE kind = 'magaza';
UPDATE users SET status = 'aktif' WHERE role = 'musteri' AND status = 'bekliyor';

INSERT INTO settings (k, v) VALUES
('google_enabled', '0'),
('google_client_id', ''),
('google_client_secret', '')
ON DUPLICATE KEY UPDATE k = k;
