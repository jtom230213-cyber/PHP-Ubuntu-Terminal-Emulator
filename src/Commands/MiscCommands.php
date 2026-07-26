<?php
declare(strict_types=1);

/**
 * Miscellaneous commands: man, whatis, apropos, info, help, history, clear, reset, banner, figlet, cowsay, fortune, yes, factor, bc, expr, printf, shred, seq, sleep, timeout, watch, tee, script, screen, tmux, sudoedit, visudo, crontab, at, batch, systemctl, journalctl, service, update-rc.d, init, shutdown, reboot, halt, poweroff, logout, exit, clear_console
 */
class MiscCommands extends BaseCommand
{
    public static function getName(): string { return 'man'; }
    public static function getDescription(): string { return 'Display manual pages for commands'; }
    public static function getUsage(): string { return 'man [command]'; }

    public function run(array $args): CommandResult
    {
        $cmd = array_shift($args) ?? 'man';
        $method = 'execute' . ucfirst($cmd);
        if (method_exists($this, $method)) {
            return $this->$method($args);
        }
        return new CommandResult(output: "{$cmd}: command not found", error: true);
    }

    private function executeMan(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'What manual page do you want?', error: true);
        }
        $cmd = $args[0];
        $descriptions = [
            'ls' => 'LS(1)                           User Commands                           LS(1)

NAME
       ls - list directory contents

SYNOPSIS
       ls [OPTION]... [FILE]...

DESCRIPTION
       List information about the FILEs (the current directory by default).
       Sort entries alphabetically if none of -cftuvSUX nor --sort is specified.

       Mandatory arguments to long options are mandatory for short options too.

       -a, --all
              do not ignore entries starting with .

       -l     use a long listing format

       -R, --recursive
              list subdirectories recursively

       -h, --human-readable
              with -l, print sizes in human readable format (e.g., 1K 234M 2G)

AUTHOR
       Written by Richard M. Stallman and David MacKenzie.

SEE ALSO
       chmod(1), find(1), stat(1)',
            'cat' => 'CAT(1)                           User Commands                           CAT(1)

NAME
       cat - concatenate files and print on the standard output

SYNOPSIS
       cat [OPTION]... [FILE]...

DESCRIPTION
       Concatenate FILE(s) to standard output.

       With no FILE, or when FILE is -, read standard input.

       -n, --number
              number all output lines

       -b, --number-nonblank
              number nonempty output lines

       -E, --show-ends
              display $ at end of each line

AUTHOR
       Written by Torbjorn Granlund and Richard M. Stallman.',
            'grep' => 'GREP(1)                         User Commands                         GREP(1)

NAME
       grep, egrep, fgrep - print lines that match patterns

SYNOPSIS
       grep [OPTION...] PATTERNS [FILE...]

DESCRIPTION
       grep searches for PATTERNS in each FILE.

       -i, --ignore-case
              ignore case distinctions

       -v, --invert-match
              select non-matching lines

       -n, --line-number
              print line number with output lines

       -r, --recursive
              read all files under each directory recursively

       -c, --count
              print only a count of matching lines per file',
            'chmod' => 'CHMOD(1)                        User Commands                        CHMOD(1)

NAME
       chmod - change file mode bits

SYNOPSIS
       chmod [OPTION]... MODE[,MODE]... FILE...

DESCRIPTION
       This manual page documents the GNU version of chmod.

       chmod changes the file mode bits of each given file according to mode,
       which can be either a symbolic representation of changes to make, or
       an octal number representing the bit pattern for the new mode bits.

       The format of a symbolic mode is [ugoa...][[-+=][perms...]...]

       u  user who owns the file
       g  group who owns the file
       o  other users
       a  all of the above',
            'tar' => 'TAR(1)                          User Commands                          TAR(1)

NAME
       tar - an archiving utility

SYNOPSIS
       tar [OPTION...] [FILE]...

DESCRIPTION
       GNU tar is an archiving program designed to store multiple files in a single file
       (an archive), and to manipulate such archives.

       -c, --create  create a new archive
       -x, --extract, --get  extract files from an archive
       -f, --file=ARCHIVE  use archive file or device ARCHIVE
       -z, --gzip, --gunzip, --ungzip  filter the archive through gzip',
            'ssh' => 'SSH(1)                          User Commands                          SSH(1)

NAME
       ssh - OpenSSH remote login client

SYNOPSIS
       ssh [OPTION...] [user@]hostname [command]

DESCRIPTION
       ssh (SSH client) is a program for logging into a remote machine and for
       executing commands on a remote machine.

       -p port  Port to connect to on the remote host
       -i file  Identity file (private key) for public key authentication
       -v       Verbose mode',
            'apt' => 'APT(8)                          System Commands                        APT(8)

NAME
       apt - command-line package manager

SYNOPSIS
       apt [COMMAND] [package...]

DESCRIPTION
       apt provides a high-level commandline interface for the package management system.

       update      update list of available packages
       upgrade     upgrade the system by installing/upgrading packages
       install     install packages
       remove      remove packages
       purge       remove packages and config files
       autoremove  remove automatically all unused packages
       search      search for a package name',
        ];

        if (isset($descriptions[$cmd])) {
            return new CommandResult(output: $descriptions[$cmd]);
        }

        // Generic man page
        return new CommandResult(output:
            "{$cmd}(1)                        User Commands                        {$cmd}(1)\n\n" .
            "NAME\n       {$cmd} - " . $this->getCommandDescription($cmd) . "\n\n" .
            "SYNOPSIS\n       {$cmd} [OPTION...] [ARGUMENT...]\n\n" .
            "DESCRIPTION\n       This is a simulated man page for the {$cmd} command.\n" .
            "       Type '{$cmd} --help' for usage information.\n\n" .
            "SEE ALSO\n       help(1), info(1)\n");
    }

    private function getCommandDescription(string $cmd): string
    {
        $handler = CommandRegistry::getHandler($cmd);
        if ($handler && method_exists($handler, 'getDescription')) {
            return $handler::getDescription();
        }
        return "perform a task";
    }

    private function executeWhatis(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'whatis: missing operand', error: true);
        }
        $output = '';
        foreach ($args as $arg) {
            $handler = CommandRegistry::getHandler($arg);
            if ($handler) {
                $output .= "{$arg} (" . ($handler === ShellCommands::class ? '1' : '1') . ") - " . $handler::getDescription() . "\n";
            } else {
                $output .= "{$arg}: nothing appropriate\n";
            }
        }
        return new CommandResult(output: rtrim($output));
    }

    private function executeApropos(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'apropos: missing keyword', error: true);
        }
        $keyword = strtolower($args[0]);
        $output = '';
        foreach (CommandRegistry::getAllCommands() as $cmd) {
            $handler = CommandRegistry::getHandler($cmd);
            if ($handler && str_contains(strtolower($handler::getDescription()), $keyword)) {
                $output .= "{$cmd} (1) - {$handler::getDescription()}\n";
            }
        }
        if ($output === '') {
            $output = "{$keyword}: nothing appropriate";
        }
        return new CommandResult(output: rtrim($output));
    }

    private function executeInfo(array $args): CommandResult
    {
        $cmd = $args[0] ?? '';
        return new CommandResult(output: "File: *manpages*, Node: {$cmd}, Up: Top\n\n" .
            "This is the Info page for {$cmd}.\n" .
            "For more information, type 'man {$cmd}'.");
    }

    private function executeHelp(array $args): CommandResult
    {
        $allCommands = CommandRegistry::getAllCommands();
        sort($allCommands);

        $categories = [
            'File & Navigation' => ['pwd', 'cd', 'ls', 'dir', 'tree', 'pushd', 'popd', 'dirs', 'cat', 'touch', 'mkdir', 'rm', 'rmdir', 'mv', 'cp', 'ln', 'find', 'locate', 'which', 'file', 'stat', 'du', 'df', 'head', 'tail', 'wc', 'sort', 'uniq', 'tee', 'basename', 'dirname', 'realpath', 'shred', 'mktemp'],
            'Text Processing' => ['grep', 'sed', 'awk', 'less', 'more', 'nl', 'fold', 'strings', 'diff', 'cmp', 'paste', 'column', 'rev', 'tac', 'cut', 'tr', 'join', 'od', 'sort', 'uniq', 'wc', 'head', 'tail'],
            'System Info' => ['uname', 'hostname', 'whoami', 'id', 'date', 'cal', 'uptime', 'who', 'w', 'last', 'lastlog', 'users', 'groups', 'finger', 'arch', 'nproc', 'free', 'lscpu', 'lsblk', 'lspci', 'lsusb', 'lshw', 'dmesg', 'hostnamectl', 'timedatectl', 'locale', 'printenv', 'env', 'logname', 'tty'],
            'Processes' => ['ps', 'top', 'kill', 'pkill', 'killall', 'bg', 'fg', 'jobs', 'nice', 'renice', 'nohup', 'timeout', 'watch', 'sleep', 'yes', 'seq', 'xargs'],
            'Network' => ['ping', 'ifconfig', 'ip', 'netstat', 'ss', 'curl', 'wget', 'traceroute', 'nslookup', 'dig', 'host', 'ssh', 'scp', 'telnet', 'nc', 'nmap', 'iptables', 'route', 'arp', 'whois'],
            'Permissions' => ['chmod', 'chown', 'chgrp', 'umask', 'su', 'sudo', 'adduser', 'useradd', 'passwd'],
            'Packages' => ['apt', 'dpkg', 'snap', 'flatpak'],
            'Compression' => ['tar', 'gzip', 'gunzip', 'zip', 'unzip', 'bzip2', 'bunzip2', 'xz', 'unxz', 'zcat', 'zless', 'zgrep'],
            'Shell' => ['echo', 'alias', 'unalias', 'export', 'unset', 'set', 'source', 'type', 'readonly', 'eval', 'exec', 'exit', 'read', 'test', 'command', 'hash'],
            'Misc' => ['man', 'whatis', 'apropos', 'info', 'help', 'history', 'clear', 'reset', 'cowsay', 'fortune', 'bc', 'expr', 'printf', 'systemctl', 'journalctl', 'service', 'shutdown', 'reboot', 'halt', 'poweroff', 'logout', 'crontab', 'screen', 'script'],
        ];

        $out = "================================================================================\n";
        $out .= " PHP UBUNTU TERMINAL EMULATOR - COMMAND REFERENCE\n";
        $out .= "================================================================================\n\n";
        $out .= "Type 'man [command]' for detailed documentation.\n";
        $out .= "Type 'whatis [command]' for a brief description.\n";
        $out .= "Type 'info [command]' for Info pages.\n\n";

        foreach ($categories as $cat => $cmds) {
            $out .= "━━━ {$cat} ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $out .= implode('  ', $cmds) . "\n\n";
        }

        $out .= "================================================================================\n";
        $out .= " Total commands: " . count($allCommands) . "\n";
        $out .= "================================================================================\n";

        return new CommandResult(output: $out);
    }

    private function executeHistory(array $args): CommandResult
    {
        $history = $_SESSION['php_terminal_history'] ?? [];
        $out = '';
        $start = max(0, count($history) - 100);
        for ($i = $start; $i < count($history); $i++) {
            $out .= sprintf("%4d  %s\n", $i + 1, $history[$i]);
        }
        return new CommandResult(output: rtrim($out));
    }

    private function executeClear(array $args): CommandResult
    {
        return new CommandResult(clear: true);
    }

    private function executeReset(array $args): CommandResult
    {
        return new CommandResult(clear: true);
    }

    private function executeCowsay(array $args): CommandResult
    {
        $text = implode(' ', $args) ?: 'Hello!';
        $len = strlen($text);
        $bubble = " {$text}\n";
        return new CommandResult(output:
            "  {$bubble}\n" .
            "        \\   ^__^\n" .
            "         \\  (oo)\\_______\n" .
            "            (__)\\       )\\/\\\n" .
            "                ||----w |\n" .
            "                ||     ||\n");
    }

    private function executeFortune(array $args): CommandResult
    {
        $fortunes = [
            "The best way to predict the future is to create it.",
            "In Linux, everything is a file.",
            "There is no place like ~",
            "Unix is user-friendly. It's just very selective about who its friends are.",
            "With great power comes great responsibility. (sudo)",
            "A file that big? It might be very useful. But now it is gone.",
            "The command line is the ultimate IDE.",
            "To understand recursion, you must first understand recursion.",
            "Don't use 'rm -rf /' on your production server.",
            "The cloud is just someone else's computer.",
            "Linux is not a destination, it's a journey.",
            "There are 10 types of people: those who understand binary and those who don't.",
            "man woman: No manual entry for woman.",
            "I think Microsoft named .Net so it wouldn't show up in a Unix directory listing.",
            "Real programmers don't comment their code. If it was hard to write, it should be hard to understand.",
            "The best documentation is the source code itself.",
        ];
        return new CommandResult(output: $fortunes[array_rand($fortunes)]);
    }

    private function executeBc(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'bc: usage: echo "2+2" | bc');
        }
        $expr = implode(' ', $args);
        try {
            // Simple eval for math
            $expr = str_replace(['x', 'X'], '*', $expr);
            $result = eval("return {$expr};");
            return new CommandResult(output: (string)($result ?? '0'));
        } catch (\Throwable) {
            return new CommandResult(output: '0');
        }
    }

    private function executeExpr(array $args): CommandResult
    {
        if (count($args) < 3) {
            return new CommandResult(output: 'expr: missing operand', error: true);
        }
        $a = $args[0];
        $op = $args[1];
        $b = $args[2];
        $result = match ($op) {
            '+' => (int)$a + (int)$b,
            '-' => (int)$a - (int)$b,
            '*' => (int)$a * (int)$b,
            '/' => (int)$a / (int)$b,
            '%' => (int)$a % (int)$b,
            default => 0,
        };
        return new CommandResult(output: (string)$result);
    }

    private function executePrintf(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'printf: missing operand', error: true);
        }
        $format = $args[0];
        $vals = array_slice($args, 1);
        return new CommandResult(output: sprintf($format, ...$vals));
    }

    private function executeSystemctl(array $args): CommandResult
    {
        $action = $args[0] ?? '';
        $service = $args[1] ?? '';
        return match ($action) {
            'status' => new CommandResult(output:
                "● {$service}.service - " . ($service ? ucfirst($service) : 'Service') . "\n" .
                "     Loaded: loaded (/lib/systemd/system/{$service}.service; enabled; vendor preset: enabled)\n" .
                "     Active: active (running) since " . date('D M d H:i:s') . " UTC\n" .
                "   Main PID: 1234 (bash)\n" .
                "      Tasks: 1 (limit: 2345)\n" .
                "     Memory: 1.2M\n" .
                "        CPU: 10ms\n" .
                "     CGroup: /system.slice/{$service}.service\n"),
            'start' => new CommandResult(output: "System has not been booted with systemd as init system (PID 1). Can't operate."),
            'stop' => new CommandResult(output: "System has not been booted with systemd as init system (PID 1). Can't operate."),
            'restart' => new CommandResult(output: "System has not been booted with systemd as init system (PID 1). Can't operate."),
            'enable' => new CommandResult(output: "Synchronizing state of {$service}.service with SysV service script with /lib/systemd/systemd-sysv-install.\nExecuting: /lib/systemd/systemd-sysv-install enable {$service}"),
            'disable' => new CommandResult(output: "Removed /etc/systemd/system/multi-user.target.wants/{$service}.service."),
            'list-units' => new CommandResult(output:
                "  UNIT                     LOAD   ACTIVE SUB       DESCRIPTION\n" .
                "  syslog.service           loaded active running System Logging Service\n" .
                "  sshd.service             loaded active running OpenSSH server\n" .
                "  cron.service             loaded active running Regular background program\n" .
                "  networking.service       loaded active exited  Network connectivity\n" .
                "  systemd-journald.service loaded active running Journal Service\n"),
            default => new CommandResult(output: "systemctl: unrecognized command: {$action}"),
        };
    }

    private function executeJournalctl(array $args): CommandResult
    {
        return new CommandResult(output:
            "-- Logs begin at " . date('M d H:i:s', strtotime('-7 days')) . ", end at " . date('M d H:i:s') . "\n" .
            date('M d H:i:s') . " php-terminal systemd[1]: Started Journal Service\n" .
            date('M d H:i:s', strtotime('-1 hour')) . " php-terminal sshd[1234]: Accepted publickey for visitor\n" .
            date('M d H:i:s', strtotime('-30 minutes')) . " php-terminal sudo[1234]: visitor : TTY=pts/0 ; PWD=/home/visitor ; USER=root ; COMMAND=/bin/ls\n" .
            date('M d H:i:s', strtotime('-5 minutes')) . " php-terminal kernel: [ 1234.567890] usb 1-1: new high-speed USB device\n");
    }

    private function executeService(array $args): CommandResult
    {
        $action = $args[0] ?? '';
        $service = $args[1] ?? '';
        return match ($action) {
            'status' => new CommandResult(output: "● {$service}.service - " . ucfirst($service) . "\n" .
                "   Loaded: loaded\n" .
                "   Active: active (running) since " . date('D M d H:i:s') . "\n"),
            'start' => new CommandResult(output: "Starting {$service}...\n[ OK ] Started {$service}."),
            'stop' => new CommandResult(output: "Stopping {$service}...\n[ OK ] Stopped {$service}."),
            'restart' => new CommandResult(output: "Restarting {$service}...\n[ OK ] Restarted {$service}."),
            default => new CommandResult(output: "Usage: service <option> <service>"),
        };
    }

    private function executeShutdown(array $args): CommandResult
    {
        $time = $args[0] ?? 'now';
        return new CommandResult(output:
            "Shutdown scheduled for {$time}, use 'shutdown -c' to cancel.\n\n" .
            "Broadcast message from root@php-terminal (pts/0) (" . date('H:i') . "):\n\n" .
            "The system is going down for poweroff at " . date('H:i', strtotime('+1 minute')) . "!\n");
    }

    private function executeReboot(array $args): CommandResult
    {
        return new CommandResult(output:
            "Broadcast message from root@php-terminal (pts/0) (" . date('H:i') . "):\n\n" .
            "The system is going down for reboot at " . date('H:i', strtotime('+1 minute')) . "!\n");
    }

    private function executeHalt(array $args): CommandResult
    {
        return new CommandResult(output: "[ OK ] System halted.");
    }

    private function executePoweroff(array $args): CommandResult
    {
        return new CommandResult(output: "[ OK ] System poweroff.");
    }

    private function executeLogout(array $args): CommandResult
    {
        return new CommandResult(output: "logout\n[Process completed]", exit: true);
    }

    private function executeCrontab(array $args): CommandResult
    {
        $action = $args[0] ?? '';
        return match ($action) {
            '-l' => new CommandResult(output: "# Edit this file to introduce tasks to be run by cron.\n" .
                "# m h  dom mon dow   command\n" .
                "0 2 * * * /usr/bin/apt update\n" .
                "*/5 * * * * /usr/bin/logger -t cron \"cron task running\""),
            '-e' => new CommandResult(output: "crontab: installing new crontab"),
            '-r' => new CommandResult(output: "crontab: removing crontab"),
            default => new CommandResult(output: "crontab: usage: crontab [-u user] file\n" .
                "       crontab [-u user] [-l | -r | -e]"),
        };
    }

    private function executeScreen(array $args): CommandResult
    {
        return new CommandResult(output: "[screen: terminal multiplexer]\n" .
            "Use 'screen -S name' to create a session.\n" .
            "Use 'screen -ls' to list sessions.\n" .
            "Use 'screen -r name' to reattach.");
    }

    private function executeScript(array $args): CommandResult
    {
        $file = $args[0] ?? 'typescript';
        return new CommandResult(output: "Script started, output file is {$file}");
    }

    private function executeBanner(array $args): CommandResult
    {
        $text = strtoupper(implode(' ', $args) ?: 'BANNER');
        $lines = ['', '', '', '', '', '', '', ''];
        $glyphs = [
            'A' => ["  ##  ", " #  # ", "#    #", "######", "#    #", "#    #", "#    #", ""],
            'B' => ["##### ", "#    #", "##### ", "#    #", "#    #", "#    #", "##### ", ""],
            'E' => ["##### ", "#     ", "####  ", "#     ", "#     ", "#     ", "##### ", ""],
            'I' => ["#####", "  #  ", "  #  ", "  #  ", "  #  ", "  #  ", "#####", ""],
            ' ' => ["      ", "", "", "", "", "", "", ""],
        ];
        foreach (str_split($text) as $ch) {
            $glyph = $glyphs[$ch] ?? $glyphs['A'];
            for ($i = 0; $i < 8; $i++) {
                $lines[$i] .= $glyph[$i] ?? '';
            }
        }
        return new CommandResult(output: implode("\n", $lines));
    }

    private function executeFiglet(array $args): CommandResult
    {
        return $this->executeBanner($args);
    }

    private function executeFactor(array $args): CommandResult
    {
        $num = (int)($args[0] ?? 0);
        if ($num < 2) {
            return new CommandResult(output: 'factor: usage: factor [number]', error: true);
        }
        $factors = [];
        $n = $num;
        for ($i = 2; $i * $i <= $n; $i++) {
            while ($n % $i === 0) {
                $factors[] = $i;
                $n /= $i;
            }
        }
        if ($n > 1) $factors[] = $n;
        return new CommandResult(output: "{$num}: " . implode(' ', $factors));
    }

    private function executeTmux(array $args): CommandResult
    {
        return new CommandResult(output:
            "0: 1 windows (created ...) [80x24]\n" .
            "tmux: use ls, attach, new-session");
    }

    private function executeSudoedit(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '');
        if (!$path) {
            return new CommandResult(output: 'sudoedit: missing file operand', error: true);
        }
        return new CommandResult(output: "sudoedit: editing {$path} (simulated root permissions)");
    }

    private function executeVisudo(array $args): CommandResult
    {
        return new CommandResult(output: "/etc/sudoers.tmp unchanged");
    }

    private function executeAt(array $args): CommandResult
    {
        return new CommandResult(output: "at: usage: at time [date]");
    }

    private function executeBatch(array $args): CommandResult
    {
        return new CommandResult(output: "batch: commands will be executed when system load is less than 1.5");
    }

    private function executeUpdateRcD(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'update-rc.d: usage: update-rc.d <basename> remove|defaults', error: true);
        }
        return new CommandResult(output: "update-rc.d: Adding system startup for {$args[0]} ... (simulated)");
    }

    private function executeInit(array $args): CommandResult
    {
        return new CommandResult(output:
            "runlevel: previous=N, current=5, next=5\n" .
            "init: usage: init 0|1|2|3|4|5|6");
    }

    private function executeClearConsole(array $args): CommandResult
    {
        return new CommandResult(clear: true);
    }
}