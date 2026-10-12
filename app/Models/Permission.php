<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name'
    ];

    /**
     * MANY roles
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_permissions'
        )->withPivot([
            'read',
            'write',
            'update',
            'delete'
        ]);
    }

    /**
     * Convert a permission name into one standard key:
     * "Product Requests" / "product requests" / "product_requests" -> "product_requests"
     * (the AuthController frontend key is built the same way)
     */
    public static function normalize(string $name): string
    {
        $key = strtolower(str_replace('&', '', $name));
        return trim(preg_replace('/[\s_\-]+/', '_', $key), '_');
    }

    /**
     * Whether two names refer to the same permission — ignores differences in spaces/underscores/capitals and singular/plural.
     * "product" == "products", "product request" == "product_requests"
     */
    public static function sameKey(string $a, string $b): bool
    {
        $a = static::normalize($a);
        $b = static::normalize($b);

        return $a === $b || Str::singular($a) === Str::singular($b);
    }

    /**
     * Find a permission by name among the database permissions (names are matched after normalization).
     */
    public static function findByKey(string $name): ?self
    {
        return static::all()->first(fn (self $p) => static::sameKey($p->name, $name));
    }
}
