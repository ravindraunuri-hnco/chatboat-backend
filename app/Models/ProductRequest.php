<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRequest extends Model
{
    protected $table = 'product_requests';

    protected $fillable = [
        'chat_user_id',
        'product_name',
        'category_id',
        'custom_category_name',
        'deadline',
        'status',
        'processed_at',
    ];

    protected $casts = [
        'deadline' => 'date:Y-m-d',
        'processed_at' => 'datetime',
    ];

    public function chatUser(): BelongsTo
    {
        return $this->belongsTo(ChatUser::class, 'chat_user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Delete approved/cancelled requests after 1 day (24 hours).
     * Pending requests are never deleted. Both the admin and user lists call this first.
     */
    public static function purgeProcessed(): void
    {
        static::whereIn('status', ['approved', 'rejected'])
            ->whereRaw('COALESCE(processed_at, updated_at) <= ?', [now()->subDay()])
            ->delete();
    }
}
