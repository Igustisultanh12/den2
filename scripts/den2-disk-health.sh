#!/bin/sh
# den2-disk-health — kumpulkan data SMART semua disk fisik untuk menu "Cek Sistem" SISINDEN.
#
# smartctl butuh akses root, sedangkan PHP berjalan sebagai user `www`. Karena itu skrip ini
# dijalankan oleh root lewat cron dan hanya menulis laporan JSON (read-only) yang dibaca PHP.
#
# Pemasangan (sekali, sebagai root):
#   apt install -y smartmontools
#   install -m 755 -o root -g root /www/wwwroot/den2/scripts/den2-disk-health.sh /usr/local/sbin/den2-disk-health
#   /usr/local/sbin/den2-disk-health
#   echo '*/30 * * * * root /usr/local/sbin/den2-disk-health' > /etc/cron.d/den2-disk-health
#
# Salinan di /usr/local/sbin (milik root) sengaja dipakai, bukan file di folder web, supaya
# user web tidak bisa mengubah skrip yang dijalankan sebagai root.

set -u

OUT_DIR=/var/lib/den2-health
OUT_FILE="$OUT_DIR/smart.json"
SMARTCTL=$(command -v smartctl 2>/dev/null || echo /usr/sbin/smartctl)

umask 022
mkdir -p "$OUT_DIR"
TMP=$(mktemp "$OUT_DIR/.smart.XXXXXX") || exit 1

{
    printf '{"generated_at":"%s","disks":{' "$(date -Iseconds)"
    first=1
    for dev in $(lsblk -dn -o NAME,TYPE | awk '$2 == "disk" { print $1 }'); do
        data=""
        if [ -x "$SMARTCTL" ]; then
            data=$("$SMARTCTL" -j -a "/dev/$dev" 2>/dev/null)
        fi
        [ -n "$data" ] || data=null
        [ "$first" -eq 1 ] || printf ','
        first=0
        printf '"%s":%s' "$dev" "$data"
    done
    printf '}}\n'
} > "$TMP"

chmod 644 "$TMP"
mv -f "$TMP" "$OUT_FILE"
