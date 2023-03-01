<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Education extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'educational_backgrounds';

    public $timestamps = false;
}
