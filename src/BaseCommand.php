<?php
declare(strict_types=1);

/**
 * Base class for all terminal commands.
 */
abstract class BaseCommand
{
    /** @var string Current username */
    protected string $username = 'visitor';

    /** @var int Current user UID */
    protected int $uid = 1000;

    /** @var int Current user GID */
    protected int $gid = 1000;

    /** @var array<string, string> Environment variables */
    protected array $env = [];

    public function __construct(protected Filesystem $fs, protected string $cwd)
    {
    }

    /**
     * Execute the command.
     * @param array $args Command arguments
     * @return CommandResult
     */
    abstract public function run(array $args): CommandResult;

    /**
     * Get the command name (used for help).
     */
    abstract public static function getName(): string;

    /**
     * Get a short description.
     */
    abstract public static function getDescription(): string;

    /**
     * Get usage example.
     */
    abstract public static function getUsage(): string;

    // ─── helpers for subclasses ───────────────────────────────────

    public function setUsername(string $name): void { $this->username = $name; }
    public function setUid(int $uid): void { $this->uid = $uid; }
    public function setGid(int $gid): void { $this->gid = $gid; }
    public function setEnv(array $env): void { $this->env = $env; }
    public function getFs(): Filesystem { return $this->fs; }
    public function getCwd(): string { return $this->cwd; }
    public function setCwd(string $cwd): void { $this->cwd = $cwd; }

    /**
     * Resolve a path argument, handling ~ and relative paths.
     */
    protected function resolve(string $path): string
    {
        return $this->fs->resolve($path, $this->cwd);
    }

    /**
     * Get a human-readable size string.
     */
    protected function formatSize(int $bytes): string
    {
        if ($bytes >= 1073741824) return sprintf('%.1fG', $bytes / 1073741824);
        if ($bytes >= 1048576) return sprintf('%.1fM', $bytes / 1048576);
        if ($bytes >= 1024) return sprintf('%.1fK', $bytes / 1024);
        return (string)$bytes;
    }

    /**
     * Format a timestamp like ls -l does.
     */
    protected function formatTime(int $timestamp): string
    {
        $diff = time() - $timestamp;
        if ($diff < 86400 * 180) {
            return date('M d H:i', $timestamp);
        }
        return date('M d  Y', $timestamp);
    }

    /**
     * Parse common flags from args array.
     * Returns ['flags' => [...], 'operands' => [...]].
     */
    protected function parseFlags(array $args, string $validShort = ''): array
    {
        $flags = [];
        $operands = [];
        $done = false;
        foreach ($args as $arg) {
            if ($done || !str_starts_with($arg, '-')) {
                $operands[] = $arg;
                continue;
            }
            if ($arg === '--') {
                $done = true;
                continue;
            }
            // Long flag
            if (str_starts_with($arg, '--')) {
                $flags[substr($arg, 2)] = true;
                continue;
            }
            // Short flags
            for ($i = 1; $i < strlen($arg); $i++) {
                $ch = $arg[$i];
                if (str_contains($validShort, $ch)) {
                    $flags[$ch] = true;
                }
            }
        }
        return ['flags' => $flags, 'operands' => $operands];
    }
}

/**
 * Value object wrapping a command execution result.
 */
class CommandResult
{
    public function __construct(
        public readonly string $output = '',
        public readonly string $cwd = '',
        public readonly bool   $clear = false,
        public readonly bool   $error = false,
        public readonly bool   $exit = false,
    ) {}
}