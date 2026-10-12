<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class ChatUser extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'chat_users';
    
    // added user_type
    protected $fillable = ['name', 'email', 'password', 'user_type']; 
    
    protected $hidden = ['password'];
}