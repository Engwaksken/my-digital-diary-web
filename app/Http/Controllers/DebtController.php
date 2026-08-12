<?php

namespace App\Http\Controllers;

use App\Models\Debt;
use Illuminate\Http\Request;

class DebtController extends CrudController
{
    protected string $model = Debt::class;
    protected string $routeName = 'debts';
    protected string $title = 'Debt';
    protected string $icon = 'fa-solid fa-hand-holding-dollar';
    protected string $accent = 'amber';

    protected array $fields = [
        ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'required' => true, 'options' => [
            'borrowed' => 'Borrowed (I owe them)', 'lent' => 'Lent (they owe me)',
        ]],
        ['name' => 'person_name', 'label' => 'Person', 'type' => 'text', 'required' => true, 'placeholder' => 'Who you borrowed from / lent to'],
        ['name' => 'amount', 'label' => 'Amount', 'type' => 'number', 'required' => true, 'placeholder' => '0.00', 'money' => true],
        ['name' => 'date', 'label' => 'Date', 'type' => 'date', 'required' => true],
        ['name' => 'due_date', 'label' => 'Due Date', 'type' => 'date'],
        ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => [
            'outstanding' => 'Outstanding', 'paid' => 'Paid',
        ]],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
    ];

    protected array $rules = [
        'type' => 'required|in:borrowed,lent',
        'person_name' => 'required|string|max:255',
        'amount' => 'required|numeric|min:0',
        'date' => 'required|date',
        'due_date' => 'nullable|date',
        'status' => 'required|in:outstanding,paid',
        'notes' => 'nullable|string',
    ];

    protected function stats(Request $request): array
    {
        $userId = $request->user()->id;
        $base = Debt::where('user_id', $userId)->where('status', 'outstanding');

        $totalBorrowed = (clone $base)->where('type', 'borrowed')->sum('amount');
        $totalLent = (clone $base)->where('type', 'lent')->sum('amount');
        $net = $totalLent - $totalBorrowed;

        return [
            ['label' => 'Outstanding borrowed (I owe)', 'value' => format_money($totalBorrowed), 'icon' => 'fa-solid fa-arrow-trend-down', 'color' => 'rose'],
            ['label' => 'Outstanding lent (owed to me)', 'value' => format_money($totalLent), 'icon' => 'fa-solid fa-arrow-trend-up', 'color' => 'emerald'],
            ['label' => 'Net position', 'value' => ($net >= 0 ? '+' : '-') . format_money(abs($net)), 'icon' => 'fa-solid fa-scale-balanced', 'color' => 'amber'],
        ];
    }

    protected function chart(Request $request): ?array
    {
        $userId = $request->user()->id;
        $base = Debt::where('user_id', $userId)->where('status', 'outstanding');
        $totalBorrowed = (clone $base)->where('type', 'borrowed')->sum('amount');
        $totalLent = (clone $base)->where('type', 'lent')->sum('amount');

        if ($totalBorrowed <= 0 && $totalLent <= 0) {
            return null;
        }

        return [
            'type' => 'bar',
            'title' => 'Outstanding Borrowed vs Lent',
            'labels' => ['Borrowed (I owe)', 'Lent (owed to me)'],
            'datasets' => [['label' => 'Amount', 'data' => [(float) $totalBorrowed, (float) $totalLent]]],
        ];
    }
}
