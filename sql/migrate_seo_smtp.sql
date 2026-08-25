USE online_ilahiyat;

INSERT INTO settings (k, v) VALUES
('seo_site_title', 'Online İlahiyat'),
('seo_title_suffix', ' | Online İlahiyat'),
('seo_default_description', 'Tefsir, hadis, fıkıh, Arapça canlı dersleri ve kitap mağazası.'),
('seo_keywords', 'online ilahiyat, tefsir, hadis, fıkıh, arapça, canlı ders'),
('seo_og_image', 'assets/img/hero-cami.jpg'),
('seo_robots', 'index,follow'),
('seo_google_analytics', ''),
('seo_google_site_verification', ''),
('seo_canonical_base', ''),
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
('smtp_to_email', 'info@onlineilahiyat.com')
ON DUPLICATE KEY UPDATE k = k;
