<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Children extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'childrens';

    public $timestamps = false;
}
