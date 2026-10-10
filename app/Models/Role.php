<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasAuditColumns;

    public const MEMBER          = 'member';
    public const ADMIN_ORDER     = 'admin_order';
    public const ADMIN_INVENTORY = 'admin_inventory';
    public const CASHIER         = 'cashier';
    public const OWNER           = 'owner';

    public const ADMIN_PANEL_ROLES = [self::ADMIN_ORDER, self::ADMIN_INVENTORY, self::OWNER];
    public const POS_ROLES = [self::CASHIER];

    protected $guarded = ['id'];
    protected $casts   = ['is_active' => 'boolean'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function menuPermissions(): HasMany
    {
        return $this->hasMany(MenuPermission::class);
    }
}
