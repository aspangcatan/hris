<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonalProperties extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'personal_properties';

    public $timestamps = false;
}
