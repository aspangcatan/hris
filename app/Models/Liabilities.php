<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Liabilities extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'liabilities';

    public $timestamps = false;
}
