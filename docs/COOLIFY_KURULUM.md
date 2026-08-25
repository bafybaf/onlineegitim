# onlineilahiyat.com — Coolify kurulum kılavuzu

Bu dosya VPS + Coolify ile siteyi, veritabanını ve canlı yayını (OBS + MediaMTX) ayağa kaldırmak içindir. Adımları **sırayla** uygulayın; birini atlamayın.

Yerel XAMPP (`http://localhost/online-ilahiyat`) durur. Üretim adresi: `https://onlineilahiyat.com`

---

## 0) Elinizde olması gerekenler

- Coolify kurulu bir VPS (Ubuntu 24.04, en az 2 vCPU / 8 GB RAM)
- Domain: `onlineilahiyat.com` (DNS yönetimi sizde)
- VPS genel IP: **`76.13.14.253`**
- Bu proje: GitHub `https://github.com/bafybaf/onlineegitim.git`
- PayTR mağaza bilgileri, SMTP, Google giriş (sonraki aşamalarda)

Şifreleri bir yere not edin (veritabanı, admin, Coolify). Demo hesap yoktur; yönetici `ADMIN_PASSWORD` ile oluşur.

---

## 1) VPS’e ilk giriş

1. Hostinger / sağlayıcı panelinden VPS’in **public IP** ve root şifresini alın.
2. Bilgisayardan bağlanın (Windows PowerShell):

```bash
ssh root@76.13.14.253
```

3. Coolify hazır imaj geldiyse panel adresi genelde:

`http://76.13.14.253:8000`

Açılmıyorsa sunucuda Docker çalışıyor mu bakın:

```bash
docker ps
systemctl status coolify
```

Coolify yoksa [resmi kurulum](https://coolify.io/docs/installation) (tek satır `curl | bash`) yeter; bu kılavuz **panelin açıldığı** varsayar.

4. İlk açılışta Coolify **kayıt** ekranı çıkar. Güçlü bir e-posta + şifre ile **tek admin** oluşturun. Bu hesabı kaybetmeyin.

5. Coolify → **Settings** (veya Server) → timezone: `Europe/Istanbul`

---

## 2) Güvenlik duvarı (kritik)

Aşağıdaki portlar **açık** olmalı. Hostinger’da hem panel güvenlik duvarı hem Ubuntu `ufw` varsa ikisini de aynı tutun.

| Port | Yön | Ne için |
|------|-----|---------|
| 22 TCP | Gelen | SSH |
| 80 TCP | Gelen | HTTP → HTTPS yönlendirme |
| 443 TCP | Gelen | Site, Coolify proxy, SSL |
| 8000 TCP | Gelen | Coolify panel (isterseniz sonra kapatıp alt alan kullanın) |
| 6001–6002 TCP | Gelen | Coolify websocket (panel terminal) |
| **1935 TCP** | Gelen | OBS RTMP |
| **8888 TCP** | Gelen | HLS (yedek / doğrudan) |
| **8889 TCP** | Gelen | WebRTC WHEP |
| **8189 UDP** | Gelen | WebRTC ICE |

Ubuntu örneği:

```bash
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 8000/tcp
ufw allow 6001:6002/tcp
ufw allow 1935/tcp
ufw allow 8888:8889/tcp
ufw allow 8189/udp
ufw enable
ufw status
```

Coolify’nin kendi Docker kuralları varsa `ufw` “deny” ile kilitlenmesin. Site açılmazsa önce 80/443’ü kontrol edin.

---

## 3) DNS kayıtları

Domain panelinde (where `onlineilahiyat.com` yönetiliyorsa) **A kayıtları** (TTL 300 veya 3600):

| Ad | Tip | Değer |
|----|-----|--------|
| `@` | A | `76.13.14.253` |
| `www` | A | `76.13.14.253` |
| `hls` | A | `76.13.14.253` |
| `whep` | A | `76.13.14.253` |
| `coolify` | A | `76.13.14.253` (panel için önerilir) |

CNAME ile `www` → `onlineilahiyat.com` da olur; A da olur.

Yayılması 5–30 dakika sürebilir. Kontrol:

```bash
nslookup onlineilahiyat.com
nslookup hls.onlineilahiyat.com
```

Hepsi aynı VPS IP’yi göstermelidir. Coolify SSL **DNS oturmadan** alınamaz.

İsteğe bağlı: Coolify paneli `https://coolify.onlineilahiyat.com` (Coolify → Settings → Instance Domain). Site ile karışmasın diye paneli **kök domainde** tutmayın.

---

## 4) Kodu GitHub’a alın (Coolify böyle deploy eder)

Repo: **https://github.com/bafybaf/onlineegitim.git** (private önerilir).

Coolify → **Keys** → GitHub’a **Deploy key** veya GitHub App bağlayın (private repo için zorunlu).

---

## 5) Coolify’de proje ve Docker Compose

1. Coolify → **Projects** → **+ Add** → isim: `Online Ilahiyat`
2. **+ New Resource** → **Docker Compose** (Nixpacks / statik site değil)
3. Kaynak: az önce bağladığınız GitHub repo, branch `main`
4. Compose dosyası: `docker-compose.yml` (proje kökünde)
5. Base directory: `/` (boş / kök)
6. Kaydedin; henüz **Deploy** basmayın — önce ortam değişkenleri.

Servisler:

- `app` — PHP 8.3 + Apache (site)
- `db` — MariaDB 11
- `mediamtx` — canlı yayın

---

## 6) Ortam değişkenleri

Coolify → bu kaynak → **Environment Variables** (veya Compose env). Aşağıyı **kendi şifrelerinizle** doldurun:

```
DB_PASS=BurayaGucluSifre
DB_ROOT_PASS=BurayaBaskaGucluSifre
ADMIN_EMAIL=admin@onlineilahiyat.com
ADMIN_PASSWORD=EnAzOnKarakterSifre
ADMIN_NAME=Yönetici
BASE_URL=
LIVE_HOST=onlineilahiyat.com
LIVE_HLS_BASE=https://hls.onlineilahiyat.com
LIVE_WHEP_BASE=https://whep.onlineilahiyat.com
```

- `BASE_URL` **boş** kalacak (site kök domainde).
- `ADMIN_PASSWORD` en az 10 karakter. Container açılınca yönetici bu bilgilerle oluşur. Boş bırakırsanız ilk ziyaret `https://onlineilahiyat.com/kurulum` sayfasını açar.
- `LIVE_HOST` OBS’in bağlanacağı host; genelde aynı domain.
- Şifrelerde `:` `#` `=` gibi karakterlerden kaçının (Coolify parse hatası olmasın).

Kaydet.

---

## 7) Domain’leri servislere bağlayın

DNS oturduktan sonra:

### 7.1 Site (`app`)

1. Compose içindeki **app** servisi → **Domains**
2. Ekleyin:
   - `https://onlineilahiyat.com`
   - `https://www.onlineilahiyat.com`
3. Port: **80** (container içi Apache)
4. SSL: Let’s Encrypt (Generate)
5. `www` → apex yönlendirme açıksa `www`’yi yönlendirin; ikisi birden SSL alsın

### 7.2 HLS (`mediamtx` port 8888)

1. **mediamtx** servisi → domain: `https://hls.onlineilahiyat.com`
2. Container port: **8888**
3. SSL üretin

### 7.3 WHEP (`mediamtx` port 8889)

1. Aynı `mediamtx` servisine ikinci domain: `https://whep.onlineilahiyat.com`
2. Container port: **8889**
3. SSL üretin

Coolify tek servise iki domain/port vermezse: **+ New Resource** → aynı `mediamtx` imajına proxy yerine, Compose’da `expose: 8888/8889` duruyor; UI’de “Ports Exposes” / “Make it public” ile ayrı FQDN atayın.

OBS **RTMP** proxy’den geçmez. `1935` host’a `ports:` ile açıktır: `rtmp://onlineilahiyat.com:1935/live`

---

## 8) İlk deploy

1. Coolify → **Deploy** (veya Redeploy)
2. Build logunu izleyin. `app` imajı PHP eklentilerini derler; 3–8 dakika sürebilir.
3. Yeşil / running olmalı: `app`, `db`, `mediamtx`
4. `app` açılırken `sql/schema.sql` yüklenir ve `ADMIN_*` ile yönetici yazılır. Elle SQL import **gerekmez**.

Hata örnekleri:

- **SSL pending** → DNS henüz `76.13.14.253` adresine bakmıyor
- **port already allocated 1935** → sunucuda başka MediaMTX / eski compose var; durdurun
- **app unhealthy** → log: `docker` / Coolify Logs; çoğu zaman DB şifresi eşleşmiyor veya `ADMIN_PASSWORD` 10 karakterden kısa

---

## 9) İlk giriş

1. `https://onlineilahiyat.com` — program ve kitap kataloğu (öğrenci/öğretmen/müşteri hesabı yok)
2. Yönetim: `https://onlineilahiyat.com/wiys` — `ADMIN_EMAIL` / `ADMIN_PASSWORD`
3. `ADMIN_PASSWORD` verilmediyse: `https://onlineilahiyat.com/kurulum`
4. `https://onlineilahiyat.com/config/config.php` → **403**
5. `https://onlineilahiyat.com/sql/install.sql` → **403**

Kırık CSS / yönlendirme `/online-ilahiyat/...` görürseniz `BASE_URL` boş gitmemiştir; env’i kaydedip **Redeploy**.

---

## 10) Siteyi tarayıcıda doğrulayın

1. `https://onlineilahiyat.com` — kilit ikonu, karışık içerik uyarısı yok
2. `https://www.onlineilahiyat.com` — aynı site veya kök domaine yönleniyor
3. Ders / mağaza girişi: e-posta ve şifre kutuları **boş** (demo hesabı yok)
4. Admin: `/wiys`

---

## 11) Admin panel ayarları

`https://onlineilahiyat.com/wiys` → giriş.

1. **SEO / site URL** (varsa `seo_canonical_base` / site adresi): `https://onlineilahiyat.com`
2. **Canlı yayın / live_host**: `onlineilahiyat.com` (OBS RTMP host)
3. **SMTP** — iletişim ve kayıt mailleri
4. **PayTR** — sonraki madde
5. **Google** — sonraki madde

PayTR bildirim URL’si panelde görünür, kopyalayın:

`https://onlineilahiyat.com/api/paytr-callback.php`

---

## 12) PayTR

1. PayTR mağaza paneli → Bildirim / Callback URL:  
   `https://onlineilahiyat.com/api/paytr-callback.php`
2. Admin → Ödeme: Merchant ID, Key, Salt
3. İlk günler **test modu açık** kalabilir; gerçek tahsilat için kapatın
4. **Genel IP**: `76.13.14.253` (PayTR bazen sunucu IP ister). Admin’deki `paytr_public_ip` bu değerle gelir; değiştiyse güncelleyin
5. Deneme: mağaza sepetinden 1 ₺ test (test kartı PayTR dokümanında)

Başarısız ödeme: Coolify `app` log + PayTR hata kodu. En sık neden: callback URL hâlâ localhost veya HTTP.

---

## 13) Google ile giriş

[Google Cloud Console](https://console.cloud.google.com/) → OAuth istemcisi (Web):

- Yetkili JavaScript kaynakları: `https://onlineilahiyat.com`
- Yetkili yönlendirme URI: `https://onlineilahiyat.com/google-callback`

Admin → Google: Client ID / Secret, özelliği açın. Eski `localhost` URI’lerini silin veya ayrı istemcide bırakın.

---

## 14) Canlı yayın (OBS)

1. Coolify’de `mediamtx` **running**
2. Öğretmen: `https://onlineilahiyat.com/giris-ders` → canlı sınıf
3. OBS:
   - Servis: **Özel**
   - Sunucu: `rtmp://onlineilahiyat.com:1935/live`
   - Yayın anahtarı: sınıftaki anahtar (ör. `oda-…`) — sunucu satırına yapıştırmayın
4. Önerilen: 720p30, keyframe 1–2 sn, kulaklık (hoparlör yankı yapar)
5. Öğrenci aynı odayı **HTTPS** siteden açmalı; yayın `https://hls.onlineilahiyat.com` / `https://whep.onlineilahiyat.com` üzerinden gelir

Kontrol:

- VPS IP:1935 dışarıdan kapalıysa OBS “bağlanılamıyor”
- HLS SSL yoksa tarayıcı yayını bloklar (karma içerik) — 7.2 tamamlanmış olmalı
- `LIVE_HLS_BASE` / `LIVE_WHEP_BASE` env yanlışsa oynatıcı localhost portuna gider; Redeploy

WebRTC ICE için UDP **8189** şart. Sadece TCP 8889 yetmez.

---

## 15) Güvenlik (üretim)

Deploy biter bitmez:

1. Yönetici şifresi yalnızca sizde olsun (`ADMIN_PASSWORD` Coolify’de, git’te değil)
2. Coolify panelini `https://coolify.onlineilahiyat.com` arkasına alın; 8000’i firewall’dan kapatmayı düşünün (panel erişiminiz kalsın)
3. SSH: root şifre yerine anahtar, mümkünse `PermitRootLogin prohibit-password`
4. Coolify otomatik yedek: **db_data** volume + `storage` volume

MariaDB `root` / `ilahiyat` şifreleri `.env.example` değerinde **bırakılmamalı**.

---

## 16) Yedekleme

Coolify → Database / Volume backup (S3 veya aynı disk).

Elle DB:

```bash
mariadb-dump -u root -p online_ilahiyat > /root/backup-$(date +%F).sql
```

`storage` volume = ödev, kayıt, yüklenen dosyalar. Bunu da yedekleyin.

---

## 17) Güncelleme (yeni kod)

1. Bilgisayarda geliştirin, `git push`
2. Coolify **Deploy** (veya webhook ile otomatik)
3. `storage` ve `db_data` volume’ları silinmez; sadece imaj yenilenir
4. `docker-compose.yml` veya env değiştiyse Save + Redeploy

---

## 18) Sıralı kontrol listesi

İşiniz bitince işaretleyin:

- [ ] Coolify admin hesabı oluştu
- [ ] Firewall: 80, 443, 1935, 8888, 8889, 8189/udp
- [ ] DNS: `@`, `www`, `hls`, `whep` → `76.13.14.253`
- [ ] GitHub `bafybaf/onlineegitim` + Coolify erişimi
- [ ] Env: `DB_PASS`, `ADMIN_PASSWORD` (10+), `BASE_URL` boş, HLS/WHEP https
- [ ] Deploy yeşil (şema + yönetici otomatik)
- [ ] `https://onlineilahiyat.com` açılıyor
- [ ] `/config` ve `/sql` 403
- [ ] `/wiys` ile yönetici girişi
- [ ] PayTR callback HTTPS
- [ ] Google redirect HTTPS
- [ ] OBS `rtmp://onlineilahiyat.com:1935/live` + anahtar
- [ ] Öğrenci tarayıcıda ses/görüntü (HTTPS HLS veya WHEP)

---

## 19) Sık karşılaşılan sorunlar

**Site HTTP’de kalıyor / çerez Secure değil**  
Coolify proxy `X-Forwarded-Proto: https` gönderir; uygulama bunu kullanır. Hâlâ HTTP ise domain Coolify’de `https://` ile eklenmemiştir.

**Linkler `/online-ilahiyat/` ile üretiliyor**  
`BASE_URL` boş değil. Env’i boş kaydedip Redeploy.

**OBS bağlanıyor, izleyici boş**  
8888/8889 domain SSL + `LIVE_HLS_BASE` / `LIVE_WHEP_BASE`. UDP 8189 kapalıysa WebRTC düşer, HLS yedek devreye girmeli.

**PayTR “izin verilmeyen IP / hash”**  
Callback localhost kalmış, veya `paytr_public_ip` yanlış, veya test/canlı anahtar karışmış.

**403 İstek reddedildi**  
CSRF; normal formlar siteden gönderilince geçer. PayTR ve Google callback istisnadır.

**Disk dolu**  
Coolify Docker cleanup; eski imajları silin. 100 GB diskte log + kayıt birikir.

---

## 20) Bu depodaki ilgili dosyalar

| Dosya | Görev |
|-------|--------|
| `docker-compose.yml` | Coolify yığını |
| `docker/php/Dockerfile` | PHP 8.3 + Apache + otomatik kurulum |
| `sql/schema.sql` | İlk açılış şeması (demo hesap yok) |
| `.env.example` | Ortam şablonu |
| `config/config.php` | `DB_*`, `BASE_URL`, `LIVE_*` env |
| `docs/COOLIFY_KURULUM.md` | Bu kılavuz |

Yerel XAMPP aynı `config.php` ile çalışır: env yoksa `DB_HOST=127.0.0.1`, `BASE_URL=/online-ilahiyat`.

---

Sunucu IP veya Coolify’de takıldığınız adımın ekran görüntüsü / hata satırı olursa bir sonraki mesajda o adımdan devam edilir.
