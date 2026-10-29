#!/usr/bin/env bash
# Dijalankan di dalam android-emulator-runner (emulator sudah boot).
set -uo pipefail

APK=$(ls android/app/build/outputs/apk/debug/*.apk | head -1)
PKG=id.my.sinden2.app.debug
FLOWS="$GITHUB_WORKSPACE/.github/presentation/maestro"
OUT="$GITHUB_WORKSPACE/shots/android"
mkdir -p "$OUT"

adb install -r -g "$APK"
# Animasi dimatikan: latar aplikasi beranimasi terus sehingga Maestro menunggu
# layar "diam" di setiap langkah (run pertama 53 menit untuk satu role).
adb shell settings put global window_animation_scale 0
adb shell settings put global transition_animation_scale 0
adb shell settings put global animator_duration_scale 0
# Mode demo status bar: jam & baterai rapi.
adb shell settings put global sysui_demo_allowed 1
adb shell am broadcast -a com.android.systemui.demo -e command enter
adb shell am broadcast -a com.android.systemui.demo -e command clock -e hhmm 0800
adb shell am broadcast -a com.android.systemui.demo -e command battery -e level 100 -e plugged false
adb shell am broadcast -a com.android.systemui.demo -e command network -e wifi show -e level 4
adb shell am broadcast -a com.android.systemui.demo -e command notifications -e visible false
# Lokasi palsu: area Mako (contoh).
adb emu geo fix 112.7368 -7.2032 || true

for p in ACCESS_FINE_LOCATION ACCESS_COARSE_LOCATION ACCESS_BACKGROUND_LOCATION POST_NOTIFICATIONS CAMERA RECORD_AUDIO; do
  adb shell pm grant "$PKG" "android.permission.$p" 2>/dev/null || true
done
adb shell dumpsys deviceidle whitelist +"$PKG" || true

for role in personel admin komandan sintel; do
  dir="$OUT/$role"
  mkdir -p "$dir/debug"
  cd "$dir"
  start=$(date +%s)
  timeout 900 maestro test --format junit --output "$dir/report.xml" \
    --test-output-dir "$dir/debug" --debug-output "$dir/debug" \
    -e ROLE="$role" "$FLOWS/$role.yaml" > "$dir/maestro.log" 2>&1
  rc=$?
  echo "== $role selesai dalam $(( $(date +%s) - start )) detik (exit $rc)"
  tail -n 30 "$dir/maestro.log"
  # takeScreenshot ditulis relatif ke folder flow atau folder output: kumpulkan semuanya.
  find "$FLOWS" "$dir/debug" -maxdepth 3 -name '[0-9]*.png' -newermt "@$start" -exec mv -f {} "$dir/" \; 2>/dev/null
  adb shell screencap -p /sdcard/last.png && adb pull /sdcard/last.png "$dir/zz_last_screen.png" > /dev/null || true
  ls "$dir"
done
exit 0
