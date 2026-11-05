<?php

namespace Tests\Unit;

use App\Services\SystemCheckService;
use PHPUnit\Framework\TestCase;

class SystemCheckDiskHealthTest extends TestCase
{
    private function ataDisk(array $rawById = [], array $extra = []): array
    {
        $table = [];
        foreach ($rawById as $id => $raw) {
            $table[] = ['id' => $id, 'name' => "Attr_{$id}", 'value' => 100, 'when_failed' => '', 'raw' => ['value' => $raw]];
        }

        return array_replace_recursive([
            'model_name' => 'Hitachi HDS723020BLA642',
            'serial_number' => 'MN1220F30',
            'rotation_rate' => 7200,
            'smart_status' => ['passed' => true],
            'temperature' => ['current' => 34],
            'power_on_time' => ['hours' => 26280],
            'ata_smart_attributes' => ['table' => $table],
        ], $extra);
    }

    public function test_hdd_tanpa_sektor_rusak_dinilai_100_persen(): void
    {
        $r = SystemCheckService::parseSmart($this->ataDisk([5 => 0, 197 => 0, 198 => 0]));

        $this->assertTrue($r['available']);
        $this->assertSame(100, $r['health_percent']);
        $this->assertSame('healthy', $r['status']);
        $this->assertSame([], $r['issues']);
        $this->assertSame(34, $r['temperature']);
        $this->assertSame(26280, $r['power_on_hours']);
    }

    public function test_sektor_realokasi_dan_tertunda_menurunkan_kesehatan(): void
    {
        // 20 realokasi × 0,5 = 10, 8 pending × 1 = 8  → 82%
        $r = SystemCheckService::parseSmart($this->ataDisk([5 => 20, 197 => 8]));

        $this->assertSame(82, $r['health_percent']);
        $this->assertSame('warning', $r['status']);
        $this->assertCount(2, $r['issues']);
    }

    public function test_pengurang_dibatasi_per_atribut(): void
    {
        // 5000 realokasi tetap maksimal -50
        $r = SystemCheckService::parseSmart($this->ataDisk([5 => 5000]));

        $this->assertSame(50, $r['health_percent']);
        $this->assertSame('critical', $r['status']);
    }

    public function test_smart_failed_langsung_kritis(): void
    {
        $r = SystemCheckService::parseSmart($this->ataDisk([], ['smart_status' => ['passed' => false]]));

        $this->assertSame(5, $r['health_percent']);
        $this->assertSame('critical', $r['status']);
        $this->assertStringContainsString('FAILED', $r['issues'][0]);
    }

    public function test_nvme_memakai_percentage_used(): void
    {
        $r = SystemCheckService::parseSmart([
            'model_name' => 'Samsung SSD 980',
            'smart_status' => ['passed' => true],
            'nvme_smart_health_information_log' => ['percentage_used' => 7, 'media_errors' => 0, 'critical_warning' => 0],
        ]);

        $this->assertSame(93, $r['health_percent']);
        $this->assertSame('healthy', $r['status']);
    }

    public function test_ssd_sata_dibatasi_sisa_umur(): void
    {
        $disk = $this->ataDisk([], ['rotation_rate' => 0]);
        $disk['ata_smart_attributes']['table'][] = ['id' => 231, 'name' => 'SSD_Life_Left', 'value' => 72, 'when_failed' => '', 'raw' => ['value' => 72]];

        $this->assertSame(72, SystemCheckService::parseSmart($disk)['health_percent']);
    }

    public function test_disk_tanpa_dukungan_smart_tidak_diberi_persen(): void
    {
        $r = SystemCheckService::parseSmart([
            'smartctl' => ['messages' => [['string' => 'Unknown USB bridge']]],
        ]);

        $this->assertFalse($r['available']);
        $this->assertNull($r['health_percent']);
        $this->assertSame('Unknown USB bridge', $r['message']);
    }

    public function test_pemakaian_disk_menjumlahkan_partisi_dan_bind_mount_sekali_saja(): void
    {
        // Pohon lsblk seperti server: sdb1 di /mnt/hdd + bind mount storage/app; sda dengan LVM.
        $sdb = ['name' => 'sdb', 'type' => 'disk', 'mountpoints' => [null], 'children' => [
            ['name' => 'sdb1', 'type' => 'part', 'mountpoints' => ['/www/wwwroot/den2/storage/app', '/mnt/hdd']],
        ]];
        $sda = ['name' => 'sda', 'type' => 'disk', 'mountpoints' => [null], 'children' => [
            ['name' => 'sda1', 'type' => 'part', 'mountpoints' => ['/boot/efi']],
            ['name' => 'sda2', 'type' => 'part', 'mountpoints' => ['/boot']],
            ['name' => 'sda3', 'type' => 'part', 'mountpoints' => [null], 'children' => [
                ['name' => 'ubuntu--vg-ubuntu--lv', 'type' => 'lvm', 'mountpoints' => ['/']],
            ]],
        ]];
        $df = [
            '/' => ['size' => 100.0, 'used' => 15.0, 'avail' => 80.0],
            '/boot' => ['size' => 2.0, 'used' => 0.2, 'avail' => 1.7],
            '/boot/efi' => ['size' => 1.0, 'used' => 0.1, 'avail' => 0.9],
            '/mnt/hdd' => ['size' => 1800.0, 'used' => 30.0, 'avail' => 1700.0],
        ];

        $collect = new \ReflectionMethod(SystemCheckService::class, 'collectMountedUsage');

        [$t, $u, $f, $m] = [0.0, 0.0, 0.0, []];
        $collect->invokeArgs(null, [$sdb, $df, &$t, &$u, &$f, &$m]);
        $this->assertSame([1800.0, 30.0, 1700.0], [$t, $u, $f]);
        $this->assertContains('/mnt/hdd', $m);

        [$t, $u, $f, $m] = [0.0, 0.0, 0.0, []];
        $collect->invokeArgs(null, [$sda, $df, &$t, &$u, &$f, &$m]);
        $this->assertEqualsWithDelta(103.0, $t, 0.001);
        $this->assertEqualsWithDelta(15.3, $u, 0.001);
        $this->assertContains('/', $m);
    }
}
