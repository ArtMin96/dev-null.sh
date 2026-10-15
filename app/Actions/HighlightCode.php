<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\CodeSpans;
use Illuminate\Container\Attributes\Scoped;
use Tempest\Highlight\Highlighter;

/**
 * Scoped, so that a page of commits shares one highlighter rather than registering its languages for each commit.
 */
#[Scoped]
final readonly class HighlightCode
{
    public function __construct(
        private CodeSpans $codeSpans,
        private Highlighter $highlighter,
    ) {
        //
    }

    /**
     * Splits the text into prose, inline code and highlighted code blocks. The highlighter escapes the code, so a
     * block's HTML is safe to render as is. The line breaks around a block are dropped, since it is a block already.
     *
     * @return list<array{type: 'text', text: string}|array{type: 'code', code: string}|array{type: 'block', language: string|null, html: string}>
     */
    public function handle(string $text): array
    {
        $parts = $this->codeSpans->split($text);
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
     * @return array{type: 'block', language: string|null, html: string}
     */
    private function block(string $part): array
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $part));
        $language = strtok(mb_ltrim($lines[0], '`'), " \t") ?: null;

        return [
            'type' => 'block',
            'language' => $language,
            'html' => $this->highlighter->parse(implode("\n", array_slice($lines, 1, -1)), $language === null ? null : mb_strtolower($language)),
        ];
    }
}
