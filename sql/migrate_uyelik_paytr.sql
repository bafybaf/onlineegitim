USE online_ilahiyat;

ALTER TABLE users
  MODIFY COLUMN status ENUM('aktif','pasif','bekliyor') NOT NULL DEFAULT 'aktif';

ALTER TABLE users
  ADD COLUMN membership_expires_at DATETIME NULL AFTER status;

ALTER TABLE payments
  MODIFY COLUMN kind ENUM('kitap','program','uyelik_magaza','uyelik_ders') NOT NULL;

CREATE TABLE IF NOT EXISTS packages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('magaza','ders') NOT NULL DEFAULT 'ders',
  program_id INT UNSIGNED NULL,
  default_group_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  duration_days SMALLINT UNSIGNED NOT NULL,
  price INT UNSIGNED NOT NULL,
  auto_delete TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_pkg_prog FOREIGN KEY (program_id) REFERENCES programs(id),
  CONSTRAINT fk_pkg_grp FOREIGN KEY (default_group_id) REFERENCES class_groups(id)
) ENGINE=InnoDB;

ALTER TABLE payments
  ADD COLUMN package_id INT UNSIGNED NULL AFTER group_id,
  ADD CONSTRAINT fk_pay_pkg FOREIGN KEY (package_id) REFERENCES packages(id);

ALTER TABLE enrollments
  ADD COLUMN package_id INT UNSIGNED NULL AFTER group_id,
  ADD COLUMN started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER progress,
  ADD COLUMN expires_at DATETIME NULL AFTER started_at,
  ADD COLUMN status ENUM('aktif','suresi_doldu','silindi') NOT NULL DEFAULT 'aktif' AFTER expires_at,
  ADD CONSTRAINT fk_e_pkg FOREIGN KEY (package_id) REFERENCES packages(id);

INSERT INTO packages (kind, program_id, default_group_id, name, duration_days, price, auto_delete, active)
SELECT 'magaza', NULL, NULL, 'Pasif mağaza paketi', 365, 0, 0, 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM packages WHERE kind = 'magaza' LIMIT 1);

INSERT INTO packages (kind, program_id, default_group_id, name, duration_days, price, auto_delete, active)
SELECT 'ders', g.program_id, g.id, CONCAT(g.name, ' - yıllık'), 365, p.price_now, 0, 1
FROM class_groups g
JOIN programs p ON p.id = g.program_id
WHERE NOT EXISTS (
  SELECT 1 FROM packages x WHERE x.kind = 'ders' AND x.default_group_id = g.id
);
