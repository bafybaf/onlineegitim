USE online_ilahiyat;

ALTER TABLE live_rooms
  ADD COLUMN stream_key VARCHAR(80) NULL AFTER yoklama,
  ADD COLUMN broadcasting TINYINT(1) NOT NULL DEFAULT 0 AFTER stream_key;

UPDATE live_rooms
SET stream_key = CONCAT('oda-', id, '-', LOWER(SUBSTRING(MD5(CONCAT(id, teacher_id, RAND())), 1, 12)))
WHERE stream_key IS NULL OR stream_key = '';

ALTER TABLE live_rooms
  ADD UNIQUE KEY uq_live_stream_key (stream_key);

INSERT INTO settings (k, v) VALUES ('live_host', '')
ON DUPLICATE KEY UPDATE k = k;
