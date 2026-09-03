CREATE TABLE IF NOT EXISTS program_purchases (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  program_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NULL,
  payment_id INT UNSIGNED NULL,
  price INT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(40) NOT NULL DEFAULT 'Satın alındı',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pp_user (user_id),
  KEY idx_pp_prog (program_id),
  KEY idx_pp_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
