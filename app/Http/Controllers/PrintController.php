<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Children;
use App\Models\CivilService;
use App\Models\Education;
use App\Models\Employee;
use App\Models\FamilyBackground;
use App\Models\Learning;
use App\Models\Membership;
use App\Models\Recognition;
use App\Models\Reference;
use App\Models\ServiceRecords;
use App\Models\Skill;
use App\Models\Survey;
use App\Models\User;
use App\Models\Voluntary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class PrintController extends Controller
{
    public function printServiceRecord(Request $request)
    {
        $user = User::find($request->id);
        $employee = Employee::where('user_id', $request->id)->first();
        if (!$employee) {
            return "Ooops something went wrong";
        }

        $results = ServiceRecords::where('user_id', $request->id)->get();
        $pdf = Pdf::loadView('pdf.service_record', ['records' => $results, 'employee' => $employee, 'user' => $user]);
        return $pdf->stream('service_record.pdf', array("Attachment" => false));
    }

    public function printPds(Request $request)
    {
        try {
            if (session("access_rights") == "standard" && $request->id > 0) {
                return "Permission denied";
            }

            $user_id = ($request->id == 0) ? Auth::id() : $request->id;
            #CONSTANTS
            $LEVELS = ['ELEMENTARY', 'SECONDARY', 'VOCATIONAL/TRADE COURSE', 'COLLEGE', 'GRADUATE STUDIES'];
            $SURVEYS = [''];
            #VARIABLE ARRAYS
            $childrens = [];
            $educations = [];
            $eligibilities = [];
            $work_experiences = [];
            $voluntary_works = [];
            $learnings = [];
            $skills = [];
            $surveys =
                [
                    ['answer' => '', 'details' => 'N/A', 'extra' => 'N/A'],
                    ['answer' => '', 'details' => 'N/A', 'extra' => 'N/A'],
                    ['answer' => '', 'details' => 'N/A', 'extra' => 'N/A'],
                    ['answer' => '', 'details' => 'N/A', 'extra' => 'N/A'],
                    ['answer' => '', 'details' => 'N/A', 'extra' => 'N/A'],
                    ['answer' => '', 'details' => 'N/A', 'extra' => 'N/A'],
                    ['answer' => '', 'details' => 'N/A', 'extra' => 'N/A'],
                    ['answer' => '', 'details' => 'N/A', 'extra' => 'N/A'],
                    ['answer' => '', 'details' => 'N/A', 'extra' => 'N/A'],
                    ['answer' => '', 'details' => 'N/A', 'extra' => 'N/A'],
                    ['answer' => '', 'details' => 'N/A', 'extra' => 'N/A'],
                    ['answer' => '', 'details' => 'N/A', 'extra' => 'N/A'],
                ];

            $references = [];
            #DATA FROM TABLES
            $user = User::find($user_id, ['fname', 'mname', 'lname', 'suffix']);
            $employee = Employee::where('user_id', $user_id)->get()->first();
            $residential = Address::where([['user_id', $user_id], ['address_type', 'Residential']])->get()->first();
            $permanent = Address::where([['user_id', $user_id], ['address_type', 'Permanent']])->get()->first();
            $family = FamilyBackground::where('user_id', $user_id)->get()->first();
            $children = Children::where('user_id', $user_id)->get();
            $education = Education::where('user_id', $user_id)->get();
            $eligiblity = CivilService::where('user_id', $user_id)->orderBy('examination_date', 'DESC')->get();
            $work_experience =
                DB::table('work_experiences')
                    ->where('work_experiences.user_id', '=', $user_id)
                    ->select('work_experiences.*')
                    ->orderBy('work_experiences.working_from', 'DESC')
                    ->get();

            $voluntary_work = Voluntary::where('user_id', $user_id)->orderBy('working_from', 'DESC')->get();
            $learning = Learning::where('user_id', $user_id)->orderBy('attendance_from', 'DESC')->get();
            $skill = Skill::where('user_id', $user_id)->get();
            $recognition = Recognition::where('user_id', $user_id)->get();
            $membership = Membership::where('user_id', $user_id)->get();
            $survey = Survey::where('user_id', $user_id)->get();
            $reference = Reference::where('user_id', $user_id)->get();

            if (count($survey) > 0) $surveys = $survey;

            for ($i = 0; $i < 13; $i++) {
                $name = "N/A";
                $birthdate = "N/A";

                if ($i > 0) {
                    $name = "";
                    $birthdate = "";
                }

                #CHECK IF THE LOOP EXCEEDS TO ACTUAL DATA SIZE
                if ($i < count($children)) {
                    $name = $children[$i]->name;
                    $birthdate = date('m/d/Y', strtotime($children[$i]->birthdate));
                }

                $childrens[$i] = ['name' => $name, 'birthdate' => $birthdate];
            }

            for ($i = 0; $i < count($LEVELS); $i++) {
                $educations[$i] = [
                    'level' => $LEVELS[$i],
                    'school_name' => 'N/A',
                    'education_degree' => 'N/A',
                    'attendance_from' => 'N/A',
                    'attendance_to' => 'N/A',
                    'highest_level' => 'N/A',
                    'year_graduated' => 'N/A',
                    'scholarship' => 'N/A'
                ];
            }

            foreach ($education as $key) {
                $index = 0;
                switch ($key->level) {
                    case "Elementary":
                        $index = 0;
                        break;
                    case "Secondary":
                        $index = 1;
                        break;
                    case "Vocational/Trade Course":
                        $index = 2;
                        break;
                    case "College":
                        $index = 3;
                        break;
                    case "Graduate Studies":
                        $index = 4;
                        break;
                }

                $educations[$index] = [
                    'level' => $LEVELS[$index],
                    'school_name' => $key->school_name,
                    'education_degree' => $key->education_degree,
                    'attendance_from' => $key->attendance_from,
                    'attendance_to' => $key->attendance_to,
                    'highest_level' => $key->highest_level,
                    'year_graduated' => ($key->year_graduated == 0) ? "N/A" : $key->year_graduated,
                    'scholarship' => $key->scholarship
                ];
            }

            for ($i = 0; $i < 10; $i++) {
                $eligibility_column_1 = "N/A";
                $eligibility_column_2 = "N/A";
                $eligibility_column_3 = "N/A";
                $eligibility_column_4 = "N/A";
                $eligibility_column_5 = "N/A";
                $eligibility_column_6 = "N/A";

                if ($i > 0) {
                    $eligibility_column_1 = "";
                    $eligibility_column_2 = "";
                    $eligibility_column_3 = "";
                    $eligibility_column_4 = "";
                    $eligibility_column_5 = "";
                    $eligibility_column_6 = "";
                }

                #CHECK IF THE LOOP EXCEEDS TO ACTUAL DATA SIZE
                if ($i < count($eligiblity)) {
                    $eligibility_column_1 = $eligiblity[$i]->career_service;
                    $eligibility_column_2 = $eligiblity[$i]->rating;
                    $eligibility_column_3 = date('m/d/Y', strtotime($eligiblity[$i]->examination_date));
                    $eligibility_column_4 = $eligiblity[$i]->examination_place;
                    $eligibility_column_5 = $eligiblity[$i]->license_no;
                    $eligibility_column_6 = date('m/d/Y', strtotime($eligiblity[$i]->license_expiry));

                }

                $eligibilities[$i] = ['career_service' => $eligibility_column_1, 'rating' => $eligibility_column_2,
                    'examination_date' => $eligibility_column_3, 'examination_place' => $eligibility_column_4,
                    'license_no' => $eligibility_column_5, 'license_expiry' => $eligibility_column_6];
            }

            for ($i = 0; $i < 25; $i++) {
                $work_column_1 = "N/A";
                $work_column_2 = "N/A";
                $work_column_3 = "N/A";
                $work_column_4 = "N/A";
                $work_column_5 = "N/A";
                $work_column_6 = "N/A";
                $work_column_7 = "N/A";
                $work_column_8 = "N/A";

                if ($i > 0) {
                    $work_column_1 = "";
                    $work_column_2 = "";
                    $work_column_3 = "";
                    $work_column_4 = "";
                    $work_column_5 = "";
                    $work_column_6 = "";
                    $work_column_7 = "";
                    $work_column_8 = "";
                }

                #CHECK IF THE LOOP EXCEEDS TO ACTUAL DATA SIZE
                if ($i < count($work_experience)) {
                    $work_column_1 = date('m/d/Y', strtotime($work_experience[$i]->working_from));
                    $work_column_2 = ($work_experience[$i]->is_current_work == 1) ? "Present" : date('m/d/Y', strtotime($work_experience[$i]->working_to));
                    $work_column_3 = $work_experience[$i]->position;
                    $work_column_4 = $work_experience[$i]->agency;
                    $work_column_5 = $work_experience[$i]->monthly_salary;
                    $work_column_6 = $work_experience[$i]->salary_grade;
                    $work_column_7 = $work_experience[$i]->appointment_status;
                    $work_column_8 = ($work_experience[$i]->is_government == 1) ? "Y" : "N";
                }

                $work_experiences[$i] = ['working_from' => $work_column_1, 'working_to' => $work_column_2,
                    'position' => $work_column_3, 'agency' => $work_column_4,
                    'monthly_salary' => $work_column_5, 'salary_grade' => $work_column_6,
                    'appointment_status' => $work_column_7, 'is_government' => $work_column_8];
            }

            for ($i = 0; $i < 10; $i++) {
                $voluntary_column_1 = "N/A";
                $voluntary_column_2 = "N/A";
                $voluntary_column_3 = "N/A";
                $voluntary_column_4 = "N/A";
                $voluntary_column_5 = "N/A";

                if ($i > 0) {
                    $voluntary_column_1 = "";
                    $voluntary_column_2 = "";
                    $voluntary_column_3 = "";
                    $voluntary_column_4 = "";
                    $voluntary_column_5 = "";
                }

                #CHECK IF THE LOOP EXCEEDS TO ACTUAL DATA SIZE
                if ($i < count($voluntary_work)) {
                    $voluntary_column_1 = $voluntary_work[$i]->organization_address;
                    $voluntary_column_2 = date('m/d/Y', strtotime($voluntary_work[$i]->working_from));
                    $voluntary_column_3 = date('m/d/Y', strtotime($voluntary_work[$i]->working_to));
                    $voluntary_column_4 = $voluntary_work[$i]->number_hours;
                    $voluntary_column_5 = $voluntary_work[$i]->position;
                }

                $voluntary_works[$i] = ['organization_address' => $voluntary_column_1, 'working_from' => $voluntary_column_2,
                    'working_to' => $voluntary_column_3, 'number_hours' => $voluntary_column_4,
                    'position' => $voluntary_column_5];
            }

            for ($i = 0; $i < 20; $i++) {
                $learning_column_1 = "N/A";
                $learning_column_2 = "N/A";
                $learning_column_3 = "N/A";
                $learning_column_4 = "N/A";
                $learning_column_5 = "N/A";
                $learning_column_6 = "N/A";

                if ($i > 0) {
                    $learning_column_1 = "";
                    $learning_column_2 = "";
                    $learning_column_3 = "";
                    $learning_column_4 = "";
                    $learning_column_5 = "";
                    $learning_column_6 = "";
                }

                if ($i < count($learning)) {
                    $learning_column_1 = $learning[$i]->learning_title;
                    $learning_column_2 = date('m/d/Y', strtotime($learning[$i]->attendance_from));
                    $learning_column_3 = date('m/d/Y', strtotime($learning[$i]->attendance_to));
                    $learning_column_4 = $learning[$i]->number_hours;
                    $learning_column_5 = $learning[$i]->learning_type;
                    $learning_column_6 = $learning[$i]->conducted_by;
                }

                $learnings[$i] = ['learning_title' => $learning_column_1, 'attendance_from' => $learning_column_2,
                    'attendance_to' => $learning_column_3, 'number_hours' => $learning_column_4,
                    'learning_type' => $learning_column_5, 'conducted_by' => $learning_column_6];
            }

            $next_page_learning_outer_array = [];
            $next_page_learning_inner_array = [];
            $outer_counter = 0;
            $counter = 0;

            if (count($learning) > 20) {
                for ($i = 20; $i < count($learning); $i++) {
                    if ($counter % 40 == 0 && $counter > 0) {
                        $next_page_learning_outer_array[$outer_counter++] = $next_page_learning_inner_array;
                        $counter = 0;
                        $next_page_learning_inner_array = [];
                    }

                    $learning_column_1 = $learning[$i]->learning_title;
                    $learning_column_2 = date('m/d/Y', strtotime($learning[$i]->attendance_from));
                    $learning_column_3 = date('m/d/Y', strtotime($learning[$i]->attendance_to));
                    $learning_column_4 = $learning[$i]->number_hours;
                    $learning_column_5 = $learning[$i]->learning_type;
                    $learning_column_6 = $learning[$i]->conducted_by;

                    $next_page_learning_inner_array[$counter++] = ['learning_title' => $learning_column_1, 'attendance_from' => $learning_column_2,
                        'attendance_to' => $learning_column_3, 'number_hours' => $learning_column_4,
                        'learning_type' => $learning_column_5, 'conducted_by' => $learning_column_6];

                    #CHECK IF LAST LOOP
                    if ($i == count($learning) - 1) {
                        $next_page_learning_outer_array[$outer_counter++] = $next_page_learning_inner_array;
                    }
                }
            }

            for ($i = 0; $i < 7; $i++) {
                $_skill = "N/A";
                $_recognition = "N/A";
                $_membership = "N/A";

                if ($i > 0) {
                    $_skill = "";
                }

                if ($i > 0) {
                    $_recognition = "";
                }

                if ($i > 0) {
                    $_membership = "";
                }

                #CHECK IF THE LOOP EXCEEDS TO ACTUAL DATA SIZE
                if ($i < count($skill)) {
                    $_skill = $skill[$i]->skills;
                }
                if ($i < count($recognition)) {
                    $_recognition = $recognition[$i]->recognition;
                }
                if ($i < count($membership)) {
                    $_membership = $membership[$i]->association;
                }

                $skills[$i] = ['skill' => $_skill, 'recognition' => $_recognition, 'association' => $_membership];
            }


            for ($i = 0; $i < 3; $i++) {
                $name = "N/A";
                $address = "N/A";
                $contact_no = "N/A";

                #CHECK IF THE LOOP EXCEEDS TO ACTUAL DATA SIZE
                if ($i < count($reference)) {
                    $name = $reference[$i]->name;
                    $address = $reference[$i]->address;
                    $contact_no = $reference[$i]->contact_no;
                }
                $references[$i] = ['name' => $name, 'address' => $address, 'contact_no' => $contact_no];
            }

            $pdf = Pdf::loadView('pdf.pds', ['user' => $user, 'employee' => $employee,
                'residential' => $residential, 'permanent' => $permanent, 'family' => $family, 'childrens' => $childrens,
                'educations' => $educations, 'eligibility' => $eligibilities, 'work_experience' => $work_experiences,
                'voluntary_work' => $voluntary_works, 'learning' => $learnings, 'next_page_learning' => $next_page_learning_outer_array, 'skill' => $skills, 'survey' => $surveys,
                'reference' => $references, 'pds_date' => $request->pds_date]);

            return $pdf->stream('invoice.pdf', array("Attachment" => false));
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
    }
}
