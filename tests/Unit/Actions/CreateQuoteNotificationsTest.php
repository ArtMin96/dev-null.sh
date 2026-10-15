<?php

declare(strict_types=1);

use App\Actions\CreateQuoteNotifications;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;

it('notifies the quoted author about approved quotes', function (): void {
    $quoted = Commit::factory()->create();
    $quote = Commit::factory()->quote($quoted)->create();

    $action = resolve(CreateQuoteNotifications::class);

    $action->handle($quote);

    $notification = $quoted->user->notifications()->sole();

    expect($notification)
        ->type->toBe(NotificationType::Quoted)
        ->actor_id->toBe($quote->user_id)
        ->commit_id->toBe($quote->id)
        ->key->toBe('quoted:'.$quote->id);
});

it('does not notify about commits that are not approved quotes', function (): void {
    $quoted = Commit::factory()->create();

    $action = resolve(CreateQuoteNotifications::class);

    $action->handle(
        Commit::factory()->create(),
        Commit::factory()->for($quoted, 'parent')->create(),
        Commit::factory()->pending()->quote($quoted)->create(),
        Commit::factory()->rejected()->quote($quoted)->create(),
    );

    expect(Notification::query()->count())->toBe(0);
});

it('does not notify authors quoting themselves', function (): void {
    $quoted = Commit::factory()->create();
    $quote = Commit::factory()->for($quoted->user)->quote($quoted)->create();

    $action = resolve(CreateQuoteNotifications::class);

    $action->handle($quote);

    expect(Notification::query()->count())->toBe(0);
});

it('does not notify about quotes of deleted commits', function (): void {
    $quoted = Commit::factory()->create();
    $quote = Commit::factory()->quote($quoted)->create();
    $quoted->delete();

    $action = resolve(CreateQuoteNotifications::class);

    $action->handle($quote);

    expect(Notification::query()->count())->toBe(0);
});

it('notifies the author of each quoted commit at once', function (): void {
    $first = Commit::factory()->quote()->create();
    $second = Commit::factory()->quote()->create();

    $action = resolve(CreateQuoteNotifications::class);

    $action->handle($first, $second);

    expect(Notification::query()->orderBy('commit_id')->pluck('recipient_id', 'commit_id')->all())->toEqualCanonicalizing([
        $first->id => $first->quotedCommit?->user_id,
        $second->id => $second->quotedCommit?->user_id,
    ]);
});

it('is idempotent', function (): void {
    $quote = Commit::factory()->quote()->create();

    $action = resolve(CreateQuoteNotifications::class);

    $action->handle($quote);
    $action->handle($quote);

    expect(Notification::query()->count())->toBeOne();
});

it('does nothing without commits', function (): void {
    $action = resolve(CreateQuoteNotifications::class);

    $action->handle();

    expect(Notification::query()->count())->toBe(0);
});
