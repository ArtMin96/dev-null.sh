<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\User;
use Illuminate\Support\Str;

it('casts a vote and returns the updated poll', function (): void {
    $this->travelTo('2026-10-19 12:00:00');

    $voter = User::factory()->create();
    $commit = Commit::factory()->poll(['closes_at' => '2026-10-20 12:00:00'], ['Tabs', 'Spaces'])->create();
    [$tabs, $spaces] = $commit->poll->options;
    PollVote::factory()->create(['poll_option_id' => $tabs->id]);
    PollVote::factory()->create(['poll_option_id' => $spaces->id]);

    $response = $this->actingAs($voter)
        ->postJson(route('commit-poll-vote.store', $commit), ['option_id' => $spaces->id]);

    $response->assertOk()->assertExactJson([
        'closes_at' => '2026-10-20T12:00:00.000000Z',
        'is_closed' => false,
        'voted_option_id' => $spaces->id,
        'options' => [
            ['id' => $tabs->id, 'label' => 'Tabs', 'votes_count' => 1],
            ['id' => $spaces->id, 'label' => 'Spaces', 'votes_count' => 2],
        ],
    ]);

    expect($spaces->votes()->where('user_id', $voter->id)->exists())->toBeTrue();
});

it('keeps the first vote and returns it when voting again', function (): void {
    $voter = User::factory()->create();
    $commit = Commit::factory()->poll([], ['Tabs', 'Spaces'])->create();
    [$tabs, $spaces] = $commit->poll->options;

    $this->actingAs($voter)
        ->postJson(route('commit-poll-vote.store', $commit), ['option_id' => $tabs->id])
        ->assertOk();

    $this->postJson(route('commit-poll-vote.store', $commit), ['option_id' => $spaces->id])
        ->assertOk()
        ->assertJsonPath('voted_option_id', $tabs->id)
        ->assertJsonPath('options.0.votes_count', 1)
        ->assertJsonPath('options.1.votes_count', 0);

    expect(PollVote::query()->sole()->poll_option_id)->toBe($tabs->id);
});

it('never returns who voted', function (): void {
    $commit = Commit::factory()->poll([], ['Tabs', 'Spaces'])->create();
    $other = User::factory()->create(['username' => 'secret-voter']);
    PollVote::factory()->create(['user_id' => $other->id, 'poll_option_id' => $commit->poll?->options->first()?->id]);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('commit-poll-vote.store', $commit), ['option_id' => $commit->poll?->options->first()?->id]);

    $response->assertOk()
        ->assertJsonPath('options.0.votes_count', 2)
        ->assertDontSee($other->id)
        ->assertDontSee('secret-voter');
});

it('explains why a poll refuses a vote', function (Closure $scenario, string $message): void {
    [$voter, $commit] = $scenario();

    $this->actingAs($voter)
        ->postJson(route('commit-poll-vote.store', $commit), ['option_id' => $commit->poll?->options->first()?->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['option_id' => $message]);

    expect(PollVote::query()->count())->toBe(0);
})->with([
    'own poll' => [function (): array {
        $commit = Commit::factory()->poll()->create();

        return [$commit->user, $commit];
    }, "You can't vote on your own poll."],
    'own pending poll' => [function (): array {
        $commit = Commit::factory()->pending()->poll()->create();

        return [$commit->user, $commit];
    }, 'This poll is not open for votes yet.'],
    'own rejected poll' => [function (): array {
        $commit = Commit::factory()->rejected()->poll()->create();

        return [$commit->user, $commit];
    }, 'This poll is not open for votes yet.'],
    'closed poll' => [fn (): array => [User::factory()->create(), Commit::factory()->poll(['closes_at' => now()])->create()], 'This poll has closed.'],
]);

it('does not find polls the voter cannot see', function (Closure $commit): void {
    $commit = $commit();

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-poll-vote.store', $commit), ['option_id' => PollOption::factory()->create()->id])
        ->assertNotFound();

    expect(PollVote::query()->count())->toBe(0);
})->with([
    'pending poll' => [fn () => Commit::factory()->pending()->poll()->create()],
    'rejected poll' => [fn () => Commit::factory()->rejected()->poll()->create()],
    'text commit' => [fn () => Commit::factory()->create()],
]);

it('does not find deleted polls', function (): void {
    $commit = Commit::factory()->poll()->create();
    $commit->delete();

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-poll-vote.store', $commit), ['option_id' => $commit->poll?->options->first()?->id])
        ->assertNotFound();

    expect(PollVote::query()->count())->toBe(0);
});

it('validates the option', function (Closure $option): void {
    $commit = Commit::factory()->poll()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-poll-vote.store', $commit), ['option_id' => $option()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('option_id');

    expect(PollVote::query()->count())->toBe(0);
})->with([
    'missing' => [fn (): null => null],
    'not a string' => [fn (): array => ['id']],
    'not a uuid' => [fn (): string => 'not-a-uuid'],
    'unknown' => [fn (): string => Str::uuid7()->toString()],
    'of another poll' => [fn (): string => PollOption::factory()->create()->id],
]);

it('requires authentication to vote', function (): void {
    $commit = Commit::factory()->poll()->create();

    $this->postJson(route('commit-poll-vote.store', $commit), ['option_id' => $commit->poll?->options->first()?->id])
        ->assertUnauthorized();

    expect(PollVote::query()->count())->toBe(0);
});
