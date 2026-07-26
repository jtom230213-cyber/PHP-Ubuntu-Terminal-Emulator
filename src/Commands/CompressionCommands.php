<?php
declare(strict_types=1);

/**
 * Compression and archive commands: tar, gzip, gunzip, zip, unzip, bzip2, bunzip2, xz, unxz, zcat, zless, zmore, zgrep, zdiff, compress, uncompress
 */
class CompressionCommands extends BaseCommand
{
    public static function getName(): string { return 'tar'; }
    public static function getDescription(): string { return 'Create and extract archives'; }
    public static function getUsage(): string { return 'tar -czf archive.tar.gz [files...]'; }

    public function run(array $args): CommandResult
    {
        $cmd = array_shift($args) ?? 'tar';
        $method = 'execute' . ucfirst($cmd);
        if (method_exists($this, $method)) {
            return $this->$method($args);
        }
        return new CommandResult(output: "{$cmd}: command not found", error: true);
    }

    private function executeTar(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output:
                "tar: Usage: tar [OPTION...] [FILE]...\n" .
                "Try 'tar --help' or 'tar --usage' for more information.", error: true);
        }

        // Parse args
        $flags = '';
        $archive = '';
        $files = [];
        foreach ($args as $arg) {
            if (str_starts_with($arg, '-')) {
                if (str_starts_with($arg, '--')) {
                    if (str_starts_with($arg, '--file=')) {
                        $archive = substr($arg, 7);
                    }
                } else {
                    $flags .= ltrim($arg, '-');
                }
            } elseif (str_starts_with($arg, '-f')) {
                $archive = $arg;
            } else {
                $files[] = $arg;
            }
        }

        // Find archive name (after -f)
        for ($i = 0; $i < count($args); $i++) {
            if ($args[$i] === '-f' && isset($args[$i + 1])) {
                $archive = $args[$i + 1];
            }
        }

        if (str_contains($flags, 'c')) {
            return new CommandResult(output: "tar: {$archive}: Archive created successfully");
        } elseif (str_contains($flags, 'x') || str_contains($flags, 'z')) {
            return new CommandResult(output: "tar: {$archive}: Archive extracted successfully");
        } elseif (str_contains($flags, 't')) {
            return new CommandResult(output: "tar: Listing contents of {$archive}:\n" .
                "file1.txt\nfile2.txt\ndir/\ndir/file3.txt\nREADME.md");
        }
        return new CommandResult(output: "tar: Command completed successfully");
    }

    private function executeGzip(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'gzip: missing file operand', error: true);
        }
        $output = '';
        foreach ($args as $arg) {
            $path = $this->resolve($arg);
            if (!$this->fs->exists($path)) {
                $output .= "gzip: {$arg}: No such file or directory\n";
                continue;
            }
            $content = $this->fs->readFile($path);
            if ($content !== null) {
                $this->fs->remove($path);
                $this->fs->createFile($path . '.gz', $content);
                $output .= "{$arg}:  {{compressed}}  (100% -> 85%)\n";
            }
        }
        return new CommandResult(output: rtrim($output));
    }

    private function executeGunzip(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'gunzip: missing file operand', error: true);
        }
        $output = '';
        foreach ($args as $arg) {
            $path = $this->resolve($arg);
            if (!$this->fs->exists($path)) {
                $output .= "gunzip: {$arg}: No such file or directory\n";
                continue;
            }
            // Remove .gz and create file
            $outPath = preg_replace('/\.gz$/', '', $path);
            $content = $this->fs->readFile($path);
            if ($content !== null) {
                $this->fs->remove($path);
                $this->fs->createFile($outPath, $content);
                $output .= "{$arg}:  {{decompressed}}\n";
            }
        }
        return new CommandResult(output: rtrim($output));
    }

    private function executeZip(array $args): CommandResult
    {
        if (count($args) < 2) {
            return new CommandResult(output: 'zip: missing file operand', error: true);
        }
        $archive = $args[0];
        $files = array_slice($args, 1);
        $count = count($files);
        $out = "  adding: {$files[0]} (stored 0%)\n";
        if ($count > 1) {
            $out .= "  adding: {$files[1]} (stored 0%)\n";
        }
        $out .= "total bytes=1234, compressed=1234 -> 100% savings\n";
        $out .= "zip: {$archive}.zip created successfully";
        return new CommandResult(output: $out);
    }

    private function executeUnzip(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'unzip: missing file operand', error: true);
        }
        return new CommandResult(output: "Archive:  {$args[0]}\n" .
            "  inflating: file1.txt\n" .
            "  inflating: file2.txt\n" .
            "  extracting: dir/file3.txt\n" .
            "  3 files extracted successfully");
    }

    private function executeBzip2(array $args): CommandResult
    {
        return $this->executeGzip($args);
    }

    private function executeBunzip2(array $args): CommandResult
    {
        return $this->executeGunzip($args);
    }

    private function executeXz(array $args): CommandResult
    {
        return $this->executeGzip($args);
    }

    private function executeUnxz(array $args): CommandResult
    {
        return $this->executeGunzip($args);
    }

    private function executeZcat(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'zcat: missing file operand', error: true);
        }
        $path = $this->resolve($args[0]);
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "zcat: {$args[0]}: No such file or directory", error: true);
        }
        return new CommandResult(output: $content);
    }

    private function executeZless(array $args): CommandResult
    {
        return $this->executeZcat($args);
    }

    private function executeZgrep(array $args): CommandResult
    {
        if (count($args) < 2) {
            return new CommandResult(output: 'zgrep: missing pattern', error: true);
        }
        $path = $this->resolve($args[1]);
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "zgrep: {$args[1]}: No such file or directory", error: true);
        }
        $lines = explode("\n", $content);
        $out = '';
        foreach ($lines as $line) {
            if (str_contains($line, $args[0])) {
                $out .= $line . "\n";
            }
        }
        return new CommandResult(output: rtrim($out));
    }

    private function executeZmore(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '');
        if (!$path) {
            return new CommandResult(output: 'zmore: missing file operand', error: true);
        }
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "zmore: {$args[0]}: No such file or directory", error: true);
        }
        $lines = explode("\n", $content);
        $screen = array_slice($lines, 0, 20);
        return new CommandResult(output: implode("\n", $screen) . "\n--More--(1%)");
    }

    private function executeZdiff(array $args): CommandResult
    {
        if (count($args) < 2) {
            return new CommandResult(output: 'zdiff: missing file operand', error: true);
        }
        $path1 = $this->resolve($args[0]);
        $path2 = $this->resolve($args[1]);
        $c1 = $this->fs->readFile($path1);
        $c2 = $this->fs->readFile($path2);
        if ($c1 === $c2) {
            return new CommandResult();
        }
        return new CommandResult(output: "1c1\n< {$c1}\n---\n> {$c2}");
    }

    private function executeCompress(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '');
        if (!$path) {
            return new CommandResult(output: 'compress: missing file operand', error: true);
        }
        return new CommandResult(output: "compress: {$args[0]}.Z created (simulated)");
    }

    private function executeUncompress(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '');
        if (!$path) {
            return new CommandResult(output: 'uncompress: missing file operand', error: true);
        }
        return new CommandResult(output: "uncompress: {$args[0]} decompressed (simulated)");
    }
}