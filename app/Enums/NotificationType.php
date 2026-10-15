<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case Followed = 'followed';
    case Replied = 'replied';
    case Mentioned = 'mentioned';
    case Quoted = 'quoted';
    case AnswerAccepted = 'answer_accepted';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
