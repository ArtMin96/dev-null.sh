<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

final readonly class UpdateAcceptedAnswer
{
    /**
     * Accepts a reply as the answer of its question, replacing the previous one along with its notification, and
     * tells the answer's author, unless they asked the question. Accepting the current answer again changes nothing.
     */
    public function handle(Commit $question, Commit $answer): void
    {
        DB::transaction(function () use ($question, $answer): void {
            $question = Commit::query()->lockForUpdate()->findOrFail($question->id);

            if ($question->accepted_answer_id === $answer->id) {
                return;
            }

            Notification::query()
                ->where('commit_id', $question->id)
                ->where('type', NotificationType::AnswerAccepted)
                ->delete();

            $question->update(['accepted_answer_id' => $answer->id]);

            if ($answer->user_id === $question->user_id) {
                return;
            }

            Notification::query()->create([
                'recipient_id' => $answer->user_id,
                'actor_id' => $question->user_id,
                'type' => NotificationType::AnswerAccepted,
                'commit_id' => $question->id,
                'key' => 'answer-accepted:'.$question->id,
            ]);
        });
    }
}
