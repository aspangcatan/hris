<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Work extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'work_experiences';
    public $timestamps = false;
}
