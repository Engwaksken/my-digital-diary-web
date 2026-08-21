{{-- Add inside the dashboard's opening @php block, BEFORE:
     $todayFocus = collect($todaysPlanItems ?? [])->take(4);
--}}

@php
    /*
     * Today's Focus fallback:
     * if DashboardController did not provide $todaysPlanItems, read the same
     * Daily Planner tables used by the period review service.
     */
    $resolvedTodayFocus = collect($todaysPlanItems ?? []);

    if (
        $resolvedTodayFocus->isEmpty()
        && class_exists(\App\Services\PeriodReviewMetricsService::class)
    ) {
        try {
            $resolvedTodayFocus = app(
                \App\Services\PeriodReviewMetricsService::class
            )->todayFocus(auth()->user(), 6);
        } catch (\Throwable $e) {
            report($e);
            $resolvedTodayFocus = collect();
        }
    }

    $todayFocus = $resolvedTodayFocus->take(6);
@endphp

{{-- REMOVE/REPLACE the old line:
$todayFocus = collect($todaysPlanItems ?? [])->take(4);
--}}
