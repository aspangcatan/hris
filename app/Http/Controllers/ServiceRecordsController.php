<?php

namespace App\Http\Controllers;

use App\Models\ServiceRecords;
use Illuminate\Http\Request;

class ServiceRecordsController extends Controller
{
    //
    public function getServiceRecords(Request $request)
    {
        try {
            $service_records = ServiceRecords::where('user_id', $request->id)->get();
            return response(['code' => 200, 'results' => $service_records]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertUpdateServiceRecords(Request $request)
    {
        try {
            $service_records = ServiceRecords::find($request->id);
            if (!$service_records) {
                $service_records = new ServiceRecords;
                $service_records->user_id = $request->user_id;
            }

            $service_records->date_from = $request->date_from;
            $service_records->date_to = $request->date_to;
            $service_records->current_work = $request->current_work;
            $service_records->designation = $request->designation;
            $service_records->appointment_status = $request->appointment_status;
            $service_records->monthly_salary = $request->monthly_salary;
            $service_records->office_assignment = $request->office_assignment;
            $service_records->leave_absence = $request->leave_absence;
            $service_records->separation = $request->separation;
            $service_records->save();
            return response(['code' => 200, 'message' => 'Service records updated']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception]);
        }
    }

    public function deleteServiceRecords(Request $request)
    {
        try {
            $service_records = ServiceRecords::find($request->id);
            if (!$service_records) {
                return response(['code' => 500, 'message' => 'Service record ID does not exists']);
            }
            $service_records->forceDelete();
            return response(['code' => 200, 'message' => 'Service record deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }
}
