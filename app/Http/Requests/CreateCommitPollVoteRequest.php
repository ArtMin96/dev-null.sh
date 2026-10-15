<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\User;
use App\Rules\VotablePoll;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

final class CreateCommitPollVoteRequest extends FormRequest
{
    /**
     * A poll the voter cannot see does not exist for them, and neither does the poll of a commit without one.
     */
    public function authorize(): Response
    {
        $commit = $this->commit();

        return $commit->isVisibleTo($this->voter()) && $commit->poll instanceof Poll
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string|VotablePoll|Exists>>
     */
    public function rules(): array
    {
        return [
            'option_id' => [
                'bail',
                'required',
                'string',
                'uuid',
                new VotablePoll($this->commit(), $this->voter()),
                Rule::exists(PollOption::class, 'id')->where('poll_id', $this->poll()->id),
            ],
        ];
    }

    public function option(): PollOption
    {
        return $this->poll()->options()->whereKey($this->string('option_id')->value())->sole();
    }

    private function commit(): Commit
    {
        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return $commit;
    }

    private function poll(): Poll
    {
        $poll = $this->commit()->poll;
        assert($poll instanceof Poll);

        return $poll;
    }

    private function voter(): User
    {
        $voter = $this->user();
        assert($voter instanceof User);

        return $voter;
    }
}
