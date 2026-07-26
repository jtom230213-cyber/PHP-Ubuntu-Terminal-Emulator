<?php
declare(strict_types=1);

/**
 * Permission and package management commands:
 * chmod, chown, chgrp, umask, su, sudo, adduser, useradd, passwd, apt, dpkg, snap, flatpak
 */
class PermissionCommands extends BaseCommand
{
    public static function getName(): string { return 'chmod'; }
    public static function getDescription(): string { return 'Change file mode bits'; }
    public static function getUsage(): string { return 'chmod [mode] [file]'; }

    public function run(array $args): CommandResult
    {
        $cmd = array_shift($args) ?? 'chmod';
        $method = 'execute' . ucfirst($cmd);
        if (method_exists($this, $method)) {
            return $this->$method($args);
        }
        return new CommandResult(output: "{$cmd}: command not found", error: true);
    }

    private function executeChmod(array $args): CommandResult
    {
        if (count($args) < 2) {
            return new CommandResult(output: 'chmod: missing operand', error: true);
        }
        $modeStr = $args[0];
        $path = $this->resolve($args[1]);
        if (!$this->fs->exists($path)) {
            return new CommandResult(output: "chmod: cannot access '{$args[1]}': No such file or directory", error: true);
        }
        $entry = $this->fs->stat($path);
        $newMode = Filesystem::parseMode($modeStr, $entry['mode'] ?? 0755);
        $this->fs->chmod($path, $newMode);
        return new CommandResult(output: sprintf("mode of '%s' changed to %04o", $args[1], $newMode));
    }

    private function executeChown(array $args): CommandResult
    {
        if (count($args) < 2) {
            return new CommandResult(output: 'chown: missing operand', error: true);
        }
        $owner = $args[0];
        $path = $this->resolve($args[1]);
        if (!$this->fs->exists($path)) {
            return new CommandResult(output: "chown: cannot access '{$args[1]}': No such file or directory", error: true);
        }
        $uid = $owner === 'root' ? 0 : 1000;
        $gid = $owner === 'root' ? 0 : 1000;
        $this->fs->chown($path, $uid, $gid);
        return new CommandResult(output: "changed ownership of '{$args[1]}' to {$owner}");
    }

    private function executeChgrp(array $args): CommandResult
    {
        if (count($args) < 2) {
            return new CommandResult(output: 'chgrp: missing operand', error: true);
        }
        $group = $args[0];
        $path = $this->resolve($args[1]);
        if (!$this->fs->exists($path)) {
            return new CommandResult(output: "chgrp: cannot access '{$args[1]}': No such file or directory", error: true);
        }
        return new CommandResult(output: "changed group of '{$args[1]}' to {$group}");
    }

    private function executeUmask(array $args): CommandResult
    {
        $mask = $args[0] ?? '';
        if ($mask) {
            $_SESSION['php_terminal_umask'] = (int)$mask;
            return new CommandResult();
        }
        $current = $_SESSION['php_terminal_umask'] ?? 0022;
        return new CommandResult(output: sprintf('%04o', $current));
    }

    private function executeSu(array $args): CommandResult
    {
        $user = $args[0] ?? 'root';
        if ($user === 'root') {
            return new CommandResult(output:
                "Password: \n" .
                "root@php-terminal:/home/visitor#");
        }
        return new CommandResult(output: "su: user {$user} does not exist");
    }

    private function executeSudo(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output:
                "usage: sudo -h | -K | -k | -V\n" .
                "usage: sudo -l [-AknS] [-g group] [-h host] [-p prompt] [-U user] [-u user]\n" .
                "usage: sudo [-AbEHknPS] [-r role] [-t type] [-C num] [-g group] [-h host] [-p prompt] [-T timeout] [-u user] [VAR=value] [-i|-s] [<command>]\n" .
                "usage: sudo -e [-AknS] [-r role] [-t type] [-C num] [-g group] [-h host] [-p prompt] [-T timeout] [-u user] file ...",
                error: true);
        }
        // Simulate running as root
        $cmd = $args[0];
        $cmdArgs = array_slice($args, 1);
        $handler = CommandRegistry::getHandler($cmd);
        if ($handler) {
            /** @var BaseCommand $instance */
            $instance = new $handler($this->fs, $this->cwd);
            $instance->setUsername('root');
            $instance->setUid(0);
            $instance->setGid(0);
            return $instance->run(array_merge([$cmd], $cmdArgs));
        }
        return new CommandResult(output: "sudo: {$cmd}: command not found", error: true);
    }

    private function executeAdduser(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'adduser: missing username', error: true);
        }
        return new CommandResult(output: "Adding user `{$args[0]}' ...\n" .
            "Adding new group `{$args[0]}' (1001) ...\n" .
            "Creating home directory `/home/{$args[0]}' ...\n" .
            "Copying files from `/etc/skel' ...\n" .
            "New password: \n" .
            "Retype new password: \n" .
            "passwd: password updated successfully\n" .
            "Changing the user information for {$args[0]}\n" .
            "Enter the new value, or press ENTER for the default:\n" .
            "    Full Name []: \n" .
            "    Room Number []: \n" .
            "    Work Phone []: \n" .
            "    Home Phone []: \n" .
            "    Other []: \n" .
            "Is the information correct? [Y/n] y");
    }

    private function executeUseradd(array $args): CommandResult
    {
        // Find username in args
        $user = '';
        foreach ($args as $arg) {
            if (!str_starts_with($arg, '-')) {
                $user = $arg;
                break;
            }
        }
        if (!$user) {
            return new CommandResult(output: 'useradd: missing username', error: true);
        }
        return new CommandResult(output: "useradd: user '{$user}' added successfully");
    }

    private function executePasswd(array $args): CommandResult
    {
        $user = $args[0] ?? $this->username;
        return new CommandResult(output:
            "Changing password for {$user}.\n" .
            "Current password: \n" .
            "New password: \n" .
            "Retype new password: \n" .
            "passwd: password updated successfully");
    }

    private function executeApt(array $args): CommandResult
    {
        $action = $args[0] ?? '';
        return match ($action) {
            'update' => new CommandResult(output:
                "Hit:1 http://archive.ubuntu.com/ubuntu noble InRelease\n" .
                "Hit:2 http://archive.ubuntu.com/ubuntu noble-updates InRelease\n" .
                "Hit:3 http://security.ubuntu.com/ubuntu noble-security InRelease\n" .
                "Reading package lists... Done\n"),
            'upgrade' => new CommandResult(output:
                "Reading package lists... Done\n" .
                "Building dependency tree... Done\n" .
                "Reading state information... Done\n" .
                "Calculating upgrade... Done\n" .
                "0 upgraded, 0 newly installed, 0 to remove and 0 not upgraded.\n"),
            'install' => new CommandResult(output:
                "Reading package lists... Done\n" .
                "Building dependency tree... Done\n" .
                "Reading state information... Done\n" .
                "The following NEW packages will be installed:\n" .
                "  " . ($args[1] ?? 'package') . "\n" .
                "0 upgraded, 1 newly installed, 0 to remove and 0 not upgraded.\n" .
                "Need to get 1,234 kB of archives.\n" .
                "After this operation, 5,678 kB of additional disk space will be used.\n" .
                "Get:1 http://archive.ubuntu.com/ubuntu noble/main amd64 " . ($args[1] ?? 'package') . " [1,234 kB]\n" .
                "Fetched 1,234 kB in 0s (2.47 MB/s)\n" .
                "Selecting previously unselected package " . ($args[1] ?? 'package') . ".\n" .
                "(Reading database ... 12345 files and directories currently installed.)\n" .
                "Preparing to unpack .../" . ($args[1] ?? 'package') . " ...\n" .
                "Unpacking " . ($args[1] ?? 'package') . " ...\n" .
                "Setting up " . ($args[1] ?? 'package') . " ...\n"),
            'remove' => new CommandResult(output:
                "The following packages will be REMOVED:\n" .
                "  " . ($args[1] ?? 'package') . "\n" .
                "0 upgraded, 0 newly installed, 1 to remove and 0 not upgraded.\n" .
                "Removing " . ($args[1] ?? 'package') . " ...\n"),
            'search' => new CommandResult(output:
                "Sorting... Done\n" .
                "Full Text Search... Done\n" .
                "curl/noble  7.81.0-1ubuntu1.16 amd64\n" .
                "  command line tool for transferring data with URL syntax\n"),
            'list' => new CommandResult(output:
                "Listing... Done\n" .
                "bash/noble,now 5.2.21-2ubuntu4 amd64 [installed]\n" .
                "coreutils/noble,now 9.4-2ubuntu1 amd64 [installed]\n" .
                "curl/noble 7.81.0-1ubuntu1.16 amd64\n" .
                "git/noble 1:2.43.0-1ubuntu7 amd64\n" .
                "openssh-server/noble 1:9.6p1-3ubuntu13 amd64\n"),
            default => new CommandResult(output: "apt: valid commands: update, upgrade, install, remove, search, list"),
        };
    }

    private function executeDpkg(array $args): CommandResult
    {
        $action = $args[0] ?? '';
        return match ($action) {
            '-l', '--list' => new CommandResult(output:
                "Desired=Unknown/Install/Remove/Purge/Hold\n" .
                "| Status=Not/Inst/Conf-files/Unpacked/halF-conf/Half-inst/trig-aWait/Trig-pend\n" .
                "|/ Err?=(none)/Reinst-required (Status,Err: uppercase=bad)\n" .
                "||/ Name           Version      Architecture Description\n" .
                "+++-==============-============-============-=================================\n" .
                "ii  bash           5.2.21-2ub   amd64        GNU Bourne Again SHell\n" .
                "ii  coreutils      9.4-2ubuntu1 amd64        GNU core utilities\n" .
                "ii  curl           7.81.0-1ub   amd64        command line tool\n" .
                "ii  openssh-server 1:9.6p1-3ub  amd64        secure shell server\n"),
            '-i', '--install' => new CommandResult(output: "dpkg: (Reading database ... 12345 files and directories currently installed.)\n" .
                "Preparing to unpack " . ($args[1] ?? '') . " ...\n" .
                "Unpacking " . ($args[1] ?? '') . " ...\n" .
                "Setting up " . ($args[1] ?? '') . " ...\n"),
            default => new CommandResult(output: "dpkg: usage: dpkg -i package.deb | dpkg -l"),
        };
    }

    private function executeSnap(array $args): CommandResult
    {
        $action = $args[0] ?? 'list';
        return match ($action) {
            'list' => new CommandResult(output:
                "Name    Version  Rev    Tracking       Publisher   Notes\n" .
                "core22  20250101 1234   latest/stable  canonical   base\n" .
                "firefox 125.0   5678   latest/stable  mozilla     -"),
            'install' => new CommandResult(output:
                "snap install " . ($args[1] ?? 'package') . "\n" .
                "Download snap \"" . ($args[1] ?? 'package') . "\" (1234) from channel \"stable\"\n" .
                ($args[1] ?? 'package') . " installed"),
            default => new CommandResult(output: "usage: snap <command> [<snap>]"),
        };
    }

    private function executeFlatpak(array $args): CommandResult
    {
        $action = $args[0] ?? 'list';
        return match ($action) {
            'list' => new CommandResult(output:
                "Name          Application ID            Version        Branch  Origin\n" .
                "Discord       com.discordapp.Discord    0.0.30         stable  flathub\n" .
                "Telegram      org.telegram.desktop     4.15.6         stable  flathub"),
            'install' => new CommandResult(output:
                "Looking for matches…\n" .
                "Installing " . ($args[1] ?? 'org.example.App') . " from flathub...\n" .
                "Installation complete."),
            default => new CommandResult(output: "usage: flatpak <command> [<args>]"),
        };
    }
}