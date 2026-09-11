<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Meeting;
use App\Models\PersonalGoal;
use App\Models\ProjectTask;
use App\Models\Reminder;
use App\Models\SavingsContribution;
use App\Models\SiteSetting;
use App\Models\SpiritualPractice;
use App\Models\User;
use App\Services\Ai\ActiveAiClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class DailyInsightService
{
    public function __construct(private readonly ActiveAiClient $ai)
    {
    }

    public function current(User $user, ?Carbon $now = null): array
    {
        $timezone = $user->timezone ?: config('app.timezone', 'Africa/Kampala');
        $local = ($now ?: Carbon::now())->copy()->setTimezone($timezone);

        $bucketHour = intdiv((int) $local->format('H'), 2) * 2;
        $window = $local->format('Y-m-d') . '-' . str_pad((string) $bucketHour, 2, '0', STR_PAD_LEFT);
        $currencyCode = $this->preferredCurrencyCode($user);
        $cacheKey = "daily-insight:{$user->id}:{$currencyCode}:{$window}";

        $expiresAt = $local->copy()
            ->startOfHour()
            ->addHours(2 - ((int) $local->format('H') % 2));

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $insight = $this->generate($user, $local);

        if (($insight['generated_by'] ?? null) === 'admin_ai') {
            Cache::put($cacheKey, $insight, $expiresAt);
            Cache::put("daily-insight:last:{$user->id}:{$currencyCode}", $insight, now()->addDays(7));

            return $insight;
        }

        // Do not keep a temporary provider failure for the full insight window.
        Cache::put($cacheKey, $insight, now()->addMinutes(5));

        return Cache::get("daily-insight:last:{$user->id}:{$currencyCode}", $insight);
    }

    public function refresh(User $user): array
    {
        $this->invalidateFor($user);

        return $this->current($user);
    }

    public function invalidateFor(User|int $user): void
    {
        $id = $user instanceof User ? $user->id : $user;
        $timezone = $user instanceof User
            ? ($user->timezone ?: config('app.timezone', 'Africa/Kampala'))
            : config('app.timezone', 'Africa/Kampala');

        $local = Carbon::now($timezone);

        foreach ([-4, -2, 0, 2, 4] as $offset) {
            $at = $local->copy()->addHours($offset);
            $hour = intdiv((int) $at->format('H'), 2) * 2;
            $window = $at->format('Y-m-d') . '-' . str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
            $currencyCode = $user instanceof User
                ? $this->preferredCurrencyCode($user)
                : null;

            if ($currencyCode) {
                Cache::forget(
                    "daily-insight:{$id}:{$currencyCode}:{$window}"
                );
            }

            // Keep clearing the legacy pre-currency key during rollout.
            Cache::forget("daily-insight:{$id}:{$window}");
        }
    }

    private function generate(User $user, Carbon $local): array
    {
        try {
            // Resolve Admin AI configuration first. This ensures configuration
            // problems are logged distinctly from user-data aggregation errors.
            $provider = $this->ai->configuration($user);

            $context = $this->context($user, $local);
            $historyKey = "daily-insight-ai-history:{$user->id}";
            $history = Cache::get($historyKey, []);
            $recent = collect($history)->take(-10)->values()->all();

            $system = <<<'SYS'
You create exactly one concise "Today's Insight" for My Digital Diary.

Return one JSON object only with:
category, type, title, message, action, destination, tone, icon

Rules:
1. Use the supplied aggregated user data when there is enough relevant information.
2. type = "personal" when you use the user's supplied data; otherwise "general".
3. Do not invent facts or infer sensitive information.
4. Never expose API keys, credentials, raw private diary text or document contents.
5. Spiritual language must be inclusive. Only use religion-specific wording if faith_path is explicitly supplied.
6. Avoid generic motivational slogans and repeated wording.
7. Prefer a useful observation plus one realistic next action.
8. Keep title under 90 characters and message under 350 characters.
9. destination must be one of:
   daily-planner, meetings, budgets, expenses, savings-goals,
   project-tasks, reminders, education-plans, spiritual-practices,
   wellbeing, financial
10. tone must be one of:
    emerald, amber, sky, indigo, fuchsia, violet, rose, teal
11. icon must be a Font Awesome suffix like:
    fa-list-check, fa-wallet, fa-calendar-days, fa-seedling
12. For EVERY financial or money-related insight, use ONLY the supplied
    preferred_currency and the supplied *_display monetary values.
13. Never relabel a base/default currency amount as the user's preferred
    currency. Never invent an exchange rate or currency symbol.
14. If you mention monthly income, expenses, budget or savings, copy the
    corresponding *_display value exactly as supplied.
SYS;

            $prompt = "Local date/time: {$local->toIso8601String()}\n"
                . "Active admin AI provider: {$provider['name']} ({$provider['key']})\n\n"
                . "Aggregated user context:\n"
                . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                . "\n\nRecent insights to avoid repeating:\n"
                . ($recent
                    ? json_encode($recent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    : 'None');

            $result = $this->ai->json($system, $prompt, $user);
            $insight = $this->normalise($result, $local);

            $hash = hash(
                'sha256',
                Str::lower($insight['title'] . '|' . $insight['message'])
            );

            // If the model repeats itself, request one fresh retry rather than
            // immediately falling back to generic local copy.
            if (collect($recent)->contains(fn ($item) => ($item['hash'] ?? null) === $hash)) {
                $retryPrompt = $prompt
                    . "\n\nIMPORTANT: Your previous draft duplicated a recent insight. "
                    . "Choose a different data point, category or practical action.";

                $result = $this->ai->json($system, $retryPrompt, $user);
                $insight = $this->normalise($result, $local);
                $hash = hash(
                    'sha256',
                    Str::lower($insight['title'] . '|' . $insight['message'])
                );
            }

            $history[] = [
                'hash' => $hash,
                'title' => $insight['title'],
                'message' => $insight['message'],
            ];

            Cache::put(
                $historyKey,
                array_slice($history, -20),
                now()->addDays(14)
            );

            return $insight + [
                'provider' => $provider['key'],
                'provider_name' => $provider['name'],
                'model' => $provider['model'] ?: null,
            ];
        } catch (Throwable $e) {
            Log::warning('Today Insight generation failed', [
                'user_id' => $user->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            report($e);

            return $this->failureFallback($local, $e);
        }
    }

    /**
     * Build an aggregated context defensively.
     *
     * This is deliberately schema-aware because My Digital Diary has evolved
     * across deployments. An optional/migrating module must never make Today’s
     * Insight look like the AI provider is offline.
     */
    private function context(User $user, Carbon $local): array
    {
        $uid = (int) $user->id;
        $dayStartUtc = $local->copy()->startOfDay()->utc();
        $dayEndUtc = $local->copy()->endOfDay()->utc();

        $monthStart = $local->copy()->startOfMonth()->toDateString();
        $monthEnd = $local->copy()->endOfMonth()->toDateString();

        $context = [
            'local_date' => $local->toDateString(),
            'daypart' => $this->daypart($local),
        ];

        $context['meetings_today'] = $this->safeCount(
            Meeting::class,
            $uid,
            fn (Builder $q, array $columns) =>
                in_array('start_at', $columns, true)
                    ? $q->whereBetween('start_at', [$dayStartUtc, $dayEndUtc])
                    : $q
        );

        $context['upcoming_meetings_48h'] = $this->safeCount(
            Meeting::class,
            $uid,
            fn (Builder $q, array $columns) =>
                in_array('start_at', $columns, true)
                    ? $q->whereBetween('start_at', [now(), now()->addHours(48)])
                    : $q
        );

        /*
         * Financial records are stored in the site's base currency. Today's
         * Insight must never display those raw base amounts when the user has
         * selected a different personal currency.
         *
         * Resolve the user's preferred currency once, convert every monetary
         * value, and give AI both numeric and pre-formatted values. The prompt
         * explicitly requires *_display whenever an amount is mentioned.
         */
        $settings = SiteSetting::current();
        $currencyCode = $this->preferredCurrencyCode($user);
        $currencyMeta = $settings->currencyMeta($currencyCode);

        $context['preferred_currency'] = [
            'code' => $currencyMeta['code'],
            'symbol' => $currencyMeta['symbol'],
            'decimals' => (int) $currencyMeta['decimals'],
        ];

        $monthlyIncomeBase = $this->safeSum(
            Income::class,
            $uid,
            'amount',
            ['received_at', 'date', 'created_at'],
            $monthStart,
            $monthEnd
        );

        $monthlyExpensesBase = $this->safeSum(
            Expense::class,
            $uid,
            'amount',
            ['spent_at', 'date', 'created_at'],
            $monthStart,
            $monthEnd
        );

        $monthlyBudgetBase = $this->safeBudgetAmount(
            $uid,
            $monthStart,
            $monthEnd
        );

        $monthlySavingsBase = $this->safeSum(
            SavingsContribution::class,
            $uid,
            'amount',
            [
                'contributed_at',
                'contribution_date',
                'date',
                'created_at',
            ],
            $monthStart,
            $monthEnd
        );

        $this->addPersonalisedMoney(
            $context,
            'monthly_income',
            $monthlyIncomeBase,
            $settings,
            $user
        );

        $this->addPersonalisedMoney(
            $context,
            'monthly_expenses',
            $monthlyExpensesBase,
            $settings,
            $user
        );

        $this->addPersonalisedMoney(
            $context,
            'monthly_budget',
            $monthlyBudgetBase,
            $settings,
            $user
        );

        $this->addPersonalisedMoney(
            $context,
            'monthly_savings',
            $monthlySavingsBase,
            $settings,
            $user
        );

        $context['overdue_project_tasks'] = $this->safeCount(
            ProjectTask::class,
            $uid,
            function (Builder $q, array $columns) use ($local): Builder {
                if (in_array('status', $columns, true)) {
                    $q->whereIn('status', ['todo', 'pending', 'in_progress']);
                }

                foreach (['due_date', 'due_at'] as $dateColumn) {
                    if (in_array($dateColumn, $columns, true)) {
                        return $q->whereDate($dateColumn, '<', $local->toDateString());
                    }
                }

                return $q;
            }
        );

        $context['reminders_today'] = $this->safeCount(
            Reminder::class,
            $uid,
            function (Builder $q, array $columns) use ($local): Builder {
                if (in_array('is_active', $columns, true)) {
                    $q->where('is_active', true);
                }

                foreach (['next_run_at', 'remind_at', 'due_at', 'due_date'] as $dateColumn) {
                    if (in_array($dateColumn, $columns, true)) {
                        return $q->whereDate($dateColumn, $local->toDateString());
                    }
                }

                return $q;
            }
        );

        $context['active_goals'] = $this->safeCount(
            PersonalGoal::class,
            $uid,
            function (Builder $q, array $columns): Builder {
                if (in_array('is_archived', $columns, true)) {
                    $q->where('is_archived', false);
                }

                if (in_array('status', $columns, true)) {
                    $q->whereIn('status', ['not_started', 'in_progress', 'active']);
                }

                return $q;
            }
        );

        $spiritual = $this->safeSpiritualContext($uid, $local);
        $context = array_merge($context, $spiritual);

        return array_filter(
            $context,
            fn ($value) => $value !== null
        );
    }

    /**
     * Resolve the currency selected by the user, falling back safely to the
     * site's configured base currency on older deployments.
     */
    private function preferredCurrencyCode(User $user): string
    {
        try {
            if (method_exists($user, 'preferredCurrencyCode')) {
                $code = strtoupper(
                    trim((string) $user->preferredCurrencyCode())
                );

                if ($code !== '') {
                    return $code;
                }
            }
        } catch (Throwable $e) {
            Log::notice(
                'Today Insight could not resolve user currency preference',
                [
                    'user_id' => $user->id,
                    'message' => $e->getMessage(),
                ]
            );
        }

        try {
            return strtoupper(
                SiteSetting::current()->default_currency_code ?: 'UGX'
            );
        } catch (Throwable) {
            return 'UGX';
        }
    }

    /**
     * Put a monetary value into AI context in the user's selected currency.
     *
     * Example keys:
     * monthly_income         => 125.50
     * monthly_income_display => "$ 125.50"
     *
     * We deliberately do not expose the raw base-currency value under another
     * prompt-visible key. That prevents the model from accidentally choosing
     * the wrong amount or symbol in a financial insight.
     */
    private function addPersonalisedMoney(
        array &$context,
        string $key,
        ?float $baseAmount,
        SiteSetting $settings,
        User $user
    ): void {
        if ($baseAmount === null) {
            return;
        }

        $code = $this->preferredCurrencyCode($user);
        $meta = $settings->currencyMeta($code);
        $converted = $settings->convertBaseAmount(
            (float) $baseAmount,
            $meta['code']
        );

        $context[$key] = round(
            $converted,
            max(0, (int) $meta['decimals'])
        );

        $context[$key . '_display'] =
            $settings->formatMoneyForUser(
                (float) $baseAmount,
                $user
            );
    }

    private function safeSpiritualContext(int $uid, Carbon $local): array
    {
        $table = (new SpiritualPractice())->getTable();

        if (! Schema::hasTable($table)) {
            return [];
        }

        try {
            $columns = Schema::getColumnListing($table);
            $query = SpiritualPractice::query()->where('user_id', $uid);

            if (in_array('deleted_at', $columns, true)) {
                $query->whereNull('deleted_at');
            }

            $dateColumn = collect(['practiced_at', 'created_at'])
                ->first(fn ($column) => in_array($column, $columns, true));

            $recentCount = null;
            if ($dateColumn) {
                $recentCount = (clone $query)
                    ->where($dateColumn, '>=', $local->copy()->subDays(7))
                    ->count();
            }

            $latest = (clone $query)
                ->orderByDesc($dateColumn ?: 'id')
                ->first();

            return array_filter([
                'recent_spiritual_practices_7d' => $recentCount,
                'faith_path' => in_array('faith_path', $columns, true)
                    ? $latest?->faith_path
                    : null,
            ], fn ($value) => $value !== null);
        } catch (Throwable $e) {
            Log::notice('Today Insight skipped Spiritual Growth context', [
                'user_id' => $uid,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function safeCount(
        string $modelClass,
        int $uid,
        callable $apply
    ): ?int {
        try {
            $model = new $modelClass();
            $table = $model->getTable();

            if (! Schema::hasTable($table)) {
                return null;
            }

            $columns = Schema::getColumnListing($table);
            if (! in_array('user_id', $columns, true)) {
                return null;
            }

            $query = $modelClass::query()->where('user_id', $uid);

            if (in_array('deleted_at', $columns, true)) {
                $query->whereNull('deleted_at');
            }

            $query = $apply($query, $columns) ?: $query;

            return (int) $query->count();
        } catch (Throwable $e) {
            Log::notice('Today Insight skipped context count', [
                'model' => $modelClass,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function safeSum(
        string $modelClass,
        int $uid,
        string $amountColumn,
        array $dateCandidates,
        string $from,
        string $to
    ): ?float {
        try {
            $model = new $modelClass();
            $table = $model->getTable();

            if (! Schema::hasTable($table)) {
                return null;
            }

            $columns = Schema::getColumnListing($table);

            if (
                ! in_array('user_id', $columns, true)
                || ! in_array($amountColumn, $columns, true)
            ) {
                return null;
            }

            $query = $modelClass::query()->where('user_id', $uid);

            if (in_array('deleted_at', $columns, true)) {
                $query->whereNull('deleted_at');
            }

            $dateColumn = collect($dateCandidates)
                ->first(fn ($column) => in_array($column, $columns, true));

            if ($dateColumn) {
                $query->whereBetween($dateColumn, [$from, $to . ' 23:59:59']);
            }

            return (float) $query->sum($amountColumn);
        } catch (Throwable $e) {
            Log::notice('Today Insight skipped context sum', [
                'model' => $modelClass,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function safeBudgetAmount(int $uid, string $from, string $to): ?float
    {
        try {
            $model = new Budget();
            $table = $model->getTable();

            if (! Schema::hasTable($table)) {
                return null;
            }

            $columns = Schema::getColumnListing($table);
            if (! in_array('user_id', $columns, true)) {
                return null;
            }

            $amountColumn = collect([
                'amount',
                'planned_amount',
                'budget_amount',
                'total_amount',
            ])->first(fn ($column) => in_array($column, $columns, true));

            if (! $amountColumn) {
                return null;
            }

            $query = Budget::query()->where('user_id', $uid);

            if (in_array('deleted_at', $columns, true)) {
                $query->whereNull('deleted_at');
            }

            if (in_array('period', $columns, true)) {
                $query->where('period', 'monthly');
            } else {
                $dateColumn = collect(['start_date', 'date', 'created_at'])
                    ->first(fn ($column) => in_array($column, $columns, true));

                if ($dateColumn) {
                    $query->whereBetween($dateColumn, [$from, $to . ' 23:59:59']);
                }
            }

            return (float) $query->sum($amountColumn);
        } catch (Throwable $e) {
            Log::notice('Today Insight skipped budget context', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function normalise(array $result, Carbon $local): array
    {
        $destinations = [
            'daily-planner',
            'meetings',
            'budgets',
            'expenses',
            'savings-goals',
            'project-tasks',
            'reminders',
            'education-plans',
            'spiritual-practices',
            'wellbeing',
            'financial',
        ];

        $routeNames = [
            'daily-planner' => 'daily-planner.index',
            'meetings' => 'meetings.index',
            'budgets' => 'budgets.index',
            'expenses' => 'expenses.index',
            'savings-goals' => 'savings-goals.index',
            'project-tasks' => 'project-tasks.index',
            'reminders' => 'reminders.index',
            'education-plans' => 'education-plans.index',
            'spiritual-practices' => 'spiritual-practices.index',
            'wellbeing' => 'wellbeing.index',
            'financial' => 'financial-planner.index',
        ];

        $destination = in_array(
            (string) ($result['destination'] ?? ''),
            $destinations,
            true
        )
            ? (string) $result['destination']
            : 'daily-planner';

        $title = trim((string) ($result['title'] ?? ''));
        $message = trim((string) ($result['message'] ?? ''));

        if ($title === '' || $message === '') {
            throw new \RuntimeException('The AI insight response was incomplete.');
        }

        $next = $local->copy()
            ->startOfHour()
            ->addHours(2 - ((int) $local->format('H') % 2));

        return [
            'category' => Str::limit(
                trim((string) ($result['category'] ?? 'Today')),
                80,
                ''
            ),
            'type' => in_array(
                (string) ($result['type'] ?? ''),
                ['personal', 'general'],
                true
            ) ? (string) $result['type'] : 'general',
            'icon' => preg_match(
                '/^fa-[a-z0-9-]+$/',
                (string) ($result['icon'] ?? '')
            ) ? (string) $result['icon'] : 'fa-wand-magic-sparkles',
            'tone' => in_array(
                (string) ($result['tone'] ?? ''),
                ['emerald', 'amber', 'sky', 'indigo', 'fuchsia', 'violet', 'rose', 'teal'],
                true
            ) ? (string) $result['tone'] : 'emerald',
            'title' => Str::limit($title, 180, ''),
            'message' => Str::limit($message, 650, ''),
            'action' => Str::limit(
                trim((string) ($result['action'] ?? 'Open planner')),
                80,
                ''
            ),
            'destination' => $destination,
            'route_name' => $routeNames[$destination],
            'generated_by' => 'admin_ai',
            'generated_at' => now()->toIso8601String(),
            'refresh_after' => $next->toIso8601String(),
        ];
    }

    private function failureFallback(Carbon $local, Throwable $error): array
    {
        $next = $local->copy()
            ->startOfHour()
            ->addHours(2 - ((int) $local->format('H') % 2));

        $configurationError = Str::contains(
            Str::lower($error->getMessage()),
            [
                'admin settings',
                'no default ai',
                'configuration is incomplete',
                'disabled in admin',
                'missing from ai providers',
                'no api endpoint',
                'no default model',
            ]
        );

        return [
            'category' => 'Today',
            'type' => 'general',
            'icon' => 'fa-wand-magic-sparkles',
            'tone' => $configurationError ? 'amber' : 'emerald',
            'title' => $configurationError
                ? 'AI insight needs administrator configuration.'
                : 'Your personalised insight is temporarily unavailable.',
            'message' => $configurationError
                ? 'An administrator needs to select an enabled AI provider and save its shared API key in AI Settings.'
                : 'You can continue using your planner and diary normally.',
            'action' => 'Open planner',
            'destination' => 'daily-planner',
            'route_name' => 'daily-planner.index',
            'generated_by' => 'ai_fallback',
            'generated_at' => now()->toIso8601String(),
            'refresh_after' => $next->toIso8601String(),
        ];
    }

    private function daypart(Carbon $local): string
    {
        $hour = (int) $local->format('H');

        return match (true) {
            $hour < 12 => 'morning',
            $hour < 17 => 'afternoon',
            $hour < 21 => 'evening',
            default => 'night',
        };
    }
}
