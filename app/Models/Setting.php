<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasAuditColumns;

    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean'];
}
