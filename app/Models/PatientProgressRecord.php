<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientProgressRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'session_date',
        'patient_id',
        'therapy_type',
        'attending_therapist_id',
        'goals_set',
        'activities_done',
        'evaluation_summary',
        'behavior_mood',
        'recommendation',
        'next_appointment_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'session_date' => 'date:Y-m-d',
        'next_appointment_date' => 'date:Y-m-d',
    ];

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function therapist()
    {
        return $this->belongsTo(User::class, 'attending_therapist_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

