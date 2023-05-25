<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Declarant extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'saln_declarant';
    public $timestamps = false;
}
