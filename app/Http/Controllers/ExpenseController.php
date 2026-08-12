<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends CrudController
{
    protected string $model = Expense::class;
    protected string $routeName = 'expenses';
    protected string $title = 'Expense';
    protected string $icon = 'fa-solid fa-receipt';
    protected string $accent = 'rose';
    protected string $dateField = 'spent_at';

    protected array $fields = [
        ['name' => 'category', 'label' => 'Category', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Groceries, Rent, Transport'],
        ['name' => 'amount', 'label' => 'Amount', 'type' => 'number', 'required' => true, 'placeholder' => '0.00', 'money' => true, 'hint' => 'Leave blank if you add itemized line items below instead — the total is computed from those.'],
        ['name' => 'spent_at', 'label' => 'Date', 'type' => 'date', 'required' => true],
        ['name' => 'payment_method', 'label' => 'Payment Method', 'type' => 'text', 'placeholder' => 'e.g. Cash, Card, Mobile Money'],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
    ];

    protected array $rules = [
        'category' => 'required|string|max:255',
        'amount' => 'nullable|numeric|min:0',
        'spent_at' => 'required|date',
        'payment_method' => 'nullable|string|max:255',
        'notes' => 'nullable|string',
        'items' => 'nullable|array',
        'items.*.description' => 'required_with:items|string|max:255',
        'items.*.quantity' => 'required_with:items|numeric|min:0.01',
        'items.*.unit_price' => 'required_with:items|numeric|min:0',
    ];

    /**
     * Overrides the generic CrudController::store()/update() — an
     * itemized expense (multiple description/quantity/unit-price rows,
     * like an invoice) needs its own `expense_items` rows created/replaced
     * and its total `amount` computed from them, which the generic
     * single-model create/update can't express. See
     * crud/extras/expenses-extra.blade.php for the "Itemize" UI that
     * builds the `items[]` array this expects.
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules);
        $data['user_id'] = $request->user()->id;
        $items = $data['items'] ?? [];
        unset($data['items']);

        if (! empty($items)) {
            $data['amount'] = collect($items)->sum(fn ($i) => $i['quantity'] * $i['unit_price']);
        } elseif (! isset($data['amount'])) {
            return back()->withErrors(['amount' => 'Enter an amount, or add at least one line item.'])->withInput();
        }

        $expense = Expense::create($data);

        if (! empty($items)) {
            $this->syncItems($expense, $items);
        }

        return redirect()->route('expenses.index')->with('success', 'Expense created.');
    }

    public function update(Request $request, int $id)
    {
        $expense = Expense::where('user_id', $request->user()->id)->findOrFail($id);

        $data = $request->validate($this->rules);
        $items = $data['items'] ?? [];
        unset($data['items']);

        if (! empty($items)) {
            $data['amount'] = collect($items)->sum(fn ($i) => $i['quantity'] * $i['unit_price']);
        } elseif (! isset($data['amount'])) {
            return back()->withErrors(['amount' => 'Enter an amount, or add at least one line item.'])->withInput();
        }

        $expense->update($data);

        // Only touches items if the request actually submitted an `items`
        // array — a plain edit of, say, the notes field on an already-
        // itemized expense doesn't include an items array at all (the
        // standard modal doesn't know about them), and that must NOT be
        // treated the same as "the user explicitly cleared every item."
        if ($request->has('items')) {
            $this->syncItems($expense, $items);
        }

        return redirect()->route('expenses.index')->with('success', 'Expense updated.');
    }

    /**
     * Replaces an expense's line items wholesale rather than diffing
     * against existing rows — simple and safe, since items have no other
     * relations pointing at them (nothing references an expense_items.id
     * from elsewhere). An empty $items array just clears any previously
     * itemized rows, e.g. if a user switches an expense back to a single
     * plain amount.
     */
    private function syncItems(Expense $expense, array $items): void
    {
        $expense->items()->delete();

        foreach ($items as $item) {
            $expense->items()->create([
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['quantity'] * $item['unit_price'],
            ]);
        }
    }

    protected function stats(\Illuminate\Http\Request $request): array
    {
        $userId = $request->user()->id;
        $base = Expense::where('user_id', $userId);
        $thisMonth = (clone $base)->whereBetween('spent_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount');
        $topCategory = (clone $base)->whereBetween('spent_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('category, SUM(amount) as total')->groupBy('category')->orderByDesc('total')->first();

        return [
            ['label' => 'This month', 'value' => format_money($thisMonth), 'icon' => 'fa-solid fa-receipt', 'color' => 'rose'],
            ['label' => 'Top category (this month)', 'value' => $topCategory ? $topCategory->category . ' (' . format_money($topCategory->total) . ')' : '—', 'icon' => 'fa-solid fa-chart-pie', 'color' => 'orange'],
            ['label' => 'Entries', 'value' => (string) $base->count(), 'icon' => 'fa-solid fa-list-ol', 'color' => 'slate'],
        ];
    }

    protected function chart(\Illuminate\Http\Request $request): ?array
    {
        $userId = $request->user()->id;
        $byCategory = Expense::where('user_id', $userId)
            ->whereBetween('spent_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('category, SUM(amount) as total')->groupBy('category')->orderByDesc('total')->pluck('total', 'category');

        if ($byCategory->isEmpty()) {
            return null;
        }

        return [
            'type' => 'doughnut',
            'title' => 'Spending by Category (this month)',
            'labels' => $byCategory->keys()->all(),
            'datasets' => [['data' => $byCategory->values()->map(fn ($v) => (float) $v)->all()]],
        ];
    }
}
