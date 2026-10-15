<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateAcceptedAnswer;
use App\Http\Requests\UpdateAcceptedAnswerRequest;
use App\Models\Commit;
use Illuminate\Http\JsonResponse;

final readonly class CommitAnswerAcceptanceController
{
    public function __invoke(UpdateAcceptedAnswerRequest $request, Commit $commit, UpdateAcceptedAnswer $action): JsonResponse
    {
        $answer = $request->answer();

        $action->handle($commit, $answer);

        return response()->json(['accepted_answer_id' => $answer->id]);
    }
}
