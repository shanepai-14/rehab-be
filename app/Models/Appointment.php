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
        'patient_id',        // Keep for backward compatibility
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

    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * Many-to-Many: Appointments can have multiple patients
     * This is the NEW relationship for multi-patient support
     */
    public function patients()
    {
        return $this->belongsToMany(User::class, 'appointment_patient', 'appointment_id', 'patient_id')
                    ->withTimestamps();
    }

    /**
     * Single patient relationship (backward compatibility)
     * Keep this for existing code that uses appointment->patient
     */
    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    /**
     * Doctor assigned to this appointment
     */
    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    /**
     * User who created this appointment
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who last updated this appointment
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ============================================
    // SCOPES
    // ============================================

    /**
     * Scope for upcoming appointments
     */
    public function scopeUpcoming($query)
    {
        return $query->where('appointment_date', '>=', now()->toDateString())
                    ->where('status', '!=', 'completed');
    }

    /**
     * Scope for today's appointments
     */
    public function scopeToday($query)
    {
        return $query->where('appointment_date', now()->toDateString());
    }

    /**
     * Scope for filtering by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by priority
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for appointments with multiple patients
     */
    public function scopeMultiPatient($query)
    {
        return $query->has('patients', '>', 1);
    }

    // ============================================
    // ACCESSORS (GETTERS)
    // ============================================

    /**
     * Get full date and time as a single string
     */
    public function getFullDateTimeAttribute()
    {
        return $this->appointment_date->format('Y-m-d') . ' ' . $this->appointment_time;
    }

    /**
     * Get formatted date (e.g., "Oct 15, 2025")
     */
    public function getFormattedDateAttribute()
    {
        return $this->appointment_date->format('M j, Y');
    }

    /**
     * Get formatted time (e.g., "10:30 AM")
     */
    public function getFormattedTimeAttribute()
    {
        return date('g:i A', strtotime($this->appointment_time));
    }

    /**
     * Get appointment_time value - ensure it's in H:i format
     */
    public function getAppointmentTimeAttribute($value)
    {
        if ($value) {
            return Carbon::parse($value)->format('H:i');
        }
        return $value;
    }

    /**
     * NEW: Get comma-separated patient names for display
     * Example: "John Doe, Jane Smith, Bob Johnson"
     */
    public function getPatientNamesAttribute()
    {
        // Load patients if not already loaded
        if (!$this->relationLoaded('patients')) {
            $this->load('patients');
        }

        return $this->patients->map(function ($patient) {
            return $patient->first_name . ' ' . $patient->last_name;
        })->join(', ');
    }

    /**
     * NEW: Get count of patients in this appointment
     */
    public function getPatientCountAttribute()
    {
        if (!$this->relationLoaded('patients')) {
            $this->load('patients');
        }

        return $this->patients->count();
    }

    /**
     * NEW: Check if appointment has multiple patients
     */
    public function getIsMultiPatientAttribute()
    {
        return $this->patient_count > 1;
    }

    // ============================================
    // MUTATORS (SETTERS)
    // ============================================

    /**
     * Set appointment_time - ensure it's stored in H:i:s format
     */
    public function setAppointmentTimeAttribute($value)
    {
        if ($value) {
            $this->attributes['appointment_time'] = Carbon::parse($value)->format('H:i:s');
        }
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Check if appointment is in the past
     */
    public function isPast()
    {
        $appointmentDateTime = Carbon::parse($this->full_date_time);
        return $appointmentDateTime->isPast();
    }

    /**
     * Check if appointment is today
     */
    public function isToday()
    {
        return $this->appointment_date->isToday();
    }

    /**
     * Check if appointment is upcoming
     */
    public function isUpcoming()
    {
        return $this->appointment_date->isFuture() && $this->status !== 'completed';
    }

    /**
     * Get appointment status badge color for UI
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'scheduled' => 'blue',
            'confirmed' => 'green',
            'in_progress' => 'yellow',
            'completed' => 'gray',
            'cancelled' => 'red',
            'no_show' => 'orange',
            default => 'gray'
        };
    }

    /**
     * Get appointment priority badge color for UI
     */
    public function getPriorityColorAttribute()
    {
        return match($this->priority) {
            'urgent' => 'red',
            'high' => 'orange',
            'normal' => 'blue',
            'low' => 'gray',
            default => 'blue'
        };
    }

    /**
     * NEW: Add a patient to this appointment
     */
    public function addPatient($patientId)
    {
        if (!$this->patients()->where('users.id', $patientId)->exists()) {
            $this->patients()->attach($patientId);
            
            // Update patient_id for backward compatibility if this is the first patient
            if ($this->patients()->count() === 1) {
                $this->update(['patient_id' => $patientId]);
            }
        }
    }

    /**
     * NEW: Remove a patient from this appointment
     */
    public function removePatient($patientId)
    {
        $this->patients()->detach($patientId);
        
        // Update patient_id for backward compatibility
        $firstPatient = $this->patients()->first();
        $this->update(['patient_id' => $firstPatient ? $firstPatient->id : null]);
    }

    /**
     * NEW: Sync patients (replace all patients with new list)
     */
    public function syncPatients(array $patientIds)
    {
        $this->patients()->sync($patientIds);
        
        // Update patient_id for backward compatibility
        if (!empty($patientIds)) {
            $this->update(['patient_id' => $patientIds[0]]);
        }
    }
}