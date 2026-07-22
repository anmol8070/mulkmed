<?php

namespace App\Traits;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {

        static::created(function ($model): void {
            activity_log(
                'created',
                class_basename($model),
                $model->getKey(),
                class_basename($model) . ' created',
                null,
                $model->getAttributes()
            );
        });

        static::updated(function ($model): void {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if (empty($changes)) {
                return;
            }

            $original = [];
            foreach (array_keys($changes) as $key) {
                $original[$key] = $model->getOriginal($key);
            }

            activity_log(
                'updated',
                class_basename($model),
                $model->getKey(),
                class_basename($model) . ' updated',
                $original,
                $changes
            );
        });

        static::deleted(function ($model): void {
            activity_log(
                method_exists($model, 'trashed') && $model->trashed() ? 'soft_deleted' : 'deleted',
                class_basename($model),
                $model->getKey(),
                class_basename($model) . ' deleted',
                $model->getOriginal(),
                null
            );
        });

        // Register directly via Eloquent's event API to avoid relying on
        // SoftDeletes helper methods (e.g. static::restored()).
        static::registerModelEvent('restored', function ($model): void {
            activity_log(
                'restored',
                class_basename($model),
                $model->getKey(),
                class_basename($model) . ' restored',
                null,
                $model->getAttributes()
            );
        });
    }
}
