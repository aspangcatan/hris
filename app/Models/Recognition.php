<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recognition extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'recognitions';
    public $timestamps = false;
}
