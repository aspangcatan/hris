<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voluntary extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'voluntary_works';

    public $timestamps = false;
}
