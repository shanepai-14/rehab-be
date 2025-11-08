<?php
namespace App\Http\Controllers;

use App\Http\Requests\StorePatientProgressRecordRequest;
use App\Http\Requests\UpdatePatientProgressRecordRequest;
use App\Models\PatientProgressRecord;
use App\Services\PatientProgressRecordService;
use Illuminate\Http\Request;

class PatientProgressRecordController extends Controller
{
    public function __construct(private PatientProgressRecordService $service)
    {
        $this->authorizeResource(PatientProgressRecord::class, 'record');
    }

    public function index(Request $request)
    {
        $filters = $request->only(['patient_id','therapist_id','from','to','therapy_type','per_page']);
        $records = $this->service->list($filters);
        return response()->json(['success' => true, 'data' => $records]);
    }

    public function store(StorePatientProgressRecordRequest $request)
    {
        $record = $this->service->create($request->validated(), $request->user()->id);
        return response()->json(['success' => true, 'data' => $record], 201);
    }

    public function show(PatientProgressRecord $record)
    {
        $record->load(['patient','therapist','createdBy','updatedBy']);
        return response()->json(['success' => true, 'data' => $record]);
    }

    public function update(UpdatePatientProgressRecordRequest $request, PatientProgressRecord $record)
    {
        $record = $this->service->update($record, $request->validated(), $request->user()->id);
        return response()->json(['success' => true, 'data' => $record]);
    }

    public function destroy(PatientProgressRecord $record, Request $request)
    {
        $this->service->delete($record, $request->user()->id);
        return response()->json(['success' => true]);
    }
}

