<?php

namespace App\Models;

use App\Models\Concerns\HasNameSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasNameSlug;

    protected $fillable = ['name', 'slug', 'description', 'margin_percentage', 'export_margin', 'yield_percentage', 'grinding_cost', 'created_by', 'updated_by'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Products in this category.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
