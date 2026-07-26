<?php
declare(strict_types=1);

/**
 * System info commands: uname, hostname, whoami, id, date, cal, uptime, who, w, last, lastlog, users, groups, finger, arch, nproc, free, lscpu, lsblk, lspci, lsusb, lshw, dmesg, uname, hostnamectl, timedatectl, locale, printenv, env, getconf, logname, tty, stty, nohup, setsid
 */
class SystemCommands extends BaseCommand
{
    public static function getName(): string { return 'uname'; }
    public static function getDescription(): string { return 'Print system information'; }
    public static function getUsage(): string { return 'uname [-a]'; }

    public function run(array $args): CommandResult
    {
        $cmd = array_shift($args) ?? 'uname';
        $method = 'execute' . ucfirst($cmd);
        if (method_exists($this, $method)) {
            return $this->$method($args);
        }
        return new CommandResult(output: "{$cmd}: command not found", error: true);
    }

    private function executeUname(array $args): CommandResult
    {
        $flag = $args[0] ?? '';
        return match ($flag) {
            '-a', '--all' => new CommandResult(output: 'Linux php-terminal 6.8.0-45-generic #46-Ubuntu SMP x86_64 x86_64 x86_64 GNU/Linux'),
            '-s', '--kernel-name' => new CommandResult(output: 'Linux'),
            '-n', '--nodename' => new CommandResult(output: 'php-terminal'),
            '-r', '--kernel-release' => new CommandResult(output: '6.8.0-45-generic'),
            '-v', '--kernel-version' => new CommandResult(output: '#46-Ubuntu SMP'),
            '-m', '--machine' => new CommandResult(output: 'x86_64'),
            '-p', '--processor' => new CommandResult(output: 'x86_64'),
            '-i', '--hardware-platform' => new CommandResult(output: 'x86_64'),
            '-o', '--operating-system' => new CommandResult(output: 'GNU/Linux'),
            default => new CommandResult(output: 'Linux'),
        };
    }

    private function executeHostname(array $args): CommandResult
    {
        return new CommandResult(output: 'php-terminal');
    }

    private function executeWhoami(array $args): CommandResult
    {
        return new CommandResult(output: $this->username);
    }

    private function executeId(array $args): CommandResult
    {
        $user = $args[0] ?? $this->username;
        $uid = $user === 'root' ? 0 : $this->uid;
        $gid = $user === 'root' ? 0 : $this->gid;
        $groups = $user === 'root' ? 'root' : 'visitor,sudo,users';
        return new CommandResult(output: "uid={$uid}({$user}) gid={$gid}({$user}) groups={$groups}");
    }

    private function executeDate(array $args): CommandResult
    {
        $format = $args[0] ?? '';
        return match ($format) {
            '+%s' => new CommandResult(output: (string)time()),
            '+%F' => new CommandResult(output: date('Y-m-d')),
            '+%D' => new CommandResult(output: date('m/d/y')),
            default => new CommandResult(output: date('D M d H:i:s T Y')),
        };
    }

    private function executeCal(array $args): CommandResult
    {
        $month = (int)($args[0] ?? date('n'));
        $year = (int)($args[1] ?? date('Y'));
        $output = "      " . date('F', mktime(0, 0, 0, $month, 1, $year)) . " {$year}\n" .
                  "Su Mo Tu We Th Fr Sa\n";
        $firstDay = (int)date('w', mktime(0, 0, 0, $month, 1, $year));
        $daysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
        $output .= str_repeat('   ', $firstDay);
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $output .= sprintf('%2d ', $d);
            if (($firstDay + $d) % 7 === 0) $output .= "\n";
        }
        return new CommandResult(output: rtrim($output));
    }

    private function executeUptime(array $args): CommandResult
    {
        $uptime = time() - ($_SESSION['php_terminal_start'] ?? time());
        $days = intdiv($uptime, 86400);
        $hours = intdiv($uptime % 86400, 3600);
        $mins = intdiv($uptime % 3600, 60);
        $users = 1;
        $load = sprintf('%.2f, %.2f, %.2f', 0.08, 0.12, 0.15);
        $time = date('H:i:s');
        return new CommandResult(output: " {$time} up {$days} day, {$hours}:{$mins}, {$users} user, load average: {$load}");
    }

    private function executeWho(array $args): CommandResult
    {
        $now = date('Y-m-d H:i');
        return new CommandResult(output: "{$this->username}   pts/0        {$now} (127.0.0.1)");
    }

    private function executeW(array $args): CommandResult
    {
        $now = date('H:i:s');
        $uptime = time() - ($_SESSION['php_terminal_start'] ?? time());
        $days = intdiv($uptime, 86400);
        return new CommandResult(output: " {$now} up {$days} day,  1 user,  load average: 0.08, 0.12, 0.15\n" .
            "USER     TTY      FROM             LOGIN@   IDLE   JCPU   PCPU WHAT\n" .
            "{$this->username}  pts/0    127.0.0.1      09:00    0.00s  0.05s  0.02s w");
    }

    private function executeLast(array $args): CommandResult
    {
        return new CommandResult(output:
            "{$this->username} pts/0        127.0.0.1       " . date('M d H:i') . "   still logged in\n" .
            "{$this->username} pts/0        127.0.0.1       " . date('M d H:i', strtotime('-1 day')) . " - " . date('H:i', strtotime('-1 day')) . "  (00:05)\n" .
            "reboot   system boot  " . date('M d H:i', strtotime('-3 days')) . "   still running\n" .
            "wtmp begins " . date('M d H:i', strtotime('-30 days')));
    }

    private function executeUsers(array $args): CommandResult
    {
        return new CommandResult(output: $this->username);
    }

    private function executeGroups(array $args): CommandResult
    {
        return new CommandResult(output: "{$this->username} : {$this->username} sudo users");
    }

    private function executeFinger(array $args): CommandResult
    {
        return new CommandResult(output:
            "Login: {$this->username}\t\t\tName: User\n" .
            "Directory: /home/{$this->username}\tShell: /bin/bash\n" .
            "Last login " . date('M d H:i') . " (PST) on pts/0 from 127.0.0.1\n" .
            "No mail.");
    }

    private function executeArch(array $args): CommandResult
    {
        return new CommandResult(output: 'x86_64');
    }

    private function executeNproc(array $args): CommandResult
    {
        return new CommandResult(output: '4');
    }

    private function executeFree(array $args): CommandResult
    {
        $memTotal = 8192000; // 8 GB
        $memUsed = 3200000;
        $memFree = $memTotal - $memUsed;
        $swapTotal = 2097152;
        $swapUsed = 0;
        $swapFree = $swapTotal;
        return new CommandResult(output: "               total        used        free      shared  buff/cache   available\n" .
            "Mem:        " . str_pad((string)$memTotal, 10) . " " . str_pad((string)$memUsed, 10) . " " .
            str_pad((string)$memFree, 10) . "    123456     " . ($memTotal - $memUsed - 500000) . "     " . ($memTotal - $memUsed) . "\n" .
            "Swap:       " . str_pad((string)$swapTotal, 10) . " " . str_pad((string)$swapUsed, 10) . " " .
            str_pad((string)$swapFree, 10));
    }

    private function executeLscpu(array $args): CommandResult
    {
        return new CommandResult(output:
            "Architecture:             x86_64\n" .
            "CPU op-mode(s):           32-bit, 64-bit\n" .
            "Address sizes:            39 bits physical, 48 bits virtual\n" .
            "Byte Order:               Little Endian\n" .
            "CPU(s):                   4\n" .
            "On-line CPU(s) list:      0-3\n" .
            "Vendor ID:                GenuineIntel\n" .
            "Model name:               Intel(R) Core(TM) i7-10750H\n" .
            "CPU family:               6\n" .
            "Model:                     165\n" .
            "Thread(s) per core:       2\n" .
            "Core(s) per socket:       4\n" .
            "Socket(s):                1\n" .
            "L1d cache:                192 KiB\n" .
            "L1i cache:                128 KiB\n" .
            "L2 cache:                 1 MiB\n" .
            "L3 cache:                 12 MiB\n" .
            "Flags:                    fpu vme de pse tsc msr pae mce cx8 apic sep mtrr\n");
    }

    private function executeLsblk(array $args): CommandResult
    {
        return new CommandResult(output:
            "NAME   MAJ:MIN RM   SIZE RO TYPE MOUNTPOINT\n" .
            "sda      8:0    0    20G  0 disk\n" .
            "├─sda1   8:1    0    18G  0 part /\n" .
            "├─sda2   8:2    0     1G  0 part [SWAP]\n" .
            "└─sda3   8:3    0     1G  0 part /boot\n" .
            "sr0     11:0    1  1024M  0 rom  /media/cdrom");
    }

    private function executeLspci(array $args): CommandResult
    {
        return new CommandResult(output:
            "00:00.0 Host bridge: Intel Corporation 10th Gen Core\n" .
            "00:02.0 VGA compatible controller: Intel Corporation CometLake\n" .
            "00:14.0 USB controller: Intel Corporation Comet Lake USB 3.1\n" .
            "00:1f.2 SATA controller: Intel Corporation Comet Lake SATA");
    }

    private function executeLsusb(array $args): CommandResult
    {
        return new CommandResult(output:
            "Bus 001 Device 001: Linux Foundation 2.0 root hub\n" .
            "Bus 001 Device 002: Intel Corp. Integrated Hub\n" .
            "Bus 002 Device 001: Linux Foundation 3.0 root hub");
    }

    private function executeDmesg(array $args): CommandResult
    {
        $lines = [
            "[    0.000000] Linux version 6.8.0-45-generic (build@lcy02) x86_64",
            "[    0.000000] Command line: BOOT_IMAGE=/vmlinuz-6.8.0-45-generic root=/dev/sda1 ro quiet splash",
            "[    0.000000] KERNEL supported cpus: Intel GenuineIntel",
            "[    0.000000] BIOS-e820: [mem 0x0000000000000000-0x000000000009ffff] usable",
            "[    0.000000] BIOS-e820: [mem 0x0000000000100000-0x000000001fffffff] usable",
            "[    0.000000] BIOS-e820: [mem 0x0000000020000000-0x00000000201fffff] reserved",
            "[    0.000000] BIOS-e820: [mem 0x00000000fe000000-0x00000000fe010fff] reserved",
            "[    0.000000] DMI: PHP Terminal v1.0/PHPTerminal, BIOS 1.0 01/01/2026",
            "[    0.000000] CPU0: Intel(R) Core(TM) i7-10750H CPU @ 2.60GHz",
            "[    0.000000] Memory: 8192000K/8388608K available",
            "[    0.000000] Kernel/User page tables isolation: enabled",
            "[    0.000000] ftrace: ftrace is enabled",
            "[    0.000000] sr0: scsi3-mmc drive: 24x/24x writer dvd-ram cd/rw",
            "[    0.000000] sda: sda1 sda2 sda3",
            "[    0.000000] EXT4-fs (sda1): mounted filesystem with ordered data mode. Quota mode: none.",
            "[    0.000000] systemd[1]: systemd 255.4-1ubuntu8 running in system mode (+PAM +AUDIT +SELINUX +APPARMOR)",
            "[    0.000000] systemd[1]: Detected virtualization oracle.",
            "[    0.000000] systemd[1]: Detected architecture x86-64.",
            "[    0.000000] systemd[1]: Set hostname to <php-terminal>.",
        ];
        return new CommandResult(output: implode("\n", $lines));
    }

    private function executeHostnamectl(array $args): CommandResult
    {
        return new CommandResult(output:
            "   Static hostname: php-terminal\n" .
            "         Icon name: computer-vm\n" .
            "           Chassis: vm\n" .
            "        Machine ID: abc123def456\n" .
            "           Boot ID: " . bin2hex(random_bytes(8)) . "\n" .
            "  Virtualization: oracle\n" .
            "  Operating System: Ubuntu 24.04 LTS\n" .
            "            Kernel: Linux 6.8.0-45-generic\n" .
            "      Architecture: x86-64");
    }

    private function executeTimedatectl(array $args): CommandResult
    {
        return new CommandResult(output:
            "               Local time: " . date('Y-m-d H:i:s') . " UTC\n" .
            "           Universal time: " . gmdate('Y-m-d H:i:s') . " UTC\n" .
            "                 RTC time: " . gmdate('Y-m-d H:i:s') . "\n" .
            "                Time zone: Etc/UTC (UTC, +0000)\n" .
            "System clock synchronized: yes\n" .
            "              NTP service: active\n" .
            "          RTC in local TZ: no");
    }

    private function executeLocale(array $args): CommandResult
    {
        return new CommandResult(output:
            "LANG=en_US.UTF-8\n" .
            "LANGUAGE=en_US\n" .
            "LC_CTYPE=en_US.UTF-8\n" .
            "LC_NUMERIC=en_US.UTF-8\n" .
            "LC_TIME=en_US.UTF-8\n" .
            "LC_COLLATE=en_US.UTF-8\n" .
            "LC_ALL=en_US.UTF-8");
    }

    private function executePrintenv(array $args): CommandResult
    {
        if (!empty($args)) {
            return new CommandResult(output: $this->env[$args[0]] ?? '');
        }
        $out = '';
        $defaults = [
            'HOME' => '/home/visitor',
            'USER' => 'visitor',
            'SHELL' => '/bin/bash',
            'PATH' => '/usr/local/bin:/usr/bin:/bin',
            'PWD' => $this->cwd,
            'LANG' => 'en_US.UTF-8',
            'TERM' => 'xterm-256color',
        ];
        $env = array_merge($defaults, $this->env);
        foreach ($env as $k => $v) {
            $out .= "{$k}={$v}\n";
        }
        return new CommandResult(output: rtrim($out));
    }

    private function executeEnv(array $args): CommandResult
    {
        return $this->executePrintenv($args);
    }

    private function executeLogname(array $args): CommandResult
    {
        return new CommandResult(output: $this->username);
    }

    private function executeTty(array $args): CommandResult
    {
        return new CommandResult(output: '/dev/pts/0');
    }

    private function executeLastlog(array $args): CommandResult
    {
        return new CommandResult(output:
            "Username         Port     From             Latest\n" .
            "root                                       **Never logged in**\n" .
            "{$this->username}            pts/0    127.0.0.1        " . date('M d H:i:s') . " +0000");
    }

    private function executeLshw(array $args): CommandResult
    {
        return new CommandResult(output:
            "php-terminal\n" .
            "    description: Computer\n" .
            "    product: Virtual Machine\n" .
            "    vendor: PHP Terminal\n" .
            "    width: 64 bits\n" .
            "    capabilities: smp vsyscall32\n" .
            "  *-core\n" .
            "       description: Motherboard\n" .
            "       physical id: 0\n" .
            "     *-cpu\n" .
            "          description: CPU\n" .
            "          product: Intel(R) Core(TM) i7-10750H\n" .
            "          vendor: Intel Corp.\n" .
            "          size: 2600MHz\n" .
            "          capacity: 5GHz\n" .
            "          width: 64 bits\n" .
            "  *-memory\n" .
            "       description: System Memory\n" .
            "       size: 8GiB\n" .
            "  *-network\n" .
            "       description: Ethernet interface\n" .
            "       product: Intel 82545EM Gigabit\n" .
            "       vendor: Intel Corporation\n" .
            "       physical product: eth0");
    }

    private function executeGetconf(array $args): CommandResult
    {
        $var = $args[0] ?? '';
        $values = [
            'ARG_MAX' => '2097152',
            'NAME_MAX' => '255',
            'PATH_MAX' => '4096',
            'OPEN_MAX' => '1024',
            'PAGE_SIZE' => '4096',
            'NPROCESSORS_CONF' => '4',
            'NPROCESSORS_ONLN' => '4',
        ];
        if ($var && isset($values[$var])) {
            return new CommandResult(output: $values[$var]);
        }
        return new CommandResult(output: $values['ARG_MAX'] ?? '2097152');
    }
}