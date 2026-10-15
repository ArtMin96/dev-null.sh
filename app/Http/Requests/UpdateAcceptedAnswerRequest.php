<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CommitStatus;
use App\Enums\CommitType;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAcceptedAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        $question = $this->question();

        return $question->type === CommitType::Question && $question->user_id === $user->id;
    }

    /**
     * Only an approved, nondeleted direct reply of the question can be its answer.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'answer_id' => [
                'required',
                'uuid',
                Rule::exists(Commit::class, 'id')
                    ->where('parent_id', $this->question()->id)
                    ->where('status', CommitStatus::Approved)
                    ->withoutTrashed(),
            ],
        ];
    }

    public function question(): Commit
    {
        $question = $this->route('commit');
        assert($question instanceof Commit);

        return $question;
    }

    public function answer(): Commit
    {
        return Commit::query()->findOrFail($this->string('answer_id')->value());
    }
}
