USE online_ilahiyat;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS live_schedule (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id INT UNSIGNED NOT NULL,
  teacher_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  topic VARCHAR(160) DEFAULT NULL,
  starts_at DATETIME NOT NULL,
  duration_min SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  status ENUM('planlandi','canli','bitti','iptal') NOT NULL DEFAULT 'planlandi',
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ls_starts (starts_at),
  KEY idx_ls_group (group_id),
  KEY idx_ls_teacher (teacher_id),
  CONSTRAINT fk_ls_grp FOREIGN KEY (group_id) REFERENCES class_groups(id),
  CONSTRAINT fk_ls_teach FOREIGN KEY (teacher_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Tefsir A (1), Arapça A (3), Hadis 1 (5) — bu hafta / gelecek hafta
INSERT IGNORE INTO live_schedule (id, group_id, teacher_id, title, topic, starts_at, duration_min, status, note) VALUES
(1, 1, 2, 'Tefsir A', 'Bakara 21-29', '2026-08-22 22:00:00', 60, 'planlandi', 'Cumartesi tekrar'),
(2, 1, 2, 'Tefsir A', 'Bakara 30-39', '2026-08-24 20:00:00', 90, 'planlandi', 'Pzt 20:00'),
(3, 1, 2, 'Tefsir A', 'Usul notu: nüzul', '2026-08-26 20:00:00', 90, 'planlandi', 'Çar 20:00'),
(4, 1, 2, 'Tefsir A', 'Bakara 40-46', '2026-08-31 20:00:00', 90, 'planlandi', 'Gelecek Pzt'),
(5, 3, 4, 'Arapça A', 'Mübteda-haber tekrarı', '2026-08-24 19:30:00', 90, 'planlandi', 'Pzt 19:30'),
(6, 3, 4, 'Arapça A', 'İsim cümlesi alıştırması', '2026-08-27 19:30:00', 90, 'planlandi', 'Per 19:30'),
(7, 3, 4, 'Arapça A', 'Sıfat tamlaması', '2026-08-31 19:30:00', 90, 'planlandi', 'Gelecek Pzt'),
(8, 5, 3, 'Hadis 1', 'Riyazü’s-Salihin 13', '2026-08-25 21:00:00', 75, 'planlandi', 'Sal 21:00'),
(9, 5, 3, 'Hadis 1', 'Riyazü’s-Salihin 14', '2026-09-01 21:00:00', 75, 'planlandi', 'Gelecek Sal');
