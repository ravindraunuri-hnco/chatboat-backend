<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'rm_cost',
        'grinding_cost',
        'yield_percentage',
        'keywords', // margin_percentage yahan se hata diya
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}