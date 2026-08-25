@echo off
REM ============================================================
REM Online Ilahiyat - ucretsiz canli yayin sunucusu (MediaMTX)
REM
REM 1) Bu dosyayi cift tiklatin (pencere acik kalsin).
REM 2) Ogretmen panelinden odayi acin.
REM 3) OBS: Ayarlar > Yayin
REM      Servis        : Ozel...
REM      Sunucu        : rtmp://127.0.0.1:1935/live
REM      Yayin anahtari: canli sinif sayfasindaki anahtar (BOS DEGIL)
REM      Anahtari sunucu satirina YAPISTIRMAYIN.
REM 4) OBS'te "Yayina Basla". Ogrenciler sadece izler, kameraya cikmaz.
REM 5) Gecikme: OBS Cikti > Gelismis > Keyframe araligi 1 veya 2 sn
REM    CBR 2500 kbps 720p; x264 ise tune=zerolatency
REM
REM Ogrenci izleme (oncelik WebRTC/WHEP): http://HOST:8889/live/ANAHTAR/whep
REM HLS yedek: http://HOST:8888/live/ANAHTAR/index.m3u8
REM yml degisince bu pencereyi kapatip bat'i yeniden acin.
REM Windows Guvenlik Duvari 1935 (RTMP), 8888 (HLS), 8889 (WebRTC),
REM 8189 (WebRTC UDP) portlarini engelliyorsa LAN'da yayin gorunmez.
REM ============================================================
cd /d "%~dp0tools\mediamtx"
if not exist "mediamtx.exe" (
  echo mediamtx.exe bulunamadi: %CD%
  pause
  exit /b 1
)
echo MediaMTX basliyor — bu pencereyi kapatmayin.
mediamtx.exe mediamtx.yml
