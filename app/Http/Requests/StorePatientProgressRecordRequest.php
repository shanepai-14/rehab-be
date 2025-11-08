<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientProgressRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller will handle policy via authorizeResource
    }

    public function rules(): array
    {
        return [
            'session_date' => ['required','date'],
            'patient_id' => ['required','exists:users,id'],
            'therapy_type' => ['required','string','max:255'],
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

