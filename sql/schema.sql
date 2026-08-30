-- Docker / Coolify otomatik kurulum. DROP DATABASE yok — mevcut DB kullanılır.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('ogrenci','ogretmen','admin','musteri') NOT NULL DEFAULT 'ogrenci',
  phone VARCHAR(30) DEFAULT NULL,
  city VARCHAR(80) DEFAULT NULL,
  bio VARCHAR(255) DEFAULT NULL,
  avatar VARCHAR(255) DEFAULT NULL,
  google_id VARCHAR(64) DEFAULT NULL,
  status ENUM('aktif','pasif','bekliyor') NOT NULL DEFAULT 'aktif',
  membership_expires_at DATETIME NULL,
  slug VARCHAR(80) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_google (google_id)
) ENGINE=InnoDB;

CREATE TABLE addresses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(40) NOT NULL DEFAULT 'Ev',
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  city VARCHAR(80) NOT NULL,
  district VARCHAR(80) DEFAULT NULL,
  `line` VARCHAR(255) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_addr_user (user_id),
  CONSTRAINT fk_addr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE programs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  title VARCHAR(160) NOT NULL,
  level VARCHAR(80) NOT NULL,
  hours VARCHAR(80) NOT NULL,
  price_old INT UNSIGNED NOT NULL,
  price_now INT UNSIGNED NOT NULL,
  tag VARCHAR(80) NOT NULL,
  description TEXT NOT NULL,
  image VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE class_groups (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  program_id INT UNSIGNED NOT NULL,
  teacher_id INT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  days VARCHAR(80) NOT NULL,
  description TEXT NULL,
  whatsapp_url VARCHAR(255) NULL,
  cap TINYINT UNSIGNED NOT NULL DEFAULT 10,
  CONSTRAINT fk_g_prog FOREIGN KEY (program_id) REFERENCES programs(id),
  CONSTRAINT fk_g_teach FOREIGN KEY (teacher_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE packages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('magaza','ders') NOT NULL DEFAULT 'ders',
  program_id INT UNSIGNED NULL,
  default_group_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  duration_days SMALLINT UNSIGNED NOT NULL,
  price INT UNSIGNED NOT NULL,
  auto_delete TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  access_type VARCHAR(32) NOT NULL DEFAULT 'canli_video',
  gift_book_id INT UNSIGNED NULL,
  CONSTRAINT fk_pkg_prog FOREIGN KEY (program_id) REFERENCES programs(id),
  CONSTRAINT fk_pkg_grp FOREIGN KEY (default_group_id) REFERENCES class_groups(id)
) ENGINE=InnoDB;

CREATE TABLE enrollments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  group_id INT UNSIGNED NOT NULL,
  progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
  package_id INT UNSIGNED NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  status ENUM('aktif','pasif','suresi_doldu','silindi') NOT NULL DEFAULT 'aktif',
  UNIQUE KEY uq_enroll (student_id, group_id),
  CONSTRAINT fk_e_stu FOREIGN KEY (student_id) REFERENCES users(id),
  CONSTRAINT fk_e_grp FOREIGN KEY (group_id) REFERENCES class_groups(id),
  CONSTRAINT fk_e_pkg FOREIGN KEY (package_id) REFERENCES packages(id)
) ENGINE=InnoDB;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(80) NOT NULL,
  sort SMALLINT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE books (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  title VARCHAR(160) NOT NULL,
  author VARCHAR(160) NOT NULL,
  category VARCHAR(80) NOT NULL,
  category_id INT UNSIGNED NULL,
  price INT UNSIGNED NOT NULL,
  price_old INT UNSIGNED NOT NULL,
  color VARCHAR(16) NOT NULL,
  cover VARCHAR(255) DEFAULT NULL,
  stock INT NOT NULL DEFAULT 0,
  is_digital TINYINT(1) NOT NULL DEFAULT 0,
  description TEXT,
  pages SMALLINT UNSIGNED DEFAULT NULL,
  publisher VARCHAR(160) DEFAULT NULL,
  CONSTRAINT fk_book_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE campaigns (
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
) ENGINE=InnoDB;

CREATE TABLE orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  total INT UNSIGNED NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'Hazırlanıyor',
  ship_mode VARCHAR(40) NOT NULL DEFAULT 'kargo',
  coupon VARCHAR(40) DEFAULT NULL,
  merchant_oid VARCHAR(64) DEFAULT NULL,
  pay_status VARCHAR(20) NOT NULL DEFAULT 'odendi',
  address_id INT UNSIGNED NULL,
  ship_name VARCHAR(120) NULL,
  ship_phone VARCHAR(30) NULL,
  ship_city VARCHAR(80) NULL,
  ship_district VARCHAR(80) NULL,
  ship_line VARCHAR(255) NULL,
  admin_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_o_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_o_addr FOREIGN KEY (address_id) REFERENCES addresses(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  book_id INT UNSIGNED NOT NULL,
  qty INT UNSIGNED NOT NULL DEFAULT 1,
  price INT UNSIGNED NOT NULL,
  CONSTRAINT fk_oi_ord FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_book FOREIGN KEY (book_id) REFERENCES books(id)
) ENGINE=InnoDB;

CREATE TABLE student_books (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  book_id INT UNSIGNED NOT NULL,
  status VARCHAR(80) NOT NULL,
  kind VARCHAR(40) NOT NULL,
  CONSTRAINT fk_sb_u FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_sb_b FOREIGN KEY (book_id) REFERENCES books(id)
) ENGINE=InnoDB;

CREATE TABLE live_rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT UNSIGNED NOT NULL,
  group_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  topic VARCHAR(160) NOT NULL,
  status ENUM('live','ended') NOT NULL DEFAULT 'live',
  record TINYINT(1) NOT NULL DEFAULT 1,
  yoklama TINYINT(1) NOT NULL DEFAULT 1,
  stream_key VARCHAR(80) NULL,
  play_mode VARCHAR(20) NOT NULL DEFAULT 'browser',
  broadcasting TINYINT(1) NOT NULL DEFAULT 0,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME DEFAULT NULL,
  UNIQUE KEY uq_live_stream_key (stream_key),
  CONSTRAINT fk_lr_t FOREIGN KEY (teacher_id) REFERENCES users(id),
  CONSTRAINT fk_lr_g FOREIGN KEY (group_id) REFERENCES class_groups(id)
) ENGINE=InnoDB;

CREATE TABLE live_board (
  room_id INT UNSIGNED PRIMARY KEY,
  pdf_path VARCHAR(255) NOT NULL DEFAULT '',
  page INT UNSIGNED NOT NULL DEFAULT 1,
  pages INT UNSIGNED NOT NULL DEFAULT 0,
  zoom DECIMAL(5,2) NOT NULL DEFAULT 1,
  pan_x DECIMAL(6,3) NOT NULL DEFAULT 0,
  pan_y DECIMAL(6,3) NOT NULL DEFAULT 0,
  strokes MEDIUMTEXT NOT NULL,
  rev INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE live_schedule (
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

CREATE TABLE live_chat (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED DEFAULT NULL,
  who_label VARCHAR(80) NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lc_r FOREIGN KEY (room_id) REFERENCES live_rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE attendance (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  present TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_att (room_id, student_id),
  CONSTRAINT fk_a_r FOREIGN KEY (room_id) REFERENCES live_rooms(id) ON DELETE CASCADE,
  CONSTRAINT fk_a_s FOREIGN KEY (student_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE homework (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  due_label VARCHAR(80) NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_hw_g FOREIGN KEY (group_id) REFERENCES class_groups(id)
) ENGINE=InnoDB;

CREATE TABLE homework_subs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  homework_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  body TEXT,
  file_path VARCHAR(255) NULL,
  status ENUM('open','sent','ok') NOT NULL DEFAULT 'open',
  UNIQUE KEY uq_hw (homework_id, student_id),
  CONSTRAINT fk_hs_h FOREIGN KEY (homework_id) REFERENCES homework(id) ON DELETE CASCADE,
  CONSTRAINT fk_hs_s FOREIGN KEY (student_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE tests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT UNSIGNED NOT NULL,
  group_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT,
  duration_min SMALLINT UNSIGNED DEFAULT NULL,
  status ENUM('taslak','yayinda') NOT NULL DEFAULT 'taslak',
  kind VARCHAR(20) NOT NULL DEFAULT 'quiz',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_test_teach FOREIGN KEY (teacher_id) REFERENCES users(id),
  CONSTRAINT fk_test_grp FOREIGN KEY (group_id) REFERENCES class_groups(id)
) ENGINE=InnoDB;

CREATE TABLE test_questions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  test_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  choice_a VARCHAR(500) NOT NULL,
  choice_b VARCHAR(500) NOT NULL,
  choice_c VARCHAR(500) NOT NULL,
  choice_d VARCHAR(500) NOT NULL,
  correct ENUM('a','b','c','d') NOT NULL,
  points SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_tq_test FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE test_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  test_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  score INT UNSIGNED NOT NULL DEFAULT 0,
  max_score INT UNSIGNED NOT NULL DEFAULT 0,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  submitted_at DATETIME DEFAULT NULL,
  UNIQUE KEY uq_test_stu (test_id, student_id),
  CONSTRAINT fk_ta_test FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
  CONSTRAINT fk_ta_stu FOREIGN KEY (student_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE test_answers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  choice ENUM('a','b','c','d') DEFAULT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_ans (attempt_id, question_id),
  CONSTRAINT fk_tans_att FOREIGN KEY (attempt_id) REFERENCES test_attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_tans_q FOREIGN KEY (question_id) REFERENCES test_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  thread_user_id INT UNSIGNED NOT NULL,
  from_user_id INT UNSIGNED NOT NULL,
  teacher_id INT UNSIGNED NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_m_th FOREIGN KEY (thread_user_id) REFERENCES users(id),
  CONSTRAINT fk_m_fr FOREIGN KEY (from_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE recordings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id INT UNSIGNED NOT NULL,
  teacher_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  mins SMALLINT UNSIGNED NOT NULL,
  recorded_on DATE NOT NULL,
  video_url VARCHAR(500) NULL,
  video_path VARCHAR(255) NULL,
  CONSTRAINT fk_rec_g FOREIGN KEY (group_id) REFERENCES class_groups(id)
) ENGINE=InnoDB;

CREATE TABLE lesson_notes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id INT UNSIGNED NOT NULL,
  teacher_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE certificates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  group_id INT UNSIGNED NOT NULL,
  code VARCHAR(40) NOT NULL UNIQUE,
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  progress TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  body VARCHAR(400) NOT NULL,
  link VARCHAR(255) NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(120) NOT NULL UNIQUE,
  title VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  published TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE leads (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  interest VARCHAR(80) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE contacts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL,
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE home_slides (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  badge VARCHAR(80) NOT NULL DEFAULT '',
  title VARCHAR(200) NOT NULL,
  title_accent VARCHAR(200) NOT NULL DEFAULT '',
  accent_class VARCHAR(16) NOT NULL DEFAULT 'accent',
  body TEXT NOT NULL,
  btn1_label VARCHAR(80) NOT NULL DEFAULT '',
  btn1_url VARCHAR(255) NOT NULL DEFAULT '',
  btn2_label VARCHAR(80) NOT NULL DEFAULT '',
  btn2_url VARCHAR(255) NOT NULL DEFAULT '',
  btn2_kind VARCHAR(16) NOT NULL DEFAULT 'link',
  image VARCHAR(255) NOT NULL DEFAULT '',
  alt VARCHAR(160) NOT NULL DEFAULT '',
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort SMALLINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE home_highlights (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mark VARCHAR(16) NOT NULL DEFAULT '',
  label VARCHAR(160) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort SMALLINT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE settings (
  k VARCHAR(80) NOT NULL PRIMARY KEY,
  v TEXT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  merchant_oid VARCHAR(64) NOT NULL UNIQUE,
  user_id INT UNSIGNED NOT NULL,
  kind ENUM('kitap','program','uyelik_magaza','uyelik_ders') NOT NULL,
  total INT UNSIGNED NOT NULL,
  status ENUM('bekliyor','odendi','basarisiz') NOT NULL DEFAULT 'bekliyor',
  order_id INT UNSIGNED NULL,
  program_id INT UNSIGNED NULL,
  group_id INT UNSIGNED NULL,
  package_id INT UNSIGNED NULL,
  ship_mode VARCHAR(40) NULL,
  coupon VARCHAR(40) NULL,
  basket_json TEXT NOT NULL,
  address_id INT UNSIGNED NULL,
  address_json TEXT NULL,
  fail_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at DATETIME NULL,
  CONSTRAINT fk_pay_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_pay_ord FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_prog FOREIGN KEY (program_id) REFERENCES programs(id),
  CONSTRAINT fk_pay_grp FOREIGN KEY (group_id) REFERENCES class_groups(id),
  CONSTRAINT fk_pay_pkg FOREIGN KEY (package_id) REFERENCES packages(id),
  CONSTRAINT fk_pay_addr FOREIGN KEY (address_id) REFERENCES addresses(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO settings (k, v) VALUES
('paytr_merchant_id', ''),
('paytr_merchant_key', ''),
('paytr_merchant_salt', ''),
('paytr_test_mode', '1'),
('paytr_debug', '1'),
('paytr_no_installment', '0'),
('paytr_max_installment', '0'),
('paytr_iframe_v2', '1'),
('paytr_ssl_verify', '1'),
('paytr_public_ip', '76.13.14.253'),
('site_url', 'https://onlineilahiyat.com'),
('seo_site_title', 'Online İlahiyat'),
('seo_title_suffix', ' | Online İlahiyat'),
('seo_default_description', 'Tefsir, hadis, fıkıh, Arapça canlı dersleri ve kitap mağazası.'),
('seo_keywords', 'online ilahiyat, tefsir, hadis, fıkıh, arapça, canlı ders'),
('seo_og_image', 'assets/img/hero-cami.jpg'),
('seo_robots', 'index,follow'),
('seo_google_analytics', ''),
('seo_google_site_verification', ''),
('seo_canonical_base', 'https://onlineilahiyat.com'),
('seo_home_title', ''),
('seo_home_description', ''),
('seo_home_h1', ''),
('smtp_enabled', '0'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_user', ''),
('smtp_pass', ''),
('smtp_encryption', 'tls'),
('smtp_from_email', ''),
('smtp_from_name', 'Online İlahiyat'),
('smtp_to_email', 'info@onlineilahiyat.com'),
('google_enabled', '0'),
('google_client_id', ''),
('google_client_secret', ''),
('live_host', 'onlineilahiyat.com');

-- Yönetici hesabı Docker/Coolify ADMIN_EMAIL + ADMIN_PASSWORD ile veya /kurulum sayfasından oluşur.

INSERT IGNORE INTO programs (id, slug, title, level, hours, price_old, price_now, tag, description, image) VALUES
(1, 'tefsir', 'Tefsir Programı', 'İlahiyat / İhtisas', '6 saat / hafta', 18000, 12600, '%30 erken kayıt', 'Kur’an-ı Kerim’i meal, usul ve klasik şerh üzerinden okuyan yıllık ihtisas. Canlı ders, kayıt, koçluk ve küçük grup.', 'assets/img/programs/tefsir.jpg'),
(2, 'hadis', 'Hadis Programı', 'İhtisas', '5 saat / hafta', 16500, 11550, 'Riyazü’s-Salihin', 'Hadis usulü, ricâl ve külliyat. Riyazü’s-Salihin şerhi, ezber ve metin okuma.', 'assets/img/programs/hadis.jpg'),
(3, 'fikih', 'Fıkıh Programı', 'Temel + İhtisas', '6 saat / hafta', 19000, 13300, 'İbadet & muamelat', 'İbadet, aile ve muamelat fıkhı. Mezhep mukayesesi, usul ve güncel meseleler.', 'assets/img/programs/fikih.jpg'),
(4, 'akaid', 'Akaid & Kelam', 'Temel', '4 saat / hafta', 14000, 9800, 'İtikad', 'Ehl-i sünnet itikadı, kelam tarihi ve güncel inanç soruları. Küçük grup, ödev ve koçluk.', 'assets/img/programs/akaid.jpg'),
(5, 'arapca', 'Klasik Arapça', 'A1–B2', '8 saat / hafta', 22000, 15400, 'Nahiv + sarf', 'Sarf, nahiv ve metin okuma. İlahiyat Arapçasına giden yoğun dil programı.', 'assets/img/programs/arapca.jpg'),
(6, 'kiraat', 'Kıraat & Tecvid', 'Tüm seviyeler', '3 saat / hafta', 12000, 8400, 'Küçük grup', 'Tecvid, mahreç ve kıraat usulü. En fazla 8 kişilik canlı sınıf.', 'assets/img/programs/kiraat.jpg'),
(7, 'hafizlik', 'Hafızlık Takibi', 'Birebir + grup', 'Haftalık takip', 15000, 10500, 'Ezber koçluğu', 'Ezber planı, dinletme seansları ve tekrar takvimi. Birebir + grup koçluk.', 'assets/img/programs/hafizlik.jpg'),
(8, 'vaizlik', 'Vaizlik & Hitabet', 'İleri', '3 saat / hafta', 11000, 7700, 'Minber', 'Hutbe, vaaz ve hitabet. Metin yazımı, sahne pratiği ve süre yönetimi.', 'assets/img/programs/vaizlik.jpg');

INSERT IGNORE INTO categories (id, slug, name, sort) VALUES
(1, 'tefsir', 'Tefsir', 10),
(2, 'hadis', 'Hadis', 20),
(3, 'fikih', 'Fıkıh', 30),
(4, 'akaid', 'Akaid', 40),
(5, 'arapca', 'Arapça', 50),
(6, 'kiraat', 'Kıraat', 60),
(7, 'siyer', 'Siyer', 70);

INSERT IGNORE INTO books (id, slug, title, author, category, category_id, price, price_old, color, cover, stock, is_digital, description, pages, publisher) VALUES
(1, 'tefsir-ozet', 'Tefsir Usulü El Kitabı', 'Online İlahiyat Yayınları', 'Tefsir', 1, 420, 560, '#1a3fad', 'assets/img/books/tefsir-ozet.jpg', 12, 0, 'Meal-tefsir farkı, nüzul ve klasik müfessir hatları. Tefsir programı için el kitabı.', 248, 'Online İlahiyat Yayınları'),
(2, 'riyazu', 'Riyazü’s-Salihin Seçmeler', 'Nevevî / Şerhli', 'Hadis', 2, 380, 490, '#0f2a7a', 'assets/img/books/riyazu.jpg', 4, 0, 'Riyazü’s-Salihin’den seçme metin ve kısa şerh. Niyet, ihlas, ilim ve edep.', 312, 'Nevevî Külliyatı / Online İlahiyat'),
(3, 'muvatta', 'Muvatta’dan Dersler', 'İmam Mâlik', 'Hadis', 2, 450, 590, '#0a1a4e', 'assets/img/books/muvatta.jpg', 9, 0, 'Muvatta’dan seçme bablar. Medine ameli ve hadis-fıkıh ilişkisi.', 276, 'Online İlahiyat Yayınları'),
(4, 'akaid-ders', 'Akaid Ders Notları', 'Kadromuz', 'Akaid', 4, 290, 360, '#12705a', 'assets/img/books/akaid-ders.jpg', 99, 1, 'İman esasları ve kelam ıstılahları. Dijital PDF, panele düşer.', 164, 'Online İlahiyat Kadrosu'),
(5, 'nahiv', 'Arapça Nahiv Pratik', 'Dil Atölyesi', 'Arapça', 5, 340, 430, '#0c5444', 'assets/img/books/nahiv.jpg', 7, 0, 'Mübteda-haber, fâil-mef‘ûl ve i‘rab alıştırmaları. Arapça program kaynağı.', 220, 'Dil Atölyesi'),
(6, 'usul', 'Fıkıh Usulü Giriş', 'Usul Serisi', 'Fıkıh', 3, 410, 520, '#1a3fad', 'assets/img/books/usul.jpg', 6, 0, 'Delil türleri, emir-nehiy, umum-husus ve kıyas. Fıkıh programı omurgası.', 198, 'Usul Serisi'),
(7, 'tecvid', 'Tecvid Atlası', 'Kıraat Birimi', 'Kıraat', 6, 260, 320, '#0f2a7a', 'assets/img/books/tecvid.jpg', 2, 0, 'Mahreç, sıfat, med ve idğam şemaları. Kıraat ve hafızlık için atlas.', 144, 'Kıraat Birimi'),
(8, 'siyer', 'Siyer-i Nebi Özeti', 'Siyer Okulu', 'Siyer', 7, 310, 390, '#0a1a4e', 'assets/img/books/siyer.jpg', 11, 0, 'Mekke-Medine siyer özeti. Hutbe ve vaaz için işaretli başlıklar.', 188, 'Siyer Okulu');

INSERT IGNORE INTO home_slides (id, badge, title, title_accent, accent_class, body, btn1_label, btn1_url, btn2_label, btn2_url, btn2_kind, image, alt, active, sort) VALUES
(1, '2026 sezon kayıtları açık', 'Evden canlı ilahiyat,', 'küçük grupta gerçek takip', 'accent', 'Tefsir, hadis, fıkıh ve Arapça. En fazla 10 kişilik sınıflar, haftalık koçluk ve takıldığınız yerde hoca desteği.', 'Canlı ders üyeliği al', 'kayit-ders', 'Sizi Arayalım', '', 'call', 'assets/img/hero-cami.jpg', 'İlahiyat eğitimi', 1, 10),
(2, 'Kitap mağazası', 'Dersin yanında', 'seçme ilahiyat kitapları', 'navy', 'Tefsir, hadis, fıkıh ve Arapça kaynakları. Sipariş panelinize düşer; kargo veya dijital erişim.', 'Mağazayı aç', 'kitaplar', 'Sepete bak', 'sepet', 'link', 'assets/img/hero-kitap.jpg', 'Kitap mağazası', 1, 20);

INSERT IGNORE INTO home_highlights (id, mark, label, active, sort) VALUES
(1, '10', 'En fazla 10 kişilik sınıf', 1, 10),
(2, '▶', 'Canlı ders + kayıt', 1, 20),
(3, '📚', 'Kitap mağazası', 1, 30),
(4, '✓', 'Ücretsiz tanışma', 1, 40);

SET FOREIGN_KEY_CHECKS = 1;
