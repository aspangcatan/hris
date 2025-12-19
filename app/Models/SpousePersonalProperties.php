<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpousePersonalProperties extends Model
{
    use HasFactory;
      protected $connection = 'mysql';
    protected $table = 'spouse_personal_properties';
    public $timestamps = false;
}
