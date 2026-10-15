<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How long a poll stays open once its commit is approved, in minutes.
 */
enum PollDuration: int
{
    case OneHour = 60;
    case OneDay = 1440;
    case ThreeDays = 4320;
    case SevenDays = 10080;
}
