<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveApplicationTrack extends Model
{
    use HasFactory;
    protected $connection = 'mysql';
    protected $table = 'leave_application_tracks';
}
