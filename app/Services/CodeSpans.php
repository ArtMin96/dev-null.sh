<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SnippetLanguage;
use Illuminate\Container\Attributes\Scoped;

/**
 * Scoped, so that a page of commits shares one highlighter rather than registering its languages for each commit.
 */
#[Scoped]
final readonly class CodeSpans
{
    /**
     * A fenced code block, from a line starting with ``` or more (and maybe a language) to a line that is only ``` or
     * more, or inline code between two backticks on one line. Keep in sync with resources/js/lib/code.ts.
     */
    private const string PATTERN = '/((?<![^\n])`{3,}[^`\r\n]*\r?\n(?:[\s\S]*?\r?\n)?`{3,}[ \t]*(?![^\r\n])|`[^`\r\n]+`)/';

    public function __construct(private CodeHighlighter $codeHighlighter)
    {
        //
    }

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

    /**
     * Splits the text into prose, inline code and highlighted code blocks. Blocks are highlighted like snippets, so a
     * block's HTML is only ever the escaped code within styling spans, safe to render as is. A block also carries the
     * label of the language it is highlighted in (the name its fence gives an unknown one, plain text when it names none),
     * and its code as written, to be copied. The line breaks around a block are dropped, since it is a block already.
     *
     * @return list<array{type: 'text', text: string}|array{type: 'code', code: string}|array{type: 'block', language: string|null, language_label: string, code: string, html: string}>
     */
    public function highlight(string $text): array
    {
        $parts = $this->split($text);
        $segments = [];

        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                $segments[] = $this->isBlock($part) ? $this->block($part) : ['type' => 'code', 'code' => mb_substr($part, 1, -1)];

                continue;
            }

            if ($this->isBlock($parts[$index - 1] ?? null)) {
                $part = (string) preg_replace('/\A\r?\n/', '', $part);
            }

            if ($this->isBlock($parts[$index + 1] ?? null)) {
                $part = (string) preg_replace('/\r?\n\z/', '', $part);
            }

            if ($part !== '') {
                $segments[] = ['type' => 'text', 'text' => $part];
            }
        }

        return $segments;
    }

    private function isBlock(?string $part): bool
    {
        return $part !== null && str_starts_with($part, '```');
    }

    /**
     * @return array{type: 'block', language: string|null, language_label: string, code: string, html: string}
     */
    private function block(string $part): array
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $part));
        $language = strtok(mb_ltrim($lines[0], '`'), " \t") ?: null;
        $snippetLanguage = SnippetLanguage::tryFromName((string) $language);
        $code = implode("\n", array_slice($lines, 1, -1));

        return [
            'type' => 'block',
            'language' => $language,
            'language_label' => $snippetLanguage?->label() ?? $language ?? SnippetLanguage::Text->label(),
            'code' => $code,
            'html' => $this->codeHighlighter->highlight($snippetLanguage ?? SnippetLanguage::Text, $code),
        ];
    }
}
