<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CivilService extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'eligibilities';
    public $timestamps = false;
}
