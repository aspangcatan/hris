<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RelatedDuties extends Model
{
    use HasFactory;
    protected $connection = 'mysql';
    protected $table = 'related_duties';

    public $timestamps = false;
}
