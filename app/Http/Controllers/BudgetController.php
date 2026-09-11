<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Debt;
use App\Services\BudgetExpenseService;
use App\Services\BudgetImportService;
use App\Services\DailyInsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function index(Request $request): View
    {
        $selectedMonth = $this->normaliseMonth((string) $request->query('budget_month', now()->format('Y-m')));
        $previousMonth = \Carbon\Carbon::createFromFormat('Y-m', $selectedMonth)->subMonthNoOverflow()->format('Y-m');
        $nextMonth = \Carbon\Carbon::createFromFormat('Y-m', $selectedMonth)->addMonthNoOverflow()->format('Y-m');

        $tableExists = false;
        $columns = [];
        $items = collect();
        $debts = collect();
        $availableMonths = collect([$selectedMonth]);
        $expenseLinkingReady = false;
        $pageLoadWarning = null;
        $statistics = ['total'=>0.0,'monthly'=>0.0,'weekly'=>0.0,'items'=>0];
        $total = 0.0;
        $expensedCount = 0;

        try {
            $tableExists = Schema::hasTable('budgets');
            $columns = $tableExists ? Schema::getColumnListing('budgets') : [];

            if ($tableExists && in_array('user_id', $columns, true)) {
                $base = Budget::query()->where('user_id', $request->user()->id);
                if (in_array('is_archived', $columns, true)) {
                    $base->where('is_archived', false);
                }

                $statistics['items'] = (int) (clone $base)->count();
                if (in_array('amount', $columns, true)) {
                    $statistics['total'] = (float) (clone $base)->sum('amount');
                }

                $monthQuery = clone $base;
                if (in_array('month_year', $columns, true)) $monthQuery->where('month_year', $selectedMonth);
                if (in_array('period', $columns, true)) $monthQuery->where('period', 'monthly');
                $items = $monthQuery->orderBy('id')->get();

                if (in_array('amount', $columns, true)) {
                    $total = (float) $items->sum('amount');
                    $statistics['monthly'] = $total;
                }

                if (in_array('period', $columns, true) && in_array('amount', $columns, true)) {
                    $statistics['weekly'] = (float) (clone $base)->where('period', 'weekly')->sum('amount');
                }

                if (in_array('is_expensed', $columns, true)) {
                    $expensedCount = $items->filter(fn ($item) => (bool) data_get($item, 'is_expensed', false))->count();
                }

                if (in_array('month_year', $columns, true)) {
                    $availableMonths = (clone $base)->whereNotNull('month_year')->distinct()->orderByDesc('month_year')->pluck('month_year')->filter()->values();
                    if (! $availableMonths->contains($selectedMonth)) $availableMonths->prepend($selectedMonth);
                }
            } elseif ($tableExists) {
                $pageLoadWarning = 'The budgets table is missing the user_id ownership column. Run the latest migrations.';
            } else {
                $pageLoadWarning = 'The budgets table does not exist yet. Run the latest migrations.';
            }

            try { $expenseLinkingReady = $this->applicationReady(); }
            catch (\Throwable $exception) { report($exception); $expenseLinkingReady = false; }

            if (Schema::hasTable('debts') && Schema::hasColumn('debts','user_id') && Schema::hasColumn('debts','type') && Schema::hasColumn('debts','status')) {
                try {
                    $q = Debt::query()->where('user_id',$request->user()->id)->where('type','borrowed')->where('status','outstanding');
                    if (Schema::hasColumn('debts','is_archived')) $q->where('is_archived',false);
                    $select = ['id'];
                    foreach (['person_name','amount','due_date'] as $column) if (Schema::hasColumn('debts',$column)) $select[] = $column;
                    $debts = $q->orderBy('id')->get($select);
                } catch (\Throwable $exception) { report($exception); $debts = collect(); }
            }
        } catch (\Throwable $exception) {
            report($exception);
            $items = collect();
            $debts = collect();
            $availableMonths = collect([$selectedMonth]);
            $expenseLinkingReady = false;
            $pageLoadWarning = 'Budgets opened in safe mode because some data could not be loaded. Run the latest migrations and clear Laravel caches.';
        }

        return view('budgets.index', compact(
            'tableExists','columns','items','debts','availableMonths','selectedMonth',
            'previousMonth','nextMonth','expenseLinkingReady','statistics','total',
            'expensedCount','pageLoadWarning'
        ));
    }

    public function store(
        Request $request,
        BudgetExpenseService $flow
    ): RedirectResponse {
        if (! $this->basicBudgetSchemaReady()) {
            return back()->withInput()->with('error', 'Budgets cannot be saved until the latest database migrations are applied.');
        }
        $data = $this->validatedBudgetData($request);

        $budget = Budget::create([
            'user_id' => $request->user()->id,
            'category' => $data['category'],
            'amount' => $data['amount'],
            'period' => $data['period'],
            'month_year' => $data['month_year'],
            'notes' => $data['notes'] ?? null,
            'application_type' => $data['application_type'],
            'debt_id' => $data['application_type'] === 'debt_payment'
                ? ($data['debt_id'] ?? null)
                : null,
            'is_expensed' => false,
            'expensed_at' => null,
            'applied_amount' => 0,
        ]);

        if ($data['is_expensed']) {
            $flow->apply($budget, true);
        }

        $this->invalidateInsight($request);

        return redirect()
            ->route('budgets.index', [
                'budget_month' => $data['month_year'],
            ])
            ->with('success', $this->successMessage($budget->fresh()));
    }

    public function update(
        Request $request,
        int $id,
        BudgetExpenseService $flow
    ): RedirectResponse {
        if (! $this->basicBudgetSchemaReady()) {
            return back()->withInput()->with('error', 'Budgets cannot be updated until the latest database migrations are applied.');
        }
        $budget = $this->ownedBudgetOrFail($request, $id);

        if ((bool) $budget->is_expensed) {
            $flow->apply($budget, false);
            $budget->refresh();
        }

        $data = $this->validatedBudgetData($request);

        $budget->update([
            'category' => $data['category'],
            'amount' => $data['amount'],
            'period' => $data['period'],
            'month_year' => $data['month_year'],
            'notes' => $data['notes'] ?? null,
            'application_type' => $data['application_type'],
            'debt_id' => $data['application_type'] === 'debt_payment'
                ? ($data['debt_id'] ?? null)
                : null,
            'is_expensed' => false,
            'expensed_at' => null,
            'applied_amount' => 0,
        ]);

        if ($data['is_expensed']) {
            $flow->apply($budget->fresh(), true);
        }

        $this->invalidateInsight($request);

        return redirect()
            ->route('budgets.index', [
                'budget_month' => $data['month_year'],
            ])
            ->with('success', $this->successMessage($budget->fresh()));
    }

    public function destroy(
        Request $request,
        int $id,
        BudgetExpenseService $flow
    ): RedirectResponse {
        $budget = $this->ownedBudgetOrFail($request, $id);
        $month = $budget->month_year ?: now()->format('Y-m');

        $flow->detachExpense($budget);
        $budget->delete();

        $this->invalidateInsight($request);

        return redirect()
            ->route('budgets.index', [
                'budget_month' => $month,
            ])
            ->with('success', 'Budget item deleted.');
    }

    public function setExpenseStatus(
        Request $request,
        int $id,
        BudgetExpenseService $flow
    ): RedirectResponse|JsonResponse {
        try {
            $budget = $this->ownedBudgetOrFail($request, $id);

            $data = $request->validate([
                'is_expensed' => ['required', 'boolean'],
                'spent_at' => ['nullable', 'date'],
            ]);

            $budget = $flow->apply(
                $budget,
                (bool) $data['is_expensed'],
                $data['spent_at'] ?? null
            );

            $this->invalidateInsight($request);

            $message = ! $budget->is_expensed
                ? 'Budget payment reversed.'
                : (
                    $budget->application_type === 'debt_payment'
                        ? 'Debt reduced and Income balance updated.'
                        : 'Added to Expenses and Income balance updated.'
                );

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => $budget,
                ]);
            }

            return back()->with('success', $message);
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => collect($exception->errors())
                        ->flatten()
                        ->first(),
                    'errors' => $exception->errors(),
                ], 422);
            }

            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not apply this Budget payment.',
                ], 500);
            }

            return back()->with(
                'error',
                'Could not apply this Budget payment.'
            );
        }
    }

    public function duplicateMonth(
        Request $request
    ): RedirectResponse|JsonResponse {
        $data = $request->validate([
            'source_month' => [
                'required',
                'regex:/^\d{4}-\d{2}$/',
            ],
            'target_month' => [
                'required',
                'regex:/^\d{4}-\d{2}$/',
            ],
        ]);

        if ($data['source_month'] === $data['target_month']) {
            throw ValidationException::withMessages([
                'target_month' => 'Choose a different target month.',
            ]);
        }

        $source = Budget::query()
            ->where('user_id', $request->user()->id)
            ->where('period', 'monthly')
            ->where('month_year', $data['source_month'])
            ->when(
                Schema::hasColumn('budgets', 'is_archived'),
                fn ($query) => $query->where('is_archived', false)
            )
            ->orderBy('id')
            ->get();

        if ($source->isEmpty()) {
            throw ValidationException::withMessages([
                'source_month' => 'No Budget items were found in the source month.',
            ]);
        }

        $targetExists = Budget::query()
            ->where('user_id', $request->user()->id)
            ->where('period', 'monthly')
            ->where('month_year', $data['target_month'])
            ->exists();

        if ($targetExists) {
            throw ValidationException::withMessages([
                'target_month' => 'The target month already has Budget items.',
            ]);
        }

        $created = [];

        foreach ($source as $item) {
            $created[] = Budget::create([
                'user_id' => $request->user()->id,
                'source_budget_id' => Schema::hasColumn(
                    'budgets',
                    'source_budget_id'
                ) ? $item->id : null,
                'category' => $item->category,
                'amount' => $item->amount,
                'period' => 'monthly',
                'month_year' => $data['target_month'],
                'notes' => $item->notes,
                'application_type' => $item->application_type ?: 'expense',
                'debt_id' => $item->debt_id,
                'is_expensed' => false,
                'expensed_at' => null,
                'applied_amount' => 0,
            ]);
        }

        $this->invalidateInsight($request);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $created,
            ], 201);
        }

        return redirect()
            ->route('budgets.index', [
                'budget_month' => $data['target_month'],
            ])
            ->with(
                'success',
                count($created).' Budget item(s) duplicated.'
            );
    }

    public function extractImport(
        Request $request,
        BudgetImportService $service
    ): JsonResponse {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:20480',
                'mimes:xlsx,xls,csv,pdf,doc,docx,jpg,jpeg,png,webp',
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $service->extract(
                $request->user(),
                $request->file('file')
            ),
        ]);
    }

    public function confirmImport(
        Request $request
    ): RedirectResponse|JsonResponse {
        $items = $request->input('items');

        if (
            ! is_array($items) &&
            $request->filled('items_json')
        ) {
            $items = json_decode(
                (string) $request->input('items_json'),
                true
            );
        }

        $request->merge(['items' => $items]);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.category' => ['required', 'string', 'max:255'],
            'items.*.planned_amount' => ['required', 'numeric', 'min:0'],
            'items.*.period' => ['nullable', 'in:weekly,monthly,annually'],
            'items.*.month_year' => ['nullable', 'string', 'max:20'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.notes' => ['nullable', 'string'],
            'filename' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:30'],
            'confidence' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $created = [];

        foreach ($data['items'] as $item) {
            $created[] = Budget::create([
                'user_id' => $request->user()->id,
                'category' => $item['category'],
                'amount' => $item['planned_amount'],
                'period' => $item['period'] ?? 'monthly',
                'month_year' => $item['month_year'] ?? null,
                'notes' => trim(
                    ($item['description'] ?? '') .
                    (
                        ($item['notes'] ?? '')
                            ? "\n".$item['notes']
                            : ''
                    )
                ) ?: null,
                'application_type' => 'expense',
                'debt_id' => null,
                'is_expensed' => false,
                'applied_amount' => 0,
                'import_source' => $data['source'] ?? null,
                'import_filename' => $data['filename'] ?? null,
                'import_confidence' => $data['confidence'] ?? null,
                'import_metadata' => ['source_item' => $item],
            ]);
        }

        $this->invalidateInsight($request);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $created,
            ], 201);
        }

        return redirect()
            ->route('budgets.index')
            ->with(
                'success',
                count($created).' Budget line(s) imported.'
            );
    }

    private function validatedBudgetData(Request $request): array
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'period' => [
                'required',
                Rule::in(['weekly', 'monthly', 'annually']),
            ],
            'month_year' => [
                'required',
                'regex:/^\d{4}-\d{2}$/',
            ],
            'notes' => ['nullable', 'string'],
            'application_type' => [
                'required',
                Rule::in(['expense', 'debt_payment']),
            ],
            'debt_id' => [
                'nullable',
                'integer',
                Rule::exists('debts', 'id')->where(
                    fn ($query) => $query
                        ->where('user_id', $request->user()->id)
                        ->where('type', 'borrowed')
                ),
            ],
            'is_expensed' => ['nullable', 'boolean'],
        ]);

        $data['is_expensed'] = $request->boolean('is_expensed');

        if (
            $data['application_type'] === 'debt_payment' &&
            $data['is_expensed'] &&
            empty($data['debt_id'])
        ) {
            throw ValidationException::withMessages([
                'debt_id' => 'Select which debt this payment should reduce.',
            ]);
        }

        return $data;
    }

    private function ownedBudgetOrFail(
        Request $request,
        int $id
    ): Budget {
        return Budget::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
    }

    private function normaliseMonth(string $month): string
    {
        return preg_match('/^\d{4}-\d{2}$/', $month)
            ? $month
            : now()->format('Y-m');
    }

    private function basicBudgetSchemaReady(): bool
    {
        try {
            if (! Schema::hasTable('budgets')) return false;
            foreach (['user_id','category','amount','period','month_year'] as $column) {
                if (! Schema::hasColumn('budgets', $column)) return false;
            }
            return true;
        } catch (\Throwable $exception) {
            report($exception);
            return false;
        }
    }

    private function applicationReady(): bool
    {
        foreach ([
            ['budgets', 'is_expensed'],
            ['budgets', 'application_type'],
            ['budgets', 'debt_id'],
            ['budgets', 'applied_amount'],
            ['expenses', 'budget_id'],
        ] as [$table, $column]) {
            if (
                ! Schema::hasTable($table) ||
                ! Schema::hasColumn($table, $column)
            ) {
                return false;
            }
        }

        return Schema::hasTable('debts');
    }

    private function invalidateInsight(Request $request): void
    {
        try {
            app(DailyInsightService::class)
                ->invalidateFor($request->user());
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function successMessage(Budget $budget): string
    {
        if (! $budget->is_expensed) {
            return 'Budget item saved.';
        }

        return $budget->application_type === 'debt_payment'
            ? 'Budget saved and debt reduced.'
            : 'Budget saved and Expense created.';
    }
}
