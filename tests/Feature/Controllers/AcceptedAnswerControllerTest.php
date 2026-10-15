<?php

declare(strict_types=1);

use App\Enums\CommitType;
use App\Enums\Log;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Str;

it('lets the author of a question accept a reply as its answer', function (): void {
    $question = Commit::factory()->question()->create();
    $answer = Commit::factory()->for($question, 'parent')->create();

    $response = $this->actingAs($question->user)
        ->putJson(route('accepted-answer.store', $question), ['answer_id' => $answer->id]);

    $response->assertOk()
        ->assertExactJson(['accepted_answer_id' => $answer->id]);

    expect($question->fresh()->accepted_answer_id)->toBe($answer->id);

    $notification = $answer->user->notifications()->sole();

    expect($notification->type)->toBe(NotificationType::AnswerAccepted)
        ->and($notification->actor_id)->toBe($question->user_id)
        ->and($notification->commit_id)->toBe($question->id);
});

it('replaces the accepted answer, notifying only the author of the new one', function (): void {
    $question = Commit::factory()->question()->create();
    [$first, $second] = Commit::factory()->for($question, 'parent')->count(2)->create();

    $this->actingAs($question->user)
        ->putJson(route('accepted-answer.store', $question), ['answer_id' => $first->id])
        ->assertOk();

    $this->putJson(route('accepted-answer.store', $question), ['answer_id' => $second->id])
        ->assertOk()
        ->assertExactJson(['accepted_answer_id' => $second->id]);

    expect($question->fresh()->accepted_answer_id)->toBe($second->id)
        ->and($first->user->notifications()->exists())->toBeFalse()
        ->and($second->user->notifications()->sole()->type)->toBe(NotificationType::AnswerAccepted);
});

it('lets the author of a question accept their own reply without notifying themselves', function (): void {
    $question = Commit::factory()->question()->create();
    $answer = Commit::factory()->for($question->user)->for($question, 'parent')->create();

    $this->actingAs($question->user)
        ->putJson(route('accepted-answer.store', $question), ['answer_id' => $answer->id])
        ->assertOk();

    expect($question->fresh()->accepted_answer_id)->toBe($answer->id)
        ->and(Notification::query()->exists())->toBeFalse();
});

it('lets the author of a question remove its accepted answer along with its notification', function (): void {
    $question = Commit::factory()->question()->create();
    $answer = Commit::factory()->for($question, 'parent')->create();

    $this->actingAs($question->user)
        ->putJson(route('accepted-answer.store', $question), ['answer_id' => $answer->id])
        ->assertOk();

    $this->deleteJson(route('accepted-answer.destroy', $question))
        ->assertOk()
        ->assertExactJson(['accepted_answer_id' => null]);

    expect($question->fresh()->accepted_answer_id)->toBeNull()
        ->and(Notification::query()->exists())->toBeFalse();
});

it('only lets the author of a question choose its answer', function (string $commit, string $viewer): void {
    $author = User::factory()->create();
    $question = Commit::factory()->for($author)->create(['type' => $commit === 'question' ? CommitType::Question : CommitType::Text]);
    $answer = Commit::factory()->for($question, 'parent')->create();

    $this->actingAs($viewer === 'author' ? $author : User::factory()->create());

    $this->putJson(route('accepted-answer.store', $question), ['answer_id' => $answer->id])->assertForbidden();
    $this->deleteJson(route('accepted-answer.destroy', $question))->assertForbidden();

    expect($question->fresh()->accepted_answer_id)->toBeNull()
        ->and(Notification::query()->exists())->toBeFalse();
})->with([
    'someone else on a question' => ['question', 'someone else'],
    'the author of a text commit' => ['text', 'author'],
]);

it('requires authentication to choose an answer', function (): void {
    $question = Commit::factory()->question()->create();
    $answer = Commit::factory()->for($question, 'parent')->create();

    $this->putJson(route('accepted-answer.store', $question), ['answer_id' => $answer->id])->assertUnauthorized();
    $this->deleteJson(route('accepted-answer.destroy', $question))->assertUnauthorized();

    expect($question->fresh()->accepted_answer_id)->toBeNull();
});

it('only accepts an approved direct reply of the question', function (Closure $answer): void {
    $question = Commit::factory()->question()->create();

    $response = $this->actingAs($question->user)
        ->putJson(route('accepted-answer.store', $question), ['answer_id' => $answer($question)]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('answer_id');

    expect($question->fresh()->accepted_answer_id)->toBeNull()
        ->and(Notification::query()->exists())->toBeFalse();
})->with([
    'missing' => [fn (Commit $question): null => null],
    'not a uuid' => [fn (Commit $question): string => 'not-a-uuid'],
    'unknown' => [fn (Commit $question): string => Str::uuid7()->toString()],
    'the question itself' => [fn (Commit $question): string => $question->id],
    'a reply to another commit' => [fn (Commit $question): string => Commit::factory()->for(Commit::factory(), 'parent')->create()->id],
    'a nested reply' => [fn (Commit $question): string => Commit::factory()->for(Commit::factory()->for($question, 'parent'), 'parent')->create()->id],
    'a pending reply' => [fn (Commit $question): string => Commit::factory()->pending()->for($question->user)->for($question, 'parent')->create()->id],
    'a rejected reply' => [fn (Commit $question): string => Commit::factory()->rejected()->for($question->user)->for($question, 'parent')->create()->id],
    'a deleted reply' => [fn (Commit $question): string => Commit::factory()->for($question, 'parent')->create(['deleted_at' => now()])->id],
]);

it('cannot choose the answer of a deleted question', function (): void {
    $question = Commit::factory()->question()->create();
    $answer = Commit::factory()->for($question, 'parent')->create();
    $question->delete();

    $this->actingAs($question->user)
        ->putJson(route('accepted-answer.store', $question), ['answer_id' => $answer->id])
        ->assertNotFound();
    $this->deleteJson(route('accepted-answer.destroy', $question))->assertNotFound();
});

it('leaves a question without an accepted answer once the answer is deleted', function (): void {
    $question = Commit::factory()->question()->create();
    $answer = Commit::factory()->for($question, 'parent')->create();

    $this->actingAs($question->user)
        ->putJson(route('accepted-answer.store', $question), ['answer_id' => $answer->id])
        ->assertOk();

    $this->actingAs($answer->user)
        ->fromRoute('log.show', Log::Everyone)
        ->delete(route('commit.destroy', $answer))
        ->assertRedirectToRoute('log.show', Log::Everyone);

    expect($question->fresh()->accepted_answer_id)->toBeNull()
        ->and(Notification::query()->exists())->toBeFalse();
});

it('withdraws the notification of an accepted answer once the answer is rejected', function (): void {
    config(['app.admins' => ['admin']]);

    $question = Commit::factory()->question()->create();
    $answer = Commit::factory()->for($question, 'parent')->create();

    $this->actingAs($question->user)
        ->putJson(route('accepted-answer.store', $question), ['answer_id' => $answer->id])
        ->assertOk();

    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->fromRoute('moderation.show')
        ->post(route('commit-rejection.store', $answer))
        ->assertRedirectToRoute('moderation.show');

    expect($answer->user->notifications()->sole()->type)->toBe(NotificationType::Rejected);
});
