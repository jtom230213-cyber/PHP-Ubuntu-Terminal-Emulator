<?php
declare(strict_types=1);

/**
 * Network commands: ping, ifconfig, ip, netstat, ss, curl, wget, traceroute, nslookup, dig, host, ssh, scp, ftp, telnet, nc, nmap, iptables, route, arp, ethtool, mtr, whois, hostname (already in SystemCommands)
 */
class NetworkCommands extends BaseCommand
{
    public static function getName(): string { return 'ping'; }
    public static function getDescription(): string { return 'Send ICMP echo requests'; }
    public static function getUsage(): string { return 'ping [host]'; }

    public function run(array $args): CommandResult
    {
        $cmd = array_shift($args) ?? 'ping';
        $method = 'execute' . ucfirst($cmd);
        if (method_exists($this, $method)) {
            return $this->$method($args);
        }
        return new CommandResult(output: "{$cmd}: command not found", error: true);
    }

    private function executePing(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'ping: missing host operand', error: true);
        }

        $count = 4;
        $host = 'localhost';
        $timeout = 3;

        $i = 0;
        while ($i < count($args)) {
            $arg = $args[$i];
            if ($arg === '-c' && isset($args[$i + 1])) {
                $i++;
                $count = max(1, (int)$args[$i]);
            } elseif ($arg === '-W' && isset($args[$i + 1])) {
                $i++;
                $timeout = max(1, (int)$args[$i]);
            } elseif (!str_starts_with($arg, '-')) {
                $host = $arg;
            }
            $i++;
        }

        // Resolve hostname
        $ip = @gethostbyname($host);
        if ($ip === $host) {
            $ip = @gethostbyname($host);
            if ($ip === $host) {
                return new CommandResult(output: "ping: {$host}: Temporary failure in name resolution", error: true);
            }
        }

        $out = "PING {$host} ({$ip}) 56(84) bytes of data.\n";
        $successCount = 0;
        $totalTime = 0;

        for ($i = 0; $i < $count; $i++) {
            // Try TCP connection on port 80/443 as a real connectivity check
            $port = 80;
            $start = microtime(true);
            $fp = @fsockopen($ip, $port, $errno, $errStr, $timeout);
            $elapsed = microtime(true) - $start;

            if ($fp) {
                fclose($fp);
                $timeMs = round($elapsed * 1000, 3);
                $totalTime += $timeMs;
                $successCount++;
                $out .= "64 bytes from {$ip}: icmp_seq={$i} ttl=64 time={$timeMs} ms\n";
            } else {
                // Try port 443
                $start = microtime(true);
                $fp = @fsockopen($ip, 443, $errno, $errStr, $timeout);
                $elapsed = microtime(true) - $start;
                if ($fp) {
                    fclose($fp);
                    $timeMs = round($elapsed * 1000, 3);
                    $totalTime += $timeMs;
                    $successCount++;
                    $out .= "64 bytes from {$ip}: icmp_seq={$i} ttl=64 time={$timeMs} ms\n";
                } else {
                    // Simulated with real IP
                    $timeMs = round(rand(10, 80) + (rand(0, 99) / 1000), 3);
                    $totalTime += $timeMs;
                    $successCount++;
                    $out .= "64 bytes from {$ip}: icmp_seq={$i} ttl=64 time={$timeMs} ms (simulated)\n";
                }
            }
        }

        $loss = $count - $successCount;
        $avg = $successCount > 0 ? round($totalTime / $successCount, 3) : 0;
        $out .= "\n--- {$host} ping statistics ---\n";
        $out .= "{$count} packets transmitted, {$successCount} received, " . round(($loss / $count) * 100) . "% packet loss, time " . ($count * 30) . "ms\n";
        $out .= "rtt min/avg/max/mdev = {$avg}/{$avg}/{$avg}/0.000 ms\n";

        return new CommandResult(output: $out);
    }

    private function executeIfconfig(array $args): CommandResult
    {
        return new CommandResult(output:
            "eth0: flags=4163<UP,BROADCAST,RUNNING,MULTICAST>  mtu 1500\n" .
            "        inet 10.0.0.12  netmask 255.255.255.0  broadcast 10.0.0.255\n" .
            "        inet6 fe80::215:5dff:fe00:1  prefixlen 64  scopeid 0x20<link>\n" .
            "        ether 00:15:5d:00:00:01  txqueuelen 1000  (Ethernet)\n" .
            "        RX packets 12345  bytes 15678901 (15.6 MB)\n" .
            "        RX errors 0  dropped 0  overruns 0  frame 0\n" .
            "        TX packets 9876  bytes 1234567 (12.3 MB)\n" .
            "        TX errors 0  dropped 0 overruns 0  carrier 0  collisions 0\n" .
            "\n" .
            "lo: flags=73<UP,LOOPBACK,RUNNING>  mtu 65536\n" .
            "        inet 127.0.0.1  netmask 255.0.0.0\n" .
            "        inet6 ::1  prefixlen 128  scopeid 0x10<host>\n" .
            "        loop  txqueuelen 1000  (Local Loopback)\n" .
            "        RX packets 5678  bytes 789012 (789.0 KB)\n" .
            "        RX errors 0  dropped 0  overruns 0  frame 0\n" .
            "        TX packets 5678  bytes 789012 (789.0 KB)\n" .
            "        TX errors 0  dropped 0 overruns 0  carrier 0  collisions 0\n");
    }

    private function executeIp(array $args): CommandResult
    {
        $sub = $args[0] ?? 'addr';
        return match ($sub) {
            'addr' => new CommandResult(output:
                "1: lo: <LOOPBACK,UP,LOWER_UP> mtu 65536 state UNKNOWN qlen 1000\n" .
                "    link/loopback 00:00:00:00:00:00 brd 00:00:00:00:00:00\n" .
                "    inet 127.0.0.1/8 scope host lo\n" .
                "    inet6 ::1/128 scope host\n" .
                "2: eth0: <BROADCAST,MULTICAST,UP,LOWER_UP> mtu 1500 state UP qlen 1000\n" .
                "    link/ether 00:15:5d:00:00:01 brd ff:ff:ff:ff:ff:ff\n" .
                "    inet 10.0.0.12/24 brd 10.0.0.255 scope global eth0\n" .
                "    inet6 fe80::215:5dff:fe00:1/64 scope link\n"),
            'route' => new CommandResult(output:
                "default via 10.0.0.1 dev eth0 proto static\n" .
                "10.0.0.0/24 dev eth0 proto kernel scope link src 10.0.0.12\n" .
                "127.0.0.0/8 dev lo proto kernel scope host src 127.0.0.1\n"),
            'link' => new CommandResult(output:
                "1: lo: <LOOPBACK,UP,LOWER_UP> mtu 65536 state UNKNOWN mode DEFAULT qlen 1000\n" .
                "2: eth0: <BROADCAST,MULTICAST,UP,LOWER_UP> mtu 1500 state UP mode DEFAULT qlen 1000\n"),
            'neigh' => new CommandResult(output:
                "10.0.0.1 dev eth0 lladdr 00:15:5d:00:00:01 REACHABLE\n" .
                "10.0.0.2 dev eth0 lladdr 00:15:5d:00:00:02 STALE\n"),
            default => new CommandResult(output: "ip: '{$sub}' is not a valid command"),
        };
    }

    private function executeNetstat(array $args): CommandResult
    {
        $parsed = $this->parseFlags($args, 'tulpn');
        $flags = $parsed['flags'];
        $tcp = isset($flags['t']);
        $udp = isset($flags['u']);
        $listening = isset($flags['l']);
        $program = isset($flags['p']);

        $out = "Active Internet connections (servers and established)\n" .
               "Proto Recv-Q Send-Q Local Address           Foreign Address         State       PID/Program name\n";
        $connections = [
            ['tcp', 0, 0, '0.0.0.0:22', '0.0.0.0:*', 'LISTEN', '200/sshd'],
            ['tcp', 0, 0, '127.0.0.53:53', '0.0.0.0:*', 'LISTEN', '150/systemd-resolve'],
            ['tcp', 0, 0, '0.0.0.0:80', '0.0.0.0:*', 'LISTEN', '450/apache2'],
            ['tcp', 0, 0, '127.0.0.1:3306', '0.0.0.0:*', 'LISTEN', '500/mysqld'],
            ['tcp', 0, 0, '10.0.0.12:22', '10.0.0.1:54321', 'ESTABLISHED', '400/sshd: visitor'],
            ['tcp', 0, 0, '10.0.0.12:80', '10.0.0.1:12345', 'TIME_WAIT', '-'],
            ['udp', 0, 0, '127.0.0.53:53', '0.0.0.0:*', '', '150/systemd-resolve'],
            ['udp', 0, 0, '0.0.0.0:68', '0.0.0.0:*', '', '300/NetworkManager'],
        ];
        foreach ($connections as $c) {
            if ($tcp && $c[0] !== 'tcp') continue;
            if ($udp && $c[0] !== 'udp') continue;
            if ($listening && $c[5] !== 'LISTEN' && $c[5] !== '') continue;
            $out .= sprintf("%-5s %6s %6s %-22s %-22s %-10s %s\n", $c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $c[6]);
        }
        return new CommandResult(output: $out);
    }

    private function executeSs(array $args): CommandResult
    {
        return $this->executeNetstat($args);
    }

    private function executeCurl(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'curl: try \'curl --help\' or \'curl --manual\' for more information', error: true);
        }

        $followRedirects = false;
        $silent = false;
        $showHeaders = false;
        $url = '';

        $i = 0;
        while ($i < count($args)) {
            $arg = $args[$i];
            if ($arg === '-L') {
                $followRedirects = true;
            } elseif ($arg === '-s' || $arg === '--silent') {
                $silent = true;
            } elseif ($arg === '-i' || $arg === '--include') {
                $showHeaders = true;
            } elseif (!str_starts_with($arg, '-')) {
                $url = $arg;
            }
            $i++;
        }

        if (!$url) {
            return new CommandResult(output: 'curl: no URL specified', error: true);
        }

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = 'http://' . $url;
        }

        // Try to fetch the real URL
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'curl/8.4.0',
                'follow_location' => $followRedirects,
                'max_redirects' => $followRedirects ? 5 : 0,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $start = microtime(true);
        $content = @file_get_contents($url, false, $ctx);
        $elapsed = microtime(true) - $start;

        if ($content === false) {
            // Try without SSL
            if (str_starts_with($url, 'https://')) {
                $httpUrl = 'http://' . substr($url, 8);
                $start = microtime(true);
                $content = @file_get_contents($httpUrl, false, $ctx);
                $elapsed = microtime(true) - $start;
            }
        }

        if ($content === false) {
            $error = error_get_last();
            $msg = $error ? preg_replace('/^file_get_contents\([^)]+\):\s*/', '', $error['message']) : 'Failed to connect';
            return new CommandResult(output: "curl: (7) Failed to connect to {$url}: {$msg}", error: true);
        }

        $size = strlen($content);
        $speed = $elapsed > 0 ? round($size / $elapsed) : $size;
        $speedHuman = $speed > 1048576 ? round($speed / 1048576, 1) . 'M' : ($speed > 1024 ? round($speed / 1024, 1) . 'K' : $speed);
        $totalTime = number_format($elapsed, 3);

        $out = '';
        if (!$silent) {
            $out .= "  % Total    % Received % Xferd  Average Speed   Time    Time     Time  Current\n";
            $out .= "                                 Dload  Upload   Total   Spent    Left  Speed\n";
            $out .= "100 {$size}  100 {$size}    0     0   {$speedHuman}      0 --:--:-- --:--:-- --:--:-- {$speedHuman}\n";
            $out .= "\n";
        }

        if ($showHeaders) {
            // Fetch headers via a separate request
            $headers = @get_headers($url, true);
            if ($headers) {
                $out .= "HTTP/1.1 200 OK\n";
                foreach ($headers as $k => $v) {
                    if (is_string($k) && is_string($v)) {
                        $out .= "{$k}: {$v}\n";
                    }
                }
                $out .= "\n";
            }
        }

        // Truncate content to a reasonable size
        if (strlen($content) > 5000) {
            $content = substr($content, 0, 5000) . "\n... [truncated at 5000 bytes]";
        }
        $out .= $content;

        return new CommandResult(output: $out);
    }

    private function executeWget(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'wget: missing URL', error: true);
        }

        $outputFile = '';
        $url = '';

        $i = 0;
        while ($i < count($args)) {
            $arg = $args[$i];
            if ($arg === '-O' && isset($args[$i + 1])) {
                $i++;
                $outputFile = $args[$i];
            } elseif (!str_starts_with($arg, '-')) {
                $url = $arg;
            }
            $i++;
        }

        if (!$url) {
            return new CommandResult(output: 'wget: missing URL', error: true);
        }

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = 'http://' . $url;
        }

        // Determine output filename
        if (!$outputFile) {
            $parsedUrl = parse_url($url);
            $path = $parsedUrl['path'] ?? '/';
            $outputFile = basename($path);
            if (!$outputFile || $outputFile === '' || str_ends_with($path, '/')) {
                $outputFile = 'index.html';
            }
        }

        $outputPath = $this->resolve($outputFile);

        // Resolve hostname for real IP
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? '';
        $ip = @gethostbyname($host);
        if ($ip === $host) {
            $ip = '127.0.0.1';
        }

        $port = ($parsedUrl['scheme'] ?? 'http') === 'https' ? 443 : 80;

        $out = "--" . date('Y-m-d H:i:s') . "--  {$url}\n";
        $out .= "Resolving {$host}... {$ip}\n";
        $out .= "Connecting to {$host}|{$ip}|:{$port}... ";

        // Try real connection
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Wget/1.24.5',
                'follow_location' => true,
                'max_redirects' => 5,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $start = microtime(true);
        $content = @file_get_contents($url, false, $ctx);
        $elapsed = microtime(true) - $start;

        if ($content === false && str_starts_with($url, 'https://')) {
            $httpUrl = 'http://' . substr($url, 8);
            $start = microtime(true);
            $content = @file_get_contents($httpUrl, false, $ctx);
            $elapsed = microtime(true) - $start;
        }

        if ($content === false) {
            // Fallback: simulate
            $content = "<!DOCTYPE html>\n<html><head><title>Simulated Page</title></head><body><h1>Simulated</h1><p>This is a simulated response for {$url}</p></body></html>";
            $simulated = true;
        } else {
            $simulated = false;
        }

        $size = strlen($content);
        $speed = $elapsed > 0 ? round($size / $elapsed) : $size;
        $speedHuman = $speed > 1048576 ? round($speed / 1048576, 1) . ' MB/s' : ($speed > 1024 ? round($speed / 1024, 1) . ' KB/s' : $speed . ' B/s');

        $out .= "connected.\n";
        $out .= "HTTP request sent, awaiting response... 200 OK\n";
        $out .= "Length: {$size} [text/html]\n";
        $out .= "Saving to: '{$outputFile}'\n\n";

        // Progress bar
        $barWidth = 50;
        $progress = str_repeat('=', $barWidth);
        $out .= "     0K {$progress} 100% {$speedHuman}\n\n";

        $out .= date('Y-m-d H:i:s') . " ({$speedHuman}) - '{$outputFile}' saved [{$size}/{$size}]\n";

        if ($simulated) {
            $out .= "\n(warning: remote server not reachable, saved simulated content)";
        }

        // Save to virtual filesystem
        $parentDir = dirname($outputPath);
        if (!$this->fs->exists($parentDir)) {
            $this->fs->createDir($parentDir);
        }
        if ($this->fs->exists($outputPath)) {
            $this->fs->remove($outputPath);
        }
        $this->fs->createFile($outputPath, $content);

        return new CommandResult(output: $out);
    }

    private function executeTraceroute(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'traceroute: missing host', error: true);
        }
        $host = $args[0];

        // Resolve real IP
        $ip = @gethostbyname($host);
        if ($ip === $host) {
            $ip = '127.0.0.1';
        }

        $out = "traceroute to {$host} ({$ip}), 30 hops max, 60 byte packets\n";

        // Generate realistic-looking hops based on real IP
        $octets = explode('.', $ip);
        $base = (int)$octets[0];

        // Simulate a route path
        $hops = [
            ['router.local', '192.168.1.1', rand(1, 5)],
            ['gw.' . $host, $base . '.0.0.1', rand(5, 20)],
            ['core1.' . $host, $base . '.0.1.1', rand(10, 30)],
            ['core2.' . $host, $base . '.1.0.1', rand(15, 40)],
            ['edge1.' . $host, $base . '.2.0.1', rand(20, 50)],
        ];

        foreach ($hops as $i => $hop) {
            $hopNum = $i + 1;
            $ms1 = $hop[2] + rand(0, 5);
            $ms2 = $hop[2] + rand(0, 8);
            $ms3 = $hop[2] + rand(0, 3);
            $out .= " {$hopNum}  {$hop[0]} ({$hop[1]})  {$ms1}.{$ms2} ms  {$ms2}.{$ms3} ms  {$ms3}.{$ms1} ms\n";
        }

        // Final hop
        $hopNum = count($hops) + 1;
        $ms1 = rand(30, 80);
        $ms2 = $ms1 + rand(0, 10);
        $ms3 = $ms1 + rand(0, 5);
        $out .= " {$hopNum}  {$host} ({$ip})  {$ms1}.{$ms2} ms  {$ms2}.{$ms3} ms  {$ms3}.{$ms1} ms\n";

        return new CommandResult(output: $out);
    }

    private function executeNslookup(array $args): CommandResult
    {
        $host = $args[0] ?? 'localhost';
        $ip = @gethostbyname($host);
        if ($ip === $host) {
            $ip = '127.0.0.1';
        }
        return new CommandResult(output:
            "Server:         127.0.0.53\n" .
            "Address:        127.0.0.53#53\n" .
            "\n" .
            "Non-authoritative answer:\n" .
            "Name:   {$host}\n" .
            "Address: {$ip}\n");
    }

    private function executeDig(array $args): CommandResult
    {
        $host = $args[0] ?? 'localhost';
        $ip = @gethostbyname($host);
        if ($ip === $host) {
            $ip = '127.0.0.1';
        }

        // Try to get real DNS records
        $records = @dns_get_record($host, DNS_A + DNS_AAAA + DNS_MX + DNS_NS);
        $answerSection = '';
        $hasRealAnswer = false;

        if ($records !== false && !empty($records)) {
            foreach ($records as $rec) {
                if (($rec['type'] ?? '') === 'A') {
                    $answerSection .= "{$host}.\t\t3600\tIN\tA\t{$rec['ip']}\n";
                    $hasRealAnswer = true;
                } elseif (($rec['type'] ?? '') === 'AAAA') {
                    $answerSection .= "{$host}.\t\t3600\tIN\tAAAA\t{$rec['ipv6']}\n";
                    $hasRealAnswer = true;
                } elseif (($rec['type'] ?? '') === 'MX') {
                    $answerSection .= "{$host}.\t\t3600\tIN\tMX\t{$rec['pri']} {$rec['target']}\n";
                    $hasRealAnswer = true;
                } elseif (($rec['type'] ?? '') === 'NS') {
                    $answerSection .= "{$host}.\t\t3600\tIN\tNS\t{$rec['target']}\n";
                    $hasRealAnswer = true;
                }
            }
        }

        if (!$hasRealAnswer) {
            $answerSection = "{$host}.\t\t3600\tIN\tA\t{$ip}\n";
        }

        return new CommandResult(output:
            "; <<>> DiG 9.18.28-1ubuntu1 <<>> {$host}\n" .
            ";; global options: +cmd\n" .
            ";; Got answer:\n" .
            ";; ->>HEADER<<- opcode: QUERY, status: NOERROR, id: " . rand(10000, 60000) . "\n" .
            ";; flags: qr rd ra; QUERY: 1, ANSWER: 1, AUTHORITY: 0, ADDITIONAL: 1\n" .
            "\n" .
            ";; QUESTION SECTION:\n" .
            ";{$host}.\t\tIN\tA\n" .
            "\n" .
            ";; ANSWER SECTION:\n" .
            $answerSection .
            "\n" .
            ";; Query time: " . rand(10, 80) . " msec\n" .
            ";; SERVER: 127.0.0.53#53(127.0.0.53) (UDP)\n" .
            ";; WHEN: " . date('D M d H:i:s') . " UTC\n" .
            ";; MSG SIZE  rcvd: " . rand(50, 200) . "\n");
    }

    private function executeHost(array $args): CommandResult
    {
        $host = $args[0] ?? 'localhost';
        $ip = @gethostbyname($host);
        if ($ip === $host) {
            $ip = '127.0.0.1';
        }
        return new CommandResult(output: "{$host} has address {$ip}\n{$host} has IPv6 address ::1");
    }

    private function executeSsh(array $args): CommandResult
    {
        $host = 'localhost';
        $port = 22;
        $user = '';

        $i = 0;
        while ($i < count($args)) {
            $arg = $args[$i];
            if ($arg === '-p' && isset($args[$i + 1])) {
                $i++;
                $port = (int)$args[$i];
            } elseif (str_contains($arg, '@')) {
                $parts = explode('@', $arg, 2);
                $user = $parts[0];
                $host = $parts[1];
            } elseif (!str_starts_with($arg, '-')) {
                $host = $arg;
            }
            $i++;
        }

        $ip = @gethostbyname($host);
        if ($ip === $host) {
            $ip = $host;
        }

        // Try a real TCP connection
        $fp = @fsockopen($ip, $port, $errno, $errStr, 3);
        if ($fp) {
            fclose($fp);
            $connection = $user ? "{$user}@{$host}" : $host;
            return new CommandResult(output:
                "The authenticity of host '{$host} ({$ip})' can't be established.\n" .
                "ED25519 key fingerprint is SHA256:" . bin2hex(random_bytes(16)) . ".\n" .
                "This key is not known by any other names.\n" .
                "Are you sure you want to continue connecting (yes/no/[fingerprint])? \n" .
                "[simulated] Port {$port} on {$host} is open and accepting connections.");
        }

        return new CommandResult(output:
            "ssh: connect to host {$host} port {$port}: Connection refused\n" .
            "[simulated]");
    }

    private function executeScp(array $args): CommandResult
    {
        return new CommandResult(output: "scp: simulated file copy completed.");
    }

    private function executeTelnet(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'telnet: missing host', error: true);
        }

        $host = $args[0];
        $port = (int)($args[1] ?? 23);
        $ip = @gethostbyname($host);
        if ($ip === $host) {
            $ip = $host;
        }

        $fp = @fsockopen($ip, $port, $errno, $errStr, 3);
        if ($fp) {
            fclose($fp);
            return new CommandResult(output:
                "Trying {$ip}...\n" .
                "Connected to {$host}.\n" .
                "Escape character is '^]'.\n" .
                "[simulated telnet connection established on port {$port}]");
        }

        return new CommandResult(output:
            "Trying {$ip}...\n" .
            "telnet: Unable to connect to remote host: Connection refused");
    }

    private function executeNc(array $args): CommandResult
    {
        if (count($args) < 2) {
            return new CommandResult(output: 'nc: usage: nc -v host port');
        }

        $flags = '';
        $host = '';
        $port = 0;

        $i = 0;
        while ($i < count($args)) {
            $arg = $args[$i];
            if (str_starts_with($arg, '-')) {
                $flags .= ltrim($arg, '-');
            } elseif ($host === '') {
                $host = $arg;
            } else {
                $port = (int)$arg;
            }
            $i++;
        }

        if (!$host || !$port) {
            return new CommandResult(output: 'nc: usage: nc -v host port');
        }

        $ip = @gethostbyname($host);
        if ($ip === $host) {
            $ip = $host;
        }

        $fp = @fsockopen($ip, $port, $errno, $errStr, 3);
        if ($fp) {
            fclose($fp);
            $verbose = str_contains($flags, 'v');
            $out = $verbose ? "Connection to {$host} {$port} port [tcp/*] succeeded!\n" : '';
            $out .= "[nc: connected to {$host}:{$port}]";
            return new CommandResult(output: $out);
        }

        return new CommandResult(output: "nc: connect to {$host} port {$port} failed: Connection refused");
    }

    private function executeNmap(array $args): CommandResult
    {
        $target = 'localhost';
        $ports = [22, 80, 443, 3306, 8080];
        $fast = false;

        $i = 0;
        while ($i < count($args)) {
            $arg = $args[$i];
            if ($arg === '-F') {
                $fast = true;
                $ports = [22, 80, 443];
            } elseif ($arg === '-p' && isset($args[$i + 1])) {
                $i++;
                $ports = explode(',', $args[$i]);
                $ports = array_map('intval', $ports);
            } elseif (!str_starts_with($arg, '-')) {
                $target = $arg;
            }
            $i++;
        }

        $ip = @gethostbyname($target);
        if ($ip === $target) {
            $ip = '127.0.0.1';
        }

        $out = "Starting Nmap 7.94SVN ( https://nmap.org )\n";
        $out .= "Nmap scan report for {$target} ({$ip})\n";
        $out .= "Host is up (0.00012s latency).\n";

        $openPorts = [];
        $filteredPorts = [];
        $serviceNames = [
            21 => 'ftp', 22 => 'ssh', 23 => 'telnet', 25 => 'smtp',
            53 => 'domain', 80 => 'http', 110 => 'pop3', 143 => 'imap',
            443 => 'https', 465 => 'smtps', 587 => 'submission',
            993 => 'imaps', 995 => 'pop3s', 3306 => 'mysql',
            3389 => 'ms-wbt-server', 5432 => 'postgresql',
            6379 => 'redis', 8080 => 'http-proxy', 8443 => 'https-alt',
            27017 => 'mongodb',
        ];

        $startTime = microtime(true);
        foreach ($ports as $port) {
            $fp = @fsockopen($ip, $port, $errno, $errStr, 1.5);
            if ($fp) {
                fclose($fp);
                $openPorts[] = $port;
            } else {
                // Randomly mark some closed ports as filtered for realism
                if (rand(0, 10) === 0) {
                    $filteredPorts[] = $port;
                }
            }
        }
        $elapsed = microtime(true) - $startTime;

        $openCount = count($openPorts);
        $filteredCount = count($filteredPorts);
        $closedCount = count($ports) - $openCount - $filteredCount;

        $out .= "Not shown: {$closedCount} closed tcp ports (reset)\n";
        if ($filteredCount > 0) {
            $out .= "Some closed ports may be filtered (TODO)\n";
        }

        if ($openCount > 0) {
            $out .= "PORT    STATE    SERVICE\n";
            foreach ($openPorts as $port) {
                $service = $serviceNames[$port] ?? 'unknown';
                $out .= sprintf("%-5d  open     %s\n", $port, $service);
            }
        }

        if ($filteredCount > 0) {
            $out .= "\nPORT    STATE    SERVICE\n";
            foreach ($filteredPorts as $port) {
                $service = $serviceNames[$port] ?? 'unknown';
                $out .= sprintf("%-5d  filtered %s\n", $port, $service);
            }
        }

        $out .= "\nNmap done: 1 IP address (1 host up) scanned in " . round($elapsed, 2) . " seconds\n";

        return new CommandResult(output: $out);
    }

    private function executeIptables(array $args): CommandResult
    {
        return new CommandResult(output:
            "Chain INPUT (policy ACCEPT)\n" .
            "target     prot opt source               destination\n" .
            "ACCEPT     all  --  anywhere             anywhere             state RELATED,ESTABLISHED\n" .
            "ACCEPT     icmp --  anywhere             anywhere\n" .
            "ACCEPT     all  --  anywhere             anywhere\n" .
            "ACCEPT     tcp  --  anywhere             anywhere             state NEW tcp dpt:22\n" .
            "REJECT     all  --  anywhere             anywhere             reject-with icmp-host-prohibited\n" .
            "\n" .
            "Chain FORWARD (policy ACCEPT)\n" .
            "target     prot opt source               destination\n" .
            "REJECT     all  --  anywhere             anywhere             reject-with icmp-host-prohibited\n" .
            "\n" .
            "Chain OUTPUT (policy ACCEPT)\n" .
            "target     prot opt source               destination\n");
    }

    private function executeRoute(array $args): CommandResult
    {
        return new CommandResult(output:
            "Kernel IP routing table\n" .
            "Destination     Gateway         Genmask         Flags Metric Ref    Use Iface\n" .
            "0.0.0.0         10.0.0.1        0.0.0.0         UG    100    0        0 eth0\n" .
            "10.0.0.0        0.0.0.0         255.255.255.0   U     100    0        0 eth0\n" .
            "127.0.0.0       0.0.0.0         255.0.0.0       U     0      0        0 lo\n");
    }

    private function executeArp(array $args): CommandResult
    {
        return new CommandResult(output:
            "Address                  HWtype  HWaddress           Flags Mask            Iface\n" .
            "10.0.0.1                 ether   00:15:5d:00:00:01   C                     eth0\n" .
            "10.0.0.2                 ether   00:15:5d:00:00:02   C                     eth0\n");
    }

    private function executeWhois(array $args): CommandResult
    {
        $domain = $args[0] ?? 'example.com';

        // Try to fetch real whois data via a whois service
        $ctx = stream_context_create([
            'http' => ['timeout' => 5, 'user_agent' => 'Mozilla/5.0'],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $whoisData = @file_get_contents("https://whois.{$domain}", false, $ctx);
        if ($whoisData === false) {
            $whoisData = @file_get_contents("https://rdap.{$domain}", false, $ctx);
        }

        if ($whoisData !== false && strlen($whoisData) > 100) {
            $whoisData = substr($whoisData, 0, 2000);
            return new CommandResult(output: $whoisData);
        }

        // Fallback to simulated
        return new CommandResult(output:
            "   Domain Name: {$domain}\n" .
            "   Registry Domain ID: " . rand(100000000, 999999999) . "_DOMAIN_COM\n" .
            "   Registrar: PHPTerminal Registrar\n" .
            "   Creation Date: 2020-01-01T00:00:00Z\n" .
            "   Updated Date: 2026-01-01T00:00:00Z\n" .
            "   Name Server: NS1.{$domain}\n" .
            "   Name Server: NS2.{$domain}\n");
    }

    private function executeFtp(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output:
                "ftp: usage: ftp [host] [port]\n" .
                "ftp> help for available commands");
        }

        $host = $args[0];
        $port = (int)($args[1] ?? 21);

        $ip = @gethostbyname($host);
        if ($ip === $host) {
            $ip = $host;
        }

        $fp = @fsockopen($ip, $port, $errno, $errStr, 3);
        if ($fp) {
            fclose($fp);
            return new CommandResult(output:
                "Connected to {$host} ({$ip}).\n" .
                "220 ProFTPD 1.3.8 Server ready\n" .
                "Name ({$host}:visitor): \n" .
                "[ftp: connection established. Use 'ftp' interactively.]");
        }

        return new CommandResult(output:
            "ftp: connect: Connection refused\n" .
            "ftp: Unable to connect to {$host}:{$port}");
    }

    private function executeEthtool(array $args): CommandResult
    {
        $iface = $args[0] ?? 'eth0';
        return new CommandResult(output:
            "Settings for {$iface}:\n" .
            "        Supported ports: [ TP MII FIBRE ]\n" .
            "        Supported link modes: 10baseT/Half 10baseT/Full\n" .
            "                                100baseT/Half 100baseT/Full\n" .
            "                                1000baseT/Full\n" .
            "        Speed: 1000Mb/s\n" .
            "        Duplex: Full\n" .
            "        Auto-negotiation: on\n" .
            "        Link detected: yes");
    }

    private function executeMtr(array $args): CommandResult
    {
        $host = $args[0] ?? 'example.com';
        $ip = @gethostbyname($host);
        return new CommandResult(output:
            "PHP Terminal MTR (simulated)\n" .
            "Host: {$host} ({$ip})\n" .
            " 1. 192.168.1.1      0.5ms\n" .
            " 2. 10.0.0.1         2.1ms\n" .
            " 3. 203.0.113.1      12.3ms\n" .
            " 4. {$ip}          25.7ms\n" .
            "--- {$host} MTR statistics ---\n" .
            " 4 packets transmitted, 4 received, 0% loss");
    }

    private function executeIwconfig(array $args): CommandResult
    {
        return new CommandResult(output:
            "eth0      no wireless extensions.\n" .
            "wlan0     IEEE 802.11  ESSID:\"MyNetwork\"\n" .
            "          Mode:Managed  Frequency:2.437 GHz  Access Point: AA:BB:CC:DD:EE:FF\n" .
            "          Bit Rate=150 Mb/s  Tx-Power=20 dBm\n" .
            "          Link Quality=70/70  Signal level=-40 dBm\n");
    }

    private function executeIwlist(array $args): CommandResult
    {
        $iface = $args[0] ?? 'wlan0';
        return new CommandResult(output:
            "{$iface}     Scan completed :\n" .
            "          Cell 01 - Address: AA:BB:CC:DD:EE:FF\n" .
            "                ESSID:\"MyNetwork\"\n" .
            "                Protocop:IEEE 802.11bgn\n" .
            "                Frequency:2.430 GHz\n" .
            "                Quality=70/100  Signal=-40dBm\n");
    }

    private function executeRfkill(array $args): CommandResult
    {
        return new CommandResult(output:
            "ID TYPE      DEVICE      SOFT      HARD\n" .
            " 0 wlan      phy0   unblocked unblocked\n" .
            " 1 bt        hci0     blocked unblocked");
    }
}