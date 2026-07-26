<?php
declare(strict_types=1);

/**
 * Command-line parser: splits input into tokens, handles pipes, quotes, escapes.
 */
class Parser
{
    /**
     * Parse a command line into an array of command segments (for pipe support).
     * Each segment: [ 'command' => string, 'args' => array, 'redirect' => array|null ]
     */
    public static function parse(string $input): array
    {
        $input = trim($input);
        if ($input === '') {
            return [];
        }

        // Split on pipe (|) not inside quotes
        $segments = self::splitPipes($input);
        $result = [];

        foreach ($segments as $seg) {
            $seg = trim($seg);
            if ($seg === '') continue;

            // Check for redirects
            $redirect = null;
            if (preg_match('/\s*(2?>[>]?)\s*(\S+)\s*$/', $seg, $m)) {
                $redirect = ['op' => $m[1], 'file' => $m[2]];
                $seg = trim(substr($seg, 0, -strlen($m[0])));
            }

            $tokens = self::tokenize($seg);
            $command = array_shift($tokens) ?? '';
            $result[] = [
                'command'   => $command,
                'args'      => $tokens,
                'redirect'  => $redirect,
            ];
        }

        return $result;
    }

    /**
     * Tokenize a command string, respecting quotes and escapes.
     */
    private static function tokenize(string $input): array
    {
        $tokens = [];
        $current = '';
        $inSingle = false;
        $inDouble = false;
        $escape = false;
        $len = strlen($input);

        for ($i = 0; $i < $len; $i++) {
            $ch = $input[$i];

            if ($escape) {
                if ($inDouble && $ch !== '"' && $ch !== '\\' && $ch !== '$' && $ch !== '`') {
                    $current .= '\\';
                }
                $current .= $ch;
                $escape = false;
                continue;
            }

            if ($ch === '\\' && !$inSingle) {
                $escape = true;
                continue;
            }

            if ($ch === "'" && !$inDouble) {
                $inSingle = !$inSingle;
                continue;
            }

            if ($ch === '"' && !$inSingle) {
                $inDouble = !$inDouble;
                continue;
            }

            if ($ch === ' ' && !$inSingle && !$inDouble) {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }
                continue;
            }

            $current .= $ch;
        }

        if ($current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }

    /**
     * Split command line on pipe (|) not inside quotes.
     */
    private static function splitPipes(string $input): array
    {
        $parts = [];
        $current = '';
        $inSingle = false;
        $inDouble = false;
        $len = strlen($input);

        for ($i = 0; $i < $len; $i++) {
            $ch = $input[$i];

            if ($ch === "'" && !$inDouble) {
                $inSingle = !$inSingle;
                $current .= $ch;
                continue;
            }
            if ($ch === '"' && !$inSingle) {
                $inDouble = !$inDouble;
                $current .= $ch;
                continue;
            }

            if ($ch === '|' && !$inSingle && !$inDouble) {
                $parts[] = $current;
                $current = '';
                continue;
            }

            $current .= $ch;
        }

        $parts[] = $current;
        return $parts;
    }

    /**
     * Expand environment variables in a string: $VAR, ${VAR}
     */
    public static function expandVars(string $input, array $env): string
    {
        return preg_replace_callback(
            '/\$\{?(\w+)\}?/',
            static fn($m) => $env[$m[1]] ?? $m[0],
            $input
        );
    }

    /**
     * Expand glob patterns into matching files.
     * Returns array of matched paths, or the original token if no match.
     */
    public static function expandGlob(string $token, Filesystem $fs, string $cwd): array
    {
        if (!str_contains($token, '*') && !str_contains($token, '?')) {
            return [$token];
        }

        // Convert glob to regex
        $regex = '/^' . str_replace(
            ['\*', '\?'],
            ['.*', '.'],
            preg_quote($token, '/')
        ) . '$/';

        $matches = [];
        // Get current directory listing
        $dir = $fs->isDir($cwd) ? $cwd : dirname($cwd);
        foreach ($fs->children($dir) as $name => $entry) {
            if (preg_match($regex, $name)) {
                $matches[] = $fs->resolve($name, $cwd);
            }
        }

        return $matches ?: [$token];
    }
}