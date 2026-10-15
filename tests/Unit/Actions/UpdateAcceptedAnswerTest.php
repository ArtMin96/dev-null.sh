<?php

declare(strict_types=1);

use App\Actions\UpdateAcceptedAnswer;
use App\Enums\NotificationType;
use App\Models\Commit;

it('notifies the author of an answer once, however often it is accepted', function (): void {
    $question = Commit::factory()->question()->create();
    $answer = Commit::factory()->for($question, 'parent')->create();

    $action = resolve(UpdateAcceptedAnswer::class);

    $action->handle($question, $answer);
    $answer->user->notifications()->update(['read_at' => now()]);
    $action->handle($question, $answer);

    $notification = $answer->user->notifications()->sole();

    expect($notification->type)->toBe(NotificationType::AnswerAccepted)
        ->and($notification->key)->toBe('answer-accepted:'.$question->id)
        ->and($notification->read_at)->not->toBeNull();
});
