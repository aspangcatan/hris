<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpouseRealProperties extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'spouse_real_properties';

    public $timestamps = false;
}
