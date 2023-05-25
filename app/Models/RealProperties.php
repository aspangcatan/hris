<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RealProperties extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'real_properties';

    public $timestamps = false;
}
