# Online İlahiyat

Canlı ders, üyelik ve kitap mağazası. Üretim adresi: [onlineilahiyat.com](https://onlineilahiyat.com)

## Coolify / Docker

VPS IP: `76.13.14.253`

1. Bu repoyu Coolify’de **Docker Compose** kaynağı olarak bağlayın (`docker-compose.yml`).
2. Ortam değişkenlerini doldurun (`.env.example`):

```
DB_PASS=...
DB_ROOT_PASS=...
ADMIN_EMAIL=admin@onlineilahiyat.com
ADMIN_PASSWORD=en-az-10-karakter
ADMIN_NAME=Yönetici
BASE_URL=
LIVE_HOST=onlineilahiyat.com
```

3. Domain: `onlineilahiyat.com` ve `www` → `app:80`. Canlı izleme aynı siteden (`/mtx-hls`, `/mtx-whep`) gider; ayrı HLS/WHEP alt alanı gerekmez. OBS: `rtmp://onlineilahiyat.com:1935/live` (VPS’te **1935** açık).
4. Deploy. İlk açılışta şema yüklenir ve yönetici hesabı oluşur. `ADMIN_PASSWORD` yoksa tarayıcıda `/kurulum` açılır.

Yönetim: `https://onlineilahiyat.com/wiys`

Ayrıntı: `docs/COOLIFY_KURULUM.md`

## Yerel XAMPP

`http://localhost/online-ilahiyat` — `sql/install.sql` ile boş şema. İlk ziyarette `/kurulum` yöneticiyi oluşturur.
