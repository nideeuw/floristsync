<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuPermission extends Model
{
    use HasAuditColumns;

    protected $guarded = ['id'];
    protected $casts   = [
        'is_view'    => 'boolean',
        'is_add'     => 'boolean',
        'is_edit'    => 'boolean',
        'is_delete'  => 'boolean',
        'is_approve' => 'boolean',
        'is_publish' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
