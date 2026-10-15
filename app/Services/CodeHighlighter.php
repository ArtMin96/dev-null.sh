<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SnippetLanguage;
use App\Models\Snippet;
use Composer\InstalledVersions;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Tempest\Highlight\Highlighter;

/**
 * Scoped, so that a page of commits shares one highlighter rather than registering its languages for each of them.
 */
#[Scoped]
final readonly class CodeHighlighter
{
    /**
     * Bumped whenever the HTML rendered here changes, so that snippets cached before are highlighted again.
     */
    private const int CACHE_VERSION = 1;

    public function __construct(private Highlighter $highlighter)
    {
        //
    }

    /**
     * Keeps Tempest's output only when it is the escaped code inside `hl-*` spans, and otherwise renders the escaped
     * plain code.
     */
    public function highlight(SnippetLanguage $language, string $code): string
    {
        if ($language === SnippetLanguage::Text) {
            return e($code);
        }

        $html = $this->highlighter->parse($code, $language->value);

        $isOnlyStyled = preg_match('/<(?!span class="hl-[a-z-]+">|\/span>)/', $html) === 0
            && html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5) === $code;

        return $isOnlyStyled ? $html : e($code);
    }

    /**
     * The same code in the same language is only ever highlighted once, however many snippets share it and however
     * often they are shown.
     */
    public function highlightSnippet(Snippet $snippet): string
    {
        return Cache::memo()->remember(
            $this->cacheKey($snippet),
            now()->addWeek(),
            fn (): string => $this->highlight($snippet->language, $snippet->code),
        );
    }

    /**
     * Reads the highlighted code of many snippets from the cache in one go, so highlighting each of them afterwards is
     * served from memory.
     *
     * @param  Collection<int, Snippet>  $snippets
     */
    public function prefetch(Collection $snippets): void
    {
        Cache::memo()->many($snippets->map($this->cacheKey(...))->all());
    }

    private function cacheKey(Snippet $snippet): string
    {
        $version = self::CACHE_VERSION.':'.InstalledVersions::getVersion('tempest/highlight');

        return 'snippet-html:'.hash('sha256', "{$version}:{$snippet->language->value}:{$snippet->code}");
    }
}
