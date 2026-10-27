<?php

namespace App\Services;

class TokenDecoder
{
    private const TOKEN_PATTERN = '/\[\[([a-z][a-z0-9-]{0,63})\]\]/';

    /**
     * @param  array<string, string>  $definitions
     */
    public function decode(string $message, array $definitions): string
    {
        return preg_replace_callback(
            self::TOKEN_PATTERN,
            fn (array $match): string => $definitions[$match[1]] ?? $match[0],
            $message,
        ) ?? $message;
    }

    /**
     * @return list<string>
     */
    public function extractSymbols(string $message): array
    {
        preg_match_all(self::TOKEN_PATTERN, $message, $matches);

        return array_values(array_unique($matches[1]));
    }
}
