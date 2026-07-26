<?php
declare(strict_types=1);

/**
 * Navigation commands: pwd, cd, ls, dir, tree, pushd, popd, dirs
 */
class NavigationCommands extends BaseCommand
{
    public static function getName(): string { return 'pwd'; }
    public static function getDescription(): string { return 'Print working directory'; }
    public static function getUsage(): string { return 'pwd'; }

    public function run(array $args): CommandResult
    {
        $cmd = array_shift($args) ?? 'pwd';
        $method = 'execute' . ucfirst($cmd);
        if (method_exists($this, $method)) {
            return $this->$method($args);
        }
        // default to pwd
        return $this->executePwd($args);
    }

    private function executePwd(array $args): CommandResult
    {
        return new CommandResult(output: $this->cwd);
    }

    private function executeCd(array $args): CommandResult
    {
        $target = $args[0] ?? '~';
        $resolved = $this->resolve($target);
        if (!$this->fs->isDir($resolved)) {
            return new CommandResult(
                output: "cd: {$target}: No such file or directory",
                error: true
            );
        }
        return new CommandResult(cwd: $resolved);
    }

    private function executeLs(array $args): CommandResult
    {
        $parsed = $this->parseFlags($args, 'laR1');
        $flags = $parsed['flags'];
        $operands = $parsed['operands'];

        $showAll = isset($flags['a']);
        $long = isset($flags['l']);
        $recursive = isset($flags['R']);
        $oneColumn = isset($flags['1']);

        $target = $this->resolve($operands[0] ?? '.');
        if (!$this->fs->exists($target)) {
            return new CommandResult(
                output: "ls: cannot access '{$operands[0]}': No such file or directory",
                error: true
            );
        }

        $lines = [];
        $this->listDirectory($target, $showAll, $long, $recursive, $lines, '');
        return new CommandResult(output: implode("\n", $lines));
    }

    private function listDirectory(string $path, bool $all, bool $long, bool $recursive, array &$lines, string $prefix): void
    {
        $children = $this->fs->children($path);
        $items = [];

        if ($all) {
            $items['.'] = $this->fs->stat($path);
            $items['..'] = $this->fs->stat($this->fs->parentPath($path));
        }
        foreach ($children as $name => $entry) {
            $items[$name] = $entry;
        }

        if ($long) {
            // Total blocks line
            $blocks = 0;
            foreach ($items as $name => $entry) {
                if ($entry) $blocks += intdiv($entry['size'] + 1023, 1024);
            }
            if ($prefix !== '' || $recursive) {
                $lines[] = ($prefix ? $prefix . ':' : $path) . ':';
            }
            $lines[] = "total {$blocks}";
            foreach ($items as $name => $entry) {
                if (!$entry) continue;
                $type = $entry['type'];
                $modeStr = Filesystem::formatMode($entry['mode'], $type);
                $links = $type === 'dir' ? 2 : 1;
                $uid = $entry['uid'] === 0 ? 'root' : 'visitor';
                $gid = $entry['gid'] === 0 ? 'root' : 'visitor';
                $size = $type === 'dir' ? 4096 : $entry['size'];
                $mtime = $this->formatTime($entry['mtime']);
                $suffix = $type === 'dir' ? '/' : '';
                $lines[] = sprintf('%s %3d %-8s %-8s %8s %s %s%s',
                    $modeStr, $links, $uid, $gid, $size, $mtime, $name, $suffix);
            }
        } else {
            if ($prefix !== '' || $recursive) {
                $lines[] = ($prefix ? $prefix . ':' : $path) . ':';
            }
            $row = [];
            foreach ($items as $name => $entry) {
                if (!$entry) continue;
                $suffix = $entry['type'] === 'dir' ? '/' : '';
                $row[] = $name . $suffix;
            }
            // Columnate (simple 3-column)
            $cols = 3;
            $chunks = array_chunk($row, max(1, intdiv(count($row) + $cols - 1, $cols)));
            if ($oneColumn) {
                foreach ($row as $r) $lines[] = $r;
            } else {
                $maxLen = max(array_map('strlen', $row));
                $colWidth = $maxLen + 2;
                for ($i = 0; $i < count($chunks[0] ?? []); $i++) {
                    $out = '';
                    foreach ($chunks as $chunk) {
                        $out .= str_pad($chunk[$i] ?? '', $colWidth);
                    }
                    $lines[] = rtrim($out);
                }
            }
        }

        if ($recursive) {
            foreach ($items as $name => $entry) {
                if (!$entry || $entry['type'] !== 'dir') continue;
                if ($name === '.' || $name === '..') continue;
                $childPath = rtrim($path, '/') . '/' . $name;
                $this->listDirectory($childPath, $all, $long, $recursive, $lines, $childPath);
            }
        }
    }

    private function executeDir(array $args): CommandResult
    {
        // dir is like ls -C (but we'll alias it)
        $cmd = new DirectoryCommands($this->fs, $this->cwd);
        $cmd->setUsername($this->username);
        $cmd->setUid($this->uid);
        $cmd->setGid($this->gid);
        return $cmd->run(array_merge(['dir'], $args));
    }

    private function executeTree(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '.');
        if (!$this->fs->isDir($path)) {
            return new CommandResult(output: "tree: {$args[0]}: No such file or directory", error: true);
        }
        $lines = [basename($path) === '' ? '/' : basename($path)];
        $this->renderTree($path, '', $lines);
        $count = 0;
        foreach ($this->fs->children($path) as $n => $e) {
            $count++;
        }
        $lines[] = '';
        $lines[] = "{$count} directories, 0 files";
        return new CommandResult(output: implode("\n", $lines));
    }

    private function renderTree(string $path, string $prefix, array &$lines): void
    {
        $children = $this->fs->children($path);
        $keys = array_keys($children);
        $last = count($keys) - 1;
        foreach ($keys as $i => $name) {
            $isLast = $i === $last;
            $connector = $isLast ? '└── ' : '├── ';
            $lines[] = $prefix . $connector . $name;
            $childPath = rtrim($path, '/') . '/' . $name;
            if ($children[$name]['type'] === 'dir') {
                $ext = $isLast ? '    ' : '│   ';
                $this->renderTree($childPath, $prefix . $ext, $lines);
            }
        }
    }

    private function executePushd(array $args): CommandResult
    {
        $target = $args[0] ?? '~';
        $resolved = $this->resolve($target);
        if (!$this->fs->isDir($resolved)) {
            return new CommandResult(output: "pushd: {$target}: No such file or directory", error: true);
        }
        // Store current dir in a stack (session-based)
        // We'll use a simple global mechanism
        $stack = $_SESSION['php_terminal_dirstack'] ?? [];
        array_unshift($stack, $this->cwd);
        $_SESSION['php_terminal_dirstack'] = $stack;
        return new CommandResult(cwd: $resolved);
    }

    private function executePopd(array $args): CommandResult
    {
        $stack = $_SESSION['php_terminal_dirstack'] ?? [];
        if (empty($stack)) {
            return new CommandResult(output: "popd: directory stack empty", error: true);
        }
        $dir = array_shift($stack);
        $_SESSION['php_terminal_dirstack'] = $stack;
        return new CommandResult(cwd: $dir);
    }

    private function executeDirs(array $args): CommandResult
    {
        $stack = $_SESSION['php_terminal_dirstack'] ?? [];
        $output = $this->cwd;
        foreach ($stack as $d) {
            $output .= ' ' . $d;
        }
        return new CommandResult(output: $output);
    }
}

/**
 * Separate dir command handler.
 */
class DirectoryCommands extends BaseCommand
{
    public static function getName(): string { return 'dir'; }
    public static function getDescription(): string { return 'List directory contents (like ls -C)'; }
    public static function getUsage(): string { return 'dir [path]'; }

    public function run(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '.');
        if (!$this->fs->exists($path)) {
            return new CommandResult(output: "dir: cannot access '{$args[0]}': No such file or directory", error: true);
        }
        $children = $this->fs->children($path);
        $names = [];
        foreach ($children as $name => $entry) {
            $suffix = $entry['type'] === 'dir' ? '/' : '';
            $names[] = $name . $suffix;
        }
        // Column output
        $cols = 3;
        $rowCount = max(1, intdiv(count($names) + $cols - 1, $cols));
        $chunks = array_chunk($names, $rowCount);
        $maxLen = max(array_map('strlen', $names));
        $colWidth = $maxLen + 2;
        $lines = [];
        for ($i = 0; $i < $rowCount; $i++) {
            $out = '';
            foreach ($chunks as $chunk) {
                $out .= str_pad($chunk[$i] ?? '', $colWidth);
            }
            $lines[] = rtrim($out);
        }
        return new CommandResult(output: implode("\n", $lines));
    }
}