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
        'keywords', // margin_percentage, yield_percentage aur grinding_cost ab categories table me hain
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}