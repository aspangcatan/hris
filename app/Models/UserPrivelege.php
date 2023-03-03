<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPrivelege extends Model
{
    use HasFactory;

    protected $connection = 'users';
    protected $table = 'user_priv';
    public $timestamps = false;
}
