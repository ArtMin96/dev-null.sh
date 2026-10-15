<?php

declare(strict_types=1);

use App\Enums\SnippetLanguage;
use App\Models\Snippet;
use App\Services\CodeHighlighter;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Support\Facades\Event;
use Tempest\Highlight\Escape;
use Tempest\Highlight\Highlighter;
use Tempest\Highlight\Theme;
use Tempest\Highlight\Tokens\TokenType;

/**
 * Highlighted code is rendered as HTML, so it may only ever be the code exactly as written, within styling spans.
 */
function expectOnlyStyledCode(string $html, string $code): void
{
    preg_match_all('/<[^>]*>/', $html, $tags);

    foreach ($tags[0] as $tag) {
        expect($tag)->toMatch('/^(<span class="hl-[a-z-]+">|<\/span>)$/');
    }

    expect(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5))->toBe($code);
}

it('highlights code in its language', function (): void {
    $snippet = Snippet::factory()->create([
        'language' => SnippetLanguage::Php,
        'code' => "<?php\n\necho 'Hello, devnull!';",
    ]);

    expect(resolve(CodeHighlighter::class)->highlightSnippet($snippet))
        ->toBe("<span class=\"hl-keyword\">&lt;?php</span>\n\n<span class=\"hl-keyword\">echo</span> <span class=\"hl-value\">'Hello, devnull!'</span>;");
});

it('shows plain text as it is', function (): void {
    $snippet = Snippet::factory()->create([
        'language' => SnippetLanguage::Text,
        'code' => "<b>if (\"true\") { return 'yes'; }</b>\n  & more",
    ]);

    expect(resolve(CodeHighlighter::class)->highlightSnippet($snippet))
        ->toBe("&lt;b&gt;if (&quot;true&quot;) { return &#039;yes&#039;; }&lt;/b&gt;\n  &amp; more");
});

it('escapes markup in the code, whatever its language', function (SnippetLanguage $language, string $code): void {
    $snippet = Snippet::factory()->create(['language' => $language, 'code' => $code]);

    expectOnlyStyledCode(resolve(CodeHighlighter::class)->highlightSnippet($snippet), $code);
})->with(SnippetLanguage::cases())->with([
    'closing span and script' => ['</span><script>alert(1)</script>'],
    'image with handler' => ['<img src=x onerror=alert(1)>'],
    'nested languages' => ["<?php echo \"<script>alert('x')</script>\"; ?>\n<style>a{color:red}</style><script>let a = '<img src=x onerror=alert(1)>'</script>\n{{ \$x }} {!! \$y !!} {% if x %}<b onclick=\"alert(1)\">x</b>{% endif %}"],
]);

it('keeps code as typed where languages nest', function (SnippetLanguage $language, string $code): void {
    $snippet = Snippet::factory()->create(['language' => $language, 'code' => $code]);

    expectOnlyStyledCode(resolve(CodeHighlighter::class)->highlightSnippet($snippet), $code);
})->with([
    'script in html' => [SnippetLanguage::Html, "<script>\nlet a = '<img src=x onerror=alert(1)>';\n</script>"],
    'doc comment in php' => [SnippetLanguage::Php, "<?php\n\n/** <script>alert(1)</script> & \"quoted\" */\nfunction a(int \$b): void {}"],
    'heredoc in php' => [SnippetLanguage::Php, "<?php\n\n\$a = <<<TXT\n<img src=x onerror=alert(1)>\nTXT;"],
    'script in a heredoc in php' => [SnippetLanguage::Php, "<?php\n\$x = <<<HTML\n<script>let a = '<b>&amp;</b>';</script>\nHTML;"],
    'doc comment in javascript' => [SnippetLanguage::JavaScript, "/** @param {string} b <script>alert(1)</script> */\nfunction a(b) {}"],
    'markup in blade' => [SnippetLanguage::Blade, "@if(\$x)\n<div onclick=\"alert(1)\">{{ \$y }} {!! '<b>&amp;</b>' !!}</div>\n@endif"],
    'twig tag' => [SnippetLanguage::Twig, "{% if x %}{{ '<script>alert(1)</script>' }}{% endif %}"],
]);

it('keeps quotes, ampersands and entities as typed', function (SnippetLanguage $language): void {
    $code = "\$a = \"&amp;\" . '&lt;b&gt;' . \"it's <b>&</b>\"; // &#039; &quot;";

    $snippet = Snippet::factory()->create(['language' => $language, 'code' => $code]);

    expectOnlyStyledCode(resolve(CodeHighlighter::class)->highlightSnippet($snippet), $code);
})->with(SnippetLanguage::cases());

it('shows the code as plain text when the highlighted html is anything but styling spans', function (): void {
    $highlighter = new CodeHighlighter(new Highlighter(new class implements Theme
    {
        public function before(TokenType $tokenType): string
        {
            return '<em>';
        }

        public function after(TokenType $tokenType): string
        {
            return '</em>';
        }

        public function escape(string $content): string
        {
            return Escape::html($content);
        }
    }));

    expect($highlighter->highlight(SnippetLanguage::Php, "echo '<b>';"))->toBe('echo &#039;&lt;b&gt;&#039;;');
});

it('highlights the same code in the same language only once, across requests', function (): void {
    [$first, $sameCode, $otherLanguage] = Snippet::factory()->count(3)->sequence(
        ['language' => SnippetLanguage::Php, 'filename' => 'first.php'],
        ['language' => SnippetLanguage::Php, 'filename' => 'second.php'],
        ['language' => SnippetLanguage::Text, 'filename' => 'first.php'],
    )->create(['code' => "<?php\n\necho 'Hello, devnull!';"]);

    Event::fake([KeyWritten::class]);

    $highlight = resolve(CodeHighlighter::class);
    $highlighted = $highlight->highlightSnippet($first);

    app()->forgetScopedInstances();

    expect($highlight->highlightSnippet($sameCode))->toBe($highlighted)
        ->and($highlight->highlightSnippet($otherLanguage))->not->toBe($highlighted);

    Event::assertDispatchedTimes(KeyWritten::class, 2);
});

it('leaves the filename out of the highlighted code', function (): void {
    $snippet = Snippet::factory()->create([
        'language' => SnippetLanguage::Php,
        'filename' => 'app/Models/Secret.php',
        'code' => 'echo 1;',
    ]);

    expect(resolve(CodeHighlighter::class)->highlightSnippet($snippet))->not->toContain('Secret');
});
