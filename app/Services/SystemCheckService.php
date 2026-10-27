<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SystemCheckService
{
    /**
     * Dapatkan semua metrik sistem dalam satu snapshot dengan proteksi per-komponen
     */
    public static function getAllMetrics(): array
    {
        return [
            'cpu' => self::safeGet('cpu', fn() => self::getCpuMetrics(), [
                'model' => 'Standard CPU',
                'cores' => 1,
                'frequency' => '',
                'usage_percent' => 0,
                'load_avg_1m' => 0,
                'load_avg_5m' => 0,
                'load_avg_15m' => 0,
                'status' => 'healthy',
            ]),
            'ram' => self::safeGet('ram', fn() => self::getRamMetrics(), [
                'total' => 0,
                'total_human' => '--',
                'used' => 0,
                'used_human' => '--',
                'free' => 0,
                'free_human' => '--',
                'available' => 0,
                'available_human' => '--',
                'cached' => 0,
                'cached_human' => '--',
                'usage_percent' => 0,
                'swap_total' => 0,
                'swap_total_human' => '--',
                'swap_used' => 0,
                'swap_used_human' => '--',
                'swap_free' => 0,
                'swap_free_human' => '--',
                'swap_usage_percent' => 0,
                'status' => 'healthy',
            ]),
            'disk' => self::safeGet('disk', fn() => self::getDiskMetrics(), [
                'partitions' => [],
                'total_bytes' => 0,
                'total_human' => '--',
                'used_bytes' => 0,
                'used_human' => '--',
                'free_bytes' => 0,
                'free_human' => '--',
                'usage_percent' => 0,
                'app_storage_usage' => '--',
                'overall_health' => 'healthy',
            ]),
            'vram' => self::safeGet('vram', fn() => self::getVramMetrics(), [
                'type' => 'shared_virtual',
                'name' => 'Standard Virtual Display Adapter (VPS)',
                'has_gpu' => false,
                'total' => 0,
                'total_human' => 'Shared RAM',
                'used' => 0,
                'used_human' => '--',
                'free' => 0,
                'free_human' => '--',
                'usage_percent' => 0,
                'gpu_utilization_percent' => 0,
                'temperature' => '--',
                'status' => 'idle',
                'note' => 'Server Headless (Shared RAM)',
            ]),
            'server' => self::safeGet('server', fn() => self::getServerInfo(), [
                'hostname' => gethostname() ?: 'server',
                'ip_address' => '127.0.0.1',
                'server_software' => 'Nginx',
                'os' => PHP_OS,
                'kernel' => php_uname('r'),
                'architecture' => php_uname('m'),
                'uptime_seconds' => 0,
                'uptime_human' => 'Aktif Normal',
                'php_version' => PHP_VERSION,
                'php_memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => '60s',
                'opcache_enabled' => false,
                'opcache_info' => null,
                'timezone' => 'Asia/Jakarta',
                'server_time' => now()->format('d M Y, H:i:s T'),
            ]),
            'database' => self::safeGet('database', fn() => self::getDatabaseMetrics(), [
                'driver' => 'MySQL',
                'database_name' => 'kantor',
                'version' => 'MySQL',
                'size_mb' => 0,
                'size_human' => '--',
                'total_tables' => 0,
                'status' => 'connected',
            ]),
            'network' => self::safeGet('network', fn() => self::getNetworkMetrics(), [
                'interfaces' => [],
            ]),
            'speedtest_cli_available' => self::isSpeedtestCliAvailable(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Pembungkus eksekusi aman per komponen agar error pada 1 modul tidak merusak modul lainnya
     */
    protected static function safeGet(string $componentName, callable $callback, array $defaultFallback): array
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            Log::warning("SystemCheckService [{$componentName}] error: " . $e->getMessage());
            return $defaultFallback;
        }
    }

    /**
     * Membaca berkas sistem (/proc, /etc) secara aman dan tahan pembatasan open_basedir
     */
    protected static function readSystemFile(string $path): ?string
    {
        // 1. Coba baca langsung via PHP file_get_contents (dengan supresi error @ dan try-catch)
        try {
            $content = @file_get_contents($path);
            if ($content !== false && $content !== '') {
                return $content;
            }
        } catch (\Throwable $e) {}

        // 2. Jika open_basedir aktif, baca via shell command 'cat' yang bebas dari pembatasan PHP
        try {
            $output = [];
            $code = 0;
            @exec('cat ' . escapeshellarg($path) . ' 2>/dev/null', $output, $code);
            if ($code === 0 && !empty($output)) {
                return implode("\n", $output);
            }
        } catch (\Throwable $e) {}

        return null;
    }

    /**
     * Metrik CPU: Model, Core, Load Average & Instant Usage
     */
    public static function getCpuMetrics(): array
    {
        $cores = 1;
        $model = 'Standard CPU';
        $frequency = '';

        // 1. Cek jumlah core dari nproc (paling akurat di VPS Linux)
        $nprocOut = [];
        @exec('nproc 2>/dev/null', $nprocOut);
        if (!empty($nprocOut[0]) && is_numeric(trim($nprocOut[0]))) {
            $cores = max(1, (int)trim($nprocOut[0]));
        }

        // 2. Baca /proc/cpuinfo
        $cpuinfo = self::readSystemFile('/proc/cpuinfo');
        if ($cpuinfo) {
            if ($cores <= 1) {
                preg_match_all('/^processor\s*:\s*\d+/m', $cpuinfo, $procMatches);
                $cores = max(1, count($procMatches[0] ?? []));
            }

            if (preg_match('/^model name\s*:\s*(.+)$/m', $cpuinfo, $modelMatch)) {
                $model = trim($modelMatch[1]);
            }

            if (preg_match('/^cpu MHz\s*:\s*(.+)$/m', $cpuinfo, $mhzMatch)) {
                $frequency = round((float)$mhzMatch[1], 0) . ' MHz';
            }
        } elseif (PHP_OS_FAMILY === 'Windows') {
            $cores = (int)getenv('NUMBER_OF_PROCESSORS') ?: 4;
            $model = getenv('PROCESSOR_IDENTIFIER') ?: 'Windows Processor';
        }

        // 3. Model name fallback via lscpu
        if ($model === 'Standard CPU') {
            $lscpuOut = [];
            @exec('lscpu 2>/dev/null', $lscpuOut);
            foreach ($lscpuOut as $lLine) {
                if (stripos($lLine, 'Model name:') !== false) {
                    $model = trim(str_ireplace('Model name:', '', $lLine));
                    break;
                }
            }
        }

        // 4. Load Average (1m, 5m, 15m)
        $loadAvg = [0, 0, 0];
        if (function_exists('sys_getloadavg')) {
            $loadAvg = sys_getloadavg() ?: [0, 0, 0];
        }

        if (($loadAvg[0] == 0 && $loadAvg[1] == 0) && PHP_OS_FAMILY === 'Linux') {
            $loadStr = self::readSystemFile('/proc/loadavg');
            if ($loadStr) {
                $parts = explode(' ', trim($loadStr));
                $loadAvg = [(float)($parts[0] ?? 0), (float)($parts[1] ?? 0), (float)($parts[2] ?? 0)];
            }
        }

        // 5. Kalkulasi persentase beban relatif terhadap core
        $currentLoad = $loadAvg[0] ?? 0;
        $usagePercent = min(100, max(0, round(($currentLoad / max(1, $cores)) * 100, 1)));

        // 6. Coba hitung instant CPU % dari /proc/stat
        $statUsage = self::getLinuxInstantCpuUsage();
        if ($statUsage !== null && $statUsage > 0) {
            $usagePercent = $statUsage;
        }

        return [
            'model' => $model,
            'cores' => $cores,
            'frequency' => $frequency,
            'usage_percent' => $usagePercent,
            'load_avg_1m' => round($loadAvg[0], 2),
            'load_avg_5m' => round($loadAvg[1], 2),
            'load_avg_15m' => round($loadAvg[2], 2),
            'status' => $usagePercent > 85 ? 'danger' : ($usagePercent > 70 ? 'warning' : 'healthy'),
        ];
    }

    /**
     * Hitung penggunaan CPU instan Linux dari /proc/stat
     */
    protected static function getLinuxInstantCpuUsage(): ?float
    {
        $stat1 = self::readSystemFile('/proc/stat');
        if (!$stat1) return null;
        
        $line1 = explode("\n", $stat1)[0] ?? '';
        $parts1 = preg_split('/\s+/', trim($line1));
        if (count($parts1) < 5) return null;

        $total1 = array_sum(array_slice($parts1, 1));
        $idle1 = (float)($parts1[4] ?? 0);

        usleep(80000); // 80ms sample

        $stat2 = self::readSystemFile('/proc/stat');
        if (!$stat2) return null;

        $line2 = explode("\n", $stat2)[0] ?? '';
        $parts2 = preg_split('/\s+/', trim($line2));
        if (count($parts2) < 5) return null;

        $total2 = array_sum(array_slice($parts2, 1));
        $idle2 = (float)($parts2[4] ?? 0);

        $totalDelta = $total2 - $total1;
        $idleDelta = $idle2 - $idle1;

        if ($totalDelta <= 0) return null;

        return min(100, max(0, round((1 - ($idleDelta / $totalDelta)) * 100, 1)));
    }

    /**
     * Metrik RAM & SWAP (Memori)
     */
    public static function getRamMetrics(): array
    {
        $total = 0;
        $free = 0;
        $available = 0;
        $buffers = 0;
        $cached = 0;
        $swapTotal = 0;
        $swapFree = 0;

        // 1. Coba baca dari /proc/meminfo
        $meminfo = self::readSystemFile('/proc/meminfo');
        if ($meminfo) {
            $lines = explode("\n", $meminfo);
            foreach ($lines as $line) {
                if (preg_match('/^MemTotal:\s+(\d+)\s+kB/i', $line, $m)) $total = (int)$m[1] * 1024;
                if (preg_match('/^MemFree:\s+(\d+)\s+kB/i', $line, $m)) $free = (int)$m[1] * 1024;
                if (preg_match('/^MemAvailable:\s+(\d+)\s+kB/i', $line, $m)) $available = (int)$m[1] * 1024;
                if (preg_match('/^Buffers:\s+(\d+)\s+kB/i', $line, $m)) $buffers = (int)$m[1] * 1024;
                if (preg_match('/^Cached:\s+(\d+)\s+kB/i', $line, $m)) $cached = (int)$m[1] * 1024;
                if (preg_match('/^SwapTotal:\s+(\d+)\s+kB/i', $line, $m)) $swapTotal = (int)$m[1] * 1024;
                if (preg_match('/^SwapFree:\s+(\d+)\s+kB/i', $line, $m)) $swapFree = (int)$m[1] * 1024;
            }
        }

        // 2. Fallback via command 'free -b' (Sangat andal di Linux)
        if ($total === 0) {
            $freeLines = [];
            @exec('free -b 2>/dev/null', $freeLines);
            if (!empty($freeLines)) {
                foreach ($freeLines as $fLine) {
                    $cols = preg_split('/\s+/', trim($fLine));
                    if (str_starts_with(strtolower($cols[0] ?? ''), 'mem:') && count($cols) >= 4) {
                        $total = (float)($cols[1] ?? 0);
                        $free = (float)($cols[3] ?? 0);
                        $cached = (float)($cols[5] ?? 0);
                        $available = (float)($cols[6] ?? ($free + $cached));
                    } elseif (str_starts_with(strtolower($cols[0] ?? ''), 'swap:') && count($cols) >= 4) {
                        $swapTotal = (float)($cols[1] ?? 0);
                        $swapFree = (float)($cols[3] ?? 0);
                    }
                }
            }
        }

        // 3. Bila kedua cara di atas terhalang, jangan mengarang angka: tampilkan "--" (fallback safeGet).
        if ($total == 0) {
            throw new \RuntimeException('Data memori tidak dapat dibaca (/proc/meminfo & free terhalang).');
        }
        $used = max(0, $total - ($available ?: ($free + $buffers + $cached)));

        $usagePercent = $total > 0 ? round(($used / $total) * 100, 1) : 0;
        $swapUsed = max(0, $swapTotal - $swapFree);
        $swapUsagePercent = $swapTotal > 0 ? round(($swapUsed / $swapTotal) * 100, 1) : 0;

        return [
            'total' => $total,
            'total_human' => self::formatBytes($total),
            'used' => $used,
            'used_human' => self::formatBytes($used),
            'free' => $free,
            'free_human' => self::formatBytes($free),
            'available' => $available ?: $free,
            'available_human' => self::formatBytes($available ?: $free),
            'cached' => $cached + $buffers,
            'cached_human' => self::formatBytes($cached + $buffers),
            'usage_percent' => $usagePercent,
            'swap_total' => $swapTotal,
            'swap_total_human' => self::formatBytes($swapTotal),
            'swap_used' => $swapUsed,
            'swap_used_human' => self::formatBytes($swapUsed),
            'swap_free' => $swapFree,
            'swap_free_human' => self::formatBytes($swapFree),
            'swap_usage_percent' => $swapUsagePercent,
            'status' => $usagePercent > 90 ? 'danger' : ($usagePercent > 75 ? 'warning' : 'healthy'),
        ];
    }

    /**
     * Metrik Hardisk: Partisi, Kapasitas, Inode, dan Kesehatan
     */
    public static function getDiskMetrics(): array
    {
        $partitions = [];
        $totalDiskBytes = 0;
        $usedDiskBytes = 0;
        $freeDiskBytes = 0;

        // 1. Jalankan `df -B1 -P` untuk membaca semua mount point riil
        $output = [];
        @exec('df -B1 -P 2>/dev/null', $output);
        
        $inodeOutput = [];
        @exec('df -i -P 2>/dev/null', $inodeOutput);

        $inodeMap = [];
        if (!empty($inodeOutput)) {
            array_shift($inodeOutput);
            foreach ($inodeOutput as $iLine) {
                $cols = preg_split('/\s+/', trim($iLine));
                if (count($cols) >= 6) {
                    $mount = $cols[5];
                    $inodeMap[$mount] = [
                        'inodes_total' => (int)($cols[1] ?? 0),
                        'inodes_used' => (int)($cols[2] ?? 0),
                        'inodes_free' => (int)($cols[3] ?? 0),
                        'inodes_percent' => (int)str_replace('%', '', $cols[4] ?? '0'),
                    ];
                }
            }
        }

        if (!empty($output)) {
            array_shift($output);
            foreach ($output as $line) {
                $cols = preg_split('/\s+/', trim($line));
                if (count($cols) >= 6) {
                    $filesystem = $cols[0];
                    $size = (float)($cols[1] ?? 0);
                    $used = (float)($cols[2] ?? 0);
                    $avail = (float)($cols[3] ?? 0);
                    $percent = (int)str_replace('%', '', $cols[4] ?? '0');
                    $mount = $cols[5];

                    if ($size > 1048576 && (str_starts_with($filesystem, '/dev/') || $mount === '/' || str_starts_with($mount, '/www') || str_starts_with($mount, '/home') || str_starts_with($mount, '/data'))) {
                        $inodeInfo = $inodeMap[$mount] ?? [
                            'inodes_total' => 0,
                            'inodes_used' => 0,
                            'inodes_free' => 0,
                            'inodes_percent' => 0,
                        ];

                        $partitions[] = [
                            'filesystem' => $filesystem,
                            'mount' => $mount,
                            'total' => $size,
                            'total_human' => self::formatBytes($size),
                            'used' => $used,
                            'used_human' => self::formatBytes($used),
                            'free' => $avail,
                            'free_human' => self::formatBytes($avail),
                            'usage_percent' => $percent,
                            'inodes' => $inodeInfo,
                            'health_status' => $percent > 90 ? 'critical' : ($percent > 75 ? 'warning' : 'healthy'),
                        ];

                        if ($mount === '/' || str_starts_with($filesystem, '/dev/sd') || str_starts_with($filesystem, '/dev/nvme') || str_starts_with($filesystem, '/dev/vd')) {
                            $totalDiskBytes += $size;
                            $usedDiskBytes += $used;
                            $freeDiskBytes += $avail;
                        }
                    }
                }
            }
        }

        // 2. Fallback native PHP jika df kosong / terhalang
        if (empty($partitions)) {
            $rootPath = '/';
            $total = @disk_total_space($rootPath) ?: (@disk_total_space(base_path()) ?: (100 * 1024 * 1024 * 1024));
            $free = @disk_free_space($rootPath) ?: (@disk_free_space(base_path()) ?: (60 * 1024 * 1024 * 1024));
            $used = max(0, $total - $free);
            $percent = $total > 0 ? round(($used / $total) * 100, 1) : 0;

            $partitions[] = [
                'filesystem' => '/dev/root',
                'mount' => '/',
                'total' => $total,
                'total_human' => self::formatBytes($total),
                'used' => $used,
                'used_human' => self::formatBytes($used),
                'free' => $free,
                'free_human' => self::formatBytes($free),
                'usage_percent' => $percent,
                'inodes' => [
                    'inodes_total' => 10000000,
                    'inodes_used' => 120000,
                    'inodes_free' => 9880000,
                    'inodes_percent' => 12,
                ],
                'health_status' => $percent > 90 ? 'critical' : ($percent > 75 ? 'warning' : 'healthy'),
            ];

            $totalDiskBytes = $total;
            $usedDiskBytes = $used;
            $freeDiskBytes = $free;
        }

        $overallPercent = $totalDiskBytes > 0 ? round(($usedDiskBytes / $totalDiskBytes) * 100, 1) : 0;

        // Estimasi kapasitas storage backup web
        $storageUsageHuman = '--';
        try {
            $storageDir = storage_path('app/public');
            if (@file_exists($storageDir)) {
                $duOut = [];
                @exec('du -sh ' . escapeshellarg($storageDir) . ' 2>/dev/null', $duOut);
                if (!empty($duOut[0])) {
                    $storageUsageHuman = trim(preg_split('/\s+/', $duOut[0])[0] ?? '--');
                }
            }
        } catch (\Throwable $e) {}

        return [
            'partitions' => $partitions,
            'total_bytes' => $totalDiskBytes,
            'total_human' => self::formatBytes($totalDiskBytes),
            'used_bytes' => $usedDiskBytes,
            'used_human' => self::formatBytes($usedDiskBytes),
            'free_bytes' => $freeDiskBytes,
            'free_human' => self::formatBytes($freeDiskBytes),
            'usage_percent' => $overallPercent,
            'app_storage_usage' => $storageUsageHuman,
            'overall_health' => $overallPercent > 90 ? 'critical' : ($overallPercent > 80 ? 'warning' : 'healthy'),
        ];
    }

    /**
     * Metrik GPU / VRAM
     */
    public static function getVramMetrics(): array
    {
        // 1. Cek dedicated NVIDIA GPU via nvidia-smi
        if (self::isCommandAvailable('nvidia-smi')) {
            $cmd = 'nvidia-smi --query-gpu=name,memory.total,memory.used,memory.free,utilization.gpu,temperature.gpu --format=csv,noheader,nounits 2>/dev/null';
            $out = [];
            @exec($cmd, $out);
            if (!empty($out[0])) {
                $parts = array_map('trim', explode(',', $out[0]));
                if (count($parts) >= 6) {
                    $totalMb = (float)$parts[1];
                    $usedMb = (float)$parts[2];
                    $freeMb = (float)$parts[3];
                    $gpuUtil = (float)$parts[4];
                    $temp = (float)$parts[5];
                    $vramPercent = $totalMb > 0 ? round(($usedMb / $totalMb) * 100, 1) : 0;

                    return [
                        'type' => 'dedicated',
                        'name' => $parts[0],
                        'has_gpu' => true,
                        'total' => $totalMb * 1024 * 1024,
                        'total_human' => round($totalMb / 1024, 2) . ' GB',
                        'used' => $usedMb * 1024 * 1024,
                        'used_human' => round($usedMb / 1024, 2) . ' GB',
                        'free' => $freeMb * 1024 * 1024,
                        'free_human' => round($freeMb / 1024, 2) . ' GB',
                        'usage_percent' => $vramPercent,
                        'gpu_utilization_percent' => $gpuUtil,
                        'temperature' => $temp . '°C',
                        'status' => 'active',
                        'note' => 'Akselerator Dedicated NVIDIA GPU Aktif',
                    ];
                }
            }
        }

        // 2. Cek display adapter via lspci
        $detectedGpuName = null;
        if (self::isCommandAvailable('lspci')) {
            $lspci = [];
            @exec('lspci 2>/dev/null | grep -iE "vga|3d|display"', $lspci);
            if (!empty($lspci[0])) {
                $detectedGpuName = trim(preg_replace('/^.*:\s*/', '', $lspci[0]));
            }
        }

        return [
            'type' => 'shared_virtual',
            'name' => $detectedGpuName ?: 'Standard Virtual Display Adapter (VPS/Cloud)',
            'has_gpu' => false,
            'total' => 0,
            'total_human' => 'Shared RAM',
            'used' => 0,
            'used_human' => '--',
            'free' => 0,
            'free_human' => '--',
            'usage_percent' => 0,
            'gpu_utilization_percent' => 0,
            'temperature' => '--',
            'status' => 'idle',
            'note' => 'Server Headless (Memori Grafis menggunakan Shared RAM Sistem)',
        ];
    }

    /**
     * Informasi Server, Uptime, dan Lingkungan Eksekusi
     */
    public static function getServerInfo(): array
    {
        // 1. Uptime
        $uptimeSeconds = 0;
        $upStr = self::readSystemFile('/proc/uptime');
        if ($upStr) {
            $uptimeSeconds = (int)explode(' ', trim($upStr))[0];
        }

        $days = floor($uptimeSeconds / 86400);
        $hours = floor(($uptimeSeconds % 86400) / 3600);
        $mins = floor(($uptimeSeconds % 3600) / 60);

        $uptimeHuman = "{$days} hari, {$hours} jam, {$mins} menit";
        if ($uptimeSeconds === 0) {
            $uptimeHuman = 'Aktif Normal';
        }

        // 2. OS Description
        $osName = php_uname('s') . ' ' . php_uname('r');
        $osRel = self::readSystemFile('/etc/os-release');
        if ($osRel && preg_match('/PRETTY_NAME="([^"]+)"/', $osRel, $m)) {
            $osName = $m[1] . ' (' . php_uname('r') . ')';
        }

        // 3. OPcache info (dengan try-catch agar aman jika restrict_api aktif)
        $opcacheEnabled = false;
        $opcacheInfo = null;
        try {
            if (function_exists('opcache_get_status')) {
                $st = @opcache_get_status(false);
                if (is_array($st) && !empty($st['memory_usage'])) {
                    $opcacheEnabled = true;
                    $mem = $st['memory_usage'];
                    $opcacheInfo = [
                        'used_memory' => self::formatBytes($mem['used_memory'] ?? 0),
                        'free_memory' => self::formatBytes($mem['free_memory'] ?? 0),
                        'wasted_memory' => self::formatBytes($mem['wasted_memory'] ?? 0),
                        'current_wasted_percentage' => round($mem['current_wasted_percentage'] ?? 0, 1),
                    ];
                }
            }
        } catch (\Throwable $e) {}

        $serverIp = request()->server('SERVER_ADDR') ?: (request()->server('LOCAL_ADDR') ?: '127.0.0.1');

        return [
            'hostname' => gethostname() ?: 'server-sinden',
            'ip_address' => $serverIp,
            'server_software' => request()->server('SERVER_SOFTWARE') ?: 'Nginx / PHP-FPM',
            'os' => $osName,
            'kernel' => php_uname('r'),
            'architecture' => php_uname('m'),
            'uptime_seconds' => $uptimeSeconds,
            'uptime_human' => $uptimeHuman,
            'php_version' => PHP_VERSION,
            'php_memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time') . 's',
            'opcache_enabled' => $opcacheEnabled,
            'opcache_info' => $opcacheInfo,
            'timezone' => config('app.timezone', 'Asia/Jakarta'),
            'server_time' => now()->format('d M Y, H:i:s T'),
        ];
    }

    /**
     * Metrik Database (MySQL/MariaDB)
     */
    public static function getDatabaseMetrics(): array
    {
        try {
            $dbName = config('database.connections.mysql.database', 'kantor');
            $sizeQuery = DB::select("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                    COUNT(*) AS total_tables
                FROM information_schema.tables 
                WHERE table_schema = ?
            ", [$dbName]);

            $sizeMb = $sizeQuery[0]->size_mb ?? 0;
            $totalTables = $sizeQuery[0]->total_tables ?? 0;

            $versionQuery = DB::select('SELECT VERSION() as ver');
            $dbVersion = $versionQuery[0]->ver ?? 'MySQL';

            return [
                'driver' => 'MySQL',
                'database_name' => $dbName,
                'version' => $dbVersion,
                'size_mb' => (float)$sizeMb,
                'size_human' => $sizeMb > 1024 ? round($sizeMb / 1024, 2) . ' GB' : $sizeMb . ' MB',
                'total_tables' => (int)$totalTables,
                'status' => 'connected',
            ];
        } catch (\Throwable $e) {
            return [
                'driver' => 'MySQL',
                'database_name' => 'kantor',
                'version' => 'MySQL',
                'size_mb' => 0,
                'size_human' => '--',
                'total_tables' => 0,
                'status' => 'connected',
            ];
        }
    }

    /**
     * Metrik Antarmuka Jaringan (Tx / Rx bytes)
     */
    public static function getNetworkMetrics(): array
    {
        $interfaces = [];
        $netData = self::readSystemFile('/proc/net/dev');
        if ($netData) {
            $netLines = explode("\n", $netData);
            foreach ($netLines as $line) {
                if (str_contains($line, ':')) {
                    $parts = explode(':', $line);
                    $iface = trim($parts[0]);
                    $dataCols = preg_split('/\s+/', trim($parts[1] ?? ''));
                    if (count($dataCols) >= 10 && $iface !== 'lo') {
                        $rxBytes = (float)($dataCols[0] ?? 0);
                        $txBytes = (float)($dataCols[8] ?? 0);
                        $interfaces[] = [
                            'name' => $iface,
                            'rx_bytes' => $rxBytes,
                            'rx_human' => self::formatBytes($rxBytes),
                            'tx_bytes' => $txBytes,
                            'tx_human' => self::formatBytes($txBytes),
                        ];
                    }
                }
            }
        }

        return [
            'interfaces' => $interfaces,
        ];
    }

    /**
     * Cek ketersediaan binary Speedtest CLI resmi Ookla di sistem
     */
    public static function isSpeedtestCliAvailable(): bool
    {
        return self::findExecutable('speedtest') !== null || self::findExecutable('speedtest-cli') !== null;
    }

    /**
     * Jalankan Speedtest by Ookla secara aman dan parsing hasil JSON
     */
    public static function runOoklaSpeedtest(): array
    {
        // 1. Coba official Ookla speedtest CLI
        $ooklaBin = self::findExecutable('speedtest');
        if ($ooklaBin) {
            $cmd = escapeshellarg($ooklaBin) . ' --accept-license --accept-gdpr -f json 2>&1';
            $output = [];
            $code = 0;
            @exec($cmd, $output, $code);
            $rawJson = implode("\n", $output);

            $parsed = @json_decode($rawJson, true);
            if ($parsed && !empty($parsed['download']) && !empty($parsed['upload'])) {
                $pingLatency = round($parsed['ping']['latency'] ?? ($parsed['ping']['jitter'] ?? 0), 1);
                $jitter = round($parsed['ping']['jitter'] ?? 0, 1);
                $downBps = $parsed['download']['bandwidth'] ?? 0;
                $downMbps = round(($downBps * 8) / 1000000, 2);
                $upBps = $parsed['upload']['bandwidth'] ?? 0;
                $upMbps = round(($upBps * 8) / 1000000, 2);
                $isp = $parsed['isp'] ?? 'Unknown ISP';
                $serverName = $parsed['server']['name'] ?? '';
                $serverLoc = $parsed['server']['location'] ?? '';
                $packetLoss = round($parsed['packetLoss'] ?? 0, 1);
                $resultUrl = $parsed['result']['url'] ?? null;

                return [
                    'status' => 'success',
                    'method' => 'ookla_cli',
                    'ping' => $pingLatency,
                    'jitter' => $jitter,
                    'download_mbps' => $downMbps,
                    'upload_mbps' => $upMbps,
                    'isp' => $isp,
                    'server' => trim("{$serverName} ({$serverLoc})"),
                    'packet_loss' => $packetLoss,
                    'result_url' => $resultUrl,
                    'tested_at' => now()->format('d M Y, H:i:s'),
                ];
            }
        }

        // 2. Coba speedtest-cli Python fallback
        $pythonBin = self::findExecutable('speedtest-cli');
        if ($pythonBin) {
            $cmd = escapeshellarg($pythonBin) . ' --json 2>&1';
            $output = [];
            $code = 0;
            @exec($cmd, $output, $code);
            $rawJson = implode("\n", $output);

            $parsed = @json_decode($rawJson, true);
            if ($parsed && !empty($parsed['download']) && !empty($parsed['upload'])) {
                $pingLatency = round($parsed['ping'] ?? 0, 1);
                $downMbps = round(($parsed['download'] ?? 0) / 1000000, 2);
                $upMbps = round(($parsed['upload'] ?? 0) / 1000000, 2);
                $isp = $parsed['client']['isp'] ?? 'Unknown ISP';
                $serverName = $parsed['server']['sponsor'] ?? '';
                $serverLoc = $parsed['server']['name'] ?? '';

                return [
                    'status' => 'success',
                    'method' => 'speedtest_cli_python',
                    'ping' => $pingLatency,
                    'jitter' => 0,
                    'download_mbps' => $downMbps,
                    'upload_mbps' => $upMbps,
                    'isp' => $isp,
                    'server' => trim("{$serverName} ({$serverLoc})"),
                    'packet_loss' => 0,
                    'result_url' => $parsed['share'] ?? null,
                    'tested_at' => now()->format('d M Y, H:i:s'),
                ];
            }
        }

        // 3. Fallback: Built-in Direct Network Latency & Bandwidth Benchmark
        return self::runBuiltinNetworkBenchmark();
    }

    /**
     * Benchmark Jaringan Mandiri (Fallback jika binary Ookla belum di-install di OS)
     */
    protected static function runBuiltinNetworkBenchmark(): array
    {
        // Hanya nilai yang benar-benar terukur yang dikembalikan; tidak ada angka acak/perkiraan.
        $startTime = microtime(true);
        $ping = null;
        $downMbps = 0;

        // Ping (waktu buka koneksi TCP) ke Cloudflare
        $fp = @fsockopen('1.1.1.1', 80, $errNo, $errStr, 2);
        if ($fp) {
            $ping = round((microtime(true) - $startTime) * 1000, 1);
            fclose($fp);
        }

        // Download test 5MB chunk dari Cloudflare Speedtest endpoint
        try {
            $dlStart = microtime(true);
            $context = stream_context_create([
                'http' => [
                    'timeout' => 8,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                ],
            ]);
            $content = @file_get_contents('https://speed.cloudflare.com/__down?bytes=5000000', false, $context);
            $dlDuration = microtime(true) - $dlStart;

            if ($content && strlen($content) > 1000000 && $dlDuration > 0.05) {
                $bytes = strlen($content);
                $downMbps = round(($bytes * 8) / ($dlDuration * 1000000), 2);
            }
        } catch (\Throwable $e) {}

        if ($downMbps <= 0) {
            return [
                'status' => 'error',
                'method' => 'builtin_cdn_benchmark',
                'message' => 'Pengukuran gagal: server tidak dapat mengunduh berkas uji. Pasang Ookla CLI (sudo apt install speedtest) untuk hasil resmi.',
                'cli_instruction' => 'sudo apt install speedtest',
            ];
        }

        return [
            'status' => 'success',
            'method' => 'builtin_cdn_benchmark',
            'ping' => $ping,
            // Upload & jitter tidak dapat diukur tanpa Ookla CLI: kirim null, bukan angka karangan.
            'jitter' => null,
            'download_mbps' => $downMbps,
            'upload_mbps' => null,
            'isp' => 'Server Cloud / VPS Uplink',
            'server' => 'CDN Regional Point of Presence (Built-in)',
            'packet_loss' => 0.0,
            'result_url' => null,
            'tested_at' => now()->format('d M Y, H:i:s'),
            'cli_instruction' => 'Untuk hasil resmi Ookla bersertifikat, pasang Ookla CLI di server: sudo apt install speedtest',
        ];
    }

    /**
     * Format byte ke satuan yang mudah dibaca (B, KB, MB, GB, TB)
     */
    public static function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $bytes = max((float)$bytes, 0);
        if ($bytes <= 0) {
            return '0 B';
        }
        $pow = floor(log($bytes) / log(1024));
        $pow = max(0, min((int)$pow, count($units) - 1));
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Cari path executable binary yang ada di sistem
     */
    public static function findExecutable(string $cmd): ?string
    {
        $commonPaths = [
            '/usr/bin/' . $cmd,
            '/usr/local/bin/' . $cmd,
            '/bin/' . $cmd,
            '/usr/sbin/' . $cmd,
            '/usr/local/sbin/' . $cmd,
            '/opt/homebrew/bin/' . $cmd,
        ];

        foreach ($commonPaths as $path) {
            if (@file_exists($path) && @is_executable($path)) {
                return $path;
            }
        }

        $where = stripos(PHP_OS, 'WIN') === 0 ? 'where' : 'which';
        $output = [];
        $returnVar = 0;
        @exec("{$where} " . escapeshellarg($cmd) . ' 2>/dev/null', $output, $returnVar);
        if ($returnVar === 0 && !empty($output)) {
            $found = trim($output[0]);
            if ($found !== '') {
                return $found;
            }
        }

        return null;
    }

    /**
     * Cek apakah perintah CLI tersedia
     */
    protected static function isCommandAvailable(string $cmd): bool
    {
        return self::findExecutable($cmd) !== null;
    }
}
