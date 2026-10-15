<?php

declare(strict_types=1);

use App\Enums\CommitType;
use App\Enums\Log;
use App\Enums\SnippetLanguage;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Commit;
use App\Models\Snippet;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

it('may share a code snippet, keeping the code exactly as typed', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Finally, a use for arrow functions.',
            'type' => 'snippet',
            'snippet' => [
                'language' => 'php',
                'filename' => 'app/Actions/Double.php',
                'code' => "    \$double = fn (int \$x): int => \$x * 2;  \n\n\treturn \$double(21);\n\n",
            ],
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertSessionHasNoErrors();

    $commit = $user->commits()->sole();

    expect($commit->type)->toBe(CommitType::Snippet)
        ->and($commit->body)->toBe('Finally, a use for arrow functions.')
        ->and($commit->snippet)
        ->toBeInstanceOf(Snippet::class)
        ->language->toBe(SnippetLanguage::Php)
        ->filename->toBe('app/Actions/Double.php')
        ->code->toBe("    \$double = fn (int \$x): int => \$x * 2;  \n\n\treturn \$double(21);");
});

it('validates the snippet', function (array $snippet, string $error): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Look at this.',
            'type' => 'snippet',
            'snippet' => [...['language' => 'php', 'code' => 'echo 1;'], ...$snippet],
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertSessionHasErrors($error);

    expect(Commit::query()->count())->toBe(0)
        ->and(Snippet::query()->count())->toBe(0);
})->with([
    'missing language' => [['language' => ''], 'snippet.language'],
    'unsupported language' => [['language' => 'cobol'], 'snippet.language'],
    'missing code' => [['code' => ''], 'snippet.code'],
    'only whitespace' => [['code' => " \n\t \n"], 'snippet.code'],
    'only unicode spaces' => [['code' => "\u{00A0}\n\u{3000}\n"], 'snippet.code'],
    'code that is not UTF-8' => [['code' => "echo \"\xB1\";"], 'snippet.code'],
    'too long' => [['code' => str_repeat('a', 4001)], 'snippet.code'],
    'too many lines' => [['code' => implode("\n", range(1, 201))], 'snippet.code'],
    'long filename' => [['filename' => str_repeat('a', 98).'.php'], 'snippet.filename'],
    'filename with spaces' => [['filename' => 'my file.php'], 'snippet.filename'],
    'filename with markup' => [['filename' => '<script>.js'], 'snippet.filename'],
    'unknown field' => [['html' => '<b>bold</b>'], 'snippet'],
]);

it('requires the snippet of a snippet commit', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), ['body' => 'Look at this.', 'type' => 'snippet']);

    $response->assertSessionHasErrors(['snippet', 'snippet.language', 'snippet.code']);

    expect(Commit::query()->count())->toBe(0);
});

it('accepts snippets right at the limits', function (string $code): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Look at this.',
            'type' => 'snippet',
            'snippet' => ['language' => 'text', 'filename' => 'resources/js/app.d.ts', 'code' => $code],
        ])
        ->assertSessionHasNoErrors();

    expect($user->commits()->sole()->snippet?->code)->toBe(mb_rtrim($code, "\n"));
})->with([
    '4000 characters' => [str_repeat('é', 4000)],
    '200 lines' => [implode("\n", range(1, 200))],
    '200 lines and trailing newlines' => [implode("\n", range(1, 200))."\n\n\n"],
]);

it('counts a line break once, as the composer does, even when the browser sends it as two characters', function (): void {
    $user = User::factory()->create();

    $code = implode("\r\n", [...range(1, 199), str_repeat('a', 3312)])."\r\n";

    $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Look at this.',
            'type' => 'snippet',
            'snippet' => ['language' => 'text', 'code' => $code],
        ])
        ->assertSessionHasNoErrors();

    expect($user->commits()->sole()->snippet?->code)->toBe(implode("\n", [...range(1, 199), str_repeat('a', 3312)]));
});

it('shows a snippet with its highlighted code', function (): void {
    $snippet = Commit::factory()->snippet([
        'language' => SnippetLanguage::Php,
        'filename' => 'hello.php',
        'code' => "<?php\n\necho '<b>Hello</b>';",
    ])->create(['body' => 'My first PHP script.']);
    $reply = Commit::factory()->for($snippet, 'parent')->create();

    $this->actingAs(User::factory()->create());

    $this->get(route('commit.show', [$snippet->user, $snippet]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.type', 'snippet')
            ->where('commit.snippet', [
                'language' => 'php',
                'language_label' => 'PHP',
                'filename' => 'hello.php',
                'code' => "<?php\n\necho '<b>Hello</b>';",
                'highlighted_html' => "<span class=\"hl-keyword\">&lt;?php</span>\n\n<span class=\"hl-keyword\">echo</span> <span class=\"hl-value\">'&lt;b&gt;Hello&lt;/b&gt;'</span>;",
            ])
            ->where('replies.data.0.type', 'text')
            ->where('replies.data.0.snippet', null));

    $this->get(route('commit.show', [$reply->user, $reply]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('ancestors.0.type', 'snippet')
            ->where('ancestors.0.snippet.filename', 'hello.php'));
});

it('includes the snippets of commits in every log', function (Log $log): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->snippet(['language' => SnippetLanguage::Text, 'code' => 'a < b'])->create(['created_at' => now()->subMinute()]);
    Commit::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('log.show', $log))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.snippet', null)
            ->where('commits.data.1.type', 'snippet')
            ->where('commits.data.1.snippet', [
                'language' => 'text',
                'language_label' => 'Plain text',
                'filename' => null,
                'code' => 'a < b',
                'highlighted_html' => 'a &lt; b',
            ]));
})->with(Log::cases());

it('shows pending snippets to moderators', function (): void {
    config(['app.admins' => ['admin']]);
    $admin = User::factory()->create(['username' => 'admin']);

    Commit::factory()->pending()->snippet(['language' => SnippetLanguage::Json, 'code' => '{"a": 1}'])->create();

    $this->actingAs($admin)
        ->get(route('moderation.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.type', 'snippet')
            ->where('commits.data.0.snippet.language_label', 'JSON')
            ->where('commits.data.0.snippet.highlighted_html', fn (string $html): bool => str_contains($html, 'hl-')));
});

it('reads the highlighted code of a whole page from the cache at once', function (): void {
    config(['cache.default' => 'database']);

    $user = User::factory()->create();

    foreach (['echo 1;', 'echo 2;', 'echo 3;'] as $code) {
        Commit::factory()->for($user)->snippet(['code' => $code])->create();
    }

    $this->actingAs($user)->get(route('log.show', Log::Everyone))->assertOk();

    app()->forgetScopedInstances();
    DB::enableQueryLog();

    $this->get(route('log.show', Log::Everyone))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('commits.data', 3));

    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $query): bool => str_contains($query, '"cache"')))
        ->toHaveCount(1);
});

it('gives the composer the languages to pick from, once', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('log.show', Log::Following))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('snippetLanguages.0', ['value' => 'apache', 'label' => 'Apache'])
            ->where('snippetLanguages', fn (Collection $languages): bool => $languages->contains(['value' => 'php', 'label' => 'PHP'])
                && $languages->last() === ['value' => 'text', 'label' => 'Plain text']));

    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
        'X-Inertia-Except-Once-Props' => 'snippetLanguages',
    ])->get(route('log.show', Log::Everyone))
        ->assertOk()
        ->assertJsonPath('component', 'log/show')
        ->assertJsonMissingPath('props.snippetLanguages');
});
