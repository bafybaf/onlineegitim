@echo off
REM ============================================================
REM Online Ilahiyat - ucretsiz canli yayin sunucusu (MediaMTX)
REM
REM 1) Bu dosyayi cift tiklatin (pencere acik kalsin).
REM 2) Ogretmen panelinden odayi acin.
REM 3) Sinifta "Kamerayi ac" ile tarayicidan yayinlayin. OBS gerekmez.
REM 4) Ogrenciler ayni siteden izler; hocayi kamerada gormezsiniz.
REM
REM Ogrenci izleme (oncelik WebRTC/WHEP): http://HOST:8889/live/ANAHTAR/whep
REM HLS yedek: http://HOST:8888/live/ANAHTAR/index.m3u8
REM yml degisince bu pencereyi kapatip bat'i yeniden acin.
REM Windows Guvenlik Duvari 8888 (HLS), 8889 (WebRTC),
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
