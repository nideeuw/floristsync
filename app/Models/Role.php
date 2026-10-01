<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasAuditColumns;

    public const ADMIN   = 'admin';
    public const CASHIER = 'cashier';
    public const OWNER   = 'owner';
    public const MEMBER  = 'member';

    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
