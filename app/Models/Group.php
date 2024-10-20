<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function files()
    {
        return $this->belongsToMany(File::class, 'group_files');
    }

    public function user()
    {
        return $this->belongsToMany(User::class, 'user_groups');
    }
}
