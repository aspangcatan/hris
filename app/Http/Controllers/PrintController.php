<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\BusinessInterest;
use App\Models\Children;
use App\Models\CivilService;
use App\Models\Education;
use App\Models\Employee;
use App\Models\FamilyBackground;
use App\Models\GovernmentRelative;
use App\Models\Learning;
use App\Models\Liabilities;
use App\Models\Marriages;
use App\Models\Membership;
use App\Models\PersonalProperties;
use App\Models\RealProperties;
use App\Models\Recognition;
use App\Models\Reference;
use App\Models\RelatedAccomplishment;
use App\Models\RelatedDuties;
use App\Models\RelatedWork;
use App\Models\SalnChildren;
use App\Models\ServiceRecords;
use App\Models\Skill;
use App\Models\SpouseBusinessInterest;
use App\Models\SpouseLiabilities;
use App\Models\SpousePersonalProperties;
use App\Models\SpouseRealProperties;
use App\Models\Survey;
use App\Models\User;
use App\Models\Voluntary;
use Barryvdh\DomPDF\Facade\Pdf;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use setasign\Fpdi\Fpdi;

class PrintController extends Controller
{
    public function getMonthName($month)
    {
        switch ($month) {
            case "01":
                return "Jan. ";
            case "02":
                return "Feb. ";
            case "03":
                return "Mar. ";
            case "04":
                return "Apr. ";
            case "05":
                return "May. ";
            case "06":
                return "Jun. ";
            case "07":
                return "Jul. ";
            case "08":
                return "Aug. ";
            case "09":
                return "Sep. ";
            case "10":
                return "Oct. ";
            case "11":
                return "Nov. ";
            case "12":
                return "Dec. ";
        }
    }

    public function getInclusiveDates($date_applied)
    {
        $month = "";
        $title = "";
        $year = "";
        for ($i = 0; $i < count($date_applied); $i++) {
            $split = explode("-", $date_applied[$i]->date_applied);
            $year = $split[0];
            if ($title == "") {
                $title = self::getMonthName($split[1]) . $split[2];
                $month = $split[0];
                continue;
            }

            if ($month == $split[0]) {
                $title = $title . ", " . $split[2];
            } else {
                $title = $title . ", " . $split[1] . " " . self::getMonthName($split[1]) . $split[2];
            }
            $month = $split[1];
        }
        $title = $title . " " . $year;
        return $title;
    }

    public function getTypeXY($type)
    {
        switch ($type) {
            case "Vacation Leave":
            case 2:
                return ['x' => 19.2, 'y' => 57.5];
            case "Mandatory/Forced Leave":
            case 8:
                return ['x' => 19.2, 'y' => 61];
            case "Sick Leave":
            case 1:
                return ['x' => 19.2, 'y' => 65];
            case "Maternity Leave":
            case 9:
                return ['x' => 19.2, 'y' => 68.5];
            case "Paternity Leave":
            case 3:
                return ['x' => 19.2, 'y' => 72.3];
            case "Special Privilege":
            case 10:
                return ['x' => 19.2, 'y' => 76];
            case "Solo Parent Leave":
            case 4:
                return ['x' => 19.2, 'y' => 80];
            case "Study Leave":
            case 11:
                return ['x' => 19.2, 'y' => 83.5];
            case "10-Day VAWC Leave":
            case 5:
                return ['x' => 19.2, 'y' => 87.3];
            case "Rehabilitation Privilege":
            case 12:
                return ['x' => 19.2, 'y' => 91];
            case "Special Leave Benefits for Women":
            case 6:
                return ['x' => 19.2, 'y' => 94.5];
            case "Special Emergency (Calamity) Leave":
            case 13:
                return ['x' => 19.2, 'y' => 98.5];
            case "Adoption Leave":
            case 7:
                return ['x' => 19.2, 'y' => 102.2];
            default:
                return ['x' => 0, 'y' => 0];
        }
    }

    public function getDetailsXY($type)
    {
        switch ($type) {
            case "Within the Philippines":
                return ['x' => 88.5, 'y' => 61, 'z' => 111];
            case "Abroad (Specify)":
                return ['x' => 88.5, 'y' => 65, 'z' => 108];
            case "In Hospital (Specify Illness)":
                return ['x' => 88.5, 'y' => 72.3, 'z' => 117];
            case "Out Patient (Specify Illness)":
                return ['x' => 88.5, 'y' => 76, 'z' => 118];
            case "In case of Special Leave Benefits for Women":
                return ['x' => 0, 'y' => 87, 'z' => 104];
            case "Completion of Master's Degree":
                return ['x' => 88.5, 'y' => 98.5, 'z' => 120];
            case "BAR/Board Examination Review":
                return ['x' => 88.5, 'y' => 102.5, 'z' => 120];
            case "Monetization of Leave Credits":
                return ['x' => 88.5, 'y' => 109.5, 'z' => 120];
            case "Terminal Leave":
                return ['x' => 88.5, 'y' => 113.5, 'z' => 120];
            default:
                return ['x' => 0, 'y' => 0, 'z' => 0];
        }
    }

    public function minifyDesignation($texts)
    {
        $text_array = explode(" ", $texts);
        if (count($text_array) < 4) return strtoupper($texts);

        $title = '';
        for ($i = 0; $i < count($text_array); $i++) {
            if ($i == count($text_array) - 1) {
                continue;
            }
            $word = $text_array[$i];
            $title = strtoupper($title . '' . $word[0]);
        }

        $title = $title . ' ' . $text_array[count($text_array) - 1];
        return $title;
    }

    public function printLeaveForm(Request $request)
    {
        $leave_application = DB::table('hris.leave_applications as l')
            ->leftJoin('hris.payroll_settings as p', 'p.userid', '=', 'l.user_id')
            ->join('tdh_user.users as u', 'l.user_id', '=', 'u.id')
            ->join('tdh_user.designation as d', 'u.designation', '=', 'd.id')
            ->join('tdh_user.division as di', 'u.division', '=', 'di.id')
            ->join('tdh_user.section as s', 'u.section', '=', 's.id')
            ->where('l.id', '=', $request->id)
            ->get(['p.basic_salary', 'di.code as division', 's.code as section', 'u.fname', 'u.mname', 'u.lname', 'l.created_at', 'd.description as designation', 'l.leave_id', 'l.detail', 'l.extra', 'u.division as division_id', 'l.commutation', 'l.id', 'l.created_at'])
            ->first();

        $dates_applied = DB::table('leave_date_applied')->where('leave_application_id', $leave_application->id)->orderBy('date_applied', 'ASC')->get(['date_applied']);


        $pdf = new Fpdi();
        $pdf->AddPage();

//        $pdf->setSourceFile(public_path("pdf/leave_form_mcc.pdf"));
        switch ($leave_application->division_id) {
            case 1:
                $pdf->setSourceFile(public_path("pdf/leave_form_mcc.pdf"));
                break;
            case 2:
                $pdf->setSourceFile(public_path("pdf/leave_form_hopss.pdf"));
                break;
            case 3:
                $pdf->setSourceFile(public_path("pdf/leave_form_medical.pdf"));
                break;
            case 4:
                $pdf->setSourceFile(public_path("pdf/leave_form_nursing.pdf"));
                break;
            case 5:
                $pdf->setSourceFile(public_path("pdf/leave_form_finance.pdf"));
                break;
            case 6:
                $pdf->setSourceFile(public_path("pdf/leave_form_qmd.pdf"));
                break;
        }

        $tplId = $pdf->importPage(1);
        $pdf->useTemplate($tplId, null, null, null, 210, true);
        #OFFICE/DEPARTMENT POSITION
        $pdf->SetFont('helvetica', 'b', 6);
        $pdf->SetXY(20, 37);
        $pdf->Write(0.1, strtoupper($leave_application->division . ' / ' . $leave_application->section));
        $pdf->SetXY(70, 37);
        $pdf->Write(0.1, strtoupper($leave_application->lname));
        $pdf->SetXY(98, 37);
        $pdf->Write(0.1, strtoupper($leave_application->fname));
        $pdf->SetXY(123, 37);
        $mname = ($leave_application->mname && $leave_application->mname != "") ? strtoupper($leave_application->mname[0]) . '.' : "";
        $pdf->Write(0.1, $mname);

        $pdf->SetXY(38, 43);
        $pdf->Write(0.1, Carbon::createFromFormat('Y-m-d', explode(' ', $leave_application->created_at)[0])->format('M. j, Y'));
        $pdf->SetXY(77, 43);
        $pdf->Write(0.1, self::minifyDesignation($leave_application->designation));
        $pdf->SetXY(123, 43);
        $pdf->Write(0.1, 'P' . number_format($leave_application->basic_salary ?? 0, 2, '.', ','));

//
//        #DETAILS OF LEAVE
        $leave_type = self::getTypeXY($leave_application->leave_id);
        $leave_details = self::getDetailsXY($leave_application->detail);
        if ($leave_details['x'] != 0) {
            $pdf->SetFont('ZapfDingbats', "", 8);
            $pdf->SetXY($leave_details['x'], $leave_details['y']);
            $pdf->Write(0, chr(51));
        }
        $pdf->SetFont('helvetica', 'b', 6);
        $pdf->SetXY($leave_details['z'], $leave_details['y']);
        $pdf->Write(0.1, $leave_application->extra);

        if ($leave_type['x'] == 0) {
            $pdf->SetXY(20, 113);
            $pdf->Write(0.1, $leave_application->leave_type);
        }

        $pdf->SetFont('ZapfDingbats', "", 8);
        $pdf->SetXY($leave_type['x'], $leave_type['y']);
        $pdf->Write(0, chr(51));

        $pdf->SetFont('ZapfDingbats', "", 8);
        if ($leave_application->commutation == "Not Requested")
            $pdf->SetXY(88.5, 122);
        else
            $pdf->SetXY(88.5, 126);
        $pdf->Write(0, chr(51));

        $total_days = count($dates_applied);
        $pdf->SetFont('helvetica', 'b', 6);
        $pdf->SetXY(20, 122);
        $pdf->Write(0.1, $total_days . " day(s) (" . ($total_days * 8) . " hrs)");
        $pdf->SetXY(20, 130);
        $pdf->Write(0.1, self::getInclusiveDates($dates_applied));
        $pdf->Output('I', "Leave Form.pdf");
    }

    public function printWorkExperience(Request $request)
    {
        try {
            $related_work = RelatedWork::where('work_id', $request->work_id)->get();
            for ($i = 0; $i < count($related_work); $i++) {
                $accomplishment = RelatedAccomplishment::where('related_id', $related_work[$i]->id)->get();
                $duties = RelatedDuties::where('related_id', $related_work[$i]->id)->get();
                $related_work[$i]->accomplishments = $accomplishment;
                $related_work[$i]->duties = $duties;
            }
            $pdf = Pdf::loadView('pdf.work_experience', ['related_work' => $related_work]);
            return $pdf->stream('work_experience.pdf', array("Attachment" => false));
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
    }

    public function generateSALNReport()
    {
        // sample data (replace with DB data)
        $employees = collect(range(1, 35))->map(function ($i) {
            return [
                'no' => $i,
                'lastname' => '',
                'firstname' => '',
                'middlename' => '',
                'tin' => '',
                'position' => '',
                'net_worth' => '',
            ];
        });

        $pdf = Pdf::loadView('pdf.saln-report', [
            'employees' => $employees,
            'agency' => 'CEBU SOUTH MEDICAL CENTER',
            'calendar_year' => '2024'
        ])
            ->setPaper('legal', 'landscape');

        // 👇 PREVIEW instead of download
        return $pdf->stream('SALN_Report.pdf');
    }

    public function printSaln(Request $request)
    {
        try {
            $declarant = DB::table('tdh_user.users as u')
                ->leftJoin('hris.saln_declarant as d', 'u.id', '=', 'd.user_id')
                ->leftJoin('hris.addresses as a', function ($join) {
                    $join->on('u.id', '=', 'a.user_id')
                        ->where(function ($query) {
                            $query->whereNull('a.address_type')
                                ->orWhere('a.address_type', 'Permanent');
                        });
                })
                ->where('u.id', Auth::id())
                ->get([
                    'u.fname',
                    'u.mname',
                    'u.lname',
                    'u.suffix',
                    'd.position',
                    'd.agency',
                    'd.office',
                    'd.government_id_issued',
                    'd.government_id_no',
                    'd.date_issued',
                    'a.house_no',
                    'a.street',
                    'a.subdivision_village',
                    'a.barangay',
                    'a.city',
                    'a.province'
                ])
                ->first();

            $spouse = DB::table('hris.family_backgrounds as f')
                ->leftJoin('hris.saln_spouse as d', 'f.user_id', '=', 'd.user_id')
                ->where('f.user_id', '=', Auth::id())
                ->get(['f.spouse_fname', 'f.spouse_mname', 'f.spouse_lname', 'f.spouse_ext_name', 'd.position', 'd.agency',
                    'd.office', 'd.government_id_issued', 'd.government_id_no', 'd.date_issued'])
                ->first();


            $children_array = [
                ['name' => '', 'birthdate' => '', 'age' => ''],
                ['name' => '', 'birthdate' => '', 'age' => ''],
                ['name' => '', 'birthdate' => '', 'age' => ''],
                ['name' => '', 'birthdate' => '', 'age' => ''],
            ];

            $marriages_array = [
                ['name' => ''],
                ['name' => ''],
            ];


            $real_properties_array = [
                [
                    'description' => 'N/A',
                    'kind' => 'N/A',
                    'exact_location' => 'N/A',
                    'assessed_value' => 'N/A',
                    'current_fair' => 'N/A',
                    'acquisition_year' => 'N/A',
                    'acquisition_mode' => 'N/A',
                    'acquisition_cost' => 'N/A',
                ],
                [
                    'description' => 'N/A',
                    'kind' => 'N/A',
                    'exact_location' => 'N/A',
                    'assessed_value' => 'N/A',
                    'current_fair' => 'N/A',
                    'acquisition_year' => 'N/A',
                    'acquisition_mode' => 'N/A',
                    'acquisition_cost' => 'N/A',
                ],
                [
                    'description' => 'N/A',
                    'kind' => 'N/A',
                    'exact_location' => 'N/A',
                    'assessed_value' => 'N/A',
                    'current_fair' => 'N/A',
                    'acquisition_year' => 'N/A',
                    'acquisition_mode' => 'N/A',
                    'acquisition_cost' => 'N/A',
                ],
                [
                    'description' => 'N/A',
                    'kind' => 'N/A',
                    'exact_location' => 'N/A',
                    'assessed_value' => 'N/A',
                    'current_fair' => 'N/A',
                    'acquisition_year' => 'N/A',
                    'acquisition_mode' => 'N/A',
                    'acquisition_cost' => 'N/A',
                ]
            ];

            $spouse_real_properties_array = [
                [
                    'description' => 'N/A',
                    'kind' => 'N/A',
                    'exact_location' => 'N/A',
                    'assessed_value' => 'N/A',
                    'current_fair' => 'N/A',
                    'acquisition_year' => 'N/A',
                    'acquisition_mode' => 'N/A',
                    'acquisition_cost' => 'N/A',
                ],
                [
                    'description' => 'N/A',
                    'kind' => 'N/A',
                    'exact_location' => 'N/A',
                    'assessed_value' => 'N/A',
                    'current_fair' => 'N/A',
                    'acquisition_year' => 'N/A',
                    'acquisition_mode' => 'N/A',
                    'acquisition_cost' => 'N/A',
                ],
                [
                    'description' => 'N/A',
                    'kind' => 'N/A',
                    'exact_location' => 'N/A',
                    'assessed_value' => 'N/A',
                    'current_fair' => 'N/A',
                    'acquisition_year' => 'N/A',
                    'acquisition_mode' => 'N/A',
                    'acquisition_cost' => 'N/A',
                ],
                [
                    'description' => 'N/A',
                    'kind' => 'N/A',
                    'exact_location' => 'N/A',
                    'assessed_value' => 'N/A',
                    'current_fair' => 'N/A',
                    'acquisition_year' => 'N/A',
                    'acquisition_mode' => 'N/A',
                    'acquisition_cost' => 'N/A',
                ]
            ];

            $personal_properties_array = [
                ['gadget' => 'N/A', 'various_years' => 'N/A', 'acquisition_cost' => 'N/A'],
                ['gadget' => 'N/A', 'various_years' => 'N/A', 'acquisition_cost' => 'N/A'],
                ['gadget' => 'N/A', 'various_years' => 'N/A', 'acquisition_cost' => 'N/A'],
                ['gadget' => 'N/A', 'various_years' => 'N/A', 'acquisition_cost' => 'N/A'],
                ['gadget' => 'N/A', 'various_years' => 'N/A', 'acquisition_cost' => 'N/A'],
                ['gadget' => 'N/A', 'various_years' => 'N/A', 'acquisition_cost' => 'N/A'],
            ];

            $spouse_personal_properties_array = [
                ['gadget' => 'N/A', 'various_years' => 'N/A', 'acquisition_cost' => 'N/A'],
                ['gadget' => 'N/A', 'various_years' => 'N/A', 'acquisition_cost' => 'N/A'],
                ['gadget' => 'N/A', 'various_years' => 'N/A', 'acquisition_cost' => 'N/A'],
                ['gadget' => 'N/A', 'various_years' => 'N/A', 'acquisition_cost' => 'N/A'],
            ];


            $personal_properties_extended = [];
            $spouse_personal_properties_extended = [];

            $liabilities_array = [
                ['nature' => 'N/A', 'creditors_name' => 'N/A', 'outstanding_balance' => 'N/A'],
                ['nature' => 'N/A', 'creditors_name' => 'N/A', 'outstanding_balance' => 'N/A'],
                ['nature' => 'N/A', 'creditors_name' => 'N/A', 'outstanding_balance' => 'N/A'],
                ['nature' => 'N/A', 'creditors_name' => 'N/A', 'outstanding_balance' => 'N/A'],
            ];

            $spouse_liabilities_array = [
                ['nature' => 'N/A', 'creditors_name' => 'N/A', 'outstanding_balance' => 'N/A'],
                ['nature' => 'N/A', 'creditors_name' => 'N/A', 'outstanding_balance' => 'N/A'],
                ['nature' => 'N/A', 'creditors_name' => 'N/A', 'outstanding_balance' => 'N/A'],
                ['nature' => 'N/A', 'creditors_name' => 'N/A', 'outstanding_balance' => 'N/A'],
            ];

            $business_array = [
                ['enterprise' => 'N/A', 'address' => 'N/A', 'nature' => 'N/A', 'date_acquisition' => 'N/A'],
                ['enterprise' => 'N/A', 'address' => 'N/A', 'nature' => 'N/A', 'date_acquisition' => 'N/A'],
                ['enterprise' => 'N/A', 'address' => 'N/A', 'nature' => 'N/A', 'date_acquisition' => 'N/A'],
            ];

            $spouse_business_array = [
                ['enterprise' => 'N/A', 'address' => 'N/A', 'nature' => 'N/A', 'date_acquisition' => 'N/A'],
                ['enterprise' => 'N/A', 'address' => 'N/A', 'nature' => 'N/A', 'date_acquisition' => 'N/A'],
                ['enterprise' => 'N/A', 'address' => 'N/A', 'nature' => 'N/A', 'date_acquisition' => 'N/A'],
            ];

            $relatives_array = [
                ['name' => 'N/A', 'relationship' => 'N/A', 'position' => 'N/A', 'agency' => 'N/A'],
                ['name' => 'N/A', 'relationship' => 'N/A', 'position' => 'N/A', 'agency' => 'N/A'],
                ['name' => 'N/A', 'relationship' => 'N/A', 'position' => 'N/A', 'agency' => 'N/A'],
                ['name' => 'N/A', 'relationship' => 'N/A', 'position' => 'N/A', 'agency' => 'N/A'],
                ['name' => 'N/A', 'relationship' => 'N/A', 'position' => 'N/A', 'agency' => 'N/A'],
            ];

            $children = SalnChildren::where('user_id', Auth::id())->get();
            for ($i = 0; $i < count($children); $i++) {
                $birthdate = new DateTime($children[$i]['birthdate']);
                $today = new DateTime();
                $age = $today->diff($birthdate)->y;
                $children_array[$i] = [
                    'name' => $children[$i]['name'],
                    'birthdate' => date('m/d/Y', strtotime($children[$i]['birthdate'])),
                    'age' => $age,
                ];
            }

            $marriages = Marriages::where('userid', Auth::id())->take(2)->get();
            for ($i = 0; $i < count($marriages); $i++) {
                $marriages_array[$i] = [
                    'name' => $marriages[$i]['spouse']
                ];
            }

            $real_properties_subtotal_a = 0;
            $real_properties_subtotal_b = 0;
            $all_real_properties = RealProperties::where('user_id', Auth::id())->get();

            $real_properties_array = [];
            $real_properties_b_array = [];

            foreach ($all_real_properties as $i => $prop) {
                $cost = (double)str_replace(',', '', $prop->acquisition_cost);
                $entry = [
                    'description' => $prop->description,
                    'kind' => $prop->kind,
                    'exact_location' => $prop->exact_location,
                    'assessed_value' => $prop->assessed_value,
                    'current_fair' => $prop->current_fair,
                    'acquisition_year' => $prop->acquisition_year,
                    'acquisition_mode' => $prop->acquisition_mode,
                    'acquisition_cost' => number_format($cost, 2),
                ];

                if ($i < 4) {
                    $real_properties_array[$i] = $entry;
                    $real_properties_subtotal_a += $cost;
                } else {
                    $real_properties_b_array[] = $entry;
                    $real_properties_subtotal_b += $cost;
                }
            }

            // Pad Annex A with N/A if empty, then empty strings
            for ($i = count($real_properties_array); $i < 4; $i++) {
                $content = ($i === 0 && count($all_real_properties) === 0) ? 'N/A' : '';
                $real_properties_array[$i] = [
                    'description' => $content, 'kind' => '', 'exact_location' => '',
                    'assessed_value' => '', 'current_fair' => '', 'acquisition_year' => '',
                    'acquisition_mode' => '', 'acquisition_cost' => ''
                ];
            }

            // Pad Annex B with N/A if empty, then empty strings (Min 4 rows)
            for ($i = count($real_properties_b_array); $i < 4; $i++) {
                $content = ($i === 0 && count($all_real_properties) <= 4) ? 'N/A' : '';
                $real_properties_b_array[] = [
                    'description' => $content, 'kind' => '', 'exact_location' => '',
                    'assessed_value' => '', 'current_fair' => '', 'acquisition_year' => '',
                    'acquisition_mode' => '', 'acquisition_cost' => ''
                ];
            }

            $spouse_real_properties_subtotal = 0;
            $spouse_real_properties_db = SpouseRealProperties::where('user_id', Auth::id())->get();
            $spouse_real_properties_array = [];

            foreach ($spouse_real_properties_db as $i => $prop) {
                $cost = (double)str_replace(',', '', $prop->acquisition_cost);
                $spouse_real_properties_array[] = [
                    'description' => $prop->description,
                    'kind' => $prop->kind,
                    'exact_location' => $prop->exact_location,
                    'assessed_value' => $prop->assessed_value,
                    'current_fair' => $prop->current_fair,
                    'acquisition_year' => $prop->acquisition_year,
                    'acquisition_mode' => $prop->acquisition_mode,
                    'acquisition_cost' => number_format($cost, 2),
                ];
                $spouse_real_properties_subtotal += $cost;
            }

            // Pad Spouse Real Properties (Annex C) with N/A if empty, then empty strings (Min 4 rows)
            for ($i = count($spouse_real_properties_array); $i < 4; $i++) {
                $content = ($i === 0 && count($spouse_real_properties_db) === 0) ? 'N/A' : '';
                $spouse_real_properties_array[] = [
                    'description' => $content, 'kind' => '', 'exact_location' => '',
                    'assessed_value' => '', 'current_fair' => '', 'acquisition_year' => '',
                    'acquisition_mode' => '', 'acquisition_cost' => ''
                ];
            }


            $personal_properties_subtotal_a = 0;
            $personal_properties_subtotal_b = 0;
            $all_personal_properties = PersonalProperties::where('user_id', Auth::id())->get();

            $personal_properties_array = [];
            $personal_properties_b_array = [];

            foreach ($all_personal_properties as $i => $prop) {
                $cost = (double)str_replace(',', '', $prop->acquisition_cost);
                $entry = [
                    'gadget' => $prop->gadget,
                    'various_years' => $prop->various_years,
                    'acquisition_cost' => number_format($cost, 2)
                ];

                if ($i < 6) {
                    $personal_properties_array[$i] = $entry;
                    $personal_properties_subtotal_a += $cost;
                } else {
                    $personal_properties_b_array[] = $entry;
                    $personal_properties_subtotal_b += $cost;
                }
            }

            for ($i = count($personal_properties_array); $i < 6; $i++) {
                $content = ($i === 0 && count($all_personal_properties) === 0) ? 'N/A' : '';
                $personal_properties_array[$i] = ['gadget' => $content, 'various_years' => '', 'acquisition_cost' => ''];
            }

            // Pad Annex B with N/A if empty, then empty strings (Min 4 rows)
            for ($i = count($personal_properties_b_array); $i < 4; $i++) {
                $content = ($i === 0 && count($all_personal_properties) <= 6) ? 'N/A' : '';
                $personal_properties_b_array[] = ['gadget' => $content, 'various_years' => '', 'acquisition_cost' => ''];
            }

            $spouse_personal_properties_subtotal = 0;
            $spouse_personal_properties_db = SpousePersonalProperties::where('user_id', Auth::id())->get();
            $spouse_personal_properties_array = [];

            foreach ($spouse_personal_properties_db as $prop) {
                $cost = (double)str_replace(',', '', $prop->acquisition_cost);
                $spouse_personal_properties_array[] = [
                    'gadget' => $prop->gadget,
                    'various_years' => $prop->various_years,
                    'acquisition_cost' => number_format($cost, 2)
                ];
                $spouse_personal_properties_subtotal += $cost;
            }

            // Pad Spouse Personal Properties (Annex C) with N/A if empty, then empty strings (Min 4 rows)
            for ($i = count($spouse_personal_properties_array); $i < 4; $i++) {
                $content = ($i === 0 && count($spouse_personal_properties_db) === 0) ? 'N/A' : '';
                $spouse_personal_properties_array[] = ['gadget' => $content, 'various_years' => '', 'acquisition_cost' => ''];
            }

            $liabilities_total_a = 0;
            $liabilities_total_b = 0;
            $all_liabilities = Liabilities::where('user_id', Auth::id())->get();

            $liabilities_array = [];
            $liabilities_b_array = [];

            foreach ($all_liabilities as $i => $lib) {
                $bal = (double)str_replace(',', '', $lib->outstanding_balance);
                $entry = [
                    'nature' => $lib->nature,
                    'creditors_name' => $lib->creditors_name,
                    'outstanding_balance' => number_format($bal, 2)
                ];

                if ($i < 4) {
                    $liabilities_array[$i] = $entry;
                    $liabilities_total_a += $bal;
                } else {
                    $liabilities_b_array[] = $entry;
                    $liabilities_total_b += $bal;
                }
            }

            for ($i = count($liabilities_array); $i < 4; $i++) {
                $content = ($i === 0 && count($all_liabilities) === 0) ? 'N/A' : '';
                $liabilities_array[$i] = ['nature' => $content, 'creditors_name' => '', 'outstanding_balance' => ''];
            }

            // Pad Annex B with N/A if empty, then empty strings (Min 4 rows)
            for ($i = count($liabilities_b_array); $i < 4; $i++) {
                $content = ($i === 0 && count($all_liabilities) <= 4) ? 'N/A' : '';
                $liabilities_b_array[] = ['nature' => $content, 'creditors_name' => '', 'outstanding_balance' => ''];
            }

            $spouse_liabilities_total = 0;
            $spouse_liabilities_db = SpouseLiabilities::where('user_id', Auth::id())->get();
            $spouse_liabilities_array = [];

            foreach ($spouse_liabilities_db as $lib) {
                $bal = (double)str_replace(',', '', $lib->outstanding_balance);
                $spouse_liabilities_array[] = [
                    'nature' => $lib->nature,
                    'creditors_name' => $lib->creditors_name,
                    'outstanding_balance' => number_format($bal, 2)
                ];
                $spouse_liabilities_total += $bal;
            }

            // Pad Spouse Liabilities (Annex C) with N/A if empty, then empty strings (Min 4 rows)
            for ($i = count($spouse_liabilities_array); $i < 4; $i++) {
                $content = ($i === 0 && count($spouse_liabilities_db) === 0) ? 'N/A' : '';
                $spouse_liabilities_array[] = ['nature' => $content, 'creditors_name' => '', 'outstanding_balance' => ''];
            }

            $all_business = BusinessInterest::where('user_id', Auth::id())->get();
            $business_array = [];
            $business_b_array = [];

            foreach ($all_business as $i => $bus) {
                $entry = [
                    'enterprise' => $bus->enterprise,
                    'address' => $bus->address,
                    'nature' => $bus->nature,
                    'date_acquisition' => date('m/d/Y', strtotime($bus->date_acquisition))
                ];

                if ($i < 3) {
                    $business_array[$i] = $entry;
                } else {
                    $business_b_array[] = $entry;
                }
            }

            for ($i = count($business_array); $i < 3; $i++) {
                $content = ($i === 0 && count($all_business) === 0) ? 'N/A' : '';
                $business_array[$i] = ['enterprise' => $content, 'address' => '', 'nature' => '', 'date_acquisition' => ''];
            }

            // Pad Annex B with N/A if empty, then empty strings (Min 3 rows)
            for ($i = count($business_b_array); $i < 3; $i++) {
                $content = ($i === 0 && count($all_business) <= 3) ? 'N/A' : '';
                $business_b_array[] = ['enterprise' => $content, 'address' => '', 'nature' => '', 'date_acquisition' => ''];
            }

            $spouse_business_db = SpouseBusinessInterest::where('user_id', Auth::id())->get();
            $spouse_business_array = [];
            foreach ($spouse_business_db as $bus) {
                $spouse_business_array[] = [
                    'enterprise' => $bus->enterprise,
                    'address' => $bus->address,
                    'nature' => $bus->nature,
                    'date_acquisition' => date('m/d/Y', strtotime($bus->date_acquisition))
                ];
            }

            // Pad Spouse Business Interests (Annex C) with N/A if empty, then empty strings (Min 3 rows)
            for ($i = count($spouse_business_array); $i < 3; $i++) {
                $content = ($i === 0 && count($spouse_business_db) === 0) ? 'N/A' : '';
                $spouse_business_array[] = ['enterprise' => $content, 'address' => '', 'nature' => '', 'date_acquisition' => ''];
            }


            $all_relatives = GovernmentRelative::where('user_id', Auth::id())->get();
            $relatives_array = [];
            foreach ($all_relatives as $i => $rel) {
                if ($i < 5) {
                    $relatives_array[$i] = [
                        'name' => $rel->name,
                        'relationship' => $rel->relationship,
                        'position' => $rel->position,
                        'agency' => $rel->agency
                    ];
                }
            }

            for ($i = count($relatives_array); $i < 5; $i++) {
                $content = ($i === 0 && count($all_relatives) === 0) ? 'N/A' : '';
                $relatives_array[$i] = ['name' => $content, 'relationship' => '', 'position' => '', 'agency' => ''];
            }

            // Grand Totals for Summary
            $grand_real_subtotal = $real_properties_subtotal_a + $real_properties_subtotal_b;
            $grand_personal_subtotal = $personal_properties_subtotal_a + $personal_properties_subtotal_b;
            $grand_liabilities_total = $liabilities_total_a + $liabilities_total_b;
            $grand_assets_total = $grand_real_subtotal + $grand_personal_subtotal;
            $grand_net_worth = $grand_assets_total - $grand_liabilities_total;

            $addressParts = collect([
                $declarant->house_no ?? '', // Ensure default values are strings
                $declarant->street ?? '',
                $declarant->subdivision_village ?? '',
                $declarant->barangay ?? '',
                $declarant->city ?? '',
                $declarant->province ?? '',
            ])->filter(function ($part) {
                return !empty($part) && strtoupper($part) !== 'N/A'; // Filter out empty or 'N/A'
            })->values();

            $formattedAddress = $addressParts->join(', ');

            // Determine if Annex B is needed
            $show_annex_b = (count($all_real_properties) > 4 || count($all_personal_properties) > 6 || count($all_liabilities) > 4 || count($all_business) > 3);

            // Determine if Annex C is needed
            $show_annex_c = (count($spouse_real_properties_db) > 0 || count($spouse_personal_properties_db) > 0 || count($spouse_liabilities_db) > 0 || count($spouse_business_db) > 0);

            $total_pages = 2 + ($show_annex_b ? 1 : 0) + ($show_annex_c ? 1 : 0);

            ini_set('memory_limit', '512M');
            set_time_limit(300);

            $pdf = Pdf::loadView('pdf.new.saln', [
                'as_of_month' => $request->as_of_month,
                'as_of_day' => $request->as_of_day,
                'as_of_year' => $request->as_of_year,
                'declarant' => $declarant,
                'address' => $formattedAddress,
                'spouse' => $spouse,
                'children' => $children_array,
                'marriages' => $marriages_array,
                'real_properties' => $real_properties_array,
                'real_properties_ext' => $real_properties_b_array,
                'real_properties_subtotal' => $real_properties_subtotal_a,
                'real_properties_ext_subtotal' => $real_properties_subtotal_b,

                'spouse_real_properties' => $spouse_real_properties_array,
                'spouse_real_properties_subtotal' => $spouse_real_properties_subtotal,

                'personal_properties' => $personal_properties_array,
                'personal_properties_ext' => $personal_properties_b_array,
                'personal_properties_subtotal' => $personal_properties_subtotal_a,
                'personal_properties_ext_subtotal' => $personal_properties_subtotal_b,

                'spouse_personal_properties' => $spouse_personal_properties_array,
                'spouse_personal_properties_subtotal' => $spouse_personal_properties_subtotal,

                'liabilities' => $liabilities_array,
                'liabilities_ext' => $liabilities_b_array,
                'liabilities_total' => $liabilities_total_a,
                'liabilities_ext_total' => $liabilities_total_b,

                'spouse_liabilities' => $spouse_liabilities_array,
                'spouse_liabilities_total' => $spouse_liabilities_total,

                'business' => $business_array,
                'business_ext' => $business_b_array,
                'spouse_business' => $spouse_business_array,
                'relatives' => $relatives_array,

                'grand_real_subtotal' => $grand_real_subtotal,
                'grand_personal_subtotal' => $grand_personal_subtotal,
                'grand_liabilities_total' => $grand_liabilities_total,
                'grand_assets_total' => $grand_assets_total,
                'grand_net_worth' => $grand_net_worth,
                'print_date' => $request->print_date,
                'filing_type' => $request->filing_type,
                'compliance_for' => $request->compliance_for,
                'business_interest' => $request->business_interest,
                'government_relatives' => $request->government_relatives,
                'before_me' => $request->before_me,
                'day_of' => $request->day_of,
                'spouse_signature' => $request->spouse_signature,
                'compliance_for' => $request->compliance_for,
                'multiple_marriages' => $request->multiple_marriages,
                'show_annex_b' => $show_annex_b,
                'show_annex_c' => $show_annex_c,
                'total_pages' => $total_pages
            ]);

            $pdf->set_paper('A4', 'landscape');

            return $pdf->stream('saln.pdf', array("Attachment" => false));
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
    }


    public function printServiceRecord(Request $request)
    {
        $user = User::find($request->id);
        $employee = Employee::where('user_id', $request->id)->first();
        if (!$employee) {
            return "Ooops something went wrong";
        }

        $service_records = ServiceRecords::where('user_id', $request->id)->get();
        $next_page_service_outer_array = [];
        $next_page_service_inner_array = [];
        $outer_counter = 0;
        $counter = 0;

        $service_array = [];

        if (count($service_records) < $request->rows) {
            $service_array = $service_records;
        } else {
            for ($i = 0; $i < $request->rows; $i++) {
                $service_array[$i] = $service_records[$i];
            }

            for ($i = $request->rows; $i < count($service_records); $i++) {
                if ($counter % $request->rows == 0 && $counter > 0) {
                    $next_page_service_outer_array[$outer_counter++] = $next_page_service_inner_array;
                    $counter = 0;
                    $next_page_service_inner_array = [];
                }

                $next_page_service_inner_array[$counter++] = $service_records[$i];

                #CHECK IF LAST LOOP
                if ($i == count($service_records) - 1) {
                    $next_page_service_outer_array[$outer_counter++] = $next_page_service_inner_array;
                }
            }
        }

        $pdf = Pdf::loadView('pdf.service_record', ['service_records' => $service_array, 'next_page_service' => $next_page_service_outer_array, 'employee' => $employee, 'user' => $user]);
        return $pdf->stream('service_record.pdf', array("Attachment" => false));
    }

    public function printPds(Request $request)
    {
        try {
            if (session("access_rights") == "standard" && $request->id > 0) {
                return "Permission denied";
            }

            $user_id = Auth::id();
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
            if ($user) {
                $user->fname = mb_strtoupper($user->fname, 'UTF-8');
                $user->mname = mb_strtoupper($user->mname, 'UTF-8');
                $user->lname = mb_strtoupper($user->lname, 'UTF-8');
                $user->suffix = mb_strtoupper($user->suffix, 'UTF-8');
            }
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
                    $birthdate = date('d/m/Y', strtotime($children[$i]->birthdate));
                }

                $childrens[$i] = ['name' => $name, 'birthdate' => $birthdate];
            }

            for ($i = 0; $i < count($LEVELS); $i++) {
                $educations[$i][0] = [
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

            $index = 0;
            foreach ($education as $key) {
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

                if ($educations[$index][0]['school_name'] == "N/A") {
                    $educations[$index][0] = [
                        'level' => $LEVELS[$index],
                        'school_name' => $key->school_name,
                        'education_degree' => $key->education_degree,
                        'attendance_from' => $key->attendance_from,
                        'attendance_to' => $key->attendance_to,
                        'highest_level' => $key->highest_level,
                        'year_graduated' => ($key->year_graduated == 0) ? "N/A" : $key->year_graduated,
                        'scholarship' => $key->scholarship
                    ];
                } else {
                    $educations[$index][] = [
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
                    $eligibility_column_5 = "N/A";
                    if ($eligiblity[$i]->license_no)
                        $eligibility_column_5 = $eligiblity[$i]->license_no;

                    $eligibility_column_6 = "N/A";
                    if ($eligiblity[$i]->license_expiry)
                        $eligibility_column_6 = date('d/m/Y', strtotime($eligiblity[$i]->license_expiry));
                }
                $eligibilities[$i] = ['career_service' => $eligibility_column_1, 'rating' => $eligibility_column_2,
                    'examination_date' => $eligibility_column_3, 'examination_place' => $eligibility_column_4,
                    'license_no' => $eligibility_column_5, 'license_expiry' => $eligibility_column_6];
            }

            for ($i = 0; $i < $request->experience_rows; $i++) {
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
                    $work_column_1 = date('d/m/Y', strtotime($work_experience[$i]->working_from));
                    $work_column_2 = ($work_experience[$i]->is_current_work == 1) ? "Present" : date('d/m/Y', strtotime($work_experience[$i]->working_to));
                    $work_column_3 = $work_experience[$i]->position;
                    $work_column_4 = $work_experience[$i]->agency;
                    $work_column_5 = number_format($work_experience[$i]->monthly_salary, 2);
                    $work_column_6 = $work_experience[$i]->salary_grade;
                    $work_column_7 = $work_experience[$i]->appointment_status;
                    $work_column_8 = ($work_experience[$i]->is_government == 1) ? "Y" : "N";
                }

                $work_experiences[$i] = ['working_from' => $work_column_1, 'working_to' => $work_column_2,
                    'position' => $work_column_3, 'agency' => $work_column_4,
                    'monthly_salary' => $work_column_5, 'salary_grade' => $work_column_6,
                    'appointment_status' => $work_column_7, 'is_government' => $work_column_8];
            }


            $next_page_work_outer_array = [];
            $next_page_work_inner_array = [];
            $work_outer_counter = 0;
            $work_counter = 0;

            if (count($work_experience) > $request->experience_rows) {
                for ($i = $request->experience_rows; $i < count($work_experience); $i++) {
                    if ($work_counter % 40 == 0 && $work_counter > 0) {
                        $next_page_work_outer_array[$work_outer_counter++] = $next_page_work_inner_array;
                        $work_counter = 0;
                        $next_page_work_inner_array = [];
                    }

                    $work_column_1 = date('d/m/Y', strtotime($work_experience[$i]->working_from));
                    $work_column_2 = ($work_experience[$i]->is_current_work == 1) ? "Present" : date('d/m/Y', strtotime($work_experience[$i]->working_to));
                    $work_column_3 = $work_experience[$i]->position;
                    $work_column_4 = $work_experience[$i]->agency;
                    $work_column_5 = number_format($work_experience[$i]->monthly_salary, 2);
                    $work_column_6 = $work_experience[$i]->salary_grade;
                    $work_column_7 = $work_experience[$i]->appointment_status;
                    $work_column_8 = ($work_experience[$i]->is_government == 1) ? "Y" : "N";

                    $next_page_work_inner_array[$work_counter++] = ['working_from' => $work_column_1, 'working_to' => $work_column_2,
                        'position' => $work_column_3, 'agency' => $work_column_4,
                        'monthly_salary' => $work_column_5, 'salary_grade' => $work_column_6,
                        'appointment_status' => $work_column_7, 'is_government' => $work_column_8];

                    #CHECK IF LAST LOOP
                    if ($i == count($work_experience) - 1) {
                        $next_page_work_outer_array[$work_outer_counter++] = $next_page_work_inner_array;
                    }
                }
            }


            for ($i = 0; $i < 8; $i++) {
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
                    $voluntary_column_2 = date('d/m/Y', strtotime($voluntary_work[$i]->working_from));
                    $voluntary_column_3 = date('d/m/Y', strtotime($voluntary_work[$i]->working_to));
                    $voluntary_column_4 = $voluntary_work[$i]->number_hours;
                    $voluntary_column_5 = $voluntary_work[$i]->position;
                }

                $voluntary_works[$i] = ['organization_address' => $voluntary_column_1, 'working_from' => $voluntary_column_2,
                    'working_to' => $voluntary_column_3, 'number_hours' => $voluntary_column_4,
                    'position' => $voluntary_column_5];
            }


            for ($i = 0; $i < $request->learning_rows; $i++) {
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
                    $learning_column_2 = date('d/m/Y', strtotime($learning[$i]->attendance_from));
                    $learning_column_3 = date('d/m/Y', strtotime($learning[$i]->attendance_to));
                    $learning_column_4 = "N/A";
                    if ($learning_column_4)
                        $learning_column_4 = number_format($learning[$i]->number_hours, 1, '.', '');
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

            if (count($learning) > $request->learning_rows) {
                for ($i = $request->learning_rows; $i < count($learning); $i++) {
                    if ($counter % 40 == 0 && $counter > 0) {
                        $next_page_learning_outer_array[$outer_counter++] = $next_page_learning_inner_array;
                        $counter = 0;
                        $next_page_learning_inner_array = [];
                    }

                    $learning_column_1 = $learning[$i]->learning_title;
                    $learning_column_2 = date('d/m/Y', strtotime($learning[$i]->attendance_from));
                    $learning_column_3 = date('d/m/Y', strtotime($learning[$i]->attendance_to));
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

            $pdf = Pdf::loadView('pdf.new.pds', ['user' => $user, 'employee' => $employee,
                'residential' => $residential, 'permanent' => $permanent, 'family' => $family, 'childrens' => $childrens,
                'educations' => $educations, 'eligibility' => $eligibilities, 'work_experience' => $work_experiences, 'next_page_work' => $next_page_work_outer_array,
                'voluntary_work' => $voluntary_works, 'learning' => $learnings, 'next_page_learning' => $next_page_learning_outer_array, 'skill' => $skills, 'survey' => $surveys,
                'reference' => $references, 'pds_date' => $request->pds_date, 'sworn_date' => $request->sworn_date]);

            $pdf->setPaper('A4', 'landscape');

            return $pdf->stream('PDS.pdf', array("Attachment" => false));
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
    }
}
