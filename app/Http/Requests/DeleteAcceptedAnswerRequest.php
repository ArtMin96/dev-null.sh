<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CommitType;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteAcceptedAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        $question = $this->route('commit');
        assert($question instanceof Commit);

        return $question->type === CommitType::Question && $question->user_id === $user->id;
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
