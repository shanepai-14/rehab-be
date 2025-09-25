<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'created_by',
        'agenda',
        'details',
        'appointment_date',
        'appointment_time',
        'location',
        'duration',
        'priority',
        'status',
        'notes',
        'updated_by'
    ];

    protected $casts = [
        'appointment_date' => 'date:Y-m-d',
        'duration' => 'integer'
    ];

    // Relationships
    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeUpcoming($query)
    {
        return $query->where('appointment_date', '>=', now()->toDateString())
                    ->where('status', '!=', 'completed');
    }

    public function scopeToday($query)
    {
        return $query->where('appointment_date', now()->toDateString());
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // Accessors
    public function getFullDateTimeAttribute()
    {
        return $this->appointment_date->format('Y-m-d') . ' ' . $this->appointment_time;
    }

    public function getFormattedDateAttribute()
    {
        return $this->appointment_date->format('M j, Y');
    }

    public function getFormattedTimeAttribute()
    {
        return date('g:i A', strtotime($this->appointment_time));
    }

    public function getAppointmentTimeAttribute($value)
    {
        if ($value) {

            return Carbon::parse($value)->format('H:i');
        }
        return $value;
    }

    public function setAppointmentTimeAttribute($value)
    {
        if ($value) {
            $this->attributes['appointment_time'] = Carbon::parse($value)->format('H:i:s');
        }
    }
}

