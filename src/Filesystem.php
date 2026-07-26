<?php
declare(strict_types=1);

/**
 * Virtual filesystem with Unix-like permissions, ownership, and symlinks.
 */
class Filesystem
{
    private array $data;
    private int $nextInode;
    private const ROOT_UID = 0;
    private const ROOT_GID = 0;
    private const USER_UID = 1000;
    private const USER_GID = 1000;

    public function __construct()
    {
        $this->nextInode = 1;
        $this->data = [];
        $this->buildInitial();
    }

    // ─── public API ───────────────────────────────────────────────

    public function exists(string $path): bool
    {
        return isset($this->data[$this->normalize($path)]);
    }

    public function isDir(string $path): bool
    {
        $n = $this->normalize($path);
        return isset($this->data[$n]) && $this->data[$n]['type'] === 'dir';
    }

    public function isFile(string $path): bool
    {
        $n = $this->normalize($path);
        return isset($this->data[$n]) && $this->data[$n]['type'] === 'file';
    }

    public function isLink(string $path): bool
    {
        $n = $this->normalize($path);
        return isset($this->data[$n]) && ($this->data[$n]['type'] === 'link');
    }

    public function get(string $path): ?array
    {
        $n = $this->normalize($path);
        $entry = $this->data[$n] ?? null;
        if ($entry === null) return null;
        // Follow symlinks (one level)
        if ($entry['type'] === 'link') {
            $target = $this->normalize($entry['link_target']);
            return $this->data[$target] ?? null;
        }
        return $entry;
    }

    public function stat(string $path): ?array
    {
        $n = $this->normalize($path);
        return $this->data[$n] ?? null;
    }

    public function parentPath(string $path): string
    {
        $n = $this->normalize($path);
        $parent = dirname($n);
        return $parent === '\\' ? '/' : $parent;
    }

    public function children(string $path): array
    {
        $n = $this->normalize($path);
        $items = [];
        foreach ($this->data as $p => $entry) {
            if ($this->parentPath($p) === $n) {
                $items[basename($p)] = $entry;
            }
        }
        ksort($items);
        return $items;
    }

    public function createDir(string $path, int $uid = self::USER_UID, int $gid = self::USER_GID, int $mode = 0755): bool
    {
        $n = $this->normalize($path);
        if (isset($this->data[$n])) return false;
        $parent = $this->parentPath($n);
        if (!isset($this->data[$parent]) || $this->data[$parent]['type'] !== 'dir') return false;
        $this->data[$n] = [
            'type' => 'dir',
            'mode' => $mode,
            'uid'  => $uid,
            'gid'  => $gid,
            'inode' => $this->nextInode++,
            'mtime' => time(),
            'size'  => 4096,
        ];
        return true;
    }

    public function createFile(string $path, string $content = '', int $uid = self::USER_UID, int $gid = self::USER_GID, int $mode = 0644): bool
    {
        $n = $this->normalize($path);
        if (isset($this->data[$n])) return false;
        $parent = $this->parentPath($n);
        if (!isset($this->data[$parent]) || $this->data[$parent]['type'] !== 'dir') return false;
        $this->data[$n] = [
            'type'    => 'file',
            'mode'    => $mode,
            'uid'     => $uid,
            'gid'     => $gid,
            'inode'   => $this->nextInode++,
            'mtime'   => time(),
            'size'    => strlen($content),
            'content' => $content,
        ];
        return true;
    }

    public function createLink(string $target, string $link, int $uid = self::USER_UID, int $gid = self::USER_GID): bool
    {
        $n = $this->normalize($link);
        if (isset($this->data[$n])) return false;
        $parent = $this->parentPath($n);
        if (!isset($this->data[$parent]) || $this->data[$parent]['type'] !== 'dir') return false;
        $this->data[$n] = [
            'type'        => 'link',
            'mode'        => 0777,
            'uid'         => $uid,
            'gid'         => $gid,
            'inode'       => $this->nextInode++,
            'mtime'       => time(),
            'size'        => strlen($target),
            'link_target' => $target,
        ];
        return true;
    }

    public function writeFile(string $path, string $content): bool
    {
        $n = $this->normalize($path);
        if (!isset($this->data[$n]) || $this->data[$n]['type'] !== 'file') return false;
        $this->data[$n]['content'] = $content;
        $this->data[$n]['size'] = strlen($content);
        $this->data[$n]['mtime'] = time();
        return true;
    }

    public function appendFile(string $path, string $content): bool
    {
        $n = $this->normalize($path);
        if (!isset($this->data[$n]) || $this->data[$n]['type'] !== 'file') return false;
        $this->data[$n]['content'] .= $content;
        $this->data[$n]['size'] = strlen($this->data[$n]['content']);
        $this->data[$n]['mtime'] = time();
        return true;
    }

    public function readFile(string $path): ?string
    {
        $n = $this->normalize($path);
        if (!isset($this->data[$n]) || $this->data[$n]['type'] !== 'file') return null;
        return $this->data[$n]['content'] ?? '';
    }

    public function remove(string $path): bool
    {
        $n = $this->normalize($path);
        if ($n === '/') return false;
        if (!isset($this->data[$n])) return false;
        // Remove children if dir
        foreach (array_keys($this->data) as $p) {
            if (str_starts_with($p, $n . '/')) {
                unset($this->data[$p]);
            }
        }
        unset($this->data[$n]);
        return true;
    }

    public function rename(string $from, string $to): bool
    {
        $f = $this->normalize($from);
        $t = $this->normalize($to);
        if (!isset($this->data[$f])) return false;
        if (isset($this->data[$t])) return false;
        $parent = $this->parentPath($t);
        if (!isset($this->data[$parent]) || $this->data[$parent]['type'] !== 'dir') return false;
        $this->data[$t] = $this->data[$f];
        $this->data[$t]['mtime'] = time();
        unset($this->data[$f]);
        // Move children
        foreach ($this->data as $p => $entry) {
            if (str_starts_with($p, $f . '/')) {
                $newPath = $t . substr($p, strlen($f));
                $this->data[$newPath] = $entry;
                unset($this->data[$p]);
            }
        }
        return true;
    }

    public function copy(string $from, string $to): bool
    {
        $f = $this->normalize($from);
        $t = $this->normalize($to);
        if (!isset($this->data[$f])) return false;
        $parent = $this->parentPath($t);
        if (!isset($this->data[$parent]) || $this->data[$parent]['type'] !== 'dir') return false;
        $this->data[$t] = $this->data[$f];
        $this->data[$t]['inode'] = $this->nextInode++;
        $this->data[$t]['mtime'] = time();
        // Copy children
        foreach ($this->data as $p => $entry) {
            if (str_starts_with($p, $f . '/')) {
                $newPath = $t . substr($p, strlen($f));
                $this->data[$newPath] = $entry;
                $this->data[$newPath]['inode'] = $this->nextInode++;
            }
        }
        return true;
    }

    public function chmod(string $path, int $mode): bool
    {
        $n = $this->normalize($path);
        if (!isset($this->data[$n])) return false;
        $this->data[$n]['mode'] = $mode;
        return true;
    }

    public function chown(string $path, int $uid, int $gid = -1): bool
    {
        $n = $this->normalize($path);
        if (!isset($this->data[$n])) return false;
        $this->data[$n]['uid'] = $uid;
        if ($gid >= 0) $this->data[$n]['gid'] = $gid;
        return true;
    }

    public function normalize(string $path): string
    {
        // Handle ~
        $path = trim($path);
        if ($path === '~' || $path === '~/') return '/home/visitor';
        if (str_starts_with($path, '~/')) {
            $path = '/home/visitor/' . substr($path, 2);
        }
        // Handle relative paths (must be resolved externally with cwd)
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') continue;
            if ($part === '..') { array_pop($parts); continue; }
            $parts[] = $part;
        }
        $result = '/' . implode('/', $parts);
        return $result === '' ? '/' : $result;
    }

    public function resolve(string $path, string $cwd): string
    {
        $path = trim($path);
        if ($path === '' || $path === '~') return '/home/visitor';
        if (str_starts_with($path, '~/')) {
            return $this->normalize('/home/visitor/' . substr($path, 2));
        }
        if (!str_starts_with($path, '/')) {
            $path = rtrim($cwd, '/') . '/' . $path;
        }
        return $this->normalize($path);
    }

    /**
     * Return a simulated data: URI for a file (for cat preview).
     */
    public function getFileContent(string $path): ?string
    {
        $entry = $this->get($path);
        return $entry['content'] ?? null;
    }

    /**
     * Find files matching a glob pattern.
     */
    public function find(string $base, string $pattern, bool $caseSensitive = true): array
    {
        $base = $this->normalize($base);
        $results = [];
        $fn = $caseSensitive ? 'fnmatch' : static fn($p, $v) => fnmatch($p, $v, FNM_CASEFOLD);
        foreach ($this->data as $path => $entry) {
            if (str_starts_with($path, $base . '/') || $path === $base) {
                if ($fn($pattern, basename($path))) {
                    $results[] = $path;
                }
            }
        }
        sort($results);
        return $results;
    }

    public function getData(): array { return $this->data; }

    // ─── serialization ────────────────────────────────────────────

    public function toArray(): array { return $this->data; }
    public static function fromArray(array $data): self
    {
        $fs = new self();
        $fs->data = $data;
        // Recalculate nextInode
        $max = 0;
        foreach ($data as $entry) {
            if (($entry['inode'] ?? 0) > $max) $max = $entry['inode'];
        }
        $fs->nextInode = $max + 1;
        return $fs;
    }

    // ─── helpers ──────────────────────────────────────────────────

    private function buildInitial(): void
    {
        $now = time();
        $root = self::ROOT_UID;
        $user = self::USER_UID;
        $ug = self::USER_GID;
        $rg = self::ROOT_GID;

        $this->addRaw('/', 'dir', 0755, $root, $rg, $now, 4096);
        $this->addRaw('/root', 'dir', 0700, $root, $rg, $now, 4096);
        $this->addRaw('/home', 'dir', 0755, $root, $rg, $now, 4096);
        $this->addRaw('/home/visitor', 'dir', 0700, $user, $ug, $now, 4096);
        $this->addRaw('/tmp', 'dir', 0777, $root, $rg, $now, 4096);
        $this->addRaw('/etc', 'dir', 0755, $root, $rg, $now, 4096);
        $this->addRaw('/var', 'dir', 0755, $root, $rg, $now, 4096);
        $this->addRaw('/var/log', 'dir', 0755, $root, $rg, $now, 4096);
        $this->addRaw('/usr', 'dir', 0755, $root, $rg, $now, 4096);
        $this->addRaw('/usr/bin', 'dir', 0755, $root, $rg, $now, 4096);
        $this->addRaw('/usr/local', 'dir', 0755, $root, $rg, $now, 4096);
        $this->addRaw('/usr/local/bin', 'dir', 0755, $root, $rg, $now, 4096);
        $this->addRaw('/bin', 'link', 0777, $root, $rg, $now, 0, '/usr/bin');
        $this->addRaw('/opt', 'dir', 0755, $root, $rg, $now, 4096);
        $this->addRaw('/mnt', 'dir', 0755, $root, $rg, $now, 4096);
        $this->addRaw('/media', 'dir', 0755, $root, $rg, $now, 4096);
        $this->addRaw('/srv', 'dir', 0755, $root, $rg, $now, 4096);
        $this->addRaw('/proc', 'dir', 0555, $root, $rg, $now, 0);
        $this->addRaw('/sys', 'dir', 0555, $root, $rg, $now, 0);
        $this->addRaw('/dev', 'dir', 0755, $root, $rg, $now, 4096);
        $this->addRaw('/run', 'dir', 0755, $root, $rg, $now, 4096);

        // /etc files
        $this->addRaw('/etc/hostname', 'file', 0644, $root, $rg, $now, 13, 'php-terminal');
        $this->addRaw('/etc/passwd', 'file', 0644, $root, $rg, $now, 158,
            "root:x:0:0:root:/root:/bin/bash\n" .
            "daemon:x:1:1:daemon:/usr/sbin:/usr/sbin/nologin\n" .
            "visitor:x:1000:1000:User,,,:/home/visitor:/bin/bash\n"
        );
        $this->addRaw('/etc/shadow', 'file', 0640, $root, $rg, $now, 80,
            "root:!:$1$xyz$abc:20000:0:99999:7:::\n" .
            "visitor:\$6\$sim\$passwordhash:20000:0:99999:7:::\n"
        );
        $this->addRaw('/etc/group', 'file', 0644, $root, $rg, $now, 65,
            "root:x:0:\n" .
            "sudo:x:27:visitor\n" .
            "users:x:100:visitor\n"
        );
        $this->addRaw('/etc/os-release', 'file', 0644, $root, $rg, $now, 120,
            "PRETTY_NAME=\"Ubuntu 24.04 LTS\"\n" .
            "NAME=\"Ubuntu\"\n" .
            "VERSION_ID=\"24.04\"\n" .
            "VERSION=\"24.04 LTS (Noble Numbat)\"\n" .
            "ID=ubuntu\n"
        );
        $this->addRaw('/etc/issue', 'file', 0644, $root, $rg, $now, 27, "Ubuntu 24.04 LTS \\n \\l\n");
        $this->addRaw('/etc/resolv.conf', 'file', 0644, $root, $rg, $now, 60,
            "nameserver 127.0.0.53\n" .
            "options edns0 trust-ad\n" .
            "search .\n"
        );
        $this->addRaw('/etc/fstab', 'file', 0644, $root, $rg, $now, 185,
            "# /etc/fstab: static file system information\n" .
            "UUID=abc-123 / ext4 defaults 0 1\n" .
            "UUID=def-456 /home ext4 defaults 0 2\n" .
            "UUID=ghi-789 swap swap defaults 0 0\n"
        );
        $this->addRaw('/etc/motd', 'file', 0644, $root, $rg, $now, 58,
            "Welcome to the PHP Ubuntu Terminal Emulator!\n"
        );
        $this->addRaw('/etc/apt/sources.list', 'file', 0644, $root, $rg, $now, 200,
            "deb http://archive.ubuntu.com/ubuntu noble main restricted universe multiverse\n" .
            "deb http://archive.ubuntu.com/ubuntu noble-updates main restricted universe multiverse\n" .
            "deb http://security.ubuntu.com/ubuntu noble-security main restricted universe multiverse\n"
        );

        // /home/visitor files
        $this->addRaw('/home/visitor/readme.txt', 'file', 0644, $user, $ug, $now, 385,
            "========================================\n" .
            " PHP UBUNTU TERMINAL EMULATOR\n" .
            "========================================\n" .
            "\n" .
            "This is a simulated Ubuntu Linux environment running in your browser.\n" .
            "Perfect for learning Linux commands in a safe, sandboxed environment.\n" .
            "\n" .
            "Quick start:\n" .
            "  help          - Show all available commands\n" .
            "  ls            - List files\n" .
            "  cd [dir]      - Change directory\n" .
            "  cat [file]    - View file contents\n" .
            "  man [command] - Show command manual\n" .
            "  clear         - Clear the terminal\n" .
            "\n" .
            "Have fun learning Linux!\n"
        );
        $this->addRaw('/home/visitor/.bashrc', 'file', 0644, $user, $ug, $now, 320,
            "# ~/.bashrc: executed by bash(1) for non-login shells.\n" .
            "export PS1='\\u@\\h:\\w\\$ '\n" .
            "alias ll='ls -la'\n" .
            "alias la='ls -A'\n" .
            "alias l='ls -CF'\n" .
            "alias ..='cd ..'\n" .
            "alias ...='cd ../..'\n" .
            "alias grep='grep --color=auto'\n"
        );
        $this->addRaw('/home/visitor/.bash_logout', 'file', 0644, $user, $ug, $now, 18, "# ~/.bash_logout\n");
        $this->addRaw('/home/visitor/.profile', 'file', 0644, $user, $ug, $now, 75,
            "# ~/.profile: executed by the command interpreter for login shells.\n" .
            "if [ -f \"$HOME/.bashrc\" ]; then . \"$HOME/.bashrc\"; fi\n"
        );
        $this->addRaw('/home/visitor/projects', 'dir', 0755, $user, $ug, $now, 4096);
        $this->addRaw('/home/visitor/projects/hello.py', 'file', 0644, $user, $ug, $now, 63,
            "#!/usr/bin/env python3\n" .
            "print(\"Hello, Ubuntu World!\")\n"
        );

        // /var/log
        $this->addRaw('/var/log/syslog', 'file', 0644, $root, $rg, $now, 500,
            "Jul 25 09:00:01 php-terminal systemd[1]: Starting Daily apt upgrade...\n" .
            "Jul 25 09:00:01 php-terminal systemd[1]: Started Daily apt upgrade.\n" .
            "Jul 25 09:15:32 php-terminal kernel: [ 1234.567890] usb 1-1: new high-speed USB device\n" .
            "Jul 25 09:30:00 php-terminal CRON[1234]: (root) CMD (cd / && run-parts --report /etc/cron.hourly)\n" .
            "Jul 25 10:00:01 php-terminal systemd[1]: Starting systemd-timedated...\n" .
            "Jul 25 10:00:01 php-terminal systemd[1]: Started systemd-timedated.\n" .
            "Jul 25 10:05:23 php-terminal sshd[2345]: Accepted publickey for visitor from 192.168.1.100\n"
        );
        $this->addRaw('/var/log/auth.log', 'file', 0640, $root, $rg, $now, 350,
            "Jul 25 08:00:00 php-terminal sshd[2345]: Server listening on 0.0.0.0 port 22.\n" .
            "Jul 25 08:30:15 php-terminal sudo: visitor : TTY=pts/0 ; PWD=/home/visitor ; USER=root ; COMMAND=/bin/ls\n" .
            "Jul 25 09:00:00 php-terminal sshd[2345]: Accepted publickey for visitor from 192.168.1.100 port 54321\n" .
            "Jul 25 09:00:00 php-terminal sshd[2345]: session opened for user visitor by (uid=0)\n"
        );
        $this->addRaw('/var/log/dpkg.log', 'file', 0644, $root, $rg, $now, 200,
            "2026-07-25 08:00:00 install base-passwd:amd64 3.6.3\n" .
            "2026-07-25 08:00:01 install base-files:amd64 13ubuntu10\n" .
            "2026-07-25 08:00:05 install bash:amd64 5.2.21-2ubuntu4\n" .
            "2026-07-25 08:01:00 install coreutils:amd64 9.4-2ubuntu1\n"
        );
    }

    private function addRaw(string $path, string $type, int $mode, int $uid, int $gid, int $mtime, int $size, string $content = '', ?string $linkTarget = null): void
    {
        $entry = [
            'type'  => $type,
            'mode'  => $mode,
            'uid'   => $uid,
            'gid'   => $gid,
            'inode' => $this->nextInode++,
            'mtime' => $mtime,
            'size'  => $size,
        ];
        if ($type === 'file') {
            $entry['content'] = $content;
        }
        if ($type === 'link') {
            $entry['link_target'] = $linkTarget;
        }
        $this->data[$path] = $entry;
    }

    /**
     * Format mode (octal) to -rwxr-xr-x style.
     */
    public static function formatMode(int $mode, string $type = 'file'): string
    {
        $typeChar = match ($type) {
            'dir' => 'd',
            'link' => 'l',
            default => '-',
        };
        $perms = ['---', '--x', '-w-', '-wx', 'r--', 'r-x', 'rw-', 'rwx'];
        return $typeChar
            . $perms[($mode >> 6) & 7]
            . $perms[($mode >> 3) & 7]
            . $perms[$mode & 7];
    }

    /**
     * Parse a mode string like "755" or "u+x" to an octal integer.
     */
    public static function parseMode(string $modeStr, int $currentMode = 0755): int
    {
        if (preg_match('/^[0-7]{3,4}$/', $modeStr)) {
            return (int)octdec($modeStr);
        }
        // Symbolic mode: u+x, g-w, o=r, a+x etc.
        $mode = $currentMode;
        if (preg_match('/^([ugoa]+)([+\-=])([rwx]+)$/', $modeStr, $m)) {
            $who = $m[1];
            $op = $m[2];
            $perm = 0;
            if (str_contains($m[3], 'r')) $perm |= 4;
            if (str_contains($m[3], 'w')) $perm |= 2;
            if (str_contains($m[3], 'x')) $perm |= 1;
            for ($i = 0; $i < 3; $i++) {
                $shift = 2 - $i;
                $mask = $perm << ($shift * 3);
                if ($who === 'a' || (str_contains($who, 'u') && $i === 0) ||
                    (str_contains($who, 'g') && $i === 1) ||
                    (str_contains($who, 'o') && $i === 2)) {
                    if ($op === '+') $mode |= $mask;
                    elseif ($op === '-') $mode &= ~$mask;
                    elseif ($op === '=') {
                        $clear = 7 << ($shift * 3);
                        $mode = ($mode & ~$clear) | $mask;
                    }
                }
            }
        }
        return $mode;
    }
}