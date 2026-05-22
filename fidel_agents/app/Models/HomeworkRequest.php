<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HomeworkRequest extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'role_name',
        'request',
        'response',
        'llm_confidence',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $request) {
            if (empty($request->{$request->getKeyName()})) {
                $request->{$request->getKeyName()} = (string) Str::uuid();
            }

            if (empty($request->created_at)) {
                $request->created_at = now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
