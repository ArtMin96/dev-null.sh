<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Enums\PollDuration;
use App\Enums\SnippetLanguage;
use App\Models\Commit;
use App\Models\PollVote;
use App\Models\User;

it('requires authentication to download personal data', function (): void {
    $this->get(route('export'))->assertRedirectToRoute('home');
});

it('downloads a versioned private JSON file with an explicit profile and empty lists', function (): void {
    $user = User::factory()->create([
        'name' => 'Zoë Example',
        'bio' => "Building tools.\nKeeping them small.",
        'email' => 'zoe@example.test',
        'company' => null,
        'preferred_lane' => 3,
        'remember_token' => 'never-export-this-token',
    ]);
    $original = $user->fresh()->getAttributes();

    $response = $this->actingAs($user)->get(route('export'));

    $response->assertOk()
        ->assertStreamed()
        ->assertDownload('devnull-export-'.now()->format('Y-m-d-His').'.json')
        ->assertHeader('Content-Type', 'application/json; charset=UTF-8')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Accel-Buffering', 'no');
    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store');

    $data = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

    expect($data)->toBe([
        'schema_version' => 1,
        'exported_at' => now()->toIso8601String(),
        'profile' => [
            'username' => $user->username,
            'name' => 'Zoë Example',
            'email' => 'zoe@example.test',
            'avatar_url' => $user->avatar_url,
            'bio' => "Building tools.\nKeeping them small.",
            'location' => $user->location,
            'website' => $user->website,
            'company' => null,
            'preferred_lane' => 3,
            'created_at' => $user->created_at->toIso8601String(),
            'updated_at' => $user->updated_at->toIso8601String(),
        ],
        'commits' => [],
        'poll_votes' => [],
        'following' => [],
    ]);
    expect($user->fresh()->getAttributes())->toBe($original);
});

it('exports only the signed-in users live commits including replies and all moderation statuses', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $parent = Commit::factory()->for($other)->rejected()->create(['body' => 'Another developer private parent body']);
    $approved = Commit::factory()->for($user)->create(['body' => "Shipped Unicode support 🚀\nNext: docs."]);
    $pending = Commit::factory()->for($user)->pending()->create();
    $rejected = Commit::factory()->for($user)->rejected()->create();
    $reply = Commit::factory()->for($user)->create(['parent_id' => $parent->id]);
    $deleted = Commit::factory()->for($user)->create(['body' => 'Deleted content must never be exported']);
    $deletedReply = Commit::factory()->for($user)->create(['parent_id' => $approved->id]);
    $survivingReply = Commit::factory()->for($user)->pending()->create(['parent_id' => $deleted->id]);
    $deleted->delete();
    $deletedReply->delete();
    Commit::factory()->for($other)->create(['parent_id' => $approved->id]);
    Commit::factory()->for($other)->pending()->create();
    Commit::factory()->for($other)->rejected()->create();

    $response = $this->actingAs($user)->get(route('export', ['user_id' => $other->id]));
    $data = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
    $commits = collect($data['commits'])->keyBy('id');

    expect($commits->keys()->sort()->values()->all())->toBe(collect([
        $approved->id, $pending->id, $rejected->id, $reply->id, $survivingReply->id,
    ])->sort()->values()->all());
    expect($commits[$approved->id])->toBe([
        'id' => $approved->id,
        'parent_id' => null,
        'body' => $approved->body,
        'status' => 'approved',
        'type' => 'text',
        'release' => null,
        'poll' => null,
        'snippet' => null,
        'quoted_commit_id' => null,
        'accepted_answer_id' => null,
        'created_at' => $approved->created_at->toIso8601String(),
        'updated_at' => $approved->updated_at->toIso8601String(),
    ])
        ->and($commits[$pending->id]['status'])->toBe('pending')
        ->and($commits[$rejected->id]['status'])->toBe('rejected')
        ->and($commits[$reply->id]['parent_id'])->toBe($parent->id)
        ->and($commits[$survivingReply->id]['parent_id'])->toBe($deleted->id)
        ->and($response->streamedContent())->not->toContain($deleted->body, $deletedReply->body, $parent->body);
});

it('exports the type of each commit along with its data', function (): void {
    $user = User::factory()->create();
    $quoted = Commit::factory()->create();
    $release = Commit::factory()->for($user)->release(['project' => 'pestphp/pest', 'version' => 'v5.0.0', 'url' => null, 'notes' => 'Faster.'])->create();
    $poll = Commit::factory()->for($user)->poll(['duration' => PollDuration::ThreeDays, 'closes_at' => '2026-10-18 12:00:00'], ['Tabs', 'Spaces'])->create();
    $snippet = Commit::factory()->for($user)->snippet(['language' => SnippetLanguage::Php, 'filename' => 'app.php', 'code' => "<?php\n\necho 1;"])->create();
    $quote = Commit::factory()->for($user)->quote($quoted)->create();
    $question = Commit::factory()->for($user)->question()->create();
    $answer = Commit::factory()->for($question, 'parent')->create();
    $question->update(['accepted_answer_id' => $answer->id]);

    $response = $this->actingAs($user)->get(route('export'));
    $commits = collect(json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR)['commits'])->keyBy('id');

    expect($commits[$release->id])->toMatchArray([
        'type' => 'release',
        'release' => ['project' => 'pestphp/pest', 'version' => 'v5.0.0', 'url' => null, 'notes' => 'Faster.'],
        'poll' => null,
        'snippet' => null,
    ])
        ->and($commits[$poll->id])->toMatchArray([
            'type' => 'poll',
            'poll' => ['options' => ['Tabs', 'Spaces'], 'duration_minutes' => 4320, 'closes_at' => '2026-10-18T12:00:00+00:00'],
            'release' => null,
        ])
        ->and($commits[$snippet->id])->toMatchArray([
            'type' => 'snippet',
            'snippet' => ['language' => 'php', 'filename' => 'app.php', 'code' => "<?php\n\necho 1;"],
        ])
        ->and($commits[$quote->id])->toMatchArray(['type' => 'quote', 'quoted_commit_id' => $quoted->id])
        ->and($commits[$question->id])->toMatchArray(['type' => 'question', 'accepted_answer_id' => $answer->id])
        ->and($response->streamedContent())->not->toContain($quoted->body);
});

it('exports the poll votes of the user on polls everyone can still see', function (): void {
    $user = User::factory()->create();
    $poll = Commit::factory()->poll([], ['Tabs', 'Spaces'])->create();
    $deletedPoll = Commit::factory()->poll([], ['Vim', 'Emacs'])->create();
    $rejectedPoll = Commit::factory()->poll([], ['Light', 'Dark'])->create();
    $vote = PollVote::factory()->create(['user_id' => $user->id, 'poll_option_id' => $poll->poll?->options->last()?->id]);
    PollVote::factory()->create(['user_id' => $user->id, 'poll_option_id' => $deletedPoll->poll?->options->first()?->id]);
    PollVote::factory()->create(['user_id' => $user->id, 'poll_option_id' => $rejectedPoll->poll?->options->first()?->id]);
    PollVote::factory()->create(['poll_option_id' => $poll->poll?->options->first()?->id]);
    $deletedPoll->delete();
    $rejectedPoll->update(['status' => CommitStatus::Rejected]);

    $response = $this->actingAs($user)->get(route('export'));
    $data = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

    expect($data['poll_votes'])->toBe([
        ['commit_id' => $poll->id, 'option' => 'Spaces', 'created_at' => $vote->created_at->toIso8601String()],
    ])
        ->and($response->streamedContent())->not->toContain('Vim', 'Emacs', 'Light', 'Dark');
});

it('exports following usernames without private identity fields or followers', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create(['username' => 'demo-developer', 'email' => 'private@example.test', 'remember_token' => 'secret-token']);
    $follower = User::factory()->create();
    $user->following()->attach($followed);
    $user->followers()->attach($follower);
    $follower->following()->attach(User::factory()->create());

    $response = $this->actingAs($user)->get(route('export'));
    $data = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

    expect($data['following'])->toBe([['username' => 'demo-developer']])
        ->and($response->streamedContent())->not->toContain($followed->email, $followed->github_id, $followed->id, $followed->remember_token, $follower->username);
});

it('exports all records across chunk boundaries without omissions or duplicates', function (): void {
    $user = User::factory()->create();
    $commits = Commit::factory()->for($user)->count(501)->create();
    $following = User::factory()->count(501)->create();
    $user->following()->attach($following->modelKeys());

    $response = $this->actingAs($user)->get(route('export'));
    $data = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

    expect(collect($data['commits'])->pluck('id')->sort()->values()->all())->toBe($commits->pluck('id')->sort()->values()->all())
        ->and(collect($data['following'])->pluck('username')->sort()->values()->all())->toBe($following->pluck('username')->sort()->values()->all());
});
