<?php
declare(strict_types=1);

/**
 * Text processing commands: grep, sed, awk, cut, tr, sort, uniq, wc, head, tail, less, more, nl, fold, fmt, pr, expand, unexpand, od, hexdump, xxd, strings, diff, cmp, patch, comm, join, paste, column, rev, tac, tsort, look
 */
class TextCommands extends BaseCommand
{
    public static function getName(): string { return 'grep'; }
    public static function getDescription(): string { return 'Search for patterns in files'; }
    public static function getUsage(): string { return 'grep [pattern] [file...]'; }

    public function run(array $args): CommandResult
    {
        $cmd = array_shift($args) ?? 'grep';
        $method = 'execute' . ucfirst($cmd);
        if (method_exists($this, $method)) {
            return $this->$method($args);
        }
        return new CommandResult(output: "{$cmd}: command not found", error: true);
    }

    private function executeGrep(array $args): CommandResult
    {
        $parsed = $this->parseFlags($args, 'inrvch');
        $flags = $parsed['flags'];
        $operands = $parsed['operands'];

        if (empty($operands)) {
            return new CommandResult(output: 'grep: missing pattern', error: true);
        }

        $pattern = $operands[0];
        $files = array_slice($operands, 1);
        $caseInsensitive = isset($flags['i']);
        $invert = isset($flags['v']);
        $lineNumbers = isset($flags['n']);
        $recursive = isset($flags['r']);
        $count = isset($flags['c']);
        $showFilenames = count($files) > 1;
        $hideFilename = isset($flags['h']);

        if ($recursive && empty($files)) {
            $files = ['.'];
        }

        $output = '';
        $flags_mod = $caseInsensitive ? 'i' : '';

        foreach ($files as $file) {
            $path = $this->resolve($file);
            if ($this->fs->isDir($path)) {
                foreach ($this->fs->children($path) as $name => $entry) {
                    $files[] = $file . '/' . $name;
                }
                continue;
            }
            $content = $this->fs->readFile($path);
            if ($content === null) continue;

            $lines = explode("\n", $content);
            $matchCount = 0;
            $fileOut = '';

            foreach ($lines as $i => $line) {
                $match = $caseInsensitive
                    ? preg_match('/' . preg_quote($pattern, '/') . '/i', $line)
                    : preg_match('/' . preg_quote($pattern, '/') . '/', $line);

                if ($invert) $match = !$match;
                if (!$match) continue;

                $matchCount++;
                $prefix = '';
                if ($showFilenames && !$hideFilename) $prefix .= basename($path) . ':';
                if ($lineNumbers) $prefix .= ($i + 1) . ':';
                $fileOut .= $prefix . $line . "\n";
            }

            if ($count) {
                $prefix = $showFilenames ? basename($path) . ':' : '';
                $output .= "{$prefix}{$matchCount}\n";
            } else {
                $output .= $fileOut;
            }
        }

        return new CommandResult(output: rtrim($output));
    }

    private function executeSed(array $args): CommandResult
    {
        // Simple sed simulation
        if (empty($args)) {
            return new CommandResult(output: 'sed: missing expression', error: true);
        }
        // Find the expression
        $expr = '';
        $file = '';
        foreach ($args as $arg) {
            if (str_starts_with($arg, 's/') || str_starts_with($arg, '-e')) {
                $expr = $arg;
            } elseif (!str_starts_with($arg, '-')) {
                $file = $arg;
            }
        }
        if (!$file) {
            return new CommandResult(output: 'sed: usage: sed s/pattern/replacement/ file');
        }
        $path = $this->resolve($file);
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "sed: cannot read '{$file}'", error: true);
        }
        // Simple s/pattern/replacement/ substitution
        if (preg_match('/^s\/(.+?)\/(.*?)\/(.*)$/', $expr, $m)) {
            $pattern = $m[1];
            $replacement = $m[2];
            $flags = $m[3];
            $global = str_contains($flags, 'g');
            if ($global) {
                $content = preg_replace('/' . preg_quote($pattern, '/') . '/', $replacement, $content);
            } else {
                $content = preg_replace('/' . preg_quote($pattern, '/') . '/', $replacement, $content, 1);
            }
        }
        return new CommandResult(output: $content);
    }

    private function executeAwk(array $args): CommandResult
    {
        // Simplified awk that just prints or does basic field extraction
        $file = '';
        $pattern = '';
        $action = '{print}';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '{')) {
                $action = $arg;
            } elseif (str_starts_with($arg, '/') && str_ends_with($arg, '/')) {
                $pattern = trim($arg, '/');
            } elseif (!str_starts_with($arg, '-') && !str_starts_with($arg, '{')) {
                $file = $arg;
            }
        }
        if (!$file) {
            return new CommandResult(output: 'awk: usage: awk \'{print $1}\' file');
        }
        $path = $this->resolve($file);
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "awk: cannot read '{$file}'", error: true);
        }
        $output = '';
        foreach (explode("\n", $content) as $line) {
            if ($pattern && !preg_match('/' . $pattern . '/', $line)) continue;
            $fields = preg_split('/\s+/', $line);
            // Simple field extraction
            $processed = preg_replace_callback('/\$(\d+)/', static fn($m) => $fields[(int)$m[1] - 1] ?? '', $action);
            $processed = trim($processed, '{}');
            $output .= $processed . "\n";
        }
        return new CommandResult(output: rtrim($output));
    }

    private function executeLess(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '');
        if (!$path) {
            return new CommandResult(output: 'less: missing file operand', error: true);
        }
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "less: cannot open '{$args[0]}': No such file or directory", error: true);
        }
        // Show last 20 lines to simulate pager
        $lines = explode("\n", $content);
        $out = implode("\n", array_slice($lines, -30));
        return new CommandResult(output: $out . "\n\n[less: showing last 30 lines]");
    }

    private function executeMore(array $args): CommandResult
    {
        return $this->executeLess($args);
    }

    private function executeNl(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '');
        if (!$path) {
            return new CommandResult(output: 'nl: missing file operand', error: true);
        }
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "nl: cannot open '{$args[0]}'", error: true);
        }
        $output = '';
        foreach (explode("\n", $content) as $i => $line) {
            $output .= sprintf("%6d  %s\n", $i + 1, $line);
        }
        return new CommandResult(output: rtrim($output));
    }

    private function executeFold(array $args): CommandResult
    {
        $width = 80;
        $file = '';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '-w')) {
                $width = (int)substr($arg, 2);
            } elseif (!str_starts_with($arg, '-')) {
                $file = $arg;
            }
        }
        if (!$file) {
            return new CommandResult(output: 'fold: missing file operand', error: true);
        }
        $path = $this->resolve($file);
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "fold: cannot open '{$file}'", error: true);
        }
        $output = '';
        foreach (explode("\n", $content) as $line) {
            $output .= wordwrap($line, $width, "\n", true) . "\n";
        }
        return new CommandResult(output: rtrim($output));
    }

    private function executeStrings(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '');
        if (!$path) {
            return new CommandResult(output: 'strings: missing file operand', error: true);
        }
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "strings: cannot open '{$args[0]}'", error: true);
        }
        // Extract printable strings of length >= 4
        preg_match_all('/[[:print:]]{4,}/', $content, $matches);
        return new CommandResult(output: implode("\n", $matches[0] ?? []));
    }

    private function executeDiff(array $args): CommandResult
    {
        if (count($args) < 2) {
            return new CommandResult(output: 'diff: missing operand', error: true);
        }
        $path1 = $this->resolve($args[0]);
        $path2 = $this->resolve($args[1]);
        $c1 = $this->fs->readFile($path1);
        $c2 = $this->fs->readFile($path2);
        if ($c1 === null) {
            return new CommandResult(output: "diff: cannot open '{$args[0]}'", error: true);
        }
        if ($c2 === null) {
            return new CommandResult(output: "diff: cannot open '{$args[1]}'", error: true);
        }
        if ($c1 === $c2) {
            return new CommandResult();
        }
        $lines1 = explode("\n", $c1);
        $lines2 = explode("\n", $c2);
        $out = "--- {$args[0]}\n+++ {$args[1]}\n";
        $max = max(count($lines1), count($lines2));
        for ($i = 0; $i < $max; $i++) {
            $l1 = $lines1[$i] ?? '';
            $l2 = $lines2[$i] ?? '';
            if ($l1 !== $l2) {
                $out .= sprintf("@@ -%d,%d +%d,%d @@\n", $i + 1, 1, $i + 1, 1);
                if (isset($lines1[$i])) $out .= "-{$lines1[$i]}\n";
                if (isset($lines2[$i])) $out .= "+{$lines2[$i]}\n";
                break; // Simple: show first difference only
            }
        }
        return new CommandResult(output: rtrim($out));
    }

    private function executeCmp(array $args): CommandResult
    {
        if (count($args) < 2) {
            return new CommandResult(output: 'cmp: missing operand', error: true);
        }
        $path1 = $this->resolve($args[0]);
        $path2 = $this->resolve($args[1]);
        $c1 = $this->fs->readFile($path1);
        $c2 = $this->fs->readFile($path2);
        if ($c1 === $c2) {
            return new CommandResult();
        }
        $minLen = min(strlen($c1 ?? ''), strlen($c2 ?? ''));
        for ($i = 0; $i < $minLen; $i++) {
            if (($c1[$i] ?? '') !== ($c2[$i] ?? '')) {
                return new CommandResult(output: "{$args[0]} {$args[1]} differ: byte {$i}, line " . (substr_count(substr($c1 ?? '', 0, $i), "\n") + 1));
            }
        }
        return new CommandResult(output: "{$args[0]} {$args[1]} differ: byte {$minLen}, line " . (substr_count(substr($c1 ?? '', 0, $minLen), "\n") + 1));
    }

    private function executePaste(array $args): CommandResult
    {
        if (count($args) < 2) {
            return new CommandResult(output: 'paste: missing operand', error: true);
        }
        $contents = [];
        foreach ($args as $arg) {
            $path = $this->resolve($arg);
            $c = $this->fs->readFile($path);
            $contents[] = $c !== null ? explode("\n", $c) : [];
        }
        $output = '';
        $max = max(array_map('count', $contents));
        for ($i = 0; $i < $max; $i++) {
            $row = [];
            foreach ($contents as $lines) {
                $row[] = $lines[$i] ?? '';
            }
            $output .= implode("\t", $row) . "\n";
        }
        return new CommandResult(output: rtrim($output));
    }

    private function executeJoin(array $args): CommandResult
    {
        return new CommandResult(output: 'join: usage: join file1 file2');
    }

    private function executeColumn(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '');
        if (!$path) {
            return new CommandResult(output: 'column: missing file operand', error: true);
        }
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "column: cannot open '{$args[0]}'", error: true);
        }
        // Just return the content as-is
        return new CommandResult(output: $content);
    }

    private function executeRev(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '');
        if (!$path) {
            return new CommandResult(output: 'rev: missing file operand', error: true);
        }
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "rev: cannot open '{$args[0]}'", error: true);
        }
        $output = '';
        foreach (explode("\n", $content) as $line) {
            $output .= strrev($line) . "\n";
        }
        return new CommandResult(output: rtrim($output));
    }

    private function executeTac(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '');
        if (!$path) {
            return new CommandResult(output: 'tac: missing file operand', error: true);
        }
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "tac: cannot open '{$args[0]}'", error: true);
        }
        $lines = explode("\n", $content);
        return new CommandResult(output: implode("\n", array_reverse($lines)));
    }

    private function executeFmt(array $args): CommandResult
    {
        $width = 72;
        $file = '';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '-w')) {
                $width = (int)substr($arg, 2);
            } elseif (!str_starts_with($arg, '-')) {
                $file = $arg;
            }
        }
        if (!$file) {
            return new CommandResult(output: 'fmt: missing file operand', error: true);
        }
        $path = $this->resolve($file);
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "fmt: cannot open '{$file}'", error: true);
        }
        return new CommandResult(output: wordwrap($content, $width));
    }

    private function executePr(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '');
        if (!$path) {
            return new CommandResult(output: 'pr: missing file operand', error: true);
        }
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "pr: cannot open '{$args[0]}'", error: true);
        }
        $date = date('Y-M-d H:i');
        $out = "\n\n{$date}  {$args[0]}  Page 1\n\n" . $content . "\n";
        return new CommandResult(output: $out);
    }

    private function executeExpand(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '');
        if (!$path) {
            return new CommandResult(output: 'expand: missing file operand', error: true);
        }
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "expand: cannot open '{$args[0]}'", error: true);
        }
        return new CommandResult(output: str_replace("\t", '        ', $content));
    }

    private function executeUnexpand(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '');
        if (!$path) {
            return new CommandResult(output: 'unexpand: missing file operand', error: true);
        }
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "unexpand: cannot open '{$args[0]}'", error: true);
        }
        return new CommandResult(output: preg_replace('/ {8}/', "\t", $content));
    }

    private function executeOd(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '');
        if (!$path) {
            return new CommandResult(output: 'od: missing file operand', error: true);
        }
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "od: cannot open '{$args[0]}'", error: true);
        }
        $out = '';
        for ($i = 0; $i < min(strlen($content), 128); $i += 16) {
            $out .= sprintf('%07o ', $i);
            for ($j = 0; $j < 16; $j++) {
                $out .= sprintf('%03o ', ord($content[$i + $j] ?? "\0"));
            }
            $out .= "\n";
        }
        return new CommandResult(output: rtrim($out));
    }

    private function executeHexdump(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'hexdump: missing file operand', error: true);
        }
        return $this->executeOd($args);
    }

    private function executeXxd(array $args): CommandResult
    {
        $path = $this->resolve($args[0] ?? '');
        if (!$path) {
            return new CommandResult(output: 'xxd: missing file operand', error: true);
        }
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "xxd: cannot open '{$args[0]}'", error: true);
        }
        $out = '';
        for ($i = 0; $i < min(strlen($content), 128); $i += 16) {
            $out .= sprintf('%07x: ', $i);
            $hex = '';
            $asc = '';
            for ($j = 0; $j < 16; $j++) {
                $c = ord($content[$i + $j] ?? "\0");
                $hex .= sprintf('%02x ', $c);
                $asc .= ($c >= 32 && $c <= 126) ? chr($c) : '.';
            }
            $out .= $hex . ' ' . $asc . "\n";
        }
        return new CommandResult(output: rtrim($out));
    }

    private function executePatch(array $args): CommandResult
    {
        if (count($args) < 2) {
            return new CommandResult(output: 'patch: missing operand', error: true);
        }
        return new CommandResult(output: "patching file {$args[0]}\n[patch applied successfully]");
    }

    private function executeComm(array $args): CommandResult
    {
        if (count($args) < 2) {
            return new CommandResult(output: 'comm: missing operand', error: true);
        }
        $path1 = $this->resolve($args[0]);
        $path2 = $this->resolve($args[1]);
        $c1 = $this->fs->readFile($path1);
        $c2 = $this->fs->readFile($path2);
        if ($c1 === $c2 && $c1 !== null) {
            return new CommandResult(output: "(files are identical)");
        }
        return new CommandResult(output: join("\n", array_diff(
            explode("\n", $c1 ?? ''),
            explode("\n", $c2 ?? '')
        )));
    }

    private function executeLook(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'look: missing prefix pattern', error: true);
        }
        return new CommandResult(output: "look: usage: look [prefix] [file]");
    }

    private function executeTsort(array $args): CommandResult
    {
        return new CommandResult(output: "tsort: " . $this->fs->normalize($args[0] ?? ''));
    }
}