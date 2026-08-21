<?php

namespace App\Services;

use App\Models\SpiritualPractice;
use Illuminate\Support\Carbon;

class RecurringSpiritualPracticeService
{
    public function generateUpcoming(SpiritualPractice $parent, int $targetCount = 12): int
    {
        if (! $parent->isRecurring() || $parent->recurrence_parent_id) {
            return 0;
        }

        $existingFutureCount = $parent->recurrenceInstances()
            ->whereDate('practiced_at', '>=', now()->toDateString())
            ->count();

        if ($existingFutureCount >= $targetCount) {
            return 0;
        }

        $needed = $targetCount - $existingFutureCount;
        $lastGenerated = $parent->recurrenceInstances()
            ->orderByDesc('practiced_at')
            ->orderByDesc('id')
            ->first();

        $cursor = $lastGenerated
            ? Carbon::parse($lastGenerated->practiced_at)
            : Carbon::parse($parent->practiced_at);

        $created = 0;
        $safetyLimit = 500;

        while ($created < $needed && $safetyLimit-- > 0) {
            $cursor = $this->nextOccurrence($parent, $cursor);

            if (! $cursor) {
                break;
            }

            if ($parent->recurrence_ends_at &&
                $cursor->copy()->startOfDay()->gt($parent->recurrence_ends_at->copy()->endOfDay())) {
                break;
            }

            $exists = $parent->recurrenceInstances()
                ->whereDate('practiced_at', $cursor->toDateString())
                ->exists();

            if ($exists) {
                continue;
            }

            SpiritualPractice::create([
                'user_id' => $parent->user_id,
                'practice_type' => $parent->practice_type,
                'title' => $parent->title,
                'preacher' => $parent->preacher,
                'theme_topic' => $parent->theme_topic,
                'scriptures' => $parent->scriptures,
                'lessons_learnt' => null,
                'practiced_at' => $cursor->toDateString(),
                'practice_time' => $parent->practice_time,
                'duration_minutes' => $parent->duration_minutes,
                'reflection' => null,
                'next_planned_date' => null,
                'recurrence_parent_id' => $parent->id,
            ]);

            $created++;
        }

        return $created;
    }

    private function nextOccurrence(SpiritualPractice $parent, Carbon $after): ?Carbon
    {
        return match ($parent->recurrence_frequency) {
            'daily' => $after->copy()->addDay(),
            'weekly' => $this->nextWeeklyOccurrence($parent, $after),
            'monthly' => $after->copy()->addMonthNoOverflow(),
            default => null,
        };
    }

    private function nextWeeklyOccurrence(
        SpiritualPractice $parent,
        Carbon $after
    ): ?Carbon {
        $days = array_values(array_filter(
            (array) $parent->recurrence_days_of_week,
            fn ($day) => is_numeric($day) && (int) $day >= 1 && (int) $day <= 7
        ));
        $days = array_map('intval', $days);

        if (empty($days)) {
            return $after->copy()->addWeek();
        }

        $candidate = $after->copy()->addDay();

        for ($i = 0; $i < 8; $i++) {
            if (in_array($candidate->dayOfWeekIso, $days, true)) {
                return $candidate;
            }
            $candidate->addDay();
        }

        return null;
    }
}
