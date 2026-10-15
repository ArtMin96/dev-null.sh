<?php

declare(strict_types=1);

namespace App\Services;

final readonly class CodeSpans
{
    /**
     * A fenced code block, from a line starting with ``` or more (and maybe a language) to a line that is only ``` or
     * more, or inline
     * code between two backticks on one line. Keep in sync with resources/js/lib/code.ts.
     */
    private const string PATTERN = '/((?<![^\n])`{3,}[^`\r\n]*\r?\n(?:[\s\S]*?\r?\n)?`{3,}[ \t]*(?![^\r\n])|`[^`\r\n]+`)/';

    /**
     * Splits the text into prose and code, alternating: even indexes are prose (maybe empty), odd ones are code.
     *
     * @return list<string>
     */
    public function split(string $text): array
    {
        return preg_split(self::PATTERN, $text, flags: PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
    }

    /**
     * Applies the callback to the prose only, leaving the code as written.
     *
     * @param  callable(string): string  $callback
     */
    public function mapProse(string $text, callable $callback): string
    {
        $parts = $this->split($text);

        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) {
                $parts[$index] = $callback($part);
            }
        }

        return implode('', $parts);
    }

    /**
     * Removes the code, leaving a line break in its place so that the prose on either side stays apart.
     */
    public function withoutCode(string $text): string
    {
        return implode("\n", array_filter($this->split($text), static fn (int $index): bool => $index % 2 === 0, ARRAY_FILTER_USE_KEY));
    }
}
