<?php
// PHPServerINFO - PHP Szerver Monitorozó (Maximalizált Részletesség)

// Segédfüggvény a bájt alapú értékek formázására
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Hálózati terhelés számításához szükséges adatok gyűjtése
function getNetworkStats() {
    $stats = [];
    
    // Linux hálózati statisztika gyűjtés
    if (PHP_OS === 'Linux' && is_readable('/proc/net/dev')) {
        $netDev = file('/proc/net/dev');
        foreach ($netDev as $line) {
            if (preg_match('/^\s*(\w+):\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)/', $line, $matches)) {
                $interface = $matches[1];
                if ($interface !== 'lo') { // Loopback kihagyása
                    $stats[$interface] = [
                        'rx_bytes' => intval($matches[2]),
                        'tx_bytes' => intval($matches[10])
                    ];
                }
            }
        }
    }
    
    return $stats;
}

// Hálózati terhelés számítása
function calculateNetworkLoad($prevStats, $currentStats, $interval = 5) {
    $load = [];
    
    foreach ($currentStats as $interface => $current) {
        if (isset($prevStats[$interface])) {
            $prev = $prevStats[$interface];
            
            $rx_diff = $current['rx_bytes'] - $prev['rx_bytes'];
            $tx_diff = $current['tx_bytes'] - $prev['tx_bytes'];
            
            $rx_rate = $rx_diff / $interval; // B/s
            $tx_rate = $tx_diff / $interval; // B/s
            
            $load[$interface] = [
                'rx' => formatBytes($rx_rate) . '/s',
                'tx' => formatBytes($tx_rate) . '/s',
                'rx_raw' => $rx_rate,
                'tx_raw' => $tx_rate
            ];
        } else {
            $load[$interface] = [
                'rx' => 'N/A',
                'tx' => 'N/A',
                'rx_raw' => 0,
                'tx_raw' => 0
            ];
        }
    }
    
    return $load;
}

// Fő adatgyűjtő függvény
function getServerInfo() {
    $info = [];
    $os_name = PHP_OS;

    // --- Alapvető Rendszerinformációk ---
    $info['hostname'] = gethostname();
    $info['php_version'] = phpversion();
    $info['server_ip'] = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : 'N/A';
    
    // Alapértelmezett értékek
    $info['os_details'] = php_uname('s') . " " . php_uname('r') . " " . php_uname('m');
    $info['os_icon'] = 'fa-server';
    $info['os_title'] = 'Ismeretlen Rendszer';
    $info['cpu_details'] = 'N/A';
    $info['cpu_load'] = 'N/A (Adatgyűjtés nem támogatott vagy sikertelen)';
    $info['cpu_percent'] = 0; // ÚJ: CPU progress bar érték
    $info['core_count'] = 1; // ÚJ: Alapértelmezett magszám a számításhoz
    $info['memory'] = ['total' => 'N/A', 'used' => 'N/A', 'free' => 'N/A', 'percent' => 0];
    $info['disk_usage'] = [];
    $info['network'] = []; // ÚJ: Hálózati infók tömb
    $info['uptime'] = 'N/A';
    $info['network_load'] = []; // ÚJ: Hálózati terhelés

    if (stristr($os_name, 'LINUX')) {
        // --- LINUX RENDSZER ---

        // OS detektálás és ikon
        $distro = 'Linux';
        if (is_readable('/etc/os-release')) {
            $os_release = file_get_contents('/etc/os-release');
            if (preg_match('/^ID=(.*)$/m', $os_release, $matches)) {
                $id = trim($matches[1], '"');
                $distro = ucfirst($id);
                
                // További OS specifikus információk
                if (preg_match('/^PRETTY_NAME=(.*)$/m', $os_release, $pretty_matches)) {
                    $info['os_details'] = trim($pretty_matches[1], '"');
                }
            }
        }
        $info['os_title'] = $distro;
        $info['os_icon'] = (stristr($distro, 'Ubuntu')) ? 'fa-ubuntu' : 
                          ((stristr($distro, 'Debian')) ? 'fa-debian' : 
                          ((stristr($distro, 'CentOS') || stristr($distro, 'Red Hat') || stristr($distro, 'Fedora')) ? 'fa-redhat' : 
                          ((stristr($distro, 'Arch')) ? 'fa-linux' : 'fa-linux')));

        // CPU Részletek (Architektúra, Modell, Magok száma)
        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            if (preg_match('/model name\s+: (.*)/', $cpuinfo, $matches)) {
                $info['cpu_details'] = trim($matches[1]);
            }
            if (preg_match_all('/processor\s+:/', $cpuinfo, $matches_cores)) {
                $info['core_count'] = count($matches_cores[0]);
                $info['cpu_details'] .= " (" . $info['core_count'] . " mag)";
            }
        } else {
             $info['cpu_details'] = php_uname('m') . " architektúra";
        }
        
        // CPU Terhelés (Load Average) + Százalékos számítás
        $load = sys_getloadavg();
        $info['cpu_load'] = "1p: " . round($load[0], 2) . ", 5p: " . round($load[1], 2) . ", 15p: " . round($load[2], 2);
        
        // Százalékos terhelés számítása a magok számához viszonyítva
        $info['cpu_percent'] = round(($load[0] / $info['core_count']) * 100);
        $info['cpu_percent'] = min($info['cpu_percent'], 100); // Max 100%

        // Memória
        if (is_readable('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $matches_total);
            preg_match('/MemFree:\s+(\d+)\s+kB/', $meminfo, $matches_free);
            preg_match('/Buffers:\s+(\d+)\s+kB/', $meminfo, $matches_buffers);
            preg_match('/Cached:\s+(\d+)\s+kB/', $meminfo, $matches_cached);
            
            $total_kb = isset($matches_total[1]) ? $matches_total[1] : 0;
            $free_kb = isset($matches_free[1]) ? $matches_free[1] : 0;
            $buffers_kb = isset($matches_buffers[1]) ? $matches_buffers[1] : 0;
            $cached_kb = isset($matches_cached[1]) ? $matches_cached[1] : 0;
            
            $usable_free_kb = $free_kb + $buffers_kb + $cached_kb;
            $used_kb = $total_kb - $usable_free_kb;

            $total_gb = round($total_kb / 1024 / 1024, 2);
            $used_gb = round($used_kb / 1024 / 1024, 2);
            $percent = ($total_kb > 0) ? round(($used_kb / $total_kb) * 100) : 0;

            $info['memory'] = [
                'total' => $total_gb . ' GB',
                'used' => $used_gb . ' GB',
                'free' => round($usable_free_kb / 1024 / 1024, 2) . ' GB',
                'percent' => $percent
            ];
        }

        // Uptime
        if (is_readable('/proc/uptime')) {
            $uptime_seconds = floor(floatval(file_get_contents('/proc/uptime')));
            $days = floor($uptime_seconds / 86400);
            $hours = floor(($uptime_seconds % 86400) / 3600);
            $minutes = floor(($uptime_seconds % 3600) / 60);
            $info['uptime'] = "$days nap, $hours óra, $minutes perc";
        }
        
        // Háttértár - több partíció
        $mounts = [];
        if (is_readable('/proc/mounts')) {
            $mounts_content = file('/proc/mounts');
            foreach ($mounts_content as $line) {
                $parts = preg_split('/\s+/', $line);
                if (count($parts) >= 4 && strpos($parts[0], '/dev/') === 0 && $parts[2] !== 'tmpfs') {
                    $mounts[] = $parts[1];
                }
            }
        }
        
        if (empty($mounts)) {
            $mounts = ['/'];
        }
        
        foreach ($mounts as $mount) {
            if (function_exists('disk_total_space') && function_exists('disk_free_space')) {
                $total = @disk_total_space($mount);
                $free = @disk_free_space($mount);
                
                if ($total !== false && $free !== false) {
                    $used = $total - $free;
                    $percent = ($total > 0) ? round(($used / $total) * 100) : 0;
                    
                    $info['disk_usage'][] = [
                        'mount' => $mount,
                        'total' => formatBytes($total),
                        'used' => formatBytes($used),
                        'free' => formatBytes($free),
                        'percent' => $percent
                    ];
                }
            }
        }
        
        // Hálózat - több interfész
        $interfaces = [];
        if (is_dir('/sys/class/net')) {
            $net_dirs = scandir('/sys/class/net');
            foreach ($net_dirs as $dir) {
                if ($dir !== '.' && $dir !== '..' && $dir !== 'lo') {
                    $interfaces[] = $dir;
                }
            }
        }
        
        // Hálózati terhelés számítása
        $currentStats = getNetworkStats();
        $prevStats = isset($_SESSION['network_stats']) ? $_SESSION['network_stats'] : $currentStats;
        $info['network_load'] = calculateNetworkLoad($prevStats, $currentStats);
        $_SESSION['network_stats'] = $currentStats;
        
        foreach ($interfaces as $iface) {
            $ip = shell_exec("ip addr show $iface 2>/dev/null | grep 'inet ' | awk '{print $2}' | cut -d/ -f1");
            $mac = @file_get_contents("/sys/class/net/$iface/address");
            $status = @file_get_contents("/sys/class/net/$iface/operstate");
            
            $info['network'][] = [
                'interface' => $iface,
                'ip' => $ip ? trim($ip) : 'N/A',
                'mac' => $mac ? trim($mac) : 'N/A',
                'status' => $status ? trim($status) : 'N/A',
                'load' => isset($info['network_load'][$iface]) ? $info['network_load'][$iface] : ['rx' => 'N/A', 'tx' => 'N/A']
            ];
        }

    } elseif (stristr($os_name, 'WIN')) {
        // --- WINDOWS RENDSZER ---

        $info['os_title'] = 'Windows Server';
        $info['os_icon'] = 'fa-windows';
        $info['os_details'] = php_uname('s') . " " . php_uname('v') . " " . php_uname('m');
        
        // CPU Részletek (WMI)
        $wmi_cpu = shell_exec('wmic cpu get Name, NumberOfCores /Value 2>&1');
        if ($wmi_cpu && !stristr($wmi_cpu, 'Error')) {
            if (preg_match('/Name=(.*)/', $wmi_cpu, $matches_name)) {
                $info['cpu_details'] = trim($matches_name[1]);
            }
            if (preg_match('/NumberOfCores=(\d+)/', $wmi_cpu, $matches_cores)) {
                $info['core_count'] = (int)$matches_cores[1];
                $info['cpu_details'] .= " (" . $info['core_count'] . " mag)";
            }
        } else {
             $info['cpu_details'] = php_uname('m') . " architektúra";
        }
        
        // CPU Terhelés (Windows: PowerShell-lel megpróbáljuk)
        $ps_cmd = 'powershell "Get-Counter \'\Processor(_Total)\% Processor Time\' | Select-Object -ExpandProperty CounterSamples | Select-Object -ExpandProperty CookedValue" 2>&1';
        $cpu_usage = shell_exec($ps_cmd);
        if ($cpu_usage && is_numeric(trim($cpu_usage))) {
            $info['cpu_percent'] = round(floatval(trim($cpu_usage)));
            $info['cpu_load'] = "CPU kihasználtság: " . $info['cpu_percent'] . "%";
        } else {
            $info['cpu_load'] = 'A százalékos terhelés PHP-ból nehezen, vagy egyáltalán nem érhető el Windows alatt.';
            $info['cpu_percent'] = 0; // Marad 0%
        }

        // Memória
        $wmi_mem = shell_exec('wmic OS get TotalVisibleMemorySize, FreePhysicalMemory /Value 2>&1');
        if ($wmi_mem && !stristr($wmi_mem, 'Error')) {
            preg_match('/TotalVisibleMemorySize=(\d+)/', $wmi_mem, $total_match);
            preg_match('/FreePhysicalMemory=(\d+)/', $wmi_mem, $free_match);
            
            $total_kb = isset($total_match[1]) ? $total_match[1] : 0;
            $free_kb = isset($free_match[1]) ? $free_match[1] : 0;
            
            if ($total_kb > 0) {
                $used_kb = $total_kb - $free_kb;
                $total_gb = round($total_kb / 1024 / 1024, 2);
                $used_gb = round($used_kb / 1024 / 1024, 2);
                $percent = round(($used_kb / $total_kb) * 100);

                $info['memory'] = [
                    'total' => $total_gb . ' GB',
                    'used' => $used_gb . ' GB',
                    'free' => round($free_kb / 1024 / 1024, 2) . ' GB',
                    'percent' => $percent
                ];
            }
        }
        
        // Uptime
        $wmi_time = shell_exec('wmic os get LastBootUpTime /Value 2>&1');
        if ($wmi_time && !stristr($wmi_time, 'Error') && preg_match('/LastBootUpTime=(.*)\./', $wmi_time, $matches)) {
            $last_boot = $matches[1];
            $boot_timestamp = strtotime(substr($last_boot, 0, 4) . '-' . substr($last_boot, 4, 2) . '-' . substr($last_boot, 6, 2) . ' ' . substr($last_boot, 8, 2) . ':' . substr($last_boot, 10, 2) . ':' . substr($last_boot, 12, 2));
            
            if ($boot_timestamp !== false) {
                $uptime_seconds = time() - $boot_timestamp;

                $days = floor($uptime_seconds / 86400);
                $hours = floor(($uptime_seconds % 86400) / 3600);
                $minutes = floor(($uptime_seconds % 3600) / 60);
                $info['uptime'] = "$days nap, $hours óra, $minutes perc";
            }
        }

        // Háttértár - több meghajtó
        $drives = [];
        foreach (range('A', 'Z') as $drive) {
            $path = $drive . ':\\';
            if (is_dir($path)) {
                $drives[] = $path;
            }
        }
        
        foreach ($drives as $drive) {
            if (function_exists('disk_total_space') && function_exists('disk_free_space')) {
                $total = @disk_total_space($drive);
                $free = @disk_free_space($drive);
                
                if ($total !== false && $free !== false) {
                    $used = $total - $free;
                    $percent = ($total > 0) ? round(($used / $total) * 100) : 0;
                    
                    $info['disk_usage'][] = [
                        'mount' => $drive,
                        'total' => formatBytes($total),
                        'used' => formatBytes($used),
                        'free' => formatBytes($free),
                        'percent' => $percent
                    ];
                }
            }
        }
        
        // Hálózat (Windows: WMI)
        $wmi_net = shell_exec('wmic nicconfig where "IPEnabled=True" get Description, IPAddress, MACAddress /Value 2>&1');
        if ($wmi_net && !stristr($wmi_net, 'Error')) {
            $lines = explode("\n", $wmi_net);
            $current_iface = [];
            
            foreach ($lines as $line) {
                if (preg_match('/^Description=(.*)/', $line, $matches)) {
                    if (!empty($current_iface)) {
                        $info['network'][] = $current_iface;
                    }
                    $current_iface = ['interface' => trim($matches[1])];
                } elseif (preg_match('/^IPAddress={(.*)}/', $line, $matches)) {
                    $ips = explode('","', trim($matches[1], '"'));
                    $current_iface['ip'] = isset($ips[0]) ? $ips[0] : 'N/A';
                } elseif (preg_match('/^MACAddress=(.*)/', $line, $matches)) {
                    $current_iface['mac'] = trim($matches[1]);
                }
            }
            
            if (!empty($current_iface)) {
                $info['network'][] = $current_iface;
            }
        }
        
        // Windows hálózati terhelés (PowerShell)
        foreach ($info['network'] as &$net) {
            $net['load'] = ['rx' => 'N/A (Windows)', 'tx' => 'N/A (Windows)'];
        }
    } elseif (stristr($os_name, 'DARWIN') || stristr($os_name, 'MAC')) {
        // --- macOS RENDSZER ---
        
        $info['os_title'] = 'macOS';
        $info['os_icon'] = 'fa-apple';
        $info['os_details'] = php_uname('s') . " " . php_uname('r') . " " . php_uname('m');
        
        // CPU információk
        $cpu_model = shell_exec('sysctl -n machdep.cpu.brand_string 2>&1');
        $core_count = shell_exec('sysctl -n hw.ncpu 2>&1');
        
        $info['cpu_details'] = $cpu_model ? trim($cpu_model) : 'Apple CPU';
        if ($core_count) {
            $info['core_count'] = intval(trim($core_count));
            $info['cpu_details'] .= " (" . $info['core_count'] . " mag)";
        }
        
        // CPU terhelés
        $load = sys_getloadavg();
        $info['cpu_load'] = "1p: " . round($load[0], 2) . ", 5p: " . round($load[1], 2) . ", 15p: " . round($load[2], 2);
        $info['cpu_percent'] = round(($load[0] / $info['core_count']) * 100);
        $info['cpu_percent'] = min($info['cpu_percent'], 100);
        
        // Memória
        $mem_total = shell_exec('sysctl -n hw.memsize 2>&1');
        if ($mem_total) {
            $total_bytes = intval(trim($mem_total));
            $vm_stat = shell_exec('vm_stat 2>&1');
            
            if ($vm_stat) {
                preg_match('/Pages free:\s+(\d+)/', $vm_stat, $free_match);
                preg_match('/Pages active:\s+(\d+)/', $vm_stat, $active_match);
                preg_match('/Pages inactive:\s+(\d+)/', $vm_stat, $inactive_match);
                preg_match('/Pages wired:\s+(\d+)/', $vm_stat, $wired_match);
                
                $page_size = 4096; // macOS page size
                $free_bytes = isset($free_match[1]) ? $free_match[1] * $page_size : 0;
                $used_bytes = $total_bytes - $free_bytes;
                
                $total_gb = round($total_bytes / 1024 / 1024 / 1024, 2);
                $used_gb = round($used_bytes / 1024 / 1024 / 1024, 2);
                $percent = ($total_bytes > 0) ? round(($used_bytes / $total_bytes) * 100) : 0;
                
                $info['memory'] = [
                    'total' => $total_gb . ' GB',
                    'used' => $used_gb . ' GB',
                    'free' => round($free_bytes / 1024 / 1024 / 1024, 2) . ' GB',
                    'percent' => $percent
                ];
            }
        }
        
        // Uptime
        $boot_time = shell_exec('sysctl -n kern.boottime 2>&1');
        if ($boot_time && preg_match('/sec = (\d+)/', $boot_time, $matches)) {
            $boot_timestamp = intval($matches[1]);
            $uptime_seconds = time() - $boot_timestamp;
            
            $days = floor($uptime_seconds / 86400);
            $hours = floor(($uptime_seconds % 86400) / 3600);
            $minutes = floor(($uptime_seconds % 3600) / 60);
            $info['uptime'] = "$days nap, $hours óra, $minutes perc";
        }
        
        // Háttértár
        $df_output = shell_exec('df -k 2>&1');
        if ($df_output) {
            $lines = explode("\n", $df_output);
            foreach ($lines as $line) {
                if (preg_match('/^\/dev\/disk\d+s\d+\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)%\s+(.*)$/', $line, $matches)) {
                    $total_kb = $matches[1] * 1024;
                    $used_kb = $matches[2] * 1024;
                    $free_kb = $matches[3] * 1024;
                    $percent = $matches[4];
                    $mount = $matches[5];
                    
                    if (strpos($mount, '/Volumes/') === 0 || $mount === '/') {
                        $info['disk_usage'][] = [
                            'mount' => $mount,
                            'total' => formatBytes($total_kb),
                            'used' => formatBytes($used_kb),
                            'free' => formatBytes($free_kb),
                            'percent' => intval($percent)
                        ];
                    }
                }
            }
        }
        
        // Hálózat
        $ifconfig = shell_exec('ifconfig 2>&1');
        if ($ifconfig) {
            $interfaces = [];
            $current_iface = '';
            
            $lines = explode("\n", $ifconfig);
            foreach ($lines as $line) {
                if (preg_match('/^(\w+):/', $line, $matches)) {
                    $current_iface = $matches[1];
                    if ($current_iface !== 'lo0') {
                        $interfaces[$current_iface] = ['interface' => $current_iface];
                    }
                } elseif ($current_iface && preg_match('/\s+inet (\d+\.\d+\.\d+\.\d+)/', $line, $matches)) {
                    $interfaces[$current_iface]['ip'] = $matches[1];
                } elseif ($current_iface && preg_match('/\s+ether ([\da-f:]+)/i', $line, $matches)) {
                    $interfaces[$current_iface]['mac'] = $matches[1];
                }
            }
            
            $info['network'] = array_values($interfaces);
            
            // macOS hálózati terhelés
            foreach ($info['network'] as &$net) {
                $net['load'] = ['rx' => 'N/A (macOS)', 'tx' => 'N/A (macOS)'];
            }
        }
    }
    
    return $info;
}

// Munkamenet indítása hálózati statisztikák tárolásához
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$server_data = getServerInfo();
?>

<!DOCTYPE html>
<html lang="hu" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHPServerINFO - Szerver Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="style.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">

<nav class="navbar navbar-expand-lg bg-body-tertiary shadow-sm sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="#">
            <i class="fas fa-server me-2"></i>PHPServerINFO
        </a>
        <div class="d-flex">
            <button class="btn btn-outline-secondary me-2" id="refresh-btn">
                <i class="fas fa-sync-alt"></i> <span class="d-none d-sm-inline">Frissítés</span>
            </button>
            <button class="btn btn-outline-secondary" id="theme-toggle">
                <span class="d-none d-sm-inline">Téma</span> <span id="current-theme-icon">☀️</span>
            </button>
        </div>
    </div>
</nav>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Szerver Műszerfal</h1>
        <div class="text-end">
            <span class="badge bg-primary"><?php echo $server_data['os_title']; ?></span>
            <span class="badge bg-secondary">PHP <?php echo $server_data['php_version']; ?></span>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between">
            <h5 class="mb-0 me-3">Rendszerinformációk</h5>
            <div>
                <i class="fa-brands <?php echo $server_data['os_icon']; ?> fa-2x text-white me-2" title="<?php echo $server_data['os_title']; ?>"></i>
                <span class="badge bg-light text-dark"><?php echo date("H:i:s"); ?></span>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong><i class="fas fa-desktop me-2"></i>Operációs Rendszer:</strong> <?php echo $server_data['os_details']; ?></p>
                    <p><strong><i class="fas fa-microchip me-2"></i>CPU:</strong> <?php echo $server_data['cpu_details']; ?></p>
                    <p><strong><i class="fas fa-server me-2"></i>Hostnév:</strong> <?php echo $server_data['hostname']; ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong><i class="fas fa-clock me-2"></i>Uptime:</strong> <?php echo $server_data['uptime']; ?></p>
                    <p><strong><i class="fab fa-php me-2"></i>PHP Verzió:</strong> <?php echo $server_data['php_version']; ?></p>
                    <p><strong><i class="fas fa-network-wired me-2"></i>Szerver IP:</strong> <?php echo $server_data['server_ip']; ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        
        <div class="col-md-6 mb-4">
            <div class="card h-100 shadow-sm border-warning">
                <div class="card-body">
                    <h5 class="card-title text-warning">
                        <i class="fas fa-microchip me-2"></i>CPU Terhelés
                    </h5>
                    <h6 class="card-subtitle mb-3 text-muted">A szerver magjainak kihasználtsága (<?php echo $server_data['core_count']; ?> mag)</h6>
                    
                    <div class="progress mb-2" role="progressbar" aria-label="CPU" aria-valuenow="<?php echo $server_data['cpu_percent']; ?>" aria-valuemin="0" aria-valuemax="100" style="height: 25px;">
                        <?php 
                            $cpu_class = 'bg-success';
                            if ($server_data['cpu_percent'] > 90) $cpu_class = 'bg-danger';
                            else if ($server_data['cpu_percent'] > 70) $cpu_class = 'bg-warning';
                        ?>
                        <div class="progress-bar <?php echo $cpu_class; ?>" style="width: <?php echo $server_data['cpu_percent']; ?>%">
                            <?php echo $server_data['cpu_percent']; ?>%
                        </div>
                    </div>

                    <p class="card-text small mt-2">
                        <i class="fas fa-chart-line me-2"></i>Terhelés (Load Average): <strong><?php echo $server_data['cpu_load']; ?></strong>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card h-100 shadow-sm border-info">
                <div class="card-body">
                    <h5 class="card-title text-info">
                        <i class="fas fa-memory me-2"></i>Memória Használat
                    </h5>
                    <h6 class="card-subtitle mb-3 text-muted">Összes: <strong><?php echo $server_data['memory']['total']; ?></strong></h6>
                    
                    <div class="progress mb-2" role="progressbar" aria-label="Memória" aria-valuenow="<?php echo $server_data['memory']['percent']; ?>" aria-valuemin="0" aria-valuemax="100" style="height: 25px;">
                        <div class="progress-bar bg-info" style="width: <?php echo $server_data['memory']['percent']; ?>%">
                            <?php echo $server_data['memory']['percent']; ?>% Használt
                        </div>
                    </div>
                    
                    <p class="mb-0 small"><strong>Használt:</strong> <?php echo $server_data['memory']['used']; ?></p>
                    <p class="mb-0 small"><strong>Szabad (használható):</strong> <?php echo $server_data['memory']['free']; ?></p>
                </div>
            </div>
        </div>

    </div>
    
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-success text-white d-flex align-items-center">
            <h5 class="mb-0 me-3"><i class="fas fa-hdd me-2"></i>Háttértár Státusz</h5>
            <span class="badge bg-light text-dark"><?php echo count($server_data['disk_usage']); ?> partíció</span>
        </div>
        <div class="card-body">
            <?php if (!empty($server_data['disk_usage'])): ?>
                <div class="row">
                    <?php foreach ($server_data['disk_usage'] as $disk): ?>
                        <div class="col-md-6 mb-3">
                            <h6 class="mt-0 mb-1">Meghajtó/Partíció: <strong><?php echo $disk['mount']; ?></strong></h6>
                            <small class="text-muted">Összes: <?php echo $disk['total']; ?> | Szabad: <?php echo $disk['free']; ?></small>
                            <div class="progress mb-2" role="progressbar" aria-label="Lemezterület" aria-valuenow="<?php echo $disk['percent']; ?>" aria-valuemin="0" aria-valuemax="100">
                                <?php 
                                    $disk_class = 'bg-success';
                                    if ($disk['percent'] > 90) $disk_class = 'bg-danger';
                                    else if ($disk['percent'] > 70) $disk_class = 'bg-warning';
                                ?>
                                <div class="progress-bar <?php echo $disk_class; ?>" style="width: <?php echo $disk['percent']; ?>%">
                                    <?php echo $disk['percent']; ?>% Használt
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-secondary">A háttértár információk nem érhetők el. Ellenőrizze a PHP jogosultságokat (disk_total_space/disk_free_space).</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-secondary text-white d-flex align-items-center">
            <h5 class="mb-0 me-3"><i class="fas fa-network-wired me-2"></i>Hálózati Interfészek</h5>
            <span class="badge bg-light text-dark"><?php echo count($server_data['network']); ?> interfész</span>
        </div>
        <div class="card-body">
            <?php if (!empty($server_data['network'])): ?>
                <div class="row">
                    <?php foreach ($server_data['network'] as $net): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <?php 
                                            $status_class = 'status-unknown';
                                            if (isset($net['status'])) {
                                                $status_class = ($net['status'] === 'up') ? 'status-up' : 'status-down';
                                            }
                                        ?>
                                        <span class="status-indicator <?php echo $status_class; ?>"></span>
                                        <?php echo $net['interface']; ?>
                                    </h6>
                                    
                                    <p class="mb-1"><strong>IP Cím:</strong> <?php echo $net['ip']; ?></p>
                                    <p class="mb-1"><strong>MAC Cím:</strong> <?php echo $net['mac']; ?></p>
                                    
                                    <?php if (isset($net['load'])): ?>
                                        <div class="network-traffic mt-2">
                                            <p class="mb-0">
                                                <i class="fas fa-arrow-down traffic-down me-1"></i> 
                                                <strong>Fogadás:</strong> <?php echo $net['load']['rx']; ?>
                                            </p>
                                            <p class="mb-0">
                                                <i class="fas fa-arrow-up traffic-up me-1"></i> 
                                                <strong>Küldés:</strong> <?php echo $net['load']['tx']; ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-secondary">A hálózati adatok lekérdezése sikertelen. Ellenőrizze a PHP parancsfuttatási (shell_exec) jogosultságokat.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="alert alert-info d-flex align-items-center">
        <i class="fas fa-info-circle me-3 fa-2x"></i>
        <div>
            <strong>Információ</strong><br>
            Az adatok frissítéséhez kérjük, frissítse az oldalt vagy használja a Frissítés gombot. 
            A hálózati terhelés adatai csak Linux rendszereken érhetők el.
        </div>
    </div>

</div>

<footer class="footer bg-body-tertiary py-3 mt-5 shadow-sm border-top">
    <div class="container text-center">
        <span class="text-muted me-2">Technológiák:</span>
        <i class="fa-brands fa-php fa-2x mx-1 text-primary" title="PHP"></i>
        <i class="fa-brands fa-html5 fa-2x mx-1 text-danger" title="HTML5"></i>
        <i class="fa-brands fa-css3-alt fa-2x mx-1 text-info" title="CSS3"></i>
        <i class="fa-brands fa-js-square fa-2x mx-1 text-warning" title="JavaScript"></i>
        <i class="fa-brands fa-bootstrap fa-2x mx-1 text-purple" title="Bootstrap 5"></i>
        <br>
        <span class="text-muted small mt-2 d-block">PHPServerINFO © 2024 - Fejlesztett verzió</span>
    </div>
</footer>

<button class="btn btn-primary auto-refresh-btn rounded-circle" id="auto-refresh-btn" title="Automatikus frissítés (30s)">
    <i class="fas fa-sync-alt"></i>
</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Világos/Sötét Mód Váltó Logika
    const themeToggle = document.getElementById('theme-toggle');
    const htmlElement = document.querySelector('html');
    const themeIcon = document.getElementById('current-theme-icon');

    const storedTheme = localStorage.getItem('theme') || 'light';
    htmlElement.setAttribute('data-bs-theme', storedTheme);

    if (storedTheme === 'dark') {
        themeIcon.innerHTML = '🌙';
    } else {
        themeIcon.innerHTML = '☀️';
    }

    themeToggle.addEventListener('click', () => {
        let currentTheme = htmlElement.getAttribute('data-bs-theme');
        let newTheme = (currentTheme === 'light') ? 'dark' : 'light';
        
        htmlElement.setAttribute('data-bs-theme', newTheme);
        localStorage.setItem('theme', newTheme);

        if (newTheme === 'dark') {
            themeIcon.innerHTML = '🌙'; 
        } else {
            themeIcon.innerHTML = '☀️';
        }
    });

    // Oldal frissítése
    document.getElementById('refresh-btn').addEventListener('click', function() {
        location.reload();
    });

    // Automatikus frissítés
    let autoRefreshInterval = null;
    const autoRefreshBtn = document.getElementById('auto-refresh-btn');
    
    autoRefreshBtn.addEventListener('click', function() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
            autoRefreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
            autoRefreshBtn.classList.remove('btn-success');
            autoRefreshBtn.classList.add('btn-primary');
            autoRefreshBtn.title = 'Automatikus frissítés (30s)';
        } else {
            autoRefreshInterval = setInterval(function() {
                location.reload();
            }, 30000); // 30 másodperc
            autoRefreshBtn.innerHTML = '<i class="fas fa-stop"></i>';
            autoRefreshBtn.classList.remove('btn-primary');
            autoRefreshBtn.classList.add('btn-success');
            autoRefreshBtn.title = 'Automatikus frissítés leállítása';
        }
    });

    // Animációk a kártyákhoz
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                card.style.transition = 'opacity 0.5s, transform 0.5s';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    });
</script>
</body>
</html>
