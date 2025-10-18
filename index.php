<?php
/**
 * PHPServerINFO 3.0 - Fejlett Szerver Monitorozó Rendszer
 * Kiadás: 2025
 * Verzió: 3.0.0
 * Készítette: DevOFALL
 */

// Hibakezelés beállítása
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Munkamenet kezelés
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class ServerMonitor {
    
    // ... [A formatBytes, formatUptime, safeExec metódusok változatlanok] ...
    public static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    public static function formatUptime($seconds) {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return "$days nap, $hours óra, $minutes perc";
    }
    
    private static function safeExec($command) {
        $output = @shell_exec($command . " 2>/dev/null");
        return $output ? trim($output) : null;
    }
    // ... [A getOSInfo, getCPUInfo, getMemoryInfo, getDiskInfo, getNetworkInfo, 
    //      getInterfaceStatus, getRunningServices, getLinuxProcesses, 
    //      getContainers, getSystemdServices, getWindowsServices, getUptime metódusok változatlanok] ...
    
    // A getOSInfo metódus kódja (teljesen változatlan)
    public static function getOSInfo() {
        $os_name = PHP_OS;
        $info = [];
        if (stristr($os_name, 'LINUX')) {
            $distro = 'Linux';
            $icon = 'fa-linux';
            $version = php_uname('r');
            if (is_readable('/etc/os-release')) {
                $os_release = file_get_contents('/etc/os-release');
                if (preg_match('/^PRETTY_NAME="?([^"]+)"?/m', $os_release, $matches)) {
                    $distro = $matches[1];
                }
                if (preg_match('/^ID=([^\n]+)/m', $os_release, $matches)) {
                    $distro_id = trim($matches[1], '"');
                    switch ($distro_id) {
                        case 'ubuntu': $icon = 'fa-ubuntu'; break;
                        case 'debian': $icon = 'fa-debian'; break;
                        case 'centos': case 'rhel': case 'fedora': $icon = 'fa-redhat'; break;
                        case 'arch': $icon = 'fa-linux'; break;
                    }
                }
            }
            $info = [
                'name' => $distro, 'icon' => $icon, 'version' => $version,
                'architecture' => php_uname('m'), 'kernel' => php_uname('r')
            ];
        } elseif (stristr($os_name, 'WIN')) {
            $info = [
                'name' => 'Windows Server', 'icon' => 'fa-windows', 'version' => php_uname('v'),
                'architecture' => php_uname('m'), 'build' => php_uname('r')
            ];
        } else {
            $info = [
                'name' => 'Ismeretlen Rendszer', 'icon' => 'fa-server', 'version' => php_uname('r'),
                'architecture' => php_uname('m')
            ];
        }
        return $info;
    }

    // A getCPUInfo metódus kódja (teljesen változatlan)
    public static function getCPUInfo() {
        $info = [
            'model' => 'Ismeretlen', 'cores' => 1, 'threads' => 1, 'load' => [0, 0, 0], 'usage' => 0
        ];
        if (PHP_OS === 'Linux') {
            if (is_readable('/proc/cpuinfo')) {
                $cpuinfo = file_get_contents('/proc/cpuinfo');
                if (preg_match('/model name\s+:\s+(.+)/', $cpuinfo, $matches)) {
                    $info['model'] = trim($matches[1]);
                }
                $info['cores'] = (int)@substr_count($cpuinfo, 'processor');
                $info['threads'] = $info['cores'];
            }
            $load = sys_getloadavg();
            $info['load'] = $load;
            $info['usage'] = min(round(($load[0] / $info['cores']) * 100), 100);
        } elseif (stristr(PHP_OS, 'WIN')) {
            $wmi = self::safeExec('wmic cpu get name,numberofcores,numberoflogicalprocessors /value');
            if ($wmi) {
                if (preg_match('/Name=([^\r\n]+)/', $wmi, $matches)) {
                    $info['model'] = trim($matches[1]);
                }
                if (preg_match('/NumberOfCores=(\d+)/', $wmi, $matches)) {
                    $info['cores'] = (int)$matches[1];
                }
                if (preg_match('/NumberOfLogicalProcessors=(\d+)/', $wmi, $matches)) {
                    $info['threads'] = (int)$matches[1];
                }
            }
            $usage = self::safeExec('powershell "Get-Counter \'\\Processor(_Total)\\% Processor Time\' | Select-Object -ExpandProperty CounterSamples | Select-Object -ExpandProperty CookedValue"');
            if ($usage && is_numeric($usage)) {
                $info['usage'] = round((float)$usage);
            }
        }
        return $info;
    }

    // A getMemoryInfo metódus kódja (teljesen változatlan)
    public static function getMemoryInfo() {
        $info = [
            'total' => 0, 'used' => 0, 'free' => 0, 'cached' => 0, 'buffers' => 0, 'usage_percent' => 0
        ];
        if (PHP_OS === 'Linux' && is_readable('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $total);
            preg_match('/MemFree:\s+(\d+)\s+kB/', $meminfo, $free);
            preg_match('/Cached:\s+(\d+)\s+kB/', $meminfo, $cached);
            preg_match('/Buffers:\s+(\d+)\s+kB/', $meminfo, $buffers);
            $info['total'] = ($total[1] ?? 0) * 1024;
            $free_mem = ($free[1] ?? 0) * 1024;
            $cached_mem = ($cached[1] ?? 0) * 1024;
            $buffers_mem = ($buffers[1] ?? 0) * 1024;
            $info['used'] = $info['total'] - $free_mem;
            $info['free'] = $free_mem;
            $info['cached'] = $cached_mem;
            $info['buffers'] = $buffers_mem;
            $info['usage_percent'] = $info['total'] > 0 ? round(($info['used'] / $info['total']) * 100) : 0;
        } elseif (stristr(PHP_OS, 'WIN')) {
            $wmi = self::safeExec('wmic OS get TotalVisibleMemorySize,FreePhysicalMemory /value');
            if ($wmi) {
                preg_match('/TotalVisibleMemorySize=(\d+)/', $wmi, $total);
                preg_match('/FreePhysicalMemory=(\d+)/', $wmi, $free);
                $info['total'] = ($total[1] ?? 0) * 1024;
                $info['free'] = ($free[1] ?? 0) * 1024;
                $info['used'] = $info['total'] - $info['free'];
                $info['usage_percent'] = $info['total'] > 0 ? round(($info['used'] / $info['total']) * 100) : 0;
            }
        }
        return $info;
    }

    // A getDiskInfo metódus kódja (teljesen változatlan)
    public static function getDiskInfo() {
        $disks = [];
        if (PHP_OS === 'Linux') {
            $mounts = ['/'];
            if (is_readable('/proc/mounts')) {
                $mount_data = file('/proc/mounts');
                foreach ($mount_data as $line) {
                    $parts = preg_split('/\s+/', $line);
                    if (count($parts) >= 4 && strpos($parts[0], '/dev/') === 0 && $parts[2] !== 'tmpfs') {
                        $mounts[] = $parts[1];
                    }
                }
            }
            foreach (array_unique($mounts) as $mount) {
                $total = @disk_total_space($mount);
                $free = @disk_free_space($mount);
                if ($total !== false && $free !== false) {
                    $used = $total - $free;
                    $percent = $total > 0 ? round(($used / $total) * 100) : 0;
                    $disks[] = [
                        'mount' => $mount, 'total' => $total, 'used' => $used, 'free' => $free, 'usage_percent' => $percent
                    ];
                }
            }
        } elseif (stristr(PHP_OS, 'WIN')) {
            foreach (range('A', 'Z') as $drive) {
                $path = $drive . ':\\';
                if (is_dir($path)) {
                    $total = @disk_total_space($path);
                    $free = @disk_free_space($path);
                    if ($total !== false && $free !== false) {
                        $used = $total - $free;
                        $percent = $total > 0 ? round(($used / $total) * 100) : 0;
                        $disks[] = [
                            'mount' => $path, 'total' => $total, 'used' => $used, 'free' => $free, 'usage_percent' => $percent
                        ];
                    }
                }
            }
        }
        return $disks;
    }

    // A getNetworkInfo metódus kódja (teljesen változatlan)
    public static function getNetworkInfo() {
        $network = [];
        if (PHP_OS === 'Linux' && is_readable('/proc/net/dev')) {
            $net_dev = file('/proc/net/dev');
            foreach ($net_dev as $line) {
                if (preg_match('/^\s*([a-zA-Z0-9]+):\s+(\d+)\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+(\d+)/', $line, $matches)) {
                    $interface = $matches[1];
                    if ($interface !== 'lo') {
                        $network[] = [
                            'interface' => $interface,
                            'rx_bytes' => (int)$matches[2],
                            'tx_bytes' => (int)$matches[3],
                            'status' => self::getInterfaceStatus($interface)
                        ];
                    }
                }
            }
        }
        return $network;
    }

    // A getInterfaceStatus metódus kódja (teljesen változatlan)
    private static function getInterfaceStatus($interface) {
        $status_file = "/sys/class/net/$interface/operstate";
        if (is_readable($status_file)) {
            return trim(file_get_contents($status_file)) === 'up' ? 'up' : 'down';
        }
        return 'unknown';
    }

    // A getRunningServices metódus kódja (teljesen változatlan)
    public static function getRunningServices() {
        $services = [];
        if (PHP_OS === 'Linux') {
            $processes = self::getLinuxProcesses();
            $services = array_merge($services, $processes);
            $containers = self::getContainers();
            $services = array_merge($services, $containers);
            $systemd_services = self::getSystemdServices();
            $services = array_merge($services, $systemd_services);
        } elseif (stristr(PHP_OS, 'WIN')) {
            $windows_services = self::getWindowsServices();
            $services = array_merge($services, $windows_services);
        }
        return array_values(array_unique($services, SORT_REGULAR));
    }

    // A getLinuxProcesses metódus kódja (teljesen változatlan)
    private static function getLinuxProcesses() {
        $services = [];
        $ps_output = self::safeExec('ps aux');
        $service_patterns = [
            '/apache2|httpd/' => ['name' => 'Apache', 'type' => 'webserver', 'icon' => 'fa-globe'],
            '/nginx/' => ['name' => 'Nginx', 'type' => 'webserver', 'icon' => 'fa-globe'],
            '/lighttpd/' => ['name' => 'Lighttpd', 'type' => 'webserver', 'icon' => 'fa-globe'],
            '/mysqld|mariadb/' => ['name' => 'MySQL/MariaDB', 'type' => 'database', 'icon' => 'fa-database'],
            '/postgres/' => ['name' => 'PostgreSQL', 'type' => 'database', 'icon' => 'fa-database'],
            '/mongod/' => ['name' => 'MongoDB', 'type' => 'database', 'icon' => 'fa-database'],
            '/redis-server/' => ['name' => 'Redis', 'type' => 'database', 'icon' => 'fa-database'],
            '/php-fpm/' => ['name' => 'PHP-FPM', 'type' => 'runtime', 'icon' => 'fa-code'],
            '/node/' => ['name' => 'Node.js', 'type' => 'runtime', 'icon' => 'fa-code'],
            '/python/' => ['name' => 'Python', 'type' => 'runtime', 'icon' => 'fa-code'],
            '/java/' => ['name' => 'Java', 'type' => 'runtime', 'icon' => 'fa-code'],
            '/smbd/' => ['name' => 'Samba', 'type' => 'fileshare', 'icon' => 'fa-network-wired'],
            '/dhcpd/' => ['name' => 'DHCP Server', 'type' => 'network', 'icon' => 'fa-network-wired'],
            '/named/' => ['name' => 'BIND DNS', 'type' => 'dns', 'icon' => 'fa-globe'],
            '/vsftpd/' => ['name' => 'VSFTPD', 'type' => 'ftp', 'icon' => 'fa-file-upload'],
            '/sshd/' => ['name' => 'OpenSSH', 'type' => 'remote', 'icon' => 'fa-terminal'],
            '/postfix/' => ['name' => 'Postfix', 'type' => 'mail', 'icon' => 'fa-envelope'],
            '/dovecot/' => ['name' => 'Dovecot', 'type' => 'mail', 'icon' => 'fa-envelope'],
            '/sqlservr/' => ['name' => 'MS SQL Server', 'type' => 'database', 'icon' => 'fa-database'],
            '/rabbitmq/' => ['name' => 'RabbitMQ', 'type' => 'message', 'icon' => 'fa-envelope'],
            '/prometheus/' => ['name' => 'Prometheus', 'type' => 'monitoring', 'icon' => 'fa-chart-line'],
            '/grafana-server/' => ['name' => 'Grafana', 'type' => 'monitoring', 'icon' => 'fa-chart-bar']
        ];
        foreach ($service_patterns as $pattern => $service_info) {
            if (preg_match($pattern, $ps_output)) {
                $service_info['status'] = 'running';
                $services[] = $service_info;
            }
        }
        return $services;
    }

    // A getContainers metódus kódja (teljesen változatlan)
    private static function getContainers() {
        $containers = [];
        // Docker
        $docker_output = self::safeExec('docker ps --format "{{.Names}}|{{.Image}}|{{.Status}}"');
        if ($docker_output) {
            $lines = explode("\n", $docker_output);
            foreach ($lines as $line) {
                if (!empty($line)) {
                    $parts = explode('|', $line);
                    if (count($parts) >= 3) {
                        $containers[] = [
                            'name' => $parts[0] . ' (Docker)', 'type' => 'container', 'status' => $parts[2],
                            'image' => $parts[1], 'icon' => 'fa-docker'
                        ];
                    }
                }
            }
        }
        // Podman
        $podman_output = self::safeExec('podman ps --format "{{.Names}}|{{.Image}}|{{.Status}}"');
        if ($podman_output) {
            $lines = explode("\n", $podman_output);
            foreach ($lines as $line) {
                if (!empty($line)) {
                    $parts = explode('|', $line);
                    if (count($parts) >= 3) {
                        $containers[] = [
                            'name' => $parts[0] . ' (Podman)', 'type' => 'container', 'status' => $parts[2],
                            'image' => $parts[1], 'icon' => 'fa-box'
                        ];
                    }
                }
            }
        }
        return $containers;
    }

    // A getSystemdServices metódus kódja (teljesen változatlan)
    private static function getSystemdServices() {
        $services = [];
        $systemd_output = self::safeExec('systemctl list-units --type=service --state=running --no-legend');
        if ($systemd_output) {
            $lines = explode("\n", $systemd_output);
            foreach ($lines as $line) {
                if (preg_match('/^([a-zA-Z0-9-]+)\.service/', $line, $matches)) {
                    $service_name = $matches[1];
                    $services[] = [
                        'name' => $service_name . ' (Systemd)', 'type' => 'service', 'status' => 'active', 'icon' => 'fa-cog'
                    ];
                }
            }
        }
        return $services;
    }

    // A getWindowsServices metódus kódja (teljesen változatlan)
    private static function getWindowsServices() {
        $services = [];
        $windows_services = [
            'W3SVC' => ['name' => 'IIS', 'type' => 'webserver', 'icon' => 'fa-globe'],
            'MSSQLSERVER' => ['name' => 'MS SQL Server', 'type' => 'database', 'icon' => 'fa-database'],
            'MySQL' => ['name' => 'MySQL', 'type' => 'database', 'icon' => 'fa-database'],
            'Apache' => ['name' => 'Apache', 'type' => 'webserver', 'icon' => 'fa-globe'],
            'nginx' => ['name' => 'Nginx', 'type' => 'webserver', 'icon' => 'fa-globe'],
            'RabbitMQ' => ['name' => 'RabbitMQ', 'type' => 'message', 'icon' => 'fa-envelope']
        ];
        foreach ($windows_services as $service => $info) {
            $output = self::safeExec("sc query \"$service\"");
            if ($output && strpos($output, 'RUNNING') !== false) {
                $info['status'] = 'running';
                $services[] = $info;
            }
        }
        return $services;
    }

    // A getUptime metódus kódja (teljesen változatlan)
    private static function getUptime() {
        if (PHP_OS === 'Linux' && is_readable('/proc/uptime')) {
            $uptime_seconds = floor(floatval(file_get_contents('/proc/uptime')));
            return self::formatUptime($uptime_seconds);
        } elseif (stristr(PHP_OS, 'WIN')) {
            $wmi = self::safeExec('wmic os get LastBootUpTime /value');
            if ($wmi && preg_match('/LastBootUpTime=([0-9]{14})/', $wmi, $matches)) {
                $boot_time = $matches[1];
                $boot_timestamp = strtotime(
                    substr($boot_time, 0, 4) . '-' . 
                    substr($boot_time, 4, 2) . '-' . 
                    substr($boot_time, 6, 2) . ' ' . 
                    substr($boot_time, 8, 2) . ':' . 
                    substr($boot_time, 10, 2) . ':' . 
                    substr($boot_time, 12, 2)
                );
                if ($boot_timestamp !== false) {
                    return self::formatUptime(time() - $boot_timestamp);
                }
            }
        }
        return 'Ismeretlen';
    }

    public static function getServerInfo() {
        return [
            'os' => self::getOSInfo(),
            'cpu' => self::getCPUInfo(),
            'memory' => self::getMemoryInfo(),
            'disks' => self::getDiskInfo(),
            'network' => self::getNetworkInfo(),
            'services' => self::getRunningServices(),
            'hostname' => gethostname(),
            'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'N/A',
            'php_version' => PHP_VERSION,
            'uptime' => self::getUptime(),
            'timestamp' => time()
        ];
    }
}

// Adatok gyűjtése
$server_data = ServerMonitor::getServerInfo();

// Hálózati statisztikák tárolása (jövőbeli használatra)
if (!isset($_SESSION['network_stats'])) {
    $_SESSION['network_stats'] = [];
}
$_SESSION['last_update'] = time();
?>
<!DOCTYPE html>
<html lang="hu" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHPServerINFO 3.0 - Szerver Monitor</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <link href="style.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
<nav class="navbar navbar-expand-lg bg-body-tertiary shadow-sm sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="#">
            <i class="fas fa-server me-2 text-primary"></i>PHPServerINFO <small class="badge bg-warning ms-1">3.0</small>
        </a>
        <div class="d-flex">
            <button class="btn btn-outline-secondary me-2" id="refresh-btn">
                <i class="fas fa-sync-alt"></i> <span class="d-none d-sm-inline">Frissítés</span>
            </button>
            
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="theme-toggle">
                    <span id="current-theme-icon">☀️</span> Téma
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header">Téma választás</h6></li>
                    <li><a class="dropdown-item" href="#" data-theme="light"><i class="fas fa-sun me-2"></i> Világos</a></li>
                    <li><a class="dropdown-item" href="#" data-theme="dark"><i class="fas fa-moon me-2"></i> Sötét</a></li>
                    <li><a class="dropdown-item" href="#" data-theme="system"><i class="fas fa-desktop me-2"></i> Rendszer</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header">Szín Akcentus</h6></li>
                    <li><a class="dropdown-item accent-color" href="#" data-accent="blue"><i class="fas fa-square me-2" style="color: #0d6efd;"></i> Alapértelmezett (Kék)</a></li>
                    <li><a class="dropdown-item accent-color" href="#" data-accent="purple"><i class="fas fa-square me-2" style="color: #6f42c1;"></i> Lila</a></li>
                    <li><a class="dropdown-item accent-color" href="#" data-accent="green"><i class="fas fa-square me-2" style="color: #198754;"></i> Zöld</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Szerver Műszerfal</h1>
        <div class="text-end">
            <span class="badge bg-primary"><i class="fa-brands <?php echo $server_data['os']['icon']; ?>"></i> <?php echo $server_data['os']['name']; ?></span>
            <span class="badge bg-warning">Kiadás: 2025</span>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card h-100 shadow-sm border-primary">
                <div class="card-header bg-primary text-white d-flex align-items-center">
                    <h5 class="mb-0 me-3"><i class="fas fa-desktop me-2"></i>Rendszerinformációk</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><i class="fas fa-code-branch me-2"></i>Kernel:</strong> <?php echo $server_data['os']['kernel'] ?? $server_data['os']['version']; ?></p>
                            <p><strong><i class="fas fa-microchip me-2"></i>CPU:</strong> <?php echo $server_data['cpu']['model']; ?></p>
                            <p><strong><i class="fas fa-server me-2"></i>Hostnév:</strong> <?php echo $server_data['hostname']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong><i class="fas fa-clock me-2"></i>Uptime:</strong> <?php echo $server_data['uptime']; ?></p>
                            <p><strong><i class="fas fa-network-wired me-2"></i>Szerver IP:</strong> <?php echo $server_data['server_ip']; ?></p>
                            <p><strong><i class="fas fa-memory me-2"></i>Architektúra:</strong> <?php echo $server_data['os']['architecture']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4 mb-4">
            <div class="card h-100 shadow-sm border-info">
                <div class="card-header bg-info text-white d-flex align-items-center">
                    <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Technológiai Stack</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2"><i class="fab fa-php me-2 text-primary"></i> <strong>PHP Verzió:</strong> <span class="badge bg-secondary"><?php echo PHP_VERSION; ?></span></p>
                    <p class="mb-2"><i class="fab fa-bootstrap me-2 text-primary"></i> <strong>Frontend:</strong> <span class="badge bg-secondary">Bootstrap 5.3</span></p>
                    <p class="mb-0"><i class="fab fa-font-awesome me-2 text-primary"></i> <strong>Ikonok:</strong> <span class="badge bg-secondary">Font Awesome 6</span></p>
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
                    <h6 class="card-subtitle mb-3 text-muted">A szerver magjainak kihasználtsága (<?php echo $server_data['cpu']['cores']; ?> mag)</h6>
                    
                    <div class="progress mb-2" role="progressbar" aria-label="CPU" aria-valuenow="<?php echo $server_data['cpu']['usage']; ?>" aria-valuemin="0" aria-valuemax="100" style="height: 25px;">
                        <?php 
                            $cpu_class = 'bg-success';
                            if ($server_data['cpu']['usage'] > 90) $cpu_class = 'bg-danger';
                            else if ($server_data['cpu']['usage'] > 70) $cpu_class = 'bg-warning';
                        ?>
                        <div class="progress-bar <?php echo $cpu_class; ?>" style="width: <?php echo $server_data['cpu']['usage']; ?>%">
                            <?php echo $server_data['cpu']['usage']; ?>%
                        </div>
                    </div>
                    <p class="card-text small mt-2">
                        <i class="fas fa-chart-line me-2"></i>Terhelés: <strong><?php echo $server_data['cpu']['usage']; ?>%</strong>
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
                    <h6 class="card-subtitle mb-3 text-muted">Összes: <strong><?php echo ServerMonitor::formatBytes($server_data['memory']['total']); ?></strong></h6>
                    
                    <div class="progress mb-2" role="progressbar" aria-label="Memória" aria-valuenow="<?php echo $server_data['memory']['usage_percent']; ?>" aria-valuemin="0" aria-valuemax="100" style="height: 25px;">
                        <div class="progress-bar bg-info" style="width: <?php echo $server_data['memory']['usage_percent']; ?>%">
                            <?php echo $server_data['memory']['usage_percent']; ?>% Használt
                        </div>
                    </div>
                    
                    <p class="mb-0 small"><strong>Használt:</strong> <?php echo ServerMonitor::formatBytes($server_data['memory']['used']); ?></p>
                    <p class="mb-0 small"><strong>Szabad:</strong> <?php echo ServerMonitor::formatBytes($server_data['memory']['free']); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($server_data['services'])): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-success text-white d-flex align-items-center">
            <h5 class="mb-0 me-3"><i class="fas fa-cogs me-2"></i>Futó Alkalmazások & Szolgáltatások</h5>
            <span class="badge bg-light text-dark"><?php echo count($server_data['services']); ?> futó</span>
        </div>
        <div class="card-body">
            <div class="row">
                <?php 
                $service_colors = [
                    'webserver' => 'primary', 'database' => 'info', 'runtime' => 'warning',
                    'container' => 'success', 'service' => 'secondary', 'fileshare' => 'purple',
                    'network' => 'cyan', 'dns' => 'indigo', 'ftp' => 'pink', 'remote' => 'orange',
                    'mail' => 'danger', 'message' => 'teal', 'monitoring' => 'dark'
                ];
                
                foreach ($server_data['services'] as $service): 
                    $color = $service_colors[$service['type']] ?? 'secondary';
                ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                        <div class="card h-100 service-card border-<?php echo $color; ?>">
                            <div class="card-body text-center p-3">
                                <i class="fas <?php echo $service['icon']; ?> fa-2x text-<?php echo $color; ?> mb-2"></i>
                                <h6 class="card-title mb-1"><?php echo $service['name']; ?></h6>
                                <span class="badge bg-<?php echo $color; ?> mb-2"><?php echo $service['type']; ?></span>
                                <p class="small mb-1 text-muted">
                                    <i class="fas fa-circle text-success me-1"></i><?php echo $service['status']; ?>
                                </p>
                                <?php if (isset($service['image'])): ?>
                                    <p class="small text-truncate mb-0" title="<?php echo $service['image']; ?>">
                                        <i class="fas fa-box me-1"></i><?php echo $service['image']; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Futó Alkalmazások</h5>
        </div>
        <div class="card-body text-center">
            <p class="text-muted">Nincsenek észlelhető futó alkalmazások</p>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-success text-white d-flex align-items-center">
            <h5 class="mb-0 me-3"><i class="fas fa-hdd me-2"></i>Háttértár Státusz</h5>
            <span class="badge bg-light text-dark"><?php echo count($server_data['disks']); ?> partíció</span>
        </div>
        <div class="card-body">
            <?php if (!empty($server_data['disks'])): ?>
                <div class="row">
                    <?php foreach ($server_data['disks'] as $disk): ?>
                        <div class="col-md-6 mb-3">
                            <h6 class="mt-0 mb-1">Meghajtó: <strong><?php echo $disk['mount']; ?></strong></h6>
                            <small class="text-muted">Összes: <?php echo ServerMonitor::formatBytes($disk['total']); ?> | Szabad: <?php echo ServerMonitor::formatBytes($disk['free']); ?></small>
                            <div class="progress mb-2">
                                <?php
                                    $disk_class = 'bg-success';
                                    if ($disk['usage_percent'] > 90) $disk_class = 'bg-danger';
                                    else if ($disk['usage_percent'] > 70) $disk_class = 'bg-warning';
                                ?>
                                <div class="progress-bar <?php echo $disk_class; ?>" style="width: <?php echo $disk['usage_percent']; ?>%">
                                    <?php echo $disk['usage_percent']; ?>%
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-secondary">A háttértár információk nem érhetők el.</p>
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
                                        <small class="badge bg-secondary"><?php echo $net['status']; ?></small>
                                    </h6>
                                    <p class="network-traffic mb-0">
                                        <i class="fas fa-arrow-down traffic-up me-1"></i>Rx (Fogadott): <strong><?php echo ServerMonitor::formatBytes($net['rx_bytes']); ?></strong>
                                    </p>
                                    <p class="network-traffic mb-0">
                                        <i class="fas fa-arrow-up traffic-down me-1"></i>Tx (Küldött): <strong><?php echo ServerMonitor::formatBytes($net['tx_bytes']); ?></strong>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-secondary">A hálózati interfész információk nem érhetők el.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <footer class="text-center text-muted py-3 border-top mt-5">
        <p class="mb-1">PHPServerINFO 3.0 | <i class="fas fa-code me-1"></i>Készítette: **DevOFALL** | PHP Verzió: <?php echo PHP_VERSION; ?></p>
        <p class="mb-0 small">Utolsó frissítés: <span id="last-update-time"><?php echo date('H:i:s', $server_data['timestamp']); ?></span></p>
    </footer>

</div>

<button class="btn btn-primary auto-refresh-btn shadow-lg" id="auto-refresh-toggle" title="Automatikus frissítés be/ki">
    <i class="fas fa-redo-alt me-1"></i>
    <span id="auto-refresh-text">Auto: Ki</span>
</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const htmlElement = document.documentElement;
        const currentThemeIcon = document.getElementById('current-theme-icon');
        const refreshBtn = document.getElementById('refresh-btn');
        const autoRefreshToggle = document.getElementById('auto-refresh-toggle');
        const autoRefreshText = document.getElementById('auto-refresh-text');
        
        // Alapértelmezett beállítások
        let currentTheme = localStorage.getItem('theme') || 'system'; // Alapértelmezett "system"
        let currentAccent = localStorage.getItem('accent') || 'blue'; // Alapértelmezett "blue"
        let isAutoRefreshOn = localStorage.getItem('autoRefresh') === 'true';

        // --- Szín Akcentus Kezelés ---
        function setAccent(accent) {
            htmlElement.classList.remove('color-accent-blue', 'color-accent-purple', 'color-accent-green');
            if (accent !== 'blue') {
                htmlElement.classList.add(`color-accent-${accent}`);
            }
            localStorage.setItem('accent', accent);
            currentAccent = accent;
        }

        document.querySelectorAll('.accent-color').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                setAccent(this.getAttribute('data-accent'));
            });
        });

        // --- Téma Kezelés ---
        function getSystemTheme() {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        function setTheme(theme) {
            const actualTheme = (theme === 'system') ? getSystemTheme() : theme;
            
            htmlElement.setAttribute('data-bs-theme', actualTheme);
            localStorage.setItem('theme', theme);
            currentTheme = theme;
            currentThemeIcon.textContent = (actualTheme === 'dark') ? '🌙' : '☀️';
        }
        
        document.querySelectorAll('[data-theme]').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                setTheme(this.getAttribute('data-theme'));
            });
        });

        // Rendszer téma változásának figyelése
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (currentTheme === 'system') {
                setTheme('system');
            }
        });

        // Kezdeti beállítások
        setTheme(currentTheme);
        setAccent(currentAccent);

        // --- Manuális Frissítés ---
        refreshBtn.addEventListener('click', () => {
            document.body.classList.add('fade-in');
            setTimeout(() => {
                 location.reload(); 
            }, 500); 
        });

        // --- Automatikus Frissítés ---
        let autoRefreshInterval = null;

        function toggleAutoRefresh(startInterval = true) {
            if (isAutoRefreshOn) {
                // Kikapcsolás
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
                isAutoRefreshOn = false;
                autoRefreshText.textContent = 'Auto: Ki';
            } else {
                // Bekapcsolás
                isAutoRefreshOn = true;
                autoRefreshText.textContent = 'Auto: Be';
                if (startInterval) {
                     autoRefreshInterval = setInterval(() => {
                        document.body.classList.add('fade-in');
                        setTimeout(() => {
                             location.reload(); 
                        }, 500); 
                    }, 5000); 
                }
            }
            localStorage.setItem('autoRefresh', isAutoRefreshOn);
        }

        autoRefreshToggle.addEventListener('click', () => {
             toggleAutoRefresh(false); // Átmenetileg leállítja, majd a funkció maga kezeli az új beállítást
        });

        // Kezdő állapot beállítása (ha be volt kapcsolva utoljára)
        if (isAutoRefreshOn) {
            toggleAutoRefresh(true);
        }
        
        // Animáció eltávolítása betöltés után
        document.body.classList.remove('fade-in');
    });
</script>

</body>
</html>
