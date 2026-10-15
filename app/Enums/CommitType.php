<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\Dto\CommitTypeData;
use App\Dto\CommitTypes\PollData;
use App\Dto\CommitTypes\QuestionData;
use App\Dto\CommitTypes\QuoteData;
use App\Dto\CommitTypes\ReleaseData;
use App\Dto\CommitTypes\SnippetData;

enum CommitType: string
{
    case Text = 'text';
    case Release = 'release';
    case Poll = 'poll';
    case Snippet = 'snippet';
    case Quote = 'quote';
    case Question = 'question';

    /**
     * The data a commit of this type carries next to its body: plain text commits carry none.
     *
     * @return class-string<CommitTypeData>|null
     */
    public function data(): ?string
    {
        return match ($this) {
            self::Text => null,
            self::Release => ReleaseData::class,
            self::Poll => PollData::class,
            self::Snippet => SnippetData::class,
            self::Quote => QuoteData::class,
            self::Question => QuestionData::class,
        };
    }
}
