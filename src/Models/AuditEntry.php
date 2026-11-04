<?php

namespace Fayne\Audit\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEntry extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['actor' => 'array', 'target' => 'array', 'payload' => 'array', 'metadata' => 'array', 'occurred_at' => 'immutable_datetime', 'recorded_at' => 'immutable_datetime'];
    public function getConnectionName()
    {
        return config('filament-audit.database.connection') ?? parent::getConnectionName();
    }
}
