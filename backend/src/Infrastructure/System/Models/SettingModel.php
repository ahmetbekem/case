<?php

declare(strict_types=1);

namespace Infrastructure\System\Models;

use Infrastructure\Shared\Persistence\BaseModel;

final class SettingModel extends BaseModel
{
    protected $table = 'system.settings';

    protected $guarded = [];

    /**
     * Setting writes manuel olarak created_at/updated_at set ediyoruz
     * (mapper). Eloquent auto-touch'u devre dışı bırak — created_at
     * useCurrent default olduğundan zaten DB ayarlıyor.
     */
    public $timestamps = false;

    protected $casts = [
        'is_public' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
