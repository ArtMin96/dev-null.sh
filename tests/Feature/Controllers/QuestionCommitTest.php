<?php

declare(strict_types=1);

use App\Actions\UpdateAcceptedAnswer;
use App\Enums\CommitStatus;
use App\Enums\CommitType;
use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;

it('may ask a question', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'How do you test a queue worker?',
            'type' => 'question',
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertSessionHasNoErrors();

    $question = $user->commits()->sole();

    expect($question->type)->toBe(CommitType::Question)
        ->and($question->body)->toBe('How do you test a queue worker?')
        ->and($question->accepted_answer_id)->toBeNull();
});

it('shows which questions are answered, ignoring deleted or hidden answers', function (Log $log): void {
    $user = User::factory()->create();
    $questions = Commit::factory()->for($user)->question()->count(4)
        ->sequence(fn (Sequence $sequence): array => ['created_at' => now()->subMinutes(4 - $sequence->index)])
        ->create();

    foreach ($questions->skip(1) as $index => $question) {
        $answer = Commit::factory()->for($question, 'parent')->create();
        resolve(UpdateAcceptedAnswer::class)->handle($question, $answer);

        match ($index) {
            2 => $answer->delete(),
            3 => $answer->update(['status' => CommitStatus::Rejected]),
            default => null,
        };
    }

    $response = $this->actingAs($user)->get(route('log.show', $log));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.3.id', $questions[0]->id)
            ->where('commits.data.3.type', 'question')
            ->where('commits.data.3.accepted_answer_id', null)
            ->where('commits.data.3.is_answered', false)
            ->where('commits.data.2.accepted_answer_id', $questions[1]->fresh()->accepted_answer_id)
            ->where('commits.data.2.is_answered', true)
            ->where('commits.data.1.is_answered', false)
            ->where('commits.data.0.is_answered', false));
})->with(Log::cases());

it('shows the accepted answer of a question among its replies', function (): void {
    $question = Commit::factory()->question()->create();
    [$answer, $reply] = Commit::factory()->for($question, 'parent')
        ->count(2)
        ->sequence(fn (Sequence $sequence): array => ['created_at' => now()->subMinutes(2 - $sequence->index)])
        ->create();
    resolve(UpdateAcceptedAnswer::class)->handle($question, $answer);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('commit.show', [$question->user, $question]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.type', 'question')
            ->where('commit.accepted_answer_id', $answer->id)
            ->where('commit.is_answered', true)
            ->where('replies.data.0.id', $answer->id)
            ->where('replies.data.1.id', $reply->id));
});
