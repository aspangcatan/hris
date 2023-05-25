<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GovernmentRelative extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'government_relatives';

    public $timestamps = false;
}
