<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\Dto\CommitTypeData;
use App\Dto\CommitTypes\ReleaseData;

enum CommitType: string
{
    case Text = 'text';
    case Release = 'release';

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
        };
    }
}
