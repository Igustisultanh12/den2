#!/usr/bin/env bash
# Dijalankan di dalam android-emulator-runner (emulator sudah boot).
set -uo pipefail

APK=$(ls android/app/build/outputs/apk/debug/*.apk | head -1)
PKG=id.my.sinden2.app.debug
OUT="$GITHUB_WORKSPACE/shots/android"
mkdir -p "$OUT"

adb install -r -g "$APK"
adb shell settings put global window_animation_scale 0.5
adb shell settings put global transition_animation_scale 0.5
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

cd "$OUT"
status=0
for role in admin komandan personel sintel; do
  mkdir -p "$OUT/$role"
  cd "$OUT/$role"
  maestro test --format junit --output "$OUT/$role/report.xml" \
    -e ROLE="$role" "$GITHUB_WORKSPACE/.github/presentation/maestro/$role.yaml" > "$OUT/$role/maestro.log" 2>&1 || status=1
  tail -n 40 "$OUT/$role/maestro.log"
  # Cadangan bila Maestro gagal di tengah: tangkap layar terakhir.
  adb exec-out screencap -p > "$OUT/$role/zz_last_screen.png" || true
  cd "$OUT"
done
ls -R "$OUT"
exit 0
