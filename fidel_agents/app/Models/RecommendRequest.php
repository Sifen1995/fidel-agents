<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RecommendRequest extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'student_id',
        'grade_level',
        'request',
        'response',
        'focus_subject',
        'llm_provider',
        'llm_model',
        'processed_offline',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_offline' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

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
}
