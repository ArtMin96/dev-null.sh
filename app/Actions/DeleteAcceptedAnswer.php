<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

final readonly class DeleteAcceptedAnswer
{
    /**
     * Leaves the question without an accepted answer, and its author's notification goes along with it.
     */
    public function handle(Commit $question): void
    {
        DB::transaction(function () use ($question): void {
            Notification::query()
                ->where('commit_id', $question->id)
                ->where('type', NotificationType::AnswerAccepted)
                ->delete();

            $question->update(['accepted_answer_id' => null]);
        });
    }
}
