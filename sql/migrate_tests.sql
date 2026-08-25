USE online_ilahiyat;

CREATE TABLE IF NOT EXISTS tests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT UNSIGNED NOT NULL,
  group_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT,
  duration_min SMALLINT UNSIGNED DEFAULT NULL,
  status ENUM('taslak','yayinda') NOT NULL DEFAULT 'taslak',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_test_teach FOREIGN KEY (teacher_id) REFERENCES users(id),
  CONSTRAINT fk_test_grp FOREIGN KEY (group_id) REFERENCES class_groups(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS test_questions (
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

CREATE TABLE IF NOT EXISTS test_attempts (
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

CREATE TABLE IF NOT EXISTS test_answers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  choice ENUM('a','b','c','d') DEFAULT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_ans (attempt_id, question_id),
  CONSTRAINT fk_tans_att FOREIGN KEY (attempt_id) REFERENCES test_attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_tans_q FOREIGN KEY (question_id) REFERENCES test_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO tests (id, teacher_id, group_id, title, description, duration_min, status) VALUES
(1, 2, 1, 'Bakara 1–5 kısa test', 'Tefsir A grubu için yayınlanmış demo test. Her öğrenci bir kez çözer.', 15, 'yayinda'),
(2, 2, 1, 'Tefsir usulü (taslak)', 'Yayınlanmamış taslak — öğrenciler bunu görmez.', 20, 'taslak'),
(3, 4, 3, 'Nahiv: mübteda ve haber', 'Arapça A grubu için kısa kontrol testi.', 10, 'yayinda');

INSERT IGNORE INTO test_questions (id, test_id, body, choice_a, choice_b, choice_c, choice_d, correct, points, sort_order) VALUES
(1, 1, '“Elif Lâm Mîm” ifadesi hangi surenin başında geçer?', 'Fâtiha', 'Bakara', 'İhlâs', 'Nâs', 'b', 10, 1),
(2, 1, 'Tefsir ilminin temel konusu nedir?', 'Hadis ricali', 'Kur’an’ın anlaşılması ve açıklanması', 'Fıkıh usulü', 'Nahiv kaideleri', 'b', 10, 2),
(3, 1, 'Meal ile tefsir arasındaki fark hangisidir?', 'İkisi aynıdır', 'Meal kısa çeviri, tefsir açıklama ve yorumdur', 'Meal her zaman daha uzundur', 'Tefsir yalnızca kıraat ilmidir', 'b', 10, 3),
(4, 2, 'Tefsir usulü neyi inceler?', 'Tefsir yöntem ve kaidelerini', 'Yalnızca kıraat vecihlerini', 'Hadis metinlerini', 'Fıkıh furûunu', 'a', 10, 1),
(5, 3, 'İsim cümlesinde mübteda nedir?', 'Cümlenin haberi', 'İsim cümlesinin öznesi', 'Fiilin faili', 'Harf-i cer', 'b', 10, 1),
(6, 3, 'Haber, mübteda hakkında ne bildirir?', 'Yalnızca i‘rabını', 'Hüküm / yüklem', 'Kıraat vechini', 'Sarf veznini', 'b', 10, 2);

INSERT IGNORE INTO test_attempts (id, test_id, student_id, score, max_score, started_at, submitted_at) VALUES
(1, 1, 7, 20, 30, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT IGNORE INTO test_answers (id, attempt_id, question_id, choice, is_correct) VALUES
(1, 1, 1, 'b', 1),
(2, 1, 2, 'b', 1),
(3, 1, 3, 'a', 0);
