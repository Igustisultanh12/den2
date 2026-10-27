<?php

namespace App\Support;

/**
 * Membaca identitas APK Android (package, versionName, versionCode) dari
 * AndroidManifest.xml biner di dalam berkas APK — tanpa alat eksternal.
 *
 * Dipakai saat Admin mengunggah pembaruan aplikasi di Pengaturan web agar
 * nomor versi diambil langsung dari APK (tidak salah ketik) dan APK aplikasi
 * lain ditolak.
 */
class ApkInspector
{
    private const RES_STRING_POOL = 0x0001;
    private const RES_XML = 0x0003;
    private const RES_XML_START_ELEMENT = 0x0102;
    private const RES_XML_RESOURCE_MAP = 0x0180;

    private const ATTR_VERSION_CODE = 0x0101021b;
    private const ATTR_VERSION_NAME = 0x0101021c;

    private const TYPE_STRING = 0x03;
    private const TYPE_INT_DEC = 0x10;
    private const TYPE_INT_HEX = 0x11;

    /**
     * @return array{package: string, version_name: string, version_code: int}|null
     */
    public static function inspect(string $apkPath): ?array
    {
        if (!class_exists(\ZipArchive::class)) {
            return null;
        }
        $zip = new \ZipArchive();
        if ($zip->open($apkPath) !== true) {
            return null;
        }
        $xml = $zip->getFromName('AndroidManifest.xml');
        $zip->close();
        if (!is_string($xml) || $xml === '') {
            return null;
        }
        try {
            return self::parseManifest($xml);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return array{package: string, version_name: string, version_code: int}|null */
    public static function parseManifest(string $b): ?array
    {
        $len = strlen($b);
        if ($len < 8 || self::u16($b, 0) !== self::RES_XML) {
            return null;
        }
        $strings = [];
        $resIds = [];
        $pos = self::u16($b, 2);
        while ($pos + 8 <= $len) {
            $type = self::u16($b, $pos);
            $headerSize = self::u16($b, $pos + 2);
            $size = self::u32($b, $pos + 4);
            if ($size < 8 || $pos + $size > $len) {
                return null;
            }
            if ($type === self::RES_STRING_POOL) {
                $strings = self::stringPool($b, $pos);
            } elseif ($type === self::RES_XML_RESOURCE_MAP) {
                for ($i = $pos + $headerSize; $i + 4 <= $pos + $size; $i += 4) {
                    $resIds[] = self::u32($b, $i);
                }
            } elseif ($type === self::RES_XML_START_ELEMENT) {
                $name = $strings[self::u32($b, $pos + 20)] ?? '';
                if ($name === 'manifest') {
                    return self::manifestAttributes($b, $pos, $strings, $resIds);
                }
            }
            $pos += $size;
        }
        return null;
    }

    private static function manifestAttributes(string $b, int $pos, array $strings, array $resIds): ?array
    {
        $attrStart = self::u16($b, $pos + 24);
        $attrSize = self::u16($b, $pos + 26) ?: 20;
        $attrCount = self::u16($b, $pos + 28);
        $out = ['package' => '', 'version_name' => '', 'version_code' => 0];
        for ($i = 0; $i < $attrCount; $i++) {
            $a = $pos + 16 + $attrStart + $i * $attrSize;
            $nameIdx = self::u32($b, $a + 4);
            $raw = self::u32($b, $a + 8);
            $dataType = ord($b[$a + 15]);
            $data = self::u32($b, $a + 16);
            $name = $strings[$nameIdx] ?? '';
            $resId = $resIds[$nameIdx] ?? 0;
            $value = $raw !== 0xFFFFFFFF ? ($strings[$raw] ?? null) : null;
            if ($value === null && $dataType === self::TYPE_STRING) {
                $value = $strings[$data] ?? null;
            }
            if ($name === 'package') {
                $out['package'] = (string) $value;
            } elseif ($name === 'versionName' || $resId === self::ATTR_VERSION_NAME) {
                $out['version_name'] = (string) $value;
            } elseif ($name === 'versionCode' || $resId === self::ATTR_VERSION_CODE) {
                $out['version_code'] = in_array($dataType, [self::TYPE_INT_DEC, self::TYPE_INT_HEX], true) ? $data : (int) $value;
            }
        }
        return $out['package'] !== '' ? $out : null;
    }

    /** @return list<string> */
    private static function stringPool(string $b, int $pos): array
    {
        $count = self::u32($b, $pos + 8);
        $flags = self::u32($b, $pos + 16);
        $stringsStart = self::u32($b, $pos + 20);
        $utf8 = ($flags & 0x100) !== 0;
        $headerSize = self::u16($b, $pos + 2);
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $off = $pos + $stringsStart + self::u32($b, $pos + $headerSize + $i * 4);
            if ($utf8) {
                // panjang UTF-16 (1–2 byte), lalu panjang UTF-8 (1–2 byte), lalu data.
                $off += (ord($b[$off]) & 0x80) ? 2 : 1;
                $n = ord($b[$off]);
                if ($n & 0x80) {
                    $n = (($n & 0x7F) << 8) | ord($b[$off + 1]);
                    $off += 2;
                } else {
                    $off += 1;
                }
                $out[] = substr($b, $off, $n);
            } else {
                $n = self::u16($b, $off);
                if ($n & 0x8000) {
                    $n = (($n & 0x7FFF) << 16) | self::u16($b, $off + 2);
                    $off += 4;
                } else {
                    $off += 2;
                }
                $out[] = mb_convert_encoding(substr($b, $off, $n * 2), 'UTF-8', 'UTF-16LE');
            }
        }
        return $out;
    }

    private static function u16(string $b, int $o): int
    {
        return unpack('v', $b, $o)[1];
    }

    private static function u32(string $b, int $o): int
    {
        return unpack('V', $b, $o)[1];
    }
}
