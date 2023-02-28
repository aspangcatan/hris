<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FamilyBackground extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'family_backgrounds';

    public $timestamps = false;
}
