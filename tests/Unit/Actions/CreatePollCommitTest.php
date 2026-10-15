<?php

declare(strict_types=1);

use App\Actions\CreateCommit;
use App\Dto\CommitTypes\PollData;
use App\Enums\CommitStatus;
use App\Enums\CommitType;
use App\Enums\PollDuration;
use App\Models\User;

it('creates a poll along with its commit, keeping the order of its options and waiting for approval to open it', function (): void {
    $action = resolve(CreateCommit::class);

    $commit = $action->handle(User::factory()->create(), 'Best editor?', data: new PollData(
        options: ['Vim', 'Emacs', 'Nano', 'Ed'],
        duration: PollDuration::SevenDays,
    ));

    expect($commit->refresh())
        ->type->toBe(CommitType::Poll)
        ->body->toBe('Best editor?')
        ->status->toBe(CommitStatus::Pending)
        ->and($commit->poll?->duration)->toBe(PollDuration::SevenDays)
        ->and($commit->poll?->closes_at)->toBeNull()
        ->and($commit->poll?->options->pluck('label')->all())->toBe(['Vim', 'Emacs', 'Nano', 'Ed']);
});
