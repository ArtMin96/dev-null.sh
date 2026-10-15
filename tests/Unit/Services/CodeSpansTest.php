<?php

declare(strict_types=1);

use App\Services\CodeSpans;

it('splits text into alternating prose and code', function (string $text, array $parts): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->split($text))->toBe($parts);
})->with([
    'no code' => ['plain text', ['plain text']],
    'inline code' => ['run `composer install` now', ['run ', '`composer install`', ' now']],
    'code block' => ["look:\n```php\necho 1;\n```\nnice", ["look:\n", "```php\necho 1;\n```", "\nnice"]],
    'code block with crlf' => ["```\r\necho 1;\r\n```", ['', "```\r\necho 1;\r\n```", '']],
    'backticks inside a code block' => ["```js\nconst a = `b`;\n```", ['', "```js\nconst a = `b`;\n```", '']],
]);

it('leaves code that is never closed as prose', function (string $text): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->split($text))->toBe([$text]);
})->with([
    'unclosed inline code' => 'a lone ` backtick',
    'inline code across lines' => "`starts here\nends here`",
    'unclosed code block' => "```php\necho 1;",
    'fence in the middle of a line' => "see ```php\necho 1;\n```",
    'empty inline code' => 'empty `` code',
]);

it('maps the prose only', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->mapProse("hi `hi`\n```\nhi\n```", mb_strtoupper(...)))
        ->toBe("HI `hi`\n```\nhi\n```");
});

it('removes the code', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->withoutCode('see `a.com`b.com'))->toBe("see \nb.com");
});

it('keeps text without code as a single text segment', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->highlight('just shipped it'))->toBe([
        ['type' => 'text', 'text' => 'just shipped it'],
    ]);
});

it('splits inline code from the text around it', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->highlight('run `composer install` now'))->toBe([
        ['type' => 'text', 'text' => 'run '],
        ['type' => 'code', 'code' => 'composer install'],
        ['type' => 'text', 'text' => ' now'],
    ]);
});

it('highlights a code block in its language', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->highlight("```php\n\$name = 'devnull';\n```"))->toBe([
        [
            'type' => 'block',
            'language' => 'php',
            'language_label' => 'PHP',
            'code' => "\$name = 'devnull';",
            'html' => '<span class="hl-variable">$name</span> = <span class="hl-value">\'devnull\'</span>;',
        ],
    ]);
});

it('drops the line breaks around a code block', function (string $lineBreak): void {
    $codeSpans = resolve(CodeSpans::class);

    $segments = $codeSpans->highlight(implode($lineBreak, ['before', '```', 'x', 'y', '```', 'after', '', 'end']));

    expect($segments)->toBe([
        ['type' => 'text', 'text' => 'before'],
        ['type' => 'block', 'language' => null, 'language_label' => 'Plain text', 'code' => "x\ny", 'html' => "x\ny"],
        ['type' => 'text', 'text' => "after{$lineBreak}{$lineBreak}end"],
    ]);
})->with([
    'lf' => "\n",
    'crlf' => "\r\n",
]);

it('escapes code in an unknown language or without one', function (?string $language): void {
    $codeSpans = resolve(CodeSpans::class);

    $segments = $codeSpans->highlight("```{$language}\n<script>alert(1)</script>\n```");

    expect($segments)->toBe([
        ['type' => 'block', 'language' => $language, 'language_label' => $language ?? 'Plain text', 'code' => '<script>alert(1)</script>', 'html' => '&lt;script&gt;alert(1)&lt;/script&gt;'],
    ]);
})->with([
    'unknown language' => 'brainfuck',
    'no language' => null,
]);

it('escapes html inside highlighted code', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    $html = $codeSpans->highlight("```html\n<img src=x onerror=alert(1)>\n```")[0]['html'];

    expect($html)->not->toContain('<img')->toContain('&lt;');
});

it('highlights a language written in any case and keeps the label as written', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->highlight("```PHP\n\$name;\n```"))->toBe([
        ['type' => 'block', 'language' => 'PHP', 'language_label' => 'PHP', 'code' => '$name;', 'html' => '<span class="hl-variable">$name</span>;'],
    ]);
});

it('takes the language from the first word of the fence', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->highlight("```php title=\"app.php\"\n\$name;\n```"))->toBe([
        ['type' => 'block', 'language' => 'php', 'language_label' => 'PHP', 'code' => '$name;', 'html' => '<span class="hl-variable">$name</span>;'],
    ]);
});

it('highlights a code block named by an alias of its language', function (string $alias, string $name, string $code): void {
    $codeSpans = resolve(CodeSpans::class);

    $html = $codeSpans->highlight("```{$alias}\n{$code}\n```")[0]['html'];

    expect($html)->toBe($codeSpans->highlight("```{$name}\n{$code}\n```")[0]['html'])
        ->and($html === e($code))->toBe($name === 'text');
})->with([
    ['apacheconf', 'apache', 'RewriteEngine On'],
    ['htaccess', 'apache', 'RewriteEngine On'],
    ['sh', 'bash', 'echo "hi"'],
    ['shell', 'bash', 'echo "hi"'],
    ['docker', 'dockerfile', 'FROM php:8.5'],
    ['env', 'dotenv', 'APP_NAME=devnull'],
    ['gql', 'graphql', 'query { user { id } }'],
    ['https', 'http', 'GET /log HTTP/1.1'],
    ['js', 'javascript', 'const a = 1;'],
    ['node', 'javascript', 'const a = 1;'],
    ['md', 'markdown', '# devnull'],
    ['nginxconf', 'nginx', 'server { listen 80; }'],
    ['py', 'python', 'def a(): pass'],
    ['console', 'terminal', '$ composer install'],
    ['term', 'terminal', '$ composer install'],
    ['tf', 'terraform', 'resource "a" "b" {}'],
    ['hcl', 'terraform', 'resource "a" "b" {}'],
    ['terragrunt', 'terraform', 'resource "a" "b" {}'],
    ['ts', 'typescript', 'const a: number = 1;'],
    ['yml', 'yaml', 'name: devnull'],
    ['txt', 'text', '<b>plain</b>'],
]);

it('keeps an empty code block', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->highlight("```\n```"))->toBe([
        ['type' => 'block', 'language' => null, 'language_label' => 'Plain text', 'code' => '', 'html' => ''],
    ]);
});

it('accepts fences of more than three backticks', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->highlight("feature:\n````php\n\$login;\n````"))->toBe([
        ['type' => 'text', 'text' => 'feature:'],
        ['type' => 'block', 'language' => 'php', 'language_label' => 'PHP', 'code' => '$login;', 'html' => '<span class="hl-variable">$login</span>;'],
    ])->and($codeSpans->highlight("```\n// test\n````"))->toBe([
        ['type' => 'block', 'language' => null, 'language_label' => 'Plain text', 'code' => '// test', 'html' => '// test'],
    ]);
});
