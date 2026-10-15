<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('labels a code block with the language its fence names, by name or by alias', function (string $fence, string $label): void {
    $commit = Commit::factory()->create(['body' => "```{$fence}\nx = 1\n```"]);

    $this->actingAs(User::factory()->create())
        ->get(route('commit.show', [$commit->user, $commit]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.body_segments.0.language_label', $label));
})->with([
    ['javascript', 'JavaScript'],
    ['js', 'JavaScript'],
    ['JS', 'JavaScript'],
    ['ts', 'TypeScript'],
    ['yml', 'YAML'],
    ['sh', 'Bash'],
    ['console', 'Terminal'],
    ['php', 'PHP'],
]);

it('labels a code block in an unknown language by its name, and one without a language as plain text, escaped', function (string $fence, string $label): void {
    $commit = Commit::factory()->create(['body' => "```{$fence}\n<script>alert(1)</script>\n```"]);

    $this->actingAs(User::factory()->create())
        ->get(route('commit.show', [$commit->user, $commit]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.body_segments.0.language_label', $label)
            ->where('commit.body_segments.0.html', '&lt;script&gt;alert(1)&lt;/script&gt;'));
})->with([
    'unknown language' => ['brainfuck', 'brainfuck'],
    'no language' => ['', 'Plain text'],
    'plain text' => ['txt', 'Plain text'],
]);

it('sends the raw code of a code block to copy, beside its escaped html', function (string $body, string $code): void {
    $commit = Commit::factory()->create(['body' => $body]);

    $this->actingAs(User::factory()->create())
        ->get(route('commit.show', [$commit->user, $commit]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.body_segments.0.code', $code)
            ->where('commit.body_segments.0.html', fn (string $html): bool => ! str_contains($html, '<b>') && str_contains($html, '&lt;b&gt;')));
})->with([
    'highlighted' => ["```php\n\$tag = '<b>' . \"&amp;\";\n```", "\$tag = '<b>' . \"&amp;\";"],
    'markup, quotes and ampersands in strings' => ["```js\nconst a = \"<b>&amp;</b>\" + '<script>alert(1)</script>';\n```", "const a = \"<b>&amp;</b>\" + '<script>alert(1)</script>';"],
    'written with crlf line breaks' => ["```js\r\nconst a = '<b>';\r\n\r\n  return a;\r\n```", "const a = '<b>';\n\n  return a;"],
]);
