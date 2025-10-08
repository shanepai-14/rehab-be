<?php

namespace App\Events;

use App\Models\Appointment;
use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentCreatedForDoctor implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $appointment;
    public $eventData;

    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment->load(['patients', 'patient', 'doctor']);
        
        // Get patient count and names
        $patientCount = $this->getPatientCount();
        $patientInfo = $this->getPatientInfo($patientCount);
        
        // Generate message
        $message = "Your appointment with {$patientInfo} is scheduled on {$this->appointment->formatted_date} at {$this->appointment->formatted_time}.";
        
        // Create notification in database for doctor
        Notification::create([
            'user_id' => $this->appointment->doctor->id,
            'title' => 'New Appointment Scheduled',
            'message' => $message,
            'type' => 'info',
            'related_type' => 'App\Models\Appointment',
            'related_id' => $this->appointment->id,
            'action_url' => '/appointments/' . $this->appointment->id
        ]);
        
        $this->eventData = [
            'appointment_id' => $this->appointment->id,
            'event' => 'created',
            'message' => $message,
            'appointment' => [
                'id' => $this->appointment->id,
                'agenda' => $this->appointment->agenda,
                'date' => $this->appointment->formatted_date,
                'time' => $this->appointment->formatted_time,
                'patients' => $this->getPatientsData(),
                'patient_count' => $patientCount,
                'status' => $this->appointment->status,
                'priority' => $this->appointment->priority
            ],
            'timestamp' => now()->toISOString()
        ];
    }

    private function getPatientCount()
    {
        if ($this->appointment->patients && $this->appointment->patients->count() > 0) {
            return $this->appointment->patients->count();
        } elseif ($this->appointment->patient) {
            return 1;
        }
        
        return 0;
    }

    private function getPatientInfo($count)
    {
        if ($count == 0) {
            return 'unknown patients';
        }
        
        if ($count <= 5) {
            if ($this->appointment->patients && $this->appointment->patients->count() > 0) {
                $names = $this->appointment->patients->map(function($patient) {
                    return $patient->first_name . ' ' . $patient->last_name;
                })->toArray();
                
                if ($count == 1) {
                    return $names[0];
                } elseif ($count == 2) {
                    return $names[0] . ' and ' . $names[1];
                } else {
                    $lastPatient = array_pop($names);
                    return implode(', ', $names) . ', and ' . $lastPatient;
                }
            } elseif ($this->appointment->patient) {
                return $this->appointment->patient->first_name . ' ' . $this->appointment->patient->last_name;
            }
        }
        
        return "{$count} patients";
    }

    private function getPatientsData()
    {
        if ($this->appointment->patients && $this->appointment->patients->count() > 0) {
            return $this->appointment->patients->map(function($patient) {
                return [
                    'id' => $patient->id,
                    'name' => $patient->first_name . ' ' . $patient->last_name,
                    'contact_number' => $patient->contact_number
                ];
            })->toArray();
        } elseif ($this->appointment->patient) {
            return [
                [
                    'id' => $this->appointment->patient->id,
                    'name' => $this->appointment->patient->first_name . ' ' . $this->appointment->patient->last_name,
                    'contact_number' => $this->appointment->patient->contact_number
                ]
            ];
        }
        
        return [];
    }

    public function broadcastOn()
    {
        return new Channel("user.{$this->appointment->doctor->contact_number}");
    }

    public function broadcastAs()
    {
        return 'appointment.created';
    }

    public function broadcastWith()
    {
        return $this->eventData;
    }
}