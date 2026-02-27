<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

trait HasPostingStatus
{
    public static function bootHasPostingStatus(): void
    {
        static::updating(function (Model $model) {
            // If model does not even have a "status" attribute, do nothing
            if (! array_key_exists('status', $model->getAttributes())) {
                return;
            }

            $originalStatus = $model->getOriginal('status');

            // We only lock once the record is already posted
            if ($originalStatus !== 'posted') {
                return;
            }

            // Allow explicit overrides if a model really wants to
            if (method_exists($model, 'canEditPosted') && $model->canEditPosted() === true) {
                return;
            }

            $lockedAttributes = [];

            if (method_exists($model, 'getPostingLockedAttributes')) {
                $lockedAttributes = (array) $model->getPostingLockedAttributes();
            }

            if (empty($lockedAttributes)) {
                return;
            }

            $dirtyKeys = array_keys($model->getDirty());
            $violated  = array_values(array_intersect($dirtyKeys, $lockedAttributes));

            if (! empty($violated)) {
                $fieldsLabel = implode(', ', $violated);

                throw ValidationException::withMessages([
                    'status' => 'This document is already posted. Locked fields cannot be edited: ' . $fieldsLabel . '.',
                ]);
            }
        });
    }

    public function isPosted(): bool
    {
        return ($this->status ?? null) === 'posted';
    }

    public function isDraft(): bool
    {
        return ($this->status ?? null) === 'draft';
    }

    /**
     * Convenience helper for controllers/views.
     */
    public function canEdit(): bool
    {
        if (! array_key_exists('status', $this->getAttributes())) {
            return true;
        }

        if (! $this->isPosted()) {
            return true;
        }

        if (method_exists($this, 'canEditPosted')) {
            return (bool) $this->canEditPosted();
        }

        return false;
    }
}
