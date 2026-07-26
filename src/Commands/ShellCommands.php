<?php
declare(strict_types=1);

/**
 * Shell environment commands: alias, unalias, export, unset, set, echo, source, type, declare, readonly, shift, let, eval, exec, exit, return, read, test, trap, bind, command, builtin, enable, hash
 */
class ShellCommands extends BaseCommand
{
    public static function getName(): string { return 'echo'; }
    public static function getDescription(): string { return 'Display a line of text'; }
    public static function getUsage(): string { return 'echo [text...]'; }

    public function run(array $args): CommandResult
    {
        $cmd = array_shift($args) ?? 'echo';
        $method = 'execute' . ucfirst($cmd);
        if (method_exists($this, $method)) {
            return $this->$method($args);
        }
        return new CommandResult(output: "{$cmd}: command not found", error: true);
    }

    private function executeEcho(array $args): CommandResult
    {
        $text = implode(' ', $args);
        // Handle -n flag (no newline)
        if ($text === '-n') {
            return new CommandResult(output: '');
        }
        if (str_starts_with($text, '-n ')) {
            $text = substr($text, 3);
        }
        // Expand $HOME etc.
        $text = str_replace('$HOME', $this->env['HOME'] ?? '/home/visitor', $text);
        $text = str_replace('$USER', $this->username, $text);
        $text = str_replace('$PWD', $this->cwd, $text);
        $text = str_replace('$SHELL', '/bin/bash', $text);
        $text = str_replace('$PATH', '/usr/local/bin:/usr/bin:/bin', $text);
        return new CommandResult(output: $text);
    }

    private function executeAlias(array $args): CommandResult
    {
        if (empty($args)) {
            // List aliases
            $aliases = $_SESSION['php_terminal_aliases'] ?? [];
            if (empty($aliases)) {
                $aliases = [
                    'll' => 'ls -la',
                    'la' => 'ls -A',
                    'l' => 'ls -CF',
                    '..' => 'cd ..',
                    '...' => 'cd ../..',
                    'grep' => 'grep --color=auto',
                ];
            }
            $out = '';
            foreach ($aliases as $name => $value) {
                $out .= "alias {$name}='{$value}'\n";
            }
            return new CommandResult(output: rtrim($out));
        }
        // Parse alias name=value
        foreach ($args as $arg) {
            if (str_contains($arg, '=')) {
                [$name, $value] = explode('=', $arg, 2);
                $value = trim($value, "'\"");
                $aliases = $_SESSION['php_terminal_aliases'] ?? [];
                $aliases[$name] = $value;
                $_SESSION['php_terminal_aliases'] = $aliases;
            }
        }
        return new CommandResult();
    }

    private function executeUnalias(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'unalias: missing alias name', error: true);
        }
        $aliases = $_SESSION['php_terminal_aliases'] ?? [];
        foreach ($args as $arg) {
            unset($aliases[$arg]);
        }
        $_SESSION['php_terminal_aliases'] = $aliases;
        return new CommandResult();
    }

    private function executeExport(array $args): CommandResult
    {
        if (empty($args)) {
            return $this->executePrintenv([]);
        }
        foreach ($args as $arg) {
            if (str_contains($arg, '=')) {
                [$name, $value] = explode('=', $arg, 2);
                $this->env[$name] = $value;
                $env = $_SESSION['php_terminal_env'] ?? [];
                $env[$name] = $value;
                $_SESSION['php_terminal_env'] = $env;
            }
        }
        return new CommandResult();
    }

    private function executeUnset(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'unset: missing variable name', error: true);
        }
        foreach ($args as $arg) {
            unset($this->env[$arg]);
            $env = $_SESSION['php_terminal_env'] ?? [];
            unset($env[$arg]);
            $_SESSION['php_terminal_env'] = $env;
        }
        return new CommandResult();
    }

    private function executeSet(array $args): CommandResult
    {
        $out = '';
        // Show environment variables
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
        ksort($env);
        foreach ($env as $k => $v) {
            $out .= "{$k}={$v}\n";
        }
        return new CommandResult(output: rtrim($out));
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

    private function executeType(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'type: missing argument', error: true);
        }
        $output = '';
        foreach ($args as $arg) {
            if (CommandRegistry::exists($arg)) {
                $output .= "{$arg} is /usr/bin/{$arg}\n";
            } elseif (($this->env['aliases'][$arg] ?? null) || ($_SESSION['php_terminal_aliases'][$arg] ?? null)) {
                $output .= "{$arg} is aliased to `{$_SESSION['php_terminal_aliases'][$arg]}'\n";
            } else {
                $output .= "{$arg} not found\n";
            }
        }
        return new CommandResult(output: rtrim($output));
    }

    private function executeSource(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'source: missing file name', error: true);
        }
        $path = $this->resolve($args[0]);
        $content = $this->fs->readFile($path);
        if ($content === null) {
            return new CommandResult(output: "source: {$args[0]}: No such file or directory", error: true);
        }
        // Simulate sourcing a file
        return new CommandResult(output: "[sourced: {$args[0]}]");
    }

    private function executeReadonly(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'readonly: usage: readonly name[=value]');
        }
        return new CommandResult();
    }

    private function executeEval(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: '');
        }
        return new CommandResult(output: '[eval: ' . implode(' ', $args) . ']');
    }

    private function executeExec(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'exec: missing command', error: true);
        }
        return new CommandResult(output: "[exec: " . implode(' ', $args) . "]");
    }

    private function executeExit(array $args): CommandResult
    {
        $code = $args[0] ?? '0';
        return new CommandResult(output: "logout\n[Process completed]", exit: true);
    }

    private function executeRead(array $args): CommandResult
    {
        return new CommandResult(output: 'read: usage: read -p "Prompt: " variable');
    }

    private function executeTest(array $args): CommandResult
    {
        if (count($args) < 2) {
            return new CommandResult(output: 'test: missing operand', error: true);
        }
        $op = $args[0];
        $path = $this->resolve($args[1]);
        $result = match ($op) {
            '-f' => $this->fs->isFile($path),
            '-d' => $this->fs->isDir($path),
            '-e' => $this->fs->exists($path),
            '-L' => $this->fs->isLink($path),
            '-s' => $this->fs->isFile($path) && ($this->fs->stat($path)['size'] ?? 0) > 0,
            '-r' => true,
            '-w' => true,
            '-x' => true,
            default => null,
        };
        if ($result === null) {
            return new CommandResult(output: "[test: unknown operator {$op}]");
        }
        return new CommandResult(output: $result ? '0' : '1');
    }

    private function executeCommand(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'command: missing command name', error: true);
        }
        $cmd = $args[0];
        $cmdArgs = array_slice($args, 1);
        $handler = CommandRegistry::getHandler($cmd);
        if ($handler) {
            $instance = new $handler($this->fs, $this->cwd);
            $instance->setUsername($this->username);
            $instance->setUid($this->uid);
            $instance->setGid($this->gid);
            $instance->setEnv($this->env);
            return $instance->run(array_merge([$cmd], $cmdArgs));
        }
        return new CommandResult(output: "{$cmd}: command not found", error: true);
    }

    private function executeHash(array $args): CommandResult
    {
        return new CommandResult(output: "hash: hits\tcommand\n" .
            "   1\t/usr/bin/ls\n" .
            "   1\t/usr/bin/cat\n" .
            "   2\t/usr/bin/grep");
    }

    private function executeDeclare(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'declare: usage: declare [-aAfFgilnrtux] [-p] [name[=value]]');
        }
        $name = ltrim($args[0] ?? '', '-fp');
        return new CommandResult(output: "declare -- {$name}");
    }

    private function executeShift(array $args): CommandResult
    {
        return new CommandResult(output: "shift: processing positional parameters (simulated)");
    }

    private function executeLet(array $args): CommandResult
    {
        return new CommandResult(output: 'let: usage: let "expr"');
    }

    private function executeReturn(array $args): CommandResult
    {
        $code = (int)($args[0] ?? 0);
        return new CommandResult(output: "return {$code}", exit: true);
    }

    private function executeTrap(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'trap: usage: trap [-lp] [[arg] signal_spec ...]');
        }
        return new CommandResult(output: "trap: " . ($args[0] ?? '') . " registered for signal processing");
    }

    private function executeBind(array $args): CommandResult
    {
        if (empty($args) || in_array('-P', $args)) {
            return new CommandResult(output:
                "\"\\C-g\"           abort\n" .
                "\"\\C-l\"           clear\n" .
                "\"\\C-c\"           kill-line\n" .
                "\"\\C-d\"           delete-char");
        }
        return new CommandResult(output: "bind: key binding (simulated)");
    }

    private function executeBuiltin(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: 'builtin: usage: builtin [shell-builtin [arg ...]]');
        }
        return new CommandResult(output: "builtin: " . implode(' ', $args) . " (simulated)");
    }

    private function executeEnable(array $args): CommandResult
    {
        if (empty($args)) {
            return new CommandResult(output: "enable: usage: enable [-a] [-n] name ...");
        }
        return new CommandResult(output: "enable: " . implode(' ', $args) . " (simulated)");
    }
}