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

    if (stristr($os_name, 'LINUX')) {
        // --- LINUX RENDSZER ---

        // OS detektálás és ikon
        $distro = 'Linux';
        if (is_readable('/etc/os-release')) {
            $os_release = file_get_contents('/etc/os-release');
            if (preg_match('/^ID=(.*)$/m', $os_release, $matches)) {
                $id = trim($matches[1], '"');
                $distro = ucfirst($id);
            }
        }
        $info['os_title'] = $distro;
        $info['os_details'] = php_uname('s') . " " . php_uname('r') . " - " . $distro;
        $info['os_icon'] = (stristr($distro, 'Ubuntu')) ? 'fa-ubuntu' : ((stristr($distro, 'Debian')) ? 'fa-debian' : 'fa-linux');

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
        
        // Háttértár
        if (function_exists('disk_total_space') && function_exists('disk_free_space')) {
            $total = @disk_total_space('/');
            $free = @disk_free_space('/');
            
            if ($total !== false && $free !== false) {
                $used = $total - $free;
                $percent = ($total > 0) ? round(($used / $total) * 100) : 0;
                
                $info['disk_usage'][] = [
                    'mount' => '/',
                    'total' => formatBytes($total),
                    'used' => formatBytes($used),
                    'free' => formatBytes($free),
                    'percent' => $percent
                ];
            }
        }
        
        // Hálózat (Egyszerű listázás)
        $route = shell_exec('ip route show default 2>&1');
        if (!empty($route) && !stristr($route, 'Error') && preg_match('/dev\s+(\w+)/', $route, $matches)) {
            $iface = $matches[1];
            $mac = shell_exec("cat /sys/class/net/$iface/address 2>&1");
            
            $info['network'][] = [
                'interface' => $iface,
                'ip' => $info['server_ip'],
                'mac' => trim($mac),
                'gateway' => 'Linux parancsokkal lehetséges, de bonyolultabb',
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
        
        // CPU Terhelés (Windows: Ezt a részletes adatot nehéz lekérdezni PHP-val, 0-t adunk)
        $info['cpu_load'] = 'A százalékos terhelés PHP-ból nehezen, vagy egyáltalán nem érhető el Windows alatt.';
        $info['cpu_percent'] = 0; // Marad 0%

        // Memória
        $wmi_mem = shell_exec('wmic OS get TotalVisibleMemorySize, FreePhysicalMemory /Value 2>&1');
        if ($wmi_mem && !stristr($wmi_mem, 'Error')) {
            preg_match('/TotalVisibleMemorySize=(\d+)/', $wmi_mem, $total_match);
            preg_match('/FreePhysicalMemory=(\d+)/', $wmi_mem, $free_match);
            
            $total_kb = isset($total_match[1]) ? $total_match[1] : 0;
            $free_kb = isset($free_match[1]) ? $free_kb[1] : 0;
            
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

        // Háttértár
        if (function_exists('disk_total_space') && function_exists('disk_free_space')) {
            $total = @disk_total_space('C:\\');
            $free = @disk_free_space('C:\\');
            
            if ($total !== false && $free !== false) {
                $used = $total - $free;
                $percent = ($total > 0) ? round(($used / $total) * 100) : 0;
                
                $info['disk_usage'][] = [
                    'mount' => 'C:\\',
                    'total' => formatBytes($total),
                    'used' => formatBytes($used),
                    'free' => formatBytes($free),
                    'percent' => $percent
                ];
            }
        }
        
        // Hálózat (Windows: Alap IP és MAC)
        $wmi_net = shell_exec('wmic nicconfig where "IPEnabled=True" get IPAddress, MACAddress /Value 2>&1');
        if ($wmi_net && !stristr($wmi_net, 'Error')) {
            if (preg_match_all('/IPAddress={(.*?)}/', $wmi_net, $ip_matches) && preg_match_all('/MACAddress=(.*)/', $wmi_net, $mac_matches)) {
                
                $ips = isset($ip_matches[1][0]) ? explode('","', trim($ip_matches[1][0], '"')) : [];
                $macs = isset($mac_matches[1]) ? $mac_matches[1] : [];
                
                // Csak az elsődleges IP/MAC cím
                $info['network'][] = [
                    'interface' => 'Elsődleges',
                    'ip' => isset($ips[0]) ? $ips[0] : $info['server_ip'],
                    'mac' => isset($macs[0]) ? trim($macs[0]) : 'N/A',
                    'gateway' => 'Windows parancsokkal lehetséges, de bonyolultabb',
                ];
            }
        }
    }
    
    return $info;
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
        <a class="navbar-brand fw-bold" href="#">PHPServerINFO</a>
        <button class="btn btn-outline-secondary" id="theme-toggle">
            <span class="d-none d-sm-inline">Téma Váltás</span> <span id="current-theme-icon">☀️</span>
        </button>
    </div>
</nav>

<div class="container mt-4">

    <h1 class="mb-4">Szerver Műszerfal</h1>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex align-items-center">
            <h5 class="mb-0 me-3">Rendszerinformációk</h5>
            <i class="fa-brands <?php echo $server_data['os_icon']; ?> fa-2x text-white" title="<?php echo $server_data['os_title']; ?>"></i>
        </div>
        <div class="card-body">
            <p><strong>Operációs Rendszer:</strong> <?php echo $server_data['os_details']; ?></p>
            <p><strong>CPU:</strong> <?php echo $server_data['cpu_details']; ?></p>
            <p><strong>Hostnév:</strong> <?php echo $server_data['hostname']; ?></p>
            <p><strong>Uptime:</strong> <?php echo $server_data['uptime']; ?></p>
            <p><strong>PHP Verzió:</strong> <?php echo $server_data['php_version']; ?></p>
        </div>
    </div>

    <div class="row">
        
        <div class="col-md-6 mb-4">
            <div class="card h-100 shadow-sm border-warning">
                <div class="card-body">
                    <h5 class="card-title text-warning">CPU Terhelés</h5>
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

                    <p class="card-text small mt-2">Terhelés (Load Average): **<?php echo $server_data['cpu_load']; ?>**</p>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card h-100 shadow-sm border-info">
                <div class="card-body">
                    <h5 class="card-title text-info">Memória Használat</h5>
                    <h6 class="card-subtitle mb-3 text-muted">Összes: **<?php echo $server_data['memory']['total']; ?>**</h6>
                    
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
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Háttértár Státusz (Lemezterület)</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($server_data['disk_usage'])): ?>
                <?php foreach ($server_data['disk_usage'] as $disk): ?>
                    <h6 class="mt-3 mb-1">Meghajtó/Partíció: **<?php echo $disk['mount']; ?>**</h6>
                    <small class="text-muted">Összes: <?php echo $disk['total']; ?> | Szabad: <?php echo $disk['free']; ?></small>
                    <div class="progress mb-3" role="progressbar" aria-label="Lemezterület" aria-valuenow="<?php echo $disk['percent']; ?>" aria-valuemin="0" aria-valuemax="100">
                        <?php 
                            $disk_class = 'bg-success';
                            if ($disk['percent'] > 90) $disk_class = 'bg-danger';
                            else if ($disk['percent'] > 70) $disk_class = 'bg-warning';
                        ?>
                        <div class="progress-bar <?php echo $disk_class; ?>" style="width: <?php echo $disk['percent']; ?>%">
                            <?php echo $disk['percent']; ?>% Használt
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-secondary">A háttértár információk nem érhetők el. Ellenőrizze a PHP jogosultságokat (disk_total_space/disk_free_space).</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0">Hálózati Interfészek</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($server_data['network'])): ?>
                <?php foreach ($server_data['network'] as $net): ?>
                    <div class="border-bottom pb-2 mb-2">
                        <p class="mb-0"><strong>Interfész:</strong> <?php echo $net['interface']; ?></p>
                        <p class="mb-0"><strong>IP Cím:</strong> <?php echo $net['ip']; ?></p>
                        <p class="mb-0"><strong>MAC Cím:</strong> <?php echo $net['mac']; ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-secondary">A hálózati adatok lekérdezése sikertelen. Ellenőrizze a PHP parancsfuttatási (shell_exec) jogosultságokat.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <p class="text-center text-secondary small mt-4">Az adatok frissítéséhez kérjük, frissítse az oldalt. (Frissítés: <?php echo date("H:i:s"); ?>)</p>

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
        <span class="text-muted small mt-2 d-block">PHPServerINFO © 2024</span>
    </div>
</footer>

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
</script>
</body>
</html>
