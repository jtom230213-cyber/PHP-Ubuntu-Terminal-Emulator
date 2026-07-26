<?php
declare(strict_types=1);

/**
 * Process commands: ps, top, kill, pkill, killall, bg, fg, jobs, nice, renice, nohup, timeout, watch, sleep, yes, seq, xargs, parallel
 */
class ProcessCommands extends BaseCommand
{
    public static function getName(): string { return 'ps'; }
    public static function getDescription(): string { return 'Report a snapshot of the current processes'; }
    public static function getUsage(): string { return 'ps [aux]'; }

    public function run(array $args): CommandResult
    {
        $cmd = array_shift($args) ?? 'ps';
        $method = 'execute' . ucfirst($cmd);
        if (method_exists($this, $method)) {
            return $this->$method($args);
        }
        return new CommandResult(output: "{$cmd}: command not found", error: true);
    }

    private function executePs(array $args): CommandResult
    {
        $parsed = $this->parseFlags($args, 'auxef');
        $flags = $parsed['flags'];
        $wide = isset($flags['e']) || isset($flags['f']);

        $user = $this->username;
        $now = time();
        $processes = [
            ['root',   1,    0,  'Apr01', '?',     '00:00:45', '/sbin/init splash'],
            ['root',   2,    0,  'Apr01', '?',     '00:00:00', '[kthreadd]'],
            ['root',   3,    2,  'Apr01', '?',     '00:00:12', '[ksoftirqd/0]'],
            ['root',   5,    2,  'Apr01', '?',     '00:00:00', '[kworker/0:0H]'],
            ['root',   7,    2,  'Apr01', '?',     '00:00:08', '[rcu_preempt]'],
            ['root',   8,    2,  'Apr01', '?',     '00:00:00', '[rcu_sched]'],
            ['root',   9,    2,  'Apr01', '?',     '00:00:00', '[rcu_bh]'],
            ['root',  10,    2,  'Apr01', '?',     '00:00:00', '[migration/0]'],
            ['root',  33,    2,  'Apr01', '?',     '00:00:00', '[cpuhp/0]'],
            ['root',  34,    2,  'Apr01', '?',     '00:00:00', '[cpuhp/1]'],
            ['root', 100,    1,  'Apr01', '?',     '00:00:02', '/lib/systemd/systemd-journald'],
            ['root', 120,    1,  'Apr01', '?',     '00:00:00', '/lib/systemd/systemd-udevd'],
            ['root', 200,    1,  'Apr01', '?',     '00:00:00', '/usr/sbin/sshd -D'],
            ['root', 210,    1,  'Apr01', '?',     '00:00:00', '/usr/sbin/cron -f'],
            ['root', 220,    1,  'Apr01', 'tty1',  '00:00:00', '/sbin/agetty -o -p -- \\u --noclear tty1 linux'],
            ['root', 250,    1,  'Apr01', '?',     '00:00:00', '/usr/sbin/rsyslogd -n'],
            ['root', 300,    1,  'Apr01', '?',     '00:00:00', '/usr/sbin/NetworkManager --no-daemon'],
            ['root', 350,  200,  'Apr01', '?',     '00:00:00', 'sshd: visitor [priv]'],
            [$user,  400,  350,  '09:00', 'pts/0', '00:00:03', '-bash'],
            [$user,  410,  400,  '09:00', 'pts/0', '00:00:00', 'ps aux'],
        ];

        if ($wide) {
            $out = "USER       PID    PPID  CMD\n";
        } else {
            $out = "  PID TTY          TIME CMD\n";
        }

        foreach ($processes as $p) {
            if ($wide) {
                $out .= sprintf("%-10s %5d %5d %s\n", $p[0], $p[1], $p[2], $p[6]);
            } else {
                $tty = $p[4] === '?' ? '?' : $p[4];
                $out .= sprintf("%5d %-8s %s %s\n", $p[1], $tty, $p[5], basename($p[6]));
            }
        }
        return new CommandResult(output: rtrim($out));
    }

    private function executeTop(array $args): CommandResult
    {
        $uptime = time() - ($_SESSION['php_terminal_start'] ?? time());
        $days = intdiv($uptime, 86400);
        $hours = intdiv($uptime % 86400, 3600);
        $mins = intdiv($uptime % 3600, 60);
        $load = sprintf('%.2f, %.2f, %.2f', 0.08, 0.15, 0.20);
        return new CommandResult(output:
            "top - " . date('H:i:s') . " up {$days} day, {$hours}:{$mins},  1 user,  load average: {$load}\n" .
            "Tasks: 120 total,   1 running, 119 sleeping,   0 stopped,   0 zombie\n" .
            "%Cpu(s):  2.5 us,  1.2 sy,  0.0 ni, 96.0 id,  0.0 wa,  0.0 hi,  0.3 si,  0.0 st\n" .
            "MiB Mem :   7987.0 total,   4880.0 free,   2012.0 used,   1095.0 buff/cache\n" .
            "MiB Swap:   2048.0 total,   2048.0 free,      0.0 used.   5674.0 avail Mem\n" .
            "\n" .
            "  PID USER      PR  NI    VIRT    RES    SHR S  %CPU  %MEM     TIME+ COMMAND\n" .
            "  400 visitor   20   0  11000   4500   3200 S   0.0   0.1   0:00.03 bash\n" .
            "  410 visitor   20   0   9896   2300   1800 R   0.0   0.0   0:00.00 top\n" .
            "    1 root      20   0  98700   6200   4400 S   0.0   0.1   0:00.45 systemd\n" .
            "  100 root      20   0  45600   3400   2800 S   0.0   0.0   0:00.02 systemd-journal\n" .
            "  200 root      20   0  12300   3100   2400 S   0.0   0.0   0:00.00 sshd\n" .
            "  210 root      20   0   4400   2100   1800 S   0.0   0.0   0:00.00 cron\n" .
            "  300 root      20   0  34500   5600   3900 S   0.0   0.1   0:00.00 NetworkManager\n");
    }

    private function executeKill(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'kill: missing operand', error: true);
        }
        $signal = 15; // SIGTERM
        $pid = 0;
        if (str_starts_with($args[0], '-')) {
            $signal = (int)substr($args[0], 1);
            $pid = (int)($args[1] ?? 0);
        } else {
            $pid = (int)$args[0];
        }
        if ($pid <= 0) {
            return new CommandResult(output: 'kill: invalid pid', error: true);
        }
        // Simulated: just pretend
        return new CommandResult(output: "[1]  Terminated  (PID {$pid}) Signal {$signal}");
    }

    private function executePkill(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'pkill: missing pattern', error: true);
        }
        return new CommandResult(output: "{$args[0]}: killed 1 process(es)");
    }

    private function executeKillall(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'killall: missing operand', error: true);
        }
        return new CommandResult(output: "{$args[0]}: no process found");
    }

    private function executeJobs(array $args): CommandResult
    {
        return new CommandResult(output: '[1]  Running                 sleep 30 &');
    }

    private function executeBg(array $args): CommandResult
    {
        return new CommandResult(output: '[1] + sleep 30 &');
    }

    private function executeFg(array $args): CommandResult
    {
        return new CommandResult(output: 'sleep 30');
    }

    private function executeNice(array $args): CommandResult
    {
        return new CommandResult(output: 'nice: usage: nice -n 5 command');
    }

    private function executeRenice(array $args): CommandResult
    {
        return new CommandResult(output: 'renice: usage: renice -n 5 -p PID');
    }

    private function executeNohup(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'nohup: missing command', error: true);
        }
        return new CommandResult(output: "nohup: ignoring input and appending output to 'nohup.out'");
    }

    private function executeTimeout(array $args): CommandResult
    {
        return new CommandResult(output: 'timeout: usage: timeout 5 command');
    }

    private function executeWatch(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'watch: missing command', error: true);
        }
        return new CommandResult(output: "Every 2.0s: " . implode(' ', $args) . "\n\n[watch: running - press Ctrl+C to stop]");
    }

    private function executeSleep(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'sleep: missing operand', error: true);
        }
        return new CommandResult(output: '[sleep completed]');
    }

    private function executeYes(array $args): CommandResult
    {
        $text = $args[0] ?? 'y';
        return new CommandResult(output: str_repeat($text . "\n", 20) . "[yes: truncated after 20 lines]");
    }

    private function executeSeq(array $args): CommandResult
    {
        $start = 1;
        $end = 10;
        $step = 1;
        if (count($args) === 1) {
            $end = (int)$args[0];
        } elseif (count($args) === 2) {
            $start = (int)$args[0];
            $end = (int)$args[1];
        } elseif (count($args) >= 3) {
            $start = (int)$args[0];
            $step = (int)$args[1];
            $end = (int)$args[2];
        }
        $out = '';
        for ($i = $start; $i <= $end; $i += $step) {
            $out .= $i . "\n";
        }
        return new CommandResult(output: rtrim($out));
    }

    private function executeXargs(array $args): CommandResult
    {
        return new CommandResult(output: 'xargs: usage: find . -name "*.txt" | xargs rm');
    }

    private function executeParallel(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'parallel: missing command', error: true);
        }
        return new CommandResult(output: "parallel: command '" . implode(' ', $args) . "' would run in parallel (simulated)");
    }
}