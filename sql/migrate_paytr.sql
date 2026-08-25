USE online_ilahiyat;

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(80) NOT NULL PRIMARY KEY,
  v TEXT NOT NULL
) ENGINE=InnoDB;

INSERT INTO settings (k, v) VALUES
('paytr_merchant_id', ''),
('paytr_merchant_key', ''),
('paytr_merchant_salt', ''),
('paytr_test_mode', '1'),
('paytr_debug', '1'),
('paytr_no_installment', '0'),
('paytr_max_installment', '0'),
('paytr_iframe_v2', '1'),
('paytr_ssl_verify', '1'),
('paytr_public_ip', ''),
('site_url', '')
ON DUPLICATE KEY UPDATE k = k;

CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  merchant_oid VARCHAR(64) NOT NULL UNIQUE,
  user_id INT UNSIGNED NOT NULL,
  kind ENUM('kitap','program') NOT NULL,
  total INT UNSIGNED NOT NULL,
  status ENUM('bekliyor','odendi','basarisiz') NOT NULL DEFAULT 'bekliyor',
  order_id INT UNSIGNED NULL,
  program_id INT UNSIGNED NULL,
  group_id INT UNSIGNED NULL,
  ship_mode VARCHAR(40) NULL,
  coupon VARCHAR(40) NULL,
  basket_json TEXT NOT NULL,
  fail_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at DATETIME NULL,
  CONSTRAINT fk_pay_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_pay_ord FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_prog FOREIGN KEY (program_id) REFERENCES programs(id),
  CONSTRAINT fk_pay_grp FOREIGN KEY (group_id) REFERENCES class_groups(id)
) ENGINE=InnoDB;

ALTER TABLE orders
  ADD COLUMN merchant_oid VARCHAR(64) NULL AFTER coupon,
  ADD COLUMN pay_status VARCHAR(20) NOT NULL DEFAULT 'odendi' AFTER merchant_oid;
