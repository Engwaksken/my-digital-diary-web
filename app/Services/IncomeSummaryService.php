<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class IncomeSummaryService
{
    public function forUser(User $user): array
    {
        $uid = $user->id;

        $income = Income::query()
            ->where('user_id', $uid)
            ->when(
                Schema::hasColumn('incomes', 'is_archived'),
                fn ($query) => $query->where('is_archived', false)
            );

        $expenses = Expense::query()
            ->where('user_id', $uid)
            ->when(
                Schema::hasColumn('expenses', 'is_archived'),
                fn ($query) => $query->where('is_archived', false)
            );

        $totalIncome = (float) (clone $income)->sum('amount');

        $monthlyIncome = (float) (clone $income)
            ->whereBetween('received_at', [
                now()->copy()->startOfMonth(),
                now()->copy()->endOfMonth(),
            ])
            ->sum('amount');

        $totalExpenses = (float) (clone $expenses)->sum('amount');

        $debtPayments = 0.0;

        if (
            Schema::hasColumn('budgets', 'application_type') &&
            Schema::hasColumn('budgets', 'applied_amount') &&
            Schema::hasColumn('budgets', 'is_expensed')
        ) {
            $debtPayments = (float) Budget::query()
                ->where('user_id', $uid)
                ->where('application_type', 'debt_payment')
                ->where('is_expensed', true)
                ->when(
                    Schema::hasColumn('budgets', 'is_archived'),
                    fn ($query) => $query->where('is_archived', false)
                )
                ->sum('applied_amount');
        }

        $totalOut = $totalExpenses + $debtPayments;

        return [
            'total_income' => $totalIncome,
            'monthly_income' => $monthlyIncome,
            'balance' => $totalIncome - $totalOut,
            'total_income_out' => $totalOut,
            'total_expenses' => $totalExpenses,
            'total_debt_payments' => $debtPayments,
        ];
    }
}
