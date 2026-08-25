-- Eski taslak. Çalışan kurulumda migrate_uyelik_paytr.sql kullanın; bunu tekrar çalıştırmayın.
USE online_ilahiyat;

CREATE TABLE IF NOT EXISTS packages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  program_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  duration_days SMALLINT UNSIGNED NOT NULL,
  price INT UNSIGNED NOT NULL,
  auto_delete TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_pkg_prog FOREIGN KEY (program_id) REFERENCES programs(id)
) ENGINE=InnoDB;

INSERT INTO packages (id, program_id, name, duration_days, price, auto_delete) VALUES
(1, 1, 'Tefsir yıllık', 365, 12600, 1),
(2, 2, 'Hadis yıllık', 365, 11550, 1),
(3, 5, 'Arapça yıllık', 365, 15400, 1),
(4, 6, 'Kıraat 90 gün', 90, 8400, 1),
(5, 1, 'Tefsir 30 gün deneme', 30, 2500, 1)
ON DUPLICATE KEY UPDATE name=VALUES(name);

ALTER TABLE enrollments
  ADD COLUMN package_id INT UNSIGNED NULL AFTER group_id,
  ADD COLUMN started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER progress,
  ADD COLUMN expires_at DATETIME NULL AFTER started_at,
  ADD COLUMN status ENUM('aktif','suresi_doldu','silindi') NOT NULL DEFAULT 'aktif' AFTER expires_at;

UPDATE enrollments SET package_id = CASE group_id
  WHEN 1 THEN 1 WHEN 2 THEN 1 WHEN 3 THEN 3 WHEN 4 THEN 3 WHEN 5 THEN 2 WHEN 6 THEN 4 ELSE 1 END,
  started_at = NOW(),
  expires_at = DATE_ADD(NOW(), INTERVAL 365 DAY),
  status = 'aktif';

UPDATE enrollments SET expires_at = DATE_SUB(NOW(), INTERVAL 2 DAY), package_id = 5
WHERE student_id = 10 AND group_id = 5;

ALTER TABLE live_rooms
  ADD COLUMN stream_key VARCHAR(80) NULL AFTER yoklama,
  ADD COLUMN broadcasting TINYINT(1) NOT NULL DEFAULT 0 AFTER stream_key;

UPDATE live_rooms SET stream_key = CONCAT('live-', id, '-', LOWER(SUBSTRING(MD5(CONCAT(id, teacher_id, RAND())), 1, 10)))
WHERE stream_key IS NULL OR stream_key = '';
