<?php

declare(strict_types=1);

use App\Actions\CreateQuoteNotifications;
use App\Actions\DeleteCommit;
use App\Actions\UpdateCommitStatus;
use App\Enums\CommitStatus;
use App\Enums\CommitType;
use App\Enums\Log;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('may quote a commit', function (): void {
    $user = User::factory()->create();
    $quoted = Commit::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Everyone)
        ->post(route('commit.store'), [
            'body' => 'This is how you ship.',
            'type' => 'quote',
            'quote' => ['commit_id' => $quoted->id],
        ]);

    $response->assertRedirectToRoute('log.show', Log::Everyone)
        ->assertSessionHasNoErrors();

    expect($user->commits()->sole())
        ->type->toBe(CommitType::Quote)
        ->body->toBe('This is how you ship.')
        ->quoted_commit_id->toBe($quoted->id);
});

it('may only quote approved commits that still exist', function (Closure $quote, string $error): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Everyone)
        ->post(route('commit.store'), [
            'body' => 'This is how you ship.',
            'type' => 'quote',
            ...$quote($user),
        ]);

    $response->assertRedirectToRoute('log.show', Log::Everyone)
        ->assertSessionHasErrors($error);

    expect(Commit::query()->where('type', CommitType::Quote)->exists())->toBeFalse();
})->with([
    'missing' => [fn (): array => [], 'quote.commit_id'],
    'empty' => [fn (): array => ['quote' => ['commit_id' => '']], 'quote.commit_id'],
    'not a uuid' => [fn (): array => ['quote' => ['commit_id' => 'abc']], 'quote.commit_id'],
    'unknown' => [fn (): array => ['quote' => ['commit_id' => Str::uuid7()->toString()]], 'quote.commit_id'],
    'pending' => [fn (): array => ['quote' => ['commit_id' => Commit::factory()->pending()->create()->id]], 'quote.commit_id'],
    'own pending' => [fn (User $user): array => ['quote' => ['commit_id' => Commit::factory()->for($user)->pending()->create()->id]], 'quote.commit_id'],
    'rejected' => [fn (): array => ['quote' => ['commit_id' => Commit::factory()->rejected()->create()->id]], 'quote.commit_id'],
    'deleted' => [fn (): array => ['quote' => ['commit_id' => tap(Commit::factory()->create())->delete()->id]], 'quote.commit_id'],
    'unknown field' => [fn (): array => ['quote' => ['commit_id' => Commit::factory()->create()->id, 'body' => 'Nope']], 'quote'],
]);

it('may quote replies and quotes', function (Closure $quoted): void {
    $user = User::factory()->create();
    $quoted = $quoted();

    $this->actingAs($user)
        ->fromRoute('log.show', Log::Everyone)
        ->post(route('commit.store'), [
            'body' => 'Quoting all the way down.',
            'type' => 'quote',
            'quote' => ['commit_id' => $quoted->id],
        ])
        ->assertSessionHasNoErrors();

    expect($user->commits()->sole()->quoted_commit_id)->toBe($quoted->id);
})->with([
    'reply' => [fn (): Commit => Commit::factory()->for(Commit::factory(), 'parent')->create()],
    'quote' => [fn (): Commit => Commit::factory()->quote()->create()],
]);

it('may attach images to a quote', function (): void {
    Storage::fake();

    $user = User::factory()->create();
    $quoted = Commit::factory()->create();

    $this->actingAs($user)
        ->fromRoute('log.show', Log::Everyone)
        ->post(route('commit.store'), [
            'body' => 'Same bug, different stack.',
            'type' => 'quote',
            'quote' => ['commit_id' => $quoted->id],
            'images' => [UploadedFile::fake()->image('trace.png', 40, 30)],
        ])
        ->assertSessionHasNoErrors();

    expect($user->commits()->sole())
        ->quoted_commit_id->toBe($quoted->id)
        ->images->toHaveCount(1);
});

it('shows a quote with the commit it quotes, and as the ancestor of its replies', function (): void {
    $quoted = Commit::factory()->release(['project' => 'pestphp/pest', 'version' => 'v5.0.0'])->create(['body' => 'Pest 5 is out!']);
    $quote = Commit::factory()->quote($quoted)->create(['body' => 'This is how you ship.']);
    $reply = Commit::factory()->for($quote, 'parent')->create();

    $this->actingAs(User::factory()->create());

    $this->get(route('commit.show', [$quote->user, $quote]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.type', 'quote')
            ->where('commit.body', 'This is how you ship.')
            ->where('commit.quoted_commit.id', $quoted->id)
            ->where('commit.quoted_commit.type', 'release')
            ->where('commit.quoted_commit.body', 'Pest 5 is out!')
            ->where('commit.quoted_commit.user.username', $quoted->user->username)
            ->missing('commit.quoted_commit.user.email')
            ->missing('commit.quoted_commit.release'));

    $this->get(route('commit.show', [$reply->user, $reply]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.quoted_commit', null)
            ->where('ancestors.0.type', 'quote')
            ->where('ancestors.0.quoted_commit.id', $quoted->id));
});

it('shows quotes of commits that are no longer available without them', function (Closure $hide): void {
    $quoted = Commit::factory()->create();
    $quote = Commit::factory()->quote($quoted)->create();

    $quotedCommitId = $hide($quoted);

    $this->actingAs(User::factory()->create())
        ->get(route('commit.show', [$quote->user, $quote]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.type', 'quote')
            ->where('commit.quoted_commit_id', $quotedCommitId)
            ->where('commit.quoted_commit', null))
        ->assertDontSee($quoted->body);
})->with([
    'rejected' => [function (Commit $quoted): string {
        resolve(UpdateCommitStatus::class)->handle($quoted, CommitStatus::Rejected);

        return $quoted->id;
    }],
    'removed for good' => [function (Commit $quoted): null {
        $quoted->forceDelete();

        return null;
    }],
]);

it('shows a deleted quoted commit as a tombstone, without its author or body', function (): void {
    $quoted = Commit::factory()->create(['body' => 'Words that were deleted']);
    $quote = Commit::factory()->quote($quoted)->create();

    resolve(DeleteCommit::class)->handle($quoted);

    $this->actingAs(User::factory()->create())
        ->get(route('commit.show', [$quote->user, $quote]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.type', 'quote')
            ->where('commit.quoted_commit.id', $quoted->id)
            ->where('commit.quoted_commit.deleted_at', fn (?string $deletedAt): bool => $deletedAt !== null)
            ->missing('commit.quoted_commit.body')
            ->missing('commit.quoted_commit.user'))
        ->assertDontSee('Words that were deleted');
});

it('still shows authors their own commit once it is no longer public', function (): void {
    $quoted = Commit::factory()->rejected()->create();
    $quote = Commit::factory()->quote($quoted)->create();

    $this->actingAs($quoted->user)
        ->get(route('commit.show', [$quote->user, $quote]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.quoted_commit.id', $quoted->id)
            ->where('commit.quoted_commit.status', 'rejected'));
});

it('includes the quoted commits of quotes in the log', function (Log $log): void {
    $user = User::factory()->create();
    $quoted = Commit::factory()->create(['created_at' => now()->subMinute()]);
    Commit::factory()->for($user)->quote($quoted)->create();

    $this->actingAs($user)
        ->get(route('log.show', $log))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.type', 'quote')
            ->where('commits.data.0.quoted_commit.id', $quoted->id)
            ->where('commits.data.0.quoted_commit.user.username', $quoted->user->username));
})->with(Log::cases());

it('loads the quoted commits of a log with a bounded number of queries', function (): void {
    $this->actingAs(User::factory()->create());
    Commit::factory()->quote()->create();

    DB::enableQueryLog();
    $this->get(route('log.show', Log::Everyone))->assertOk();
    $singleCount = count(DB::getQueryLog());

    Commit::factory()->count(9)->quote()->create();
    DB::flushQueryLog();

    $this->get(route('log.show', Log::Everyone))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 20)
            ->has('commits.data.0.quoted_commit.user'));

    expect(count(DB::getQueryLog()))->toBe($singleCount);
});

it('shows moderators the commit a pending quote quotes', function (): void {
    config(['app.admins' => ['admin']]);

    $quoted = Commit::factory()->create();
    Commit::factory()->pending()->quote($quoted)->create();

    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->get(route('moderation.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.type', 'quote')
            ->where('commits.data.0.quoted_commit.id', $quoted->id)
            ->where('commits.data.0.quoted_commit.user.username', $quoted->user->username));
});

it('shows moderators the commit a pending quote quotes, even once it is no longer public', function (): void {
    config(['app.admins' => ['admin']]);

    $quoted = Commit::factory()->create(['body' => 'Words under review']);
    Commit::factory()->pending()->quote($quoted)->create();
    resolve(UpdateCommitStatus::class)->handle($quoted, CommitStatus::Rejected);

    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->get(route('moderation.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.type', 'quote')
            ->where('commits.data.0.quoted_commit.body', 'Words under review')
            ->where('commits.data.0.quoted_commit.status', 'rejected')
            ->where('commits.data.0.quoted_commit.user.username', $quoted->user->username));
});

it('shows moderators a deleted quoted commit as a tombstone', function (): void {
    config(['app.admins' => ['admin']]);

    $quoted = Commit::factory()->create(['body' => 'Words that were deleted']);
    Commit::factory()->pending()->quote($quoted)->create();
    resolve(DeleteCommit::class)->handle($quoted);

    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->get(route('moderation.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.quoted_commit.id', $quoted->id)
            ->missing('commits.data.0.quoted_commit.body'))
        ->assertDontSee('Words that were deleted');
});

it('notifies the quoted author once the quote is approved', function (): void {
    config(['app.admins' => ['admin']]);

    $quoted = Commit::factory()->create();
    $quote = Commit::factory()->pending()->quote($quoted)->create();

    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->fromRoute('moderation.show')
        ->post(route('commit-approval.store', $quote))
        ->assertRedirectToRoute('moderation.show');

    expect($quoted->user->notifications()->sole())
        ->type->toBe(NotificationType::Quoted)
        ->key->toBe('quoted:'.$quote->id);

    $this->actingAs($quoted->user)
        ->get(route('notification.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('notifications.data', 1)
            ->where('notifications.data.0.type', 'quoted')
            ->where('notifications.data.0.actor.id', $quote->user_id)
            ->where('notifications.data.0.commit.id', $quote->id));
});

it('notifies a quoted author who is also mentioned in the quote once, as quoted', function (): void {
    config(['app.admins' => ['admin']]);

    $quoted = Commit::factory()->for(User::factory()->state(['username' => 'taylorotwell']))->create();
    $mentioned = User::factory()->create(['username' => 'nunomaduro']);
    $quote = Commit::factory()->pending()->quote($quoted)->create(['body' => '@taylorotwell @nunomaduro have a look']);

    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->fromRoute('moderation.show')
        ->post(route('commit-approval.store', $quote))
        ->assertRedirectToRoute('moderation.show');

    expect($quoted->user->notifications()->sole())
        ->type->toBe(NotificationType::Quoted)
        ->commit_id->toBe($quote->id)
        ->and($mentioned->notifications()->sole())
        ->type->toBe(NotificationType::Mentioned)
        ->commit_id->toBe($quote->id);
});

it('removes the quoted notifications once the quoted commit is deleted', function (): void {
    $quoted = Commit::factory()->create();
    $quote = Commit::factory()->quote($quoted)->create();
    $otherQuote = Commit::factory()->quote()->create();
    resolve(CreateQuoteNotifications::class)->handle($quote, $otherQuote);

    $this->actingAs($quoted->user)
        ->fromRoute('log.show', Log::Everyone)
        ->delete(route('commit.destroy', $quoted))
        ->assertRedirectToRoute('log.show', Log::Everyone);

    expect($quoted->user->notifications()->exists())->toBeFalse()
        ->and($otherQuote->quotedCommit->user->notifications()->sole()->commit_id)->toBe($otherQuote->id);

    $this->assertNotSoftDeleted($quote);
});

it('never shows guests the commit a quote quotes', function (): void {
    $quoted = Commit::factory()->for(User::factory()->state(['username' => 'taylorotwell']))->create(['body' => 'A quoted commit only signed-in users may read']);
    $quote = Commit::factory()->for(User::factory()->state(['username' => 'octocat', 'name' => 'octocat']))->quote($quoted)->create(['body' => 'Worth a read.']);

    $this->get(route('commit.show', [$quote->user, $quote]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome')->missing('commit'))
        ->assertSee('<meta property="og:title" content="@octocat on /dev/null">', false)
        ->assertSee('<meta property="og:description" content="Worth a read.">', false)
        ->assertDontSee('taylorotwell')
        ->assertDontSee('A quoted commit only signed-in users may read');

    $this->get(route('user.show', $quote->user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome')->missing('commits'))
        ->assertDontSee('taylorotwell')
        ->assertDontSee('A quoted commit only signed-in users may read');

    $this->assertGuest();
});

it('never shows guests the quotes of a commit', function (): void {
    $quoted = Commit::factory()->for(User::factory()->state(['username' => 'taylorotwell']))->create(['body' => 'Shipped it.']);
    Commit::factory()->for(User::factory()->state(['username' => 'octocat']))->quote($quoted)->create(['body' => 'A quote only signed-in users may read']);

    $this->get(route('commit.show', [$quoted->user, $quoted]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome')->missing('commit'))
        ->assertDontSee('octocat')
        ->assertDontSee('A quote only signed-in users may read');
});
