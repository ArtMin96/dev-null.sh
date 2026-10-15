<?php

declare(strict_types=1);

use App\Actions\HighlightCode;

it('keeps text without code as a single text segment', function (): void {
    $action = resolve(HighlightCode::class);

    expect($action->handle('just shipped it'))->toBe([
        ['type' => 'text', 'text' => 'just shipped it'],
    ]);
});

it('splits inline code from the text around it', function (): void {
    $action = resolve(HighlightCode::class);

    expect($action->handle('run `composer install` now'))->toBe([
        ['type' => 'text', 'text' => 'run '],
        ['type' => 'code', 'code' => 'composer install'],
        ['type' => 'text', 'text' => ' now'],
    ]);
});

it('highlights a code block in its language', function (): void {
    $action = resolve(HighlightCode::class);

    expect($action->handle("```php\n\$name = 'devnull';\n```"))->toBe([
        [
            'type' => 'block',
            'language' => 'php',
            'html' => '<span class="hl-variable">$name</span> = <span class="hl-value">\'devnull\'</span>;',
        ],
    ]);
});

it('drops the line breaks around a code block', function (string $lineBreak): void {
    $action = resolve(HighlightCode::class);

    $segments = $action->handle(implode($lineBreak, ['before', '```', 'x', 'y', '```', 'after', '', 'end']));

    expect($segments)->toBe([
        ['type' => 'text', 'text' => 'before'],
        ['type' => 'block', 'language' => null, 'html' => "x\ny"],
        ['type' => 'text', 'text' => "after{$lineBreak}{$lineBreak}end"],
    ]);
})->with([
    'lf' => "\n",
    'crlf' => "\r\n",
]);

it('escapes code in an unknown language or without one', function (?string $language): void {
    $action = resolve(HighlightCode::class);

    $segments = $action->handle("```{$language}\n<script>alert(1)</script>\n```");

    expect($segments)->toBe([
        ['type' => 'block', 'language' => $language, 'html' => '&lt;script&gt;alert(1)&lt;/script&gt;'],
    ]);
})->with([
    'unknown language' => 'brainfuck',
    'no language' => null,
]);

it('escapes html inside highlighted code', function (): void {
    $action = resolve(HighlightCode::class);

    $html = $action->handle("```html\n<img src=x onerror=alert(1)>\n```")[0]['html'];

    expect($html)->not->toContain('<img')->toContain('&lt;');
});

it('highlights a language written in any case and keeps the label as written', function (): void {
    $action = resolve(HighlightCode::class);

    expect($action->handle("```PHP\n\$name;\n```"))->toBe([
        ['type' => 'block', 'language' => 'PHP', 'html' => '<span class="hl-variable">$name</span>;'],
    ]);
});

it('takes the language from the first word of the fence', function (): void {
    $action = resolve(HighlightCode::class);

    expect($action->handle("```php title=\"app.php\"\n\$name;\n```"))->toBe([
        ['type' => 'block', 'language' => 'php', 'html' => '<span class="hl-variable">$name</span>;'],
    ]);
});

it('keeps an empty code block', function (): void {
    $action = resolve(HighlightCode::class);

    expect($action->handle("```\n```"))->toBe([
        ['type' => 'block', 'language' => null, 'html' => ''],
    ]);
});

it('accepts fences of more than three backticks', function (): void {
    $action = resolve(HighlightCode::class);

    expect($action->handle("feature:\n````php\n\$login;\n````"))->toBe([
        ['type' => 'text', 'text' => 'feature:'],
        ['type' => 'block', 'language' => 'php', 'html' => '<span class="hl-variable">$login</span>;'],
    ])->and($action->handle("```\n// test\n````"))->toBe([
        ['type' => 'block', 'language' => null, 'html' => '// test'],
    ]);
});
