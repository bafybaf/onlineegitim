USE online_ilahiyat;

ALTER TABLE users
  MODIFY COLUMN role ENUM('ogrenci','ogretmen','admin','musteri') NOT NULL DEFAULT 'ogrenci';
