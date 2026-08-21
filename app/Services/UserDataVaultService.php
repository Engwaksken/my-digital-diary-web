<?php

namespace App\Services;

use App\Models\UserRecycleBinItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class UserDataVaultService
{
    public const MODELS = [
        \App\Models\Plan::class, \App\Models\Reminder::class, \App\Models\Income::class,
        \App\Models\Expense::class, \App\Models\Budget::class, \App\Models\Debt::class,
        \App\Models\SavingsGoal::class, \App\Models\SavingsContribution::class,
        \App\Models\Project::class, \App\Models\ProjectTask::class, \App\Models\Meeting::class,
        \App\Models\HealthCheckup::class, \App\Models\DietLog::class, \App\Models\ExerciseLog::class,
        \App\Models\SleepLog::class, \App\Models\EducationPlan::class, \App\Models\NetworkContact::class,
        \App\Models\PersonalRelationship::class, \App\Models\SpiritualPractice::class,
    ];

    public function capture(Model $model): void
    {
        if (! Schema::hasTable('user_recycle_bin_items')) return;
        if ($model instanceof UserRecycleBinItem) return;
        if (! in_array($model::class, self::MODELS, true)) return;
        $userId = (int) ($model->getAttribute('user_id') ?? 0);
        if ($userId < 1) return;

        $payload = $model->getAttributes();
        if ($model instanceof \App\Models\Expense) {
            try { $payload['_relations']['items'] = $model->items()->get()->map->getAttributes()->all(); } catch (\Throwable $e) {}
        }

        $label = $model->getAttribute('title') ?: $model->getAttribute('name') ?: $model->getAttribute('category') ?: class_basename($model);
        UserRecycleBinItem::create([
            'user_id'=>$userId,
            'model_type'=>$model::class,
            'original_id'=>$model->getKey(),
            'label'=>(string)$label,
            'payload'=>$payload,
            'deleted_at'=>now(),
            'expires_at'=>now()->addDays(30),
        ]);
    }

    public function restore(UserRecycleBinItem $item): Model
    {
        $class = $item->model_type;
        abort_unless(in_array($class, self::MODELS, true) && class_exists($class), 422, 'This item cannot be restored.');
        $payload = $item->payload ?: [];
        $relations = $payload['_relations'] ?? [];
        unset($payload['_relations']);
        $payload['user_id'] = $item->user_id;

        $restored = $class::unguarded(function () use ($class, $payload) {
            if (!empty($payload['id']) && $class::whereKey($payload['id'])->exists()) unset($payload['id']);
            return $class::create($payload);
        });

        if ($restored instanceof \App\Models\Expense && !empty($relations['items'])) {
            foreach ($relations['items'] as $row) {
                unset($row['id']); $row['expense_id'] = $restored->id;
                \App\Models\ExpenseItem::unguarded(fn()=>\App\Models\ExpenseItem::create($row));
            }
        }
        $item->delete();
        return $restored;
    }
}
