<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Builds a unique slug from the name (categories / products).
 * "Organic Amla", "organic amla" and "organic-amla" all produce the same slug,
 * so a unique slug prevents duplicate entries.
 */
trait HasNameSlug
{
    public static function slugFor(string $name): string
    {
        $name = trim($name);
        $slug = Str::slug($name);

        // A non-Latin name (e.g. Hindi) can produce an empty slug — a hash of the name is used then
        return $slug !== '' ? $slug : 'n-' . substr(md5(mb_strtolower($name)), 0, 12);
    }

    protected static function bootHasNameSlug(): void
    {
        static::saving(function ($model) {
            if ($model->isDirty('name') || empty($model->slug)) {
                $model->slug = static::slugFor((string) $model->name);
            }
        });
    }

    public static function nameTaken(string $name, ?int $ignoreId = null): bool
    {
        return static::where('slug', static::slugFor($name))
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    /**
     * If the same name (slug) already exists, throw a 422 validation error on the 'name' field.
     */
    public static function assertNameAvailable(string $name, ?int $ignoreId = null, string $label = 'item'): void
    {
        if (static::nameTaken($name, $ignoreId)) {
            throw ValidationException::withMessages([
                'name' => ["A {$label} with this name already exists."],
            ]);
        }
    }
}
