<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ExamPrep extends Model
{
    use HasFactory;

    protected $table = 'exam_prep';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'student_id',
        'grade_level',
        'exam_subject',
        'exam_date',
        'days_remaining',
        'request',
        'response',
        'llm_provider',
        'llm_model',
        'processed_offline',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'days_remaining' => 'integer',
            'processed_offline' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $record) {
            if (empty($record->{$record->getKeyName()})) {
                $record->{$record->getKeyName()} = (string) Str::uuid();
            }

            if (empty($record->created_at)) {
                $record->created_at = now();
            }
        });
    }
}
