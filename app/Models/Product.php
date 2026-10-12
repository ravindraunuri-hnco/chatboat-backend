<?php

namespace App\Models;

use App\Models\Concerns\HasNameSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, HasNameSlug;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'rm_cost',
        'created_by',
        'updated_by',
        'keywords', // margin_percentage, yield_percentage and grinding_cost now live in the categories table
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}