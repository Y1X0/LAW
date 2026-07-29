<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = ['name', 'code', 'address', 'phone', 'city', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }
}
