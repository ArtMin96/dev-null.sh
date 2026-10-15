<?php

declare(strict_types=1);

use App\Enums\CommitType;
use App\Enums\Log;
use App\Enums\PollDuration;
use App\Models\Commit;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('may create a poll', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Tabs or spaces?',
            'type' => 'poll',
            'poll' => ['options' => ['Tabs', 'Spaces', 'Both, in one file'], 'duration' => '4320'],
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertSessionHasNoErrors();

    $commit = $user->commits()->sole();

    expect($commit->type)->toBe(CommitType::Poll)
        ->and($commit->poll?->duration)->toBe(PollDuration::ThreeDays)
        ->and($commit->poll?->closes_at)->toBeNull()
        ->and($commit->poll?->options->pluck('label')->all())->toBe(['Tabs', 'Spaces', 'Both, in one file']);
});

it('validates the poll', function (array $poll, string $error): void {
    $response = $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Tabs or spaces?',
            'type' => 'poll',
            'poll' => [...['options' => ['Tabs', 'Spaces'], 'duration' => '1440'], ...$poll],
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertSessionHasErrors($error);

    expect(Commit::query()->count())->toBe(0)
        ->and(Poll::query()->count())->toBe(0);
})->with([
    'one option' => [['options' => ['Tabs']], 'poll.options'],
    'five options' => [['options' => ['A', 'B', 'C', 'D', 'E']], 'poll.options'],
    'options not a list' => [['options' => ['a' => 'Tabs', 'b' => 'Spaces']], 'poll.options'],
    'empty option' => [['options' => ['Tabs', '']], 'poll.options.1'],
    'blank option' => [['options' => ['Tabs', '   ']], 'poll.options.1'],
    'option not a string' => [['options' => ['Tabs', ['Spaces']]], 'poll.options.1'],
    'long option' => [['options' => ['Tabs', str_repeat('a', 51)]], 'poll.options.1'],
    'option that is not UTF-8' => [['options' => ['Tabs', "Spaces \xB1"]], 'poll.options.1'],
    'duplicate option' => [['options' => ['Tabs', 'Spaces', 'Tabs']], 'poll.options.2'],
    'duplicate option ignoring case and spaces' => [['options' => ['Tabs', ' tabs ']], 'poll.options.1'],
    'missing duration' => [['duration' => ''], 'poll.duration'],
    'unknown duration' => [['duration' => '2'], 'poll.duration'],
    'duration in days' => [['duration' => '7'], 'poll.duration'],
    'duration not a number' => [['duration' => 'week'], 'poll.duration'],
    'unknown field' => [['multiple' => true], 'poll'],
]);

it('requires the poll of a poll commit', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), ['body' => 'Tabs or spaces?', 'type' => 'poll']);

    $response->assertSessionHasErrors(['poll', 'poll.options', 'poll.duration']);

    expect(Commit::query()->count())->toBe(0);
});

it('accepts options of up to 50 characters and every duration', function (PollDuration $duration): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Pick one.',
            'type' => 'poll',
            'poll' => ['options' => [str_repeat('a', 50), 'B', 'C', 'D'], 'duration' => (string) $duration->value],
        ])
        ->assertSessionHasNoErrors();

    $poll = $user->commits()->sole()->poll;

    expect($poll?->duration)->toBe($duration)
        ->and($poll?->options)->toHaveCount(4);
})->with(PollDuration::cases());

it('turns emoji shortcodes in the options into emoji, like in the body', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Ship it on a Friday? :eyes',
            'type' => 'poll',
            'poll' => ['options' => ['Ship it :rocket', 'Wait for Monday :sad', 'Keep `:fire` in code'], 'duration' => '1440'],
        ])
        ->assertSessionHasNoErrors();

    $commit = $user->commits()->sole();

    expect($commit->body)->toBe('Ship it on a Friday? 👀')
        ->and($commit->poll?->options->pluck('label')->all())->toBe(['Ship it 🚀', 'Wait for Monday 😢', 'Keep `:fire` in code']);
});

it('does not let a poll have images', function (): void {
    Storage::fake();

    $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Which desk setup wins?',
            'type' => 'poll',
            'poll' => ['options' => ['Left', 'Right'], 'duration' => '1440'],
            'images' => [UploadedFile::fake()->image('desk.png', 40, 30)],
        ])
        ->assertSessionHasErrors(['images' => "A poll can't have images."]);

    expect(Commit::query()->count())->toBe(0)
        ->and(Poll::query()->count())->toBe(0)
        ->and(Storage::disk()->allFiles())->toBeEmpty();
});

it('does not let a reply be a poll', function (): void {
    $parent = Commit::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Everyone)
        ->post(route('commit.store'), [
            'body' => 'Neither, use an editorconfig.',
            'parent_id' => $parent->id,
            'type' => 'poll',
            'poll' => ['options' => ['Tabs', 'Spaces'], 'duration' => '1440'],
        ]);

    $response->assertSessionHasErrors('type');

    expect($parent->replies()->count())->toBe(0)
        ->and(Poll::query()->count())->toBe(0);
});

it('shows polls in the log with their results once the viewer voted', function (Log $log): void {
    $this->travelTo('2026-10-19 12:00:00');

    $viewer = User::factory()->create();
    PollVote::factory()->create(['user_id' => $viewer->id]);

    $commit = Commit::factory()->poll(['closes_at' => '2026-10-20 12:00:00'], ['Tabs', 'Spaces'])->create();
    $viewer->following()->attach($commit->user);
    [$tabs, $spaces] = $commit->poll->options;

    PollVote::factory()->create(['poll_option_id' => $spaces->id, 'user_id' => $viewer->id]);
    PollVote::factory()->create(['poll_option_id' => $spaces->id]);
    PollVote::factory()->create(['poll_option_id' => $tabs->id]);

    $this->actingAs($viewer)
        ->get(route('log.show', $log))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.type', 'poll')
            ->where('commits.data.0.poll', [
                'closes_at' => '2026-10-20T12:00:00.000000Z',
                'voted_option_id' => $spaces->id,
                'options' => [
                    ['id' => $tabs->id, 'label' => 'Tabs', 'votes_count' => 1],
                    ['id' => $spaces->id, 'label' => 'Spaces', 'votes_count' => 2],
                ],
                'is_closed' => false,
            ]));
})->with(Log::cases());

it('hides the results of an open poll from viewers who have not voted', function (): void {
    $viewer = User::factory()->create();
    $commit = Commit::factory()->poll([], ['Tabs', 'Spaces'])->create();
    PollVote::factory()->count(2)->create(['poll_option_id' => $commit->poll?->options->first()?->id]);

    $this->actingAs($viewer)
        ->get(route('log.show', Log::Everyone))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.poll.voted_option_id', null)
            ->where('commits.data.0.poll.options.0.label', 'Tabs')
            ->where('commits.data.0.poll.options.0.votes_count', null)
            ->where('commits.data.0.poll.options.1.votes_count', null));
});

it('shows the results to the author of a poll, who does not vote', function (): void {
    $commit = Commit::factory()->poll([], ['Tabs', 'Spaces'])->create();
    PollVote::factory()->count(2)->create(['poll_option_id' => $commit->poll?->options->last()?->id]);

    $this->actingAs($commit->user)
        ->get(route('commit.show', [$commit->user, $commit]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.poll.voted_option_id', null)
            ->where('commit.poll.options.0.votes_count', 0)
            ->where('commit.poll.options.1.votes_count', 2));
});

it('shows the final results of a closed poll to everyone', function (): void {
    $commit = Commit::factory()->poll(['closes_at' => now()], ['Tabs', 'Spaces'])->create();
    PollVote::factory()->create(['poll_option_id' => $commit->poll?->options->first()?->id]);

    $this->actingAs(User::factory()->create())
        ->get(route('commit.show', [$commit->user, $commit]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.poll.is_closed', true)
            ->where('commit.poll.voted_option_id', null)
            ->where('commit.poll.options.0.votes_count', 1)
            ->where('commit.poll.options.1.votes_count', 0));
});

it('never shows who voted, whoever views the results', function (Closure $viewerOf): void {
    $commit = Commit::factory()->poll(['closes_at' => now()->addHour()], ['Tabs', 'Spaces'])->create();
    $voter = User::factory()->create(['username' => 'secret-voter', 'name' => 'Secret Voter']);
    PollVote::factory()->create(['user_id' => $voter->id, 'poll_option_id' => $commit->poll?->options->first()?->id]);
    $viewer = $viewerOf($commit);

    $response = $this->actingAs($viewer)->get(route('commit.show', [$commit->user, $commit]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->where('commit.poll.options.0.votes_count', fn (?int $count): bool => $count >= 1))
        ->assertDontSee($voter->id)
        ->assertDontSee('secret-voter')
        ->assertDontSee('Secret Voter');
})->with([
    'the author' => [fn (Commit $commit): User => $commit->user],
    'another voter' => [function (Commit $commit): User {
        $viewer = User::factory()->create();
        PollVote::factory()->create(['user_id' => $viewer->id, 'poll_option_id' => $commit->poll?->options->first()?->id]);

        return $viewer;
    }],
    'anyone, once closed' => [function (Commit $commit): User {
        $commit->poll?->update(['closes_at' => now()]);

        return User::factory()->create();
    }],
]);

it('shows whether a poll is closed and that the viewer has not voted', function (): void {
    $viewer = User::factory()->create();
    $open = Commit::factory()->poll()->create(['created_at' => now()->subMinute()]);
    $closed = Commit::factory()->poll(['closes_at' => now()])->create();

    $this->actingAs($viewer)
        ->get(route('log.show', Log::Everyone))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.id', $closed->id)
            ->where('commits.data.0.poll.is_closed', true)
            ->where('commits.data.0.poll.voted_option_id', null)
            ->where('commits.data.1.id', $open->id)
            ->where('commits.data.1.poll.is_closed', false)
            ->where('commits.data.1.poll.voted_option_id', null));
});

it('shows a poll on its page and as the ancestor of its replies', function (): void {
    $viewer = User::factory()->create();
    $commit = Commit::factory()->poll([], ['Tabs', 'Spaces'])->create();
    [$tabs] = $commit->poll->options;
    PollVote::factory()->create(['poll_option_id' => $tabs->id, 'user_id' => $viewer->id]);
    $reply = Commit::factory()->for($commit, 'parent')->create();

    $this->actingAs($viewer);

    $this->get(route('commit.show', [$commit->user, $commit]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.type', 'poll')
            ->where('commit.poll.voted_option_id', $tabs->id)
            ->where('commit.poll.options.0.votes_count', 1)
            ->where('replies.data.0.poll', null));

    $this->get(route('commit.show', [$reply->user, $reply]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.poll', null)
            ->where('ancestors.0.type', 'poll')
            ->where('ancestors.0.poll.voted_option_id', $tabs->id));
});

it('never shows guests a poll, its counts, or who voted', function (Closure $url): void {
    $commit = Commit::factory()->poll(['closes_at' => now()], ['Secret option A', 'Secret option B'])->create();
    $voter = User::factory()->create(['username' => 'secret-voter']);
    PollVote::factory()->create(['user_id' => $voter->id, 'poll_option_id' => $commit->poll?->options->first()?->id]);
    $commit->user->update(['pinned_commit_id' => $commit->id]);

    $this->get($url($commit))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->missing('commit')
            ->missing('pinnedCommit')
            ->missing('commits'))
        ->assertDontSee('Secret option')
        ->assertDontSee('votes_count')
        ->assertDontSee('secret-voter')
        ->assertDontSee($voter->id);
})->with([
    'its page' => [fn (Commit $commit): string => route('commit.show', [$commit->user, $commit])],
    "its author's profile" => [fn (Commit $commit): string => route('user.show', $commit->user)],
]);

it('shows pending polls to moderators', function (): void {
    config(['app.admins' => ['admin']]);
    $admin = User::factory()->create(['username' => 'admin']);
    Commit::factory()->pending()->poll([], ['Tabs', 'Spaces'])->create();

    $this->actingAs($admin)
        ->get(route('moderation.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.type', 'poll')
            ->where('commits.data.0.poll.closes_at', null)
            ->where('commits.data.0.poll.is_closed', false)
            ->where('commits.data.0.poll.options.0.label', 'Tabs')
            ->where('commits.data.0.poll.options.1.label', 'Spaces'));
});

it('loads the polls of a page of commits with a bounded number of queries', function (): void {
    $viewer = User::factory()->create();
    $vote = function (Commit $commit) use ($viewer): void {
        PollVote::factory()->create(['user_id' => $viewer->id, 'poll_option_id' => $commit->poll?->options->first()?->id]);
        PollVote::factory()->create(['poll_option_id' => $commit->poll?->options->first()?->id]);
    };

    $vote(Commit::factory()->poll()->create());

    $this->actingAs($viewer);
    DB::enableQueryLog();

    $this->get(route('log.show', Log::Everyone))->assertOk();
    $queriesForOnePoll = count(DB::getQueryLog());

    Commit::factory()->count(5)->poll()->create()->each($vote);
    DB::flushQueryLog();

    $this->get(route('log.show', Log::Everyone))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('commits.data', 6)->where('commits.data.0.poll.options.0.votes_count', 2));

    expect(count(DB::getQueryLog()))->toBe($queriesForOnePoll);
});
