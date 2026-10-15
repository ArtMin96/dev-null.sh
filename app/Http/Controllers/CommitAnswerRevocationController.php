<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteAcceptedAnswer;
use App\Http\Requests\DeleteAcceptedAnswerRequest;
use App\Models\Commit;
use Illuminate\Http\JsonResponse;

final readonly class CommitAnswerRevocationController
{
    public function __invoke(DeleteAcceptedAnswerRequest $request, Commit $commit, DeleteAcceptedAnswer $action): JsonResponse
    {
        $action->handle($commit);

        return response()->json(['accepted_answer_id' => null]);
    }
}
