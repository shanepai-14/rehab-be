<?php
namespace App\Services;

use App\Models\PatientProgressRecord;
use Illuminate\Support\Facades\Log;

class PatientProgressRecordService
{
    public function list(array $filters = [])
    {
        $query = PatientProgressRecord::query()
            ->with(['patient', 'therapist', 'createdBy', 'updatedBy'])
            ->orderByDesc('session_date')
            ->orderByDesc('id');

        if (!empty($filters['patient_id'])) {
            $query->where('patient_id', $filters['patient_id']);
        }
        if (!empty($filters['therapist_id'])) {
            $query->where('attending_therapist_id', $filters['therapist_id']);
        }
        if (!empty($filters['from'])) {
            $query->whereDate('session_date', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->whereDate('session_date', '<=', $filters['to']);
        }
        if (!empty($filters['therapy_type'])) {
            $query->where('therapy_type', 'like', '%'.$filters['therapy_type'].'%');
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data, int $userId): PatientProgressRecord
    {
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;
        $record = PatientProgressRecord::create($data);
        Log::info('Patient progress record created', ['record_id' => $record->id, 'by' => $userId]);
        return $record->load(['patient','therapist']);
    }

    public function update(PatientProgressRecord $record, array $data, int $userId): PatientProgressRecord
    {
        $data['updated_by'] = $userId;
        $record->update($data);
        Log::info('Patient progress record updated', ['record_id' => $record->id, 'by' => $userId]);
        return $record->load(['patient','therapist']);
    }

    public function delete(PatientProgressRecord $record, int $userId): void
    {
        $record->delete();
        Log::warning('Patient progress record deleted', ['record_id' => $record->id, 'by' => $userId]);
    }
}

