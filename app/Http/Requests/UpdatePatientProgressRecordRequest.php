<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePatientProgressRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller will handle policy
    }

    public function rules(): array
    {
        return [
            'session_date' => ['sometimes','date'],
            'patient_id' => ['sometimes','exists:users,id'],
            'therapy_type' => ['sometimes','string','max:255'],
            'attending_therapist_id' => ['nullable','exists:users,id'],
            'goals_set' => ['nullable','string'],
            'activities_done' => ['nullable','string'],
            'evaluation_summary' => ['nullable','string'],
            'behavior_mood' => ['nullable','string'],
            'recommendation' => ['nullable','string'],
            'next_appointment_date' => ['nullable','date','after_or_equal:session_date'],
        ];
    }
}

