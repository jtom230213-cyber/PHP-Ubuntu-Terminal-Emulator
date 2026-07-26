<?php
declare(strict_types=1);

/**
 * File operation commands: cat, touch, mkdir, rm, rmdir, mv, cp, ln, find, locate, which, file, stat, du, df, dd, head, tail, wc, sort, uniq, cut, tr, tee, basename, dirname, realpath, readlink, shred, chattr, lsattr, install, mktemp, tempfile, pathchk, mvdir
 */
class FileCommands extends BaseCommand
{
    public static function getName(): string { return 'cat'; }
    public static function getDescription(): string { return 'Concatenate files and print on stdout'; }
    public static function getUsage(): string { return 'cat [file...]'; }

    public function run(array $args): CommandResult
    {
        $cmd = array_shift($args) ?? 'cat';
        $method = 'execute' . ucfirst($cmd);
        if (method_exists($this, $method)) {
            return $this->$method($args);
        }
        // Default to cat
        return $this->executeCat($args);
    }

    private function executeCat(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'cat: missing file operand', error: true);
        }
        $output = '';
        foreach ($args as $arg) {
            $path = $this->resolve($arg);
            $entry = $this->fs->stat($path);
            if (!$entry) {
                $output .= "cat: {$arg}: No such file or directory\n";
                continue;
            }
            if ($entry['type'] === 'dir') {
                $output .= "cat: {$arg}: Is a directory\n";
                continue;
            }
            $content = $this->fs->readFile($path);
            if ($content !== null) {
                $output .= $content;
            }
            if (!str_ends_with($output, "\n")) {
                $output .= "\n";
            }
        }
        return new CommandResult(output: rtrim($output, "\n"));
    }

    private function executeTouch(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'touch: missing file operand', error: true);
        }
        foreach ($args as $arg) {
            $path = $this->resolve($arg);
            if ($this->fs->exists($path)) {
                // Update mtime
                $this->fs->chmod($path, $this->fs->stat($path)['mode']);
            } else {
                $this->fs->createFile($path, '');
            }
        }
        return new CommandResult();
    }

    private function executeMkdir(array $args): CommandResult
    {
        $parsed = $this->parseFlags($args, 'p');
        $flags = $parsed['flags'];
        $operands = $parsed['operands'];

        if (empty($operands)) {
            return new CommandResult(output: 'mkdir: missing operand', error: true);
        }
        foreach ($operands as $arg) {
            $path = $this->resolve($arg);
            if ($this->fs->exists($path)) {
                return new CommandResult(output: "mkdir: cannot create directory '{$arg}': File exists", error: true);
            }
            if (isset($flags['p'])) {
                // Create parent directories
                $parts = explode('/', trim($path, '/'));
                $current = '';
                foreach ($parts as $part) {
                    $current .= '/' . $part;
                    if (!$this->fs->exists($current)) {
                        $this->fs->createDir($current);
                    }
                }
            } else {
                $this->fs->createDir($path);
            }
        }
        return new CommandResult();
    }

    private function executeRm(array $args): CommandResult
    {
        $parsed = $this->parseFlags($args, 'rf');
        $flags = $parsed['flags'];
        $operands = $parsed['operands'];

        if (empty($operands)) {
            return new CommandResult(output: 'rm: missing operand', error: true);
        }
        $recursive = isset($flags['r']) || isset($flags['f']);
        $force = isset($flags['f']);

        foreach ($operands as $arg) {
            $path = $this->resolve($arg);
            if (!$this->fs->exists($path)) {
                if (!$force) {
                    return new CommandResult(output: "rm: cannot remove '{$arg}': No such file or directory", error: true);
                }
                continue;
            }
            if ($this->fs->isDir($path)) {
                if (!$recursive) {
                    return new CommandResult(output: "rm: cannot remove '{$arg}': Is a directory", error: true);
                }
                // Check if empty (when not using -r with force)
                $children = $this->fs->children($path);
                if (!empty($children) && !$recursive) {
                    return new CommandResult(output: "rm: cannot remove '{$arg}': Directory not empty", error: true);
                }
            }
            $this->fs->remove($path);
        }
        return new CommandResult();
    }

    private function executeRmdir(array $args): CommandResult
    {
        $parsed = $this->parseFlags($args, 'p');
        $flags = $parsed['flags'];
        $operands = $parsed['operands'];

        if (empty($operands)) {
            return new CommandResult(output: 'rmdir: missing operand', error: true);
        }
        foreach ($operands as $arg) {
            $path = $this->resolve($arg);
            if (!$this->fs->isDir($path)) {
                return new CommandResult(output: "rmdir: failed to remove '{$arg}': No such file or directory", error: true);
            }
            $children = $this->fs->children($path);
            if (!empty($children)) {
                return new CommandResult(output: "rmdir: failed to remove '{$arg}': Directory not empty", error: true);
            }
            $this->fs->remove($path);
            if (isset($flags['p'])) {
                $parent = $this->fs->parentPath($path);
                while ($parent !== '/') {
                    $c = $this->fs->children($parent);
                    if (empty($c)) {
                        $this->fs->remove($parent);
                    }
                    $parent = $this->fs->parentPath($parent);
                }
            }
        }
        return new CommandResult();
    }

    private function executeMv(array $args): CommandResult
    {
        if (count($args) < 2) {
            return new CommandResult(output: 'mv: missing file operand', error: true);
        }
        $source = $this->resolve($args[0]);
        $dest = $this->resolve($args[1]);

        if (!$this->fs->exists($source)) {
            return new CommandResult(output: "mv: cannot stat '{$args[0]}': No such file or directory", error: true);
        }

        // If dest is existing dir, move inside
        if ($this->fs->isDir($dest)) {
            $dest = rtrim($dest, '/') . '/' . basename($source);
        }

        $this->fs->rename($source, $dest);
        return new CommandResult();
    }

    private function executeCp(array $args): CommandResult
    {
        $parsed = $this->parseFlags($args, 'rR');
        $flags = $parsed['flags'];
        $operands = $parsed['operands'];

        if (count($operands) < 2) {
            return new CommandResult(output: 'cp: missing file operand', error: true);
        }
        $recursive = isset($flags['r']) || isset($flags['R']);
        $source = $this->resolve($operands[0]);
        $dest = $this->resolve($operands[1]);

        if (!$this->fs->exists($source)) {
            return new CommandResult(output: "cp: cannot stat '{$operands[0]}': No such file or directory", error: true);
        }

        // If dest is existing dir, copy inside
        if ($this->fs->isDir($dest)) {
            $dest = rtrim($dest, '/') . '/' . basename($source);
        }

        if ($this->fs->isDir($source)) {
            if (!$recursive) {
                return new CommandResult(output: "cp: -r not specified; omitting directory '{$operands[0]}'", error: true);
            }
            $this->copyRecursive($source, $dest);
        } else {
            $content = $this->fs->readFile($source);
            $this->fs->createFile($dest, $content ?? '');
        }
        return new CommandResult();
    }

    private function copyRecursive(string $src, string $dst): void
    {
        $this->fs->createDir($dst);
        foreach ($this->fs->children($src) as $name => $entry) {
            $childSrc = rtrim($src, '/') . '/' . $name;
            $childDst = rtrim($dst, '/') . '/' . $name;
            if ($entry['type'] === 'dir') {
                $this->copyRecursive($childSrc, $childDst);
            } else {
                $content = $this->fs->readFile($childSrc);
                $this->fs->createFile($childDst, $content ?? '');
            }
        }
    }

    private function executeLn(array $args): CommandResult
    {
        $parsed = $this->parseFlags($args, 's');
        $flags = $parsed['flags'];
        $operands = $parsed['operands'];

        if (count($operands) < 2) {
            return new CommandResult(output: 'ln: missing file operand', error: true);
        }
        $target = $this->resolve($operands[0]);
        $link = $this->resolve($operands[1]);

        if (!$this->fs->exists($target)) {
            return new CommandResult(output: "ln: failed to access '{$operands[0]}': No such file or directory", error: true);
        }

        if (isset($flags['s'])) {
            $this->fs->createLink($operands[0], $link);
        } else {
            // Hard link: just copy the file
            $content = $this->fs->readFile($target);
            $this->fs->createFile($link, $content ?? '');
        }
        return new CommandResult();
    }

    private function executeFind(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '.');
        $pattern = $args[1] ?? '*';
        if (!str_contains($pattern, '*') && !str_contains($pattern, '?')) {
            $pattern = '*' . $pattern . '*';
        }
        $matches = $this->fs->find($path, $pattern);
        return new CommandResult(output: implode("\n", $matches));
    }

    private function executeLocate(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'locate: missing pattern', error: true);
        }
        $pattern = $args[0];
        $results = [];
        foreach ($this->fs->getData() as $path => $entry) {
            if (str_contains($path, $pattern)) {
                $results[] = $path;
            }
        }
        sort($results);
        return new CommandResult(output: implode("\n", $results));
    }

    private function executeWhich(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'which: missing command name', error: true);
        }
        $output = '';
        foreach ($args as $cmd) {
            if (CommandRegistry::exists($cmd)) {
                $output .= "/usr/bin/{$cmd}\n";
            } else {
                $output .= "{$cmd} not found\n";
            }
        }
        return new CommandResult(output: rtrim($output));
    }

    private function executeFile(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'file: missing file operand', error: true);
        }
        $output = '';
        foreach ($args as $arg) {
            $path = $this->resolve($arg);
            $entry = $this->fs->stat($path);
            if (!$entry) {
                $output .= "{$arg}: cannot open: No such file or directory\n";
                continue;
            }
            $type = match ($entry['type']) {
                'dir' => 'directory',
                'link' => 'symbolic link',
                'file' => 'ASCII text',
                default => 'data',
            };
            $output .= "{$arg}: {$type}\n";
        }
        return new CommandResult(output: rtrim($output));
    }

    private function executeStat(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '.');
        $entry = $this->fs->stat($path);
        if (!$entry) {
            return new CommandResult(output: "stat: cannot stat '{$args[0]}': No such file or directory", error: true);
        }
        $type = $entry['type'];
        $modeStr = Filesystem::formatMode($entry['mode'], $type);
        $out = "  File: " . $this->fs->normalize($path) . "\n"
             . "  Size: {$entry['size']}\tBlocks: " . intdiv($entry['size'] + 511, 512) . "\n"
             . "Device: 0,0\tInode: {$entry['inode']}\tLinks: 1\n"
             . "Access: ({$modeStr}) Uid: {$entry['uid']}\tGid: {$entry['gid']}\n"
             . "Access: " . date('Y-m-d H:i:s', $entry['mtime']) . "\n"
             . "Modify: " . date('Y-m-d H:i:s', $entry['mtime']) . "\n";
        return new CommandResult(output: $out);
    }

    private function executeDu(array $args): CommandResult
    {
        $parsed = $this->parseFlags($args, 'sh');
        $flags = $parsed['flags'];
        $operands = $parsed['operands'];
        $path = $this->resolve($operands[0] ?? '.');
        $total = $this->calculateSize($path);
        if (isset($flags['h'])) {
            $out = $this->formatSize($total) . "\t" . ($operands[0] ?? '.');
        } else {
            $out = $total . "\t" . ($operands[0] ?? '.');
        }
        return new CommandResult(output: $out);
    }

    private function calculateSize(string $path): int
    {
        $entry = $this->fs->stat($path);
        if (!$entry) return 0;
        if ($entry['type'] === 'file') return $entry['size'];
        $total = 4096;
        foreach ($this->fs->children($path) as $name => $child) {
            $total += $this->calculateSize(rtrim($path, '/') . '/' . $name);
        }
        return $total;
    }

    private function executeDf(array $args): CommandResult
    {
        // Show a simulated disk usage.
        $total = 20971520; // 20 GB
        $used = 5242880;   // 5 GB
        $avail = $total - $used;
        $usePercent = round(($used / $total) * 100);
        $out = "Filesystem     1K-blocks      Used Available Use% Mounted on\n"
             . "/dev/sda1       " . str_pad((string)$total, 10) . " " . str_pad((string)$used, 8) . " "
             . str_pad((string)$avail, 9) . " {$usePercent}% /";
        return new CommandResult(output: $out);
    }

    private function executeHead(array $args): CommandResult
    {
        $parsed = $this->parseFlags($args, 'n');
        $flags = $parsed['flags'];
        $operands = $parsed['operands'];
        $lines = (int)($flags['n'] ?? 10);
        $path = $this->resolve($operands[0] ?? '');
        if (!$path) {
            return new CommandResult(output: 'head: missing file operand', error: true);
        }
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "head: cannot open '{$operands[0]}': No such file or directory", error: true);
        }
        $allLines = explode("\n", $content);
        $out = implode("\n", array_slice($allLines, 0, $lines));
        return new CommandResult(output: $out);
    }

    private function executeTail(array $args): CommandResult
    {
        $parsed = $this->parseFlags($args, 'n');
        $flags = $parsed['flags'];
        $operands = $parsed['operands'];
        $lines = (int)($flags['n'] ?? 10);
        $path = $this->resolve($operands[0] ?? '');
        if (!$path) {
            return new CommandResult(output: 'tail: missing file operand', error: true);
        }
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "tail: cannot open '{$operands[0]}': No such file or directory", error: true);
        }
        $allLines = explode("\n", $content);
        $out = implode("\n", array_slice($allLines, -$lines));
        return new CommandResult(output: $out);
    }

    private function executeWc(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '');
        if (!$path) {
            return new CommandResult(output: 'wc: missing file operand', error: true);
        }
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "wc: cannot open '{$args[0]}': No such file or directory", error: true);
        }
        $lines = substr_count($content, "\n") + (str_ends_with($content, "\n") ? 0 : 1);
        $words = str_word_count($content);
        $chars = strlen($content);
        $out = sprintf("%4d %4d %4d %s", $lines, $words, $chars, $args[0]);
        return new CommandResult(output: $out);
    }

    private function executeSort(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '');
        if (!$path) {
            return new CommandResult(output: 'sort: missing file operand', error: true);
        }
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "sort: cannot read: '{$args[0]}'", error: true);
        }
        $lines = explode("\n", $content);
        sort($lines);
        return new CommandResult(output: implode("\n", $lines));
    }

    private function executeUniq(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '');
        if (!$path) {
            return new CommandResult(output: 'uniq: missing file operand', error: true);
        }
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "uniq: cannot read: '{$args[0]}'", error: true);
        }
        $lines = explode("\n", $content);
        $out = [];
        $prev = null;
        foreach ($lines as $line) {
            if ($line !== $prev) {
                $out[] = $line;
                $prev = $line;
            }
        }
        return new CommandResult(output: implode("\n", $out));
    }

    private function executeTee(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'tee: missing file operand', error: true);
        }
        // tee reads from stdin — in our case, we'll just create the file
        // Real tee would pipe input, but we simulate by creating
        return new CommandResult(output: "tee: usage: cat file | tee outfile");
    }

    private function executeBasename(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'basename: missing operand', error: true);
        }
        return new CommandResult(output: basename($args[0]));
    }

    private function executeDirname(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'dirname: missing operand', error: true);
        }
        return new CommandResult(output: dirname($args[0]));
    }

    private function executeRealpath(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '');
        if (!$this->fs->exists($path)) {
            return new CommandResult(output: "realpath: '{$args[0]}': No such file or directory", error: true);
        }
        return new CommandResult(output: $path);
    }

    private function executeReadlink(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '');
        $entry = $this->fs->stat($path);
        if (!$entry || $entry['type'] !== 'link') {
            return new CommandResult(output: "readlink: '{$args[0]}': No such file or directory", error: true);
        }
        return new CommandResult(output: $entry['link_target'] ?? '');
    }

    private function executeShred(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'shred: missing file operand', error: true);
        }
        foreach ($args as $arg) {
            $path = $this->resolve($arg);
            if ($this->fs->exists($path)) {
                $this->fs->writeFile($path, str_repeat("\0", 1024));
                $this->fs->remove($path);
            }
        }
        return new CommandResult();
    }

    private function executeMktemp(array $args): CommandResult
    {
        $prefix = $args[0] ?? 'tmp.';
        $tmpDir = '/tmp';
        $name = $prefix . bin2hex(random_bytes(4));
        $path = $tmpDir . '/' . $name;
        $this->fs->createFile($path, '');
        return new CommandResult(output: $path);
    }

    private function executeCut(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '');
        if (!$path) {
            return new CommandResult(output: 'cut: missing file operand', error: true);
        }
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "cut: cannot read '{$args[0]}'", error: true);
        }
        return new CommandResult(output: $content); // Simplified
    }

    private function executeTr(array $args): CommandResult
    {
        return new CommandResult(output: 'tr: usage: cat file | tr set1 set2');
    }

    private function executeChattr(array $args): CommandResult
    {
        if (count($args) < 2) {
            return new CommandResult(output: 'chattr: missing operand', error: true);
        }
        return new CommandResult(output: "chattr: attributes of '{$args[1]}' updated");
    }

    private function executeLsattr(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '.');
        if (!$this->fs->exists($path)) {
            return new CommandResult(output: "lsattr: No such file: '{$args[0]}'", error: true);
        }
        return new CommandResult(output: "--------------e------- " . ($args[0] ?? '.'));
    }

    private function executeInstall(array $args): CommandResult
    {
        if (count($args) < 2) {
            return new CommandResult(output: 'install: missing file operand', error: true);
        }
        $source = $this->resolve($args[0]);
        $dest = $this->resolve($args[1]);
        if (!$this->fs->exists($source)) {
            return new CommandResult(output: "install: cannot stat '{$args[0]}': No such file or directory", error: true);
        }
        $content = $this->fs->readFile($source);
        $this->fs->createFile($dest, $content ?? '');
        $this->fs->chmod($dest, 0755);
        return new CommandResult();
    }

    private function executeTempfile(array $args): CommandResult
    {
        $prefix = $args[0] ?? 'tmp.';
        $name = $prefix . bin2hex(random_bytes(4));
        $path = '/tmp/' . $name;
        $this->fs->createFile($path, '');
        return new CommandResult(output: $path);
    }

    private function executePathchk(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'pathchk: missing operand', error: true);
        }
        $path = $args[0];
        if (strlen(basename($path)) > 255) {
            return new CommandResult(output: "pathchk: name '{$path}' exceeds NAME_MAX", error: true);
        }
        return new CommandResult();
    }

    private function executeDd(array $args): CommandResult
    {
        $if = ($k = array_search('if', $args)) !== false ? $args[$k + 1] ?? '' : '';
        $of = ($k = array_search('of', $args)) !== false ? $args[$k + 1] ?? '' : '';
        if (!$if || !$of) {
            return new CommandResult(output: "dd: usage: dd if=<file> of=<file> [bs=N] [count=N]");
        }
        $ifPath = $this->resolve($if);
        if (!$this->fs->exists($ifPath)) {
            return new CommandResult(output: "dd: failed to open '{$if}': No such file or directory", error: true);
        }
        $content = $this->fs->readFile($ifPath);
        $ofPath = $this->resolve($of);
        $this->fs->createFile($ofPath, $content ?? '');
        $size = strlen($content ?? '');
        return new CommandResult(output: "0+1 records in\n0+1 records out\n{$size} bytes copied, 0.001s");
    }
}