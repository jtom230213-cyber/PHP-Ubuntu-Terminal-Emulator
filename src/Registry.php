<?php
declare(strict_types=1);

/**
 * Command registry - maps command names to their handler classes.
 */
class CommandRegistry
{
    /** @var array<string, class-string> */
    private static array $commands = [];

    /**
     * Register a command.
     */
    public static function register(string $name, string $handlerClass): void
    {
        self::$commands[$name] = $handlerClass;
    }

    /**
     * Register multiple commands from a handler class.
     */
    public static function registerFrom(array $names, string $handlerClass): void
    {
        foreach ($names as $name) {
            self::$commands[$name] = $handlerClass;
        }
    }

    /**
     * Get handler for a command.
     */
    public static function getHandler(string $command): ?string
    {
        return self::$commands[$command] ?? null;
    }

    /**
     * Check if a command exists.
     */
    public static function exists(string $command): bool
    {
        return isset(self::$commands[$command]);
    }

    /**
     * Get all registered command names.
     */
    public static function getAllCommands(): array
    {
        return array_keys(self::$commands);
    }

    /**
     * Find commands matching a prefix.
     */
    public static function findPrefix(string $prefix): array
    {
        $results = [];
        foreach (self::$commands as $name => $handler) {
            if (str_starts_with($name, $prefix)) {
                $results[] = $name;
            }
        }
        sort($results);
        return $results;
    }
}