<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Enums\Log;
use App\Enums\PollDuration;
use App\Models\Commit;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);

    $this->admin = User::factory()->create(['username' => 'admin']);
});

it('opens a pending poll for its full duration once it is approved', function (): void {
    $author = User::factory()->create();

    $this->actingAs($author)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Tabs or spaces?',
            'type' => 'poll',
            'poll' => ['options' => ['Tabs', 'Spaces'], 'duration' => '60'],
        ])
        ->assertSessionHasNoErrors();

    $commit = $author->commits()->sole();
    [$tabs] = $commit->poll->options;

    expect($commit->status)->toBe(CommitStatus::Pending)
        ->and($commit->poll->closes_at)->toBeNull();

    $this->travel(2)->days();

    $this->actingAs($this->admin)
        ->post(route('commit-approval.store', $commit))
        ->assertRedirect();

    expect($commit->poll->refresh()->closes_at?->toIso8601String())->toBe(now()->addHour()->toIso8601String());

    $this->travel(59)->minutes();

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-poll-vote.store', $commit), ['option_id' => $tabs->id])
        ->assertOk();

    $this->travel(1)->minute();

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-poll-vote.store', $commit), ['option_id' => $tabs->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['option_id' => 'This poll has closed.']);
});

it('opens the poll of an admin at once', function (): void {
    $this->actingAs($this->admin)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Tabs or spaces?',
            'type' => 'poll',
            'poll' => ['options' => ['Tabs', 'Spaces'], 'duration' => '4320'],
        ])
        ->assertSessionHasNoErrors();

    $commit = $this->admin->commits()->sole();
    [$tabs] = $commit->poll->options;

    expect($commit->status)->toBe(CommitStatus::Approved)
        ->and($commit->poll->closes_at?->toIso8601String())->toBe(now()->addDays(3)->toIso8601String());

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-poll-vote.store', $commit), ['option_id' => $tabs->id])
        ->assertOk();
});

it('opens every poll approved at once for its own duration', function (): void {
    $oneHour = Commit::factory()->pending()->poll(['duration' => PollDuration::OneHour])->create();
    $oneWeek = Commit::factory()->pending()->poll(['duration' => PollDuration::SevenDays])->create();
    $text = Commit::factory()->pending()->create();

    $this->travel(5)->hours();

    $this->actingAs($this->admin)
        ->fromRoute('moderation.show')
        ->put(route('moderation.update'), ['commits' => [$oneHour->id, $oneWeek->id, $text->id]])
        ->assertRedirectToRoute('moderation.show');

    expect($oneHour->poll->refresh()->closes_at?->toIso8601String())->toBe(now()->addHour()->toIso8601String())
        ->and($oneWeek->poll->refresh()->closes_at?->toIso8601String())->toBe(now()->addWeek()->toIso8601String())
        ->and($text->refresh()->status)->toBe(CommitStatus::Approved);
});

it('does not extend a poll that is approved again', function (): void {
    $commit = Commit::factory()->pending()->poll(['duration' => PollDuration::ThreeDays])->create();

    $this->actingAs($this->admin)->post(route('commit-approval.store', $commit))->assertRedirect();
    $closesAt = now()->addDays(3)->toIso8601String();

    $this->travel(1)->hour();
    $this->post(route('commit-rejection.store', $commit))->assertRedirect();

    $this->travel(1)->hour();
    $this->post(route('commit-approval.store', $commit))->assertRedirect();

    expect($commit->refresh()->status)->toBe(CommitStatus::Approved)
        ->and($commit->poll?->closes_at?->toIso8601String())->toBe($closesAt);
});

it('refuses votes before the poll is approved', function (): void {
    $commit = Commit::factory()->pending()->poll()->create();
    $option = ['option_id' => $commit->poll?->options->first()?->id];

    $this->actingAs($commit->user)
        ->postJson(route('commit-poll-vote.store', $commit), $option)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['option_id' => 'This poll is not open for votes yet.']);

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-poll-vote.store', $commit), $option)
        ->assertNotFound();

    expect($commit->poll?->closes_at)->toBeNull()
        ->and($commit->poll?->options->first()?->votes()->count())->toBe(0);
});
