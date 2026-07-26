<?php
declare(strict_types=1);

/**
 * Simple PSR-4 compatible autoloader.
 */
spl_autoload_register(static function (string $class): void {
    // Map classes to files
    $map = [
        'Filesystem' => __DIR__ . '/src/Filesystem.php',
        'Parser' => __DIR__ . '/src/Parser.php',
        'CommandRegistry' => __DIR__ . '/src/Registry.php',
        'BaseCommand' => __DIR__ . '/src/BaseCommand.php',
        'CommandResult' => __DIR__ . '/src/BaseCommand.php',

        // Command groups
        'NavigationCommands' => __DIR__ . '/src/Commands/NavigationCommands.php',
        'DirectoryCommands' => __DIR__ . '/src/Commands/NavigationCommands.php',
        'FileCommands' => __DIR__ . '/src/Commands/FileCommands.php',
        'TextCommands' => __DIR__ . '/src/Commands/TextCommands.php',
        'SystemCommands' => __DIR__ . '/src/Commands/SystemCommands.php',
        'ProcessCommands' => __DIR__ . '/src/Commands/ProcessCommands.php',
        'NetworkCommands' => __DIR__ . '/src/Commands/NetworkCommands.php',
        'PermissionCommands' => __DIR__ . '/src/Commands/PermissionCommands.php',
        'CompressionCommands' => __DIR__ . '/src/Commands/CompressionCommands.php',
        'ShellCommands' => __DIR__ . '/src/Commands/ShellCommands.php',
        'MiscCommands' => __DIR__ . '/src/Commands/MiscCommands.php',
    ];

    if (isset($map[$class])) {
        require_once $map[$class];
    }
});

/**
 * Register all commands.
 */
function registerAllCommands(): void
{
    // Navigation
    CommandRegistry::registerFrom(
        ['pwd', 'cd', 'ls', 'dir', 'tree', 'pushd', 'popd', 'dirs'],
        NavigationCommands::class
    );

    // File operations
    CommandRegistry::registerFrom(
        [
            'cat', 'touch', 'mkdir', 'rm', 'rmdir', 'mv', 'cp', 'ln',
            'find', 'locate', 'which', 'file', 'stat', 'du', 'df',
            'head', 'tail', 'wc', 'sort', 'uniq', 'cut', 'tr', 'tee',
            'basename', 'dirname', 'realpath', 'readlink', 'shred',
            'chattr', 'lsattr', 'install', 'mktemp', 'tempfile', 'pathchk',
            'dd',
        ],
        FileCommands::class
    );

    // Text processing
    CommandRegistry::registerFrom(
        ['grep', 'sed', 'awk', 'less', 'more', 'nl', 'fold', 'fmt',
         'pr', 'expand', 'unexpand', 'od', 'hexdump', 'xxd',
         'strings', 'diff', 'cmp', 'patch', 'comm', 'join', 'paste',
         'column', 'rev', 'tac', 'look', 'tsort'],
        TextCommands::class
    );

    // System info
    CommandRegistry::registerFrom(
        ['uname', 'hostname', 'whoami', 'id', 'date', 'cal', 'uptime',
         'who', 'w', 'last', 'lastlog', 'users', 'groups', 'finger',
         'arch', 'nproc', 'free', 'lscpu', 'lsblk', 'lspci', 'lsusb',
         'lshw', 'dmesg', 'hostnamectl', 'timedatectl', 'locale',
         'printenv', 'env', 'getconf', 'logname', 'tty'],
        SystemCommands::class
    );

    // Processes
    CommandRegistry::registerFrom(
        ['ps', 'top', 'kill', 'pkill', 'killall', 'bg', 'fg', 'jobs',
         'nice', 'renice', 'nohup', 'timeout', 'watch', 'sleep', 'yes',
         'seq', 'xargs', 'parallel'],
        ProcessCommands::class
    );

    // Network
    CommandRegistry::registerFrom(
        ['ping', 'ifconfig', 'ip', 'netstat', 'ss', 'curl', 'wget',
         'traceroute', 'nslookup', 'dig', 'host', 'ssh', 'scp', 'ftp',
         'telnet', 'nc', 'nmap', 'iptables', 'route', 'arp', 'ethtool',
         'mtr', 'whois', 'iwconfig', 'iwlist', 'rfkill'],
        NetworkCommands::class
    );

    // Permissions & packages
    CommandRegistry::registerFrom(
        ['chmod', 'chown', 'chgrp', 'umask', 'su', 'sudo', 'adduser',
         'useradd', 'passwd', 'apt', 'dpkg', 'snap', 'flatpak'],
        PermissionCommands::class
    );

    // Compression
    CommandRegistry::registerFrom(
        ['tar', 'gzip', 'gunzip', 'zip', 'unzip', 'bzip2', 'bunzip2',
         'xz', 'unxz', 'zcat', 'zless', 'zmore', 'zgrep', 'zdiff',
         'compress', 'uncompress'],
        CompressionCommands::class
    );

    // Shell
    CommandRegistry::registerFrom(
        ['echo', 'alias', 'unalias', 'export', 'unset', 'set',
         'source', 'type', 'declare', 'readonly', 'shift', 'let',
         'eval', 'exec', 'exit', 'return', 'read', 'test', 'trap',
         'bind', 'command', 'builtin', 'enable', 'hash'],
        ShellCommands::class
    );

    // Misc
    CommandRegistry::registerFrom(
        ['man', 'whatis', 'apropos', 'info', 'help', 'history',
         'clear', 'reset', 'banner', 'figlet', 'cowsay', 'fortune',
         'factor', 'bc', 'expr', 'printf',
         'script', 'screen', 'tmux', 'sudoedit',
         'visudo', 'crontab', 'at', 'batch', 'systemctl', 'journalctl',
         'service', 'update-rc.d', 'init', 'shutdown', 'reboot',
         'halt', 'poweroff', 'logout', 'clear_console'],
        MiscCommands::class
    );
}

/**
 * Execute a command string and return the result.
 */
function executeCommand(string $input, Filesystem $fs, string $cwd, array $env = []): CommandResult
{
    $input = trim($input);
    if ($input === '') {
        return new CommandResult(cwd: $cwd);
    }

    // Store in history
    $history = $_SESSION['php_terminal_history'] ?? [];
    if ($input !== 'history') {
        $history[] = $input;
        if (count($history) > 500) {
            $history = array_slice($history, -500);
        }
        $_SESSION['php_terminal_history'] = $history;
    }

    // Check for aliases
    $aliases = $_SESSION['php_terminal_aliases'] ?? [];
    $firstWord = explode(' ', $input)[0];
    if (isset($aliases[$firstWord])) {
        $input = $aliases[$firstWord] . substr($input, strlen($firstWord));
    }

    // Expand environment variables
    $input = Parser::expandVars($input, $env);

    // Parse the command
    $segments = Parser::parse($input);
    if (empty($segments)) {
        return new CommandResult(cwd: $cwd);
    }

    // For now, handle single commands (no pipe)
    $seg = $segments[0];
    $cmd = $seg['command'];
    $args = $seg['args'];

    // Check if it's a registered command
    $handler = CommandRegistry::getHandler($cmd);
    if ($handler === null) {
        return new CommandResult(
            output: "{$cmd}: command not found\nType 'help' for available commands.",
            cwd: $cwd,
            error: true
        );
    }

    /** @var BaseCommand $instance */
    $instance = new $handler($fs, $cwd);
    $instance->setUsername($_SESSION['php_terminal_user'] ?? 'visitor');
    $instance->setUid($_SESSION['php_terminal_uid'] ?? 1000);
    $instance->setGid($_SESSION['php_terminal_gid'] ?? 1000);
    $instance->setEnv($env);

    $result = $instance->run(array_merge([$cmd], $args));

    // Ensure cwd is propagated
    if ($result->cwd === '') {
        return new CommandResult(
            output: $result->output,
            cwd: $cwd,
            clear: $result->clear,
            error: $result->error,
            exit: $result->exit
        );
    }

    return $result;
}

/**
 * Get the MOTD (Message of the Day).
 */
function getMotd(): string
{
    $motd = <<<MOTD
██╗  ██╗██╗   ██╗██████╗ ██╗   ██╗███╗   ██╗████████╗██╗   ██╗
██║  ██║╚██╗ ██╔╝██╔══██╗██║   ██║████╗  ██║╚══██╔══╝╚██╗ ██╔╝
███████║ ╚████╔╝ ██████╔╝██║   ██║██╔██╗ ██║   ██║    ╚████╔╝ 
██╔══██║  ╚██╔╝  ██╔══██╗██║   ██║██║╚██╗██║   ██║     ╚██╔╝  
██║  ██║   ██║   ██████╔╝╚██████╔╝██║ ╚████║   ██║      ██║   
╚═╝  ╚═╝   ╚═╝   ╚═════╝  ╚═════╝ ╚═╝  ╚═══╝   ╚═╝      ╚═╝   
                                                                 
 ████████╗███████╗██████╗ ███╗   ███╗██╗███╗   ██╗ █████╗ ██╗     
 ╚══██╔══╝██╔════╝██╔══██╗████╗ ████║██║████╗  ██║██╔══██╗██║     
    ██║   █████╗  ██████╔╝██╔████╔██║██║██╔██╗ ██║███████║██║     
    ██║   ██╔══╝  ██╔══██╗██║╚██╔╝██║██║██║╚██╗██║██╔══██║██║     
    ██║   ███████╗██║  ██║██║ ╚═╝ ██║██║██║ ╚████║██║  ██║███████╗
    ╚═╝   ╚══════╝╚═╝  ╚═╝╚═╝     ╚═╝╚═╝╚═╝  ╚═══╝╚═╝  ╚═╝╚══════╝

Welcome to PHP Ubuntu Terminal Emulator v2.0
A safe virtual Linux environment for learning Ubuntu commands.

System information:
  OS:        Ubuntu 24.04 LTS (Noble Numbat)
  Kernel:    Linux 6.8.0-45-generic
  Architecture: x86_64
  Memory:    7.8 GB total
  CPU:       Intel Core i7-10750H (4 cores)

Type 'help' to see all available commands.
Type 'man [command]' for detailed documentation.

MOTD;
    return $motd;
}

/**
 * Get the session's filesystem, creating it if needed.
 */
function getFilesystem(): Filesystem
{
    if (!isset($_SESSION['php_terminal_fs_data'])) {
        $_SESSION['php_terminal_fs_data'] = (new Filesystem())->toArray();
    }
    return Filesystem::fromArray($_SESSION['php_terminal_fs_data']);
}

/**
 * Save the filesystem state back to session.
 */
function saveFilesystem(Filesystem $fs): void
{
    $_SESSION['php_terminal_fs_data'] = $fs->toArray();
}

// Register all commands on load
registerAllCommands();

// Initialize start time
if (!isset($_SESSION['php_terminal_start'])) {
    $_SESSION['php_terminal_start'] = time();
}

// Initialize user
if (!isset($_SESSION['php_terminal_user'])) {
    $_SESSION['php_terminal_user'] = 'visitor';
    $_SESSION['php_terminal_uid'] = 1000;
    $_SESSION['php_terminal_gid'] = 1000;
}

// Initialize environment
if (!isset($_SESSION['php_terminal_env'])) {
    $_SESSION['php_terminal_env'] = [
        'HOME' => '/home/visitor',
        'USER' => 'visitor',
        'SHELL' => '/bin/bash',
        'PATH' => '/usr/local/bin:/usr/bin:/bin',
        'LANG' => 'en_US.UTF-8',
        'TERM' => 'xterm-256color',
    ];
}