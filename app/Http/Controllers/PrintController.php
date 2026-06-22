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
        if (count($text_array) < 4)
            return strtoupper($texts);

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

    private function getLeaveTypeOptions(): array
    {
        return [
            ['id' => 2, 'label' => 'Vacation Leave'],
            ['id' => 8, 'label' => 'Mandatory/Forced Leave'],
            ['id' => 1, 'label' => 'Sick Leave'],
            ['id' => 9, 'label' => 'Maternity Leave'],
            ['id' => 3, 'label' => 'Paternity Leave'],
            ['id' => 10, 'label' => 'Special Privilege Leave'],
            ['id' => 4, 'label' => 'Solo Parent Leave'],
            ['id' => 11, 'label' => 'Study Leave'],
            ['id' => 5, 'label' => '10-Day VAWC Leave'],
            ['id' => 12, 'label' => 'Rehabilitation Privilege'],
            ['id' => 6, 'label' => 'Special Leave Benefits for Women'],
            ['id' => 13, 'label' => 'Special Emergency (Calamity) Leave'],
            ['id' => 7, 'label' => 'Adoption Leave'],
        ];
    }

    private function getLeaveDetailGroups(): array
    {
        return [
            [
                'title' => 'In case of vacation/special privilege leave',
                'items' => [
                    'Within the Philippines',
                    'Abroad (Specify)',
                ],
            ],
            [
                'title' => 'In case of sick leave',
                'items' => [
                    'In Hospital (Specify Illness)',
                    'Out Patient (Specify Illness)',
                ],
            ],
            [
                'title' => 'Other details',
                'items' => [
                    "In case of Special Leave Benefits for Women",
                    "Completion of Master's Degree",
                    'BAR/Board Examination Review',
                    'Monetization of Leave Credits',
                    'Terminal Leave',
                ],
            ],
        ];
    }

    private function formatOfficerName($user): string
    {
        if (!$user) {
            return '';
        }

        $middleInitial = trim((string) ($user->mname ?? '')) !== ''
            ? strtoupper(substr(trim((string) $user->mname), 0, 1)) . '.'
            : '';

        $parts = array_filter([
            strtoupper((string) $user->fname),
            $middleInitial,
            strtoupper((string) $user->lname),
            strtoupper((string) $user->suffix),
        ]);

        $name = trim(implode(' ', $parts));
        $title = trim((string) ($user->title ?? ''));

        return $title !== '' ? $name . ', ' . $title : $name;
    }

    private function getUserById(int $userId)
    {
        return DB::connection('users')
            ->table('users')
            ->where('id', $userId)
            ->first(['id', 'fname', 'mname', 'lname', 'suffix', 'title']);
    }

    private function getSectionById(?int $sectionId)
    {
        if (empty($sectionId)) {
            return null;
        }

        return DB::connection('users')
            ->table('section')
            ->where('id', $sectionId)
            ->first(['id', 'division', 'head', 'subsection']);
    }

    private function getDivisionById(?int $divisionId)
    {
        if (empty($divisionId)) {
            return null;
        }

        return DB::connection('users')
            ->table('division')
            ->where('id', $divisionId)
            ->first(['id', 'head']);
    }

    private function resolveLeavePrintApprovers($leaveApplication): array
    {
        $userId = (int) $leaveApplication->user_id;
        $sectionId = (int) $leaveApplication->section_id;
        $divisionId = (int) $leaveApplication->division_id;

        $currentSection = $this->getSectionById($sectionId);
        $currentDivision = $this->getDivisionById($divisionId);

        $isSectionHead = DB::connection('users')->table('section')->where('head', $userId)->exists();
        $isDivisionHead = DB::connection('users')->table('division')->where('head', $userId)->exists();
        $isUnitHead = $currentSection && !empty($currentSection->subsection) && (int) $currentSection->head === $userId;

        $authorizedOfficerId = null;
        $authorizedOfficialId = null;

        if ($isUnitHead) {
            $parentSection = $this->getSectionById((int) $currentSection->subsection);
            $authorizedOfficerId = $parentSection->head ?? null;
            $authorizedOfficialId = $currentDivision->head ?? null;
        } elseif ($isSectionHead) {
            $authorizedOfficerId = $currentDivision->head ?? null;
            $authorizedOfficialId = 35;
        } elseif (!$isDivisionHead) {
            $authorizedOfficerId = $currentSection->head ?? null;
            $authorizedOfficialId = $currentDivision->head ?? null;
        } else {
            $authorizedOfficerId = 35;
            $authorizedOfficialId = 35;
        }

        return [
            'authorized_officer' => $this->formatOfficerName($authorizedOfficerId ? $this->getUserById((int) $authorizedOfficerId) : null),
            'authorized_official' => $this->formatOfficerName($authorizedOfficialId ? $this->getUserById((int) $authorizedOfficialId) : null),
        ];
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
            ->get([
                'p.basic_salary',
                'di.code as division',
                'di.description as division_name',
                's.code as section',
                's.description as section_name',
                'u.fname',
                'u.mname',
                'u.lname',
                'u.id as user_id',
                'u.section as section_id',
                'l.created_at',
                'd.description as designation',
                'u.division as division_id',
                'l.commutation',
                'l.id',
            ])
            ->first();

        // Distinct leave types from leave_date_applied (leave_id removed from leave_details)
        $leaveTypeRows = DB::table('hris.leave_date_applied as lda')
            ->join('hris.leave_types as lt', 'lda.leave_type_id', '=', 'lt.id')
            ->where('lda.leave_application_id', $request->id)
            ->where('lda.status', '!=', 'cancelled')
            ->select('lda.leave_type_id as leave_id', 'lt.description as leave_type_name')
            ->distinct()
            ->orderBy('lda.leave_type_id')
            ->get();

        $detailRows = DB::table('hris.leave_details')
            ->where('leave_application_id', $request->id)
            ->get(['detail', 'extra']);

        // Index-match: i-th leave_details row ↔ i-th unique leave type (same insertion order)
        $leaveDetails = $leaveTypeRows->map(function ($lt, $index) use ($detailRows) {
            $dr = $detailRows->get($index);
            return (object) [
                'leave_id'        => $lt->leave_id,
                'leave_type_name' => $lt->leave_type_name,
                'detail'          => $dr ? $dr->detail : null,
                'extra'           => $dr ? $dr->extra : null,
            ];
        });

        $selectedLeaveIds = $leaveTypeRows->pluck('leave_id')->map(fn($id) => (int) $id)->toArray();

        $sectionHeadApproval = DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $request->id)
            ->where('role', 'SH')
            ->first(['status', 'remarks']);

        $attachments = DB::table('leave_application_attachments')
            ->where('leave_application_id', $request->id)
            ->get(['file_path', 'original_name'])
            ->map(function ($a) {
                $extension = strtolower(pathinfo($a->file_path, PATHINFO_EXTENSION));
                return (object) [
                    'url'           => route('leave-attachments.serve', ['filename' => basename($a->file_path)]),
                    'original_name' => $a->original_name ?? basename($a->file_path),
                    'is_pdf'        => $extension === 'pdf',
                ];
            });

        $dates_applied = DB::table('leave_date_applied')
            ->where('leave_application_id', $leave_application->id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('date_applied', 'ASC')
            ->get(['date_applied', 'hours']);

        $totalHours = $dates_applied->sum('hours');
        $totalDays  = $totalHours / 8;

        $fullName = trim(implode(' ', array_filter([
            strtoupper($leave_application->lname ?? ''),
            strtoupper($leave_application->fname ?? ''),
            $leave_application->mname ? strtoupper(substr($leave_application->mname, 0, 1)) . '.' : null,
        ])));
        $formattedCreatedAt = Carbon::parse($leave_application->created_at)->format('M. j, Y');
        $leaveTypeOptions  = $this->getLeaveTypeOptions();
        $leaveDetailGroups = $this->getLeaveDetailGroups();
        $approvers         = $this->resolveLeavePrintApprovers($leave_application);

        $knownLeaveTypeIds = collect($leaveTypeOptions)->pluck('id')->map(fn($id) => (int) $id)->all();
        $unknownTypes      = $leaveDetails->filter(fn($ld) => !in_array((int) $ld->leave_id, $knownLeaveTypeIds, true));
        $otherLeaveType    = $unknownTypes->isNotEmpty()
            ? $unknownTypes->pluck('leave_type_name')->implode(', ')
            : null;

        // Each leave_details row stores its own detail/extra — one row per leave type.
        $detailExtraMap = [];
        foreach ($leaveDetails as $ld) {
            if (!empty($ld->detail)) {
                $detailExtraMap[$ld->detail] = $ld->extra ?? '';
            }
        }

        return view('print.leave_form', [
            'leaveApplication'       => $leave_application,
            'leaveDetails'           => $leaveDetails,
            'selectedLeaveIds'       => $selectedLeaveIds,
            'detailExtraMap'         => $detailExtraMap,
            'officeLabel'            => strtoupper(trim(($leave_application->division ?? '') . ' / ' . ($leave_application->section ?? ''))),
            'fullName'               => $fullName,
            'formattedCreatedAt'     => $formattedCreatedAt,
            'designationLabel'       => self::minifyDesignation($leave_application->designation),
            'salaryLabel'            => 'P' . number_format($leave_application->basic_salary ?? 0, 2, '.', ','),
            'leaveTypeOptions'       => $leaveTypeOptions,
            'leaveDetailGroups'      => $leaveDetailGroups,
            'authorizedOfficerName'  => $approvers['authorized_officer'],
            'authorizedOfficialName' => $approvers['authorized_official'],
            'otherLeaveType'         => $otherLeaveType,
            'totalDays'              => $totalDays,
            'totalHours'             => $totalHours,
            'inclusiveDates'         => self::getInclusiveDates($dates_applied),
            'sectionHeadApproval'    => $sectionHeadApproval,
            'attachments'            => $attachments,
        ]);
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
            $user_id = $request->userid ?? Auth::id();
            $complianceForInput = trim((string) $request->input('compliance_for', ''));
            $complianceType = $request->input('compliance_type');
            $complianceFor = null;

            if ($complianceForInput !== '') {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $complianceForInput) || preg_match('/^\d{4}$/', $complianceForInput)) {
                    $complianceFor = $complianceForInput;
                } elseif (in_array($complianceForInput, ['Assumption of office', 'Annual Filing', 'Exit'], true) && empty($complianceType)) {
                    $complianceType = $complianceForInput;
                }
            }

            $declarant = DB::table('tdh_user.users as u')
                ->leftJoin('hris.saln_declarant as d', function ($join) use ($complianceFor) {
                    $join->on('u.id', '=', 'd.user_id');
                    if (!empty($complianceFor)) {
                        if (strlen($complianceFor) === 4) {
                            $join->whereYear('d.compliance_for', '=', $complianceFor);
                        } else {
                            $join->where('d.compliance_for', '=', $complianceFor);
                        }
                    }
                })
                ->leftJoin('hris.addresses as a', function ($join) {
                    $join->on('u.id', '=', 'a.user_id')
                        ->where(function ($query) {
                            $query->whereNull('a.address_type')
                                ->orWhere('a.address_type', 'Permanent');
                        });
                })
                ->where('u.id', $user_id)
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
                    'd.compliance_type',
                    'd.filing_type',
                    'a.house_no',
                    'a.street',
                    'a.subdivision_village',
                    'a.barangay',
                    'a.city',
                    'a.province'
                ])
                ->first();

            $spouse = DB::table('hris.family_backgrounds as f')
                ->leftJoin('hris.saln_spouse as d', function ($join) use ($complianceFor) {
                    $join->on('f.user_id', '=', 'd.user_id');
                    if (!empty($complianceFor)) {
                        if (strlen($complianceFor) === 4) {
                            $join->whereYear('d.compliance_for', '=', $complianceFor);
                        } else {
                            $join->where('d.compliance_for', '=', $complianceFor);
                        }
                    }
                })
                ->where('f.user_id', '=', $user_id)
                ->get([
                    'f.spouse_fname',
                    'f.spouse_mname',
                    'f.spouse_lname',
                    'f.spouse_ext_name',
                    'd.position',
                    'd.agency',
                    'd.office',
                    'd.government_id_issued',
                    'd.government_id_no',
                    'd.date_issued'
                ])
                ->first();


            $childrenQuery = SalnChildren::where('user_id', $user_id);
            if (!empty($complianceFor)) {
                if (strlen($complianceFor) === 4) {
                    $childrenQuery->whereYear('compliance_for', $complianceFor);
                } else {
                    $childrenQuery->where('compliance_for', $complianceFor);
                }
            }
            $children = $childrenQuery->get();
            $children_count = count($children);
            $effective_children_count = max(4, $children_count);

            // Base limits for Page 1 - Added 1 extra row to each as requested (original was 4 and 6)
            $real_properties_limit = 5;
            $personal_properties_limit = 7;

            // If children count is more than 5, we need to reduce the rows for properties to fit them in Page 1.
            // Each child row takes about the same space as a property row.
            if ($effective_children_count > 5) {
                $extra_children = $effective_children_count - 5;
                // Reduce personal properties first, then real properties
                for ($i = 0; $i < $extra_children; $i++) {
                    if ($personal_properties_limit > 2) {
                        $personal_properties_limit--;
                    } elseif ($real_properties_limit > 2) {
                        $real_properties_limit--;
                    }
                }
            }


            $children_array = array_fill(0, $effective_children_count, [
                'name' => 'N/A',
                'birthdate' => 'N/A',
                'age' => 'N/A'
            ]);

            $marriages_array = [
                ['name' => ''],
                ['name' => '']
            ];


            $real_properties_array = array_fill(0, $real_properties_limit, [
                'description' => 'N/A',
                'kind' => 'N/A',
                'exact_location' => 'N/A',
                'assessed_value' => 'N/A',
                'current_fair' => 'N/A',
                'acquisition_year' => 'N/A',
                'acquisition_mode' => 'N/A',
                'acquisition_cost' => 'N/A',
            ]);

            $real_properties_b_array = array_fill(0, 4, [
                'description' => 'N/A',
                'kind' => 'N/A',
                'exact_location' => 'N/A',
                'assessed_value' => 'N/A',
                'current_fair' => 'N/A',
                'acquisition_year' => 'N/A',
                'acquisition_mode' => 'N/A',
                'acquisition_cost' => 'N/A',
            ]);

            $spouse_real_properties_array = array_fill(0, 4, [
                'description' => 'N/A',
                'kind' => 'N/A',
                'exact_location' => 'N/A',
                'assessed_value' => 'N/A',
                'current_fair' => 'N/A',
                'acquisition_year' => 'N/A',
                'acquisition_mode' => 'N/A',
                'acquisition_cost' => 'N/A',
            ]);

            $personal_properties_array = array_fill(0, $personal_properties_limit, [
                'gadget' => 'N/A',
                'various_years' => 'N/A',
                'acquisition_cost' => 'N/A'
            ]);

            $personal_properties_b_array = array_fill(0, 4, [
                'gadget' => 'N/A',
                'various_years' => 'N/A',
                'acquisition_cost' => 'N/A'
            ]);

            $spouse_personal_properties_array = array_fill(0, 4, [
                'gadget' => 'N/A',
                'various_years' => 'N/A',
                'acquisition_cost' => 'N/A'
            ]);


            $personal_properties_extended = [];
            $spouse_personal_properties_extended = [];

            $liabilities_array = array_fill(0, 4, [
                'nature' => 'N/A',
                'creditors_name' => 'N/A',
                'outstanding_balance' => 'N/A'
            ]);

            $liabilities_b_array = array_fill(0, 4, [
                'nature' => 'N/A',
                'creditors_name' => 'N/A',
                'outstanding_balance' => 'N/A'
            ]);

            $spouse_liabilities_array = array_fill(0, 4, [
                'nature' => 'N/A',
                'creditors_name' => 'N/A',
                'outstanding_balance' => 'N/A'
            ]);

            $business_array = array_fill(0, 3, [
                'enterprise' => 'N/A',
                'address' => 'N/A',
                'nature' => 'N/A',
                'date_acquisition' => 'N/A'
            ]);

            $business_b_array = array_fill(0, 3, [
                'enterprise' => 'N/A',
                'address' => 'N/A',
                'nature' => 'N/A',
                'date_acquisition' => 'N/A'
            ]);

            $spouse_business_array = array_fill(0, 3, [
                'enterprise' => 'N/A',
                'address' => 'N/A',
                'nature' => 'N/A',
                'date_acquisition' => 'N/A'
            ]);

            $relatives_array = array_fill(0, 5, [
                'name' => 'N/A',
                'relationship' => 'N/A',
                'position' => 'N/A',
                'agency' => 'N/A'
            ]);

            for ($i = 0; $i < $children_count; $i++) {
                $birthdate_val = new DateTime($children[$i]['birthdate']);
                $today = new DateTime();
                $age = $today->diff($birthdate_val)->y;
                $children_array[$i] = [
                    'name' => $children[$i]['name'],
                    'birthdate' => date('m/d/Y', strtotime($children[$i]['birthdate'])),
                    'age' => $age,
                ];
            }

            $marriagesQuery = Marriages::where('userid', $user_id);
            if (!empty($complianceFor)) {
                if (strlen($complianceFor) === 4) {
                    $marriagesQuery->whereYear('compliance_for', $complianceFor);
                } else {
                    $marriagesQuery->where('compliance_for', $complianceFor);
                }
            }
            $marriages = $marriagesQuery->take(2)->get();
            for ($i = 0; $i < count($marriages); $i++) {
                $marriages_array[$i] = [
                    'name' => $marriages[$i]['spouse']
                ];
            }

            $real_properties_subtotal_a = 0;
            $real_properties_subtotal_b = 0;
            $realPropQuery = RealProperties::where('user_id', $user_id);
            if (!empty($complianceFor)) {
                if (strlen($complianceFor) === 4) {
                    $realPropQuery->whereYear('compliance_for', $complianceFor);
                } else {
                    $realPropQuery->where('compliance_for', $complianceFor);
                }
            }
            $all_real_properties = $realPropQuery->get();

            foreach ($all_real_properties as $i => $prop) {
                $cost = (double) str_replace(',', '', $prop->acquisition_cost);
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

                if ($i < $real_properties_limit) {
                    $real_properties_array[$i] = $entry;
                    $real_properties_subtotal_a += $cost;
                } else {
                    $b_idx = $i - $real_properties_limit;
                    if ($b_idx < 4) {
                        $real_properties_b_array[$b_idx] = $entry;
                    } else {
                        $real_properties_b_array[] = $entry;
                    }
                    $real_properties_subtotal_b += $cost;
                }
            }


            $spouse_real_properties_subtotal = 0;
            $spouseRealPropQuery = SpouseRealProperties::where('user_id', $user_id);
            if (!empty($complianceFor)) {
                if (strlen($complianceFor) === 4) {
                    $spouseRealPropQuery->whereYear('compliance_for', $complianceFor);
                } else {
                    $spouseRealPropQuery->where('compliance_for', $complianceFor);
                }
            }
            $spouse_real_properties_db = $spouseRealPropQuery->get();

            foreach ($spouse_real_properties_db as $i => $prop) {
                $cost = (double) str_replace(',', '', $prop->acquisition_cost);
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
                    $spouse_real_properties_array[$i] = $entry;
                } else {
                    $spouse_real_properties_array[] = $entry;
                }
                $spouse_real_properties_subtotal += $cost;
            }


            $personal_properties_subtotal_a = 0;
            $personal_properties_subtotal_b = 0;
            $personalPropQuery = PersonalProperties::where('user_id', $user_id);
            if (!empty($complianceFor)) {
                if (strlen($complianceFor) === 4) {
                    $personalPropQuery->whereYear('compliance_for', $complianceFor);
                } else {
                    $personalPropQuery->where('compliance_for', $complianceFor);
                }
            }
            $all_personal_properties = $personalPropQuery->get();

            foreach ($all_personal_properties as $i => $prop) {
                $cost = (double) str_replace(',', '', $prop->acquisition_cost);
                $entry = [
                    'gadget' => $prop->gadget,
                    'various_years' => $prop->various_years,
                    'acquisition_cost' => number_format($cost, 2)
                ];

                if ($i < $personal_properties_limit) {
                    $personal_properties_array[$i] = $entry;
                    $personal_properties_subtotal_a += $cost;
                } else {
                    $b_idx = $i - $personal_properties_limit;
                    if ($b_idx < 4) {
                        $personal_properties_b_array[$b_idx] = $entry;
                    } else {
                        $personal_properties_b_array[] = $entry;
                    }
                    $personal_properties_subtotal_b += $cost;
                }
            }


            $spouse_personal_properties_subtotal = 0;
            $spousePersonalPropQuery = SpousePersonalProperties::where('user_id', $user_id);
            if (!empty($complianceFor)) {
                if (strlen($complianceFor) === 4) {
                    $spousePersonalPropQuery->whereYear('compliance_for', $complianceFor);
                } else {
                    $spousePersonalPropQuery->where('compliance_for', $complianceFor);
                }
            }
            $spouse_personal_properties_db = $spousePersonalPropQuery->get();

            foreach ($spouse_personal_properties_db as $i => $prop) {
                $cost = (double) str_replace(',', '', $prop->acquisition_cost);
                $entry = [
                    'gadget' => $prop->gadget,
                    'various_years' => $prop->various_years,
                    'acquisition_cost' => number_format($cost, 2)
                ];

                if ($i < 4) {
                    $spouse_personal_properties_array[$i] = $entry;
                } else {
                    $spouse_personal_properties_array[] = $entry;
                }
                $spouse_personal_properties_subtotal += $cost;
            }


            $liabilities_total_a = 0;
            $liabilities_total_b = 0;
            $liabilitiesQuery = Liabilities::where('user_id', $user_id);
            if (!empty($complianceFor)) {
                if (strlen($complianceFor) === 4) {
                    $liabilitiesQuery->whereYear('compliance_for', $complianceFor);
                } else {
                    $liabilitiesQuery->where('compliance_for', $complianceFor);
                }
            }
            $all_liabilities = $liabilitiesQuery->get();

            foreach ($all_liabilities as $i => $lib) {
                $bal = (double) str_replace(',', '', $lib->outstanding_balance);
                $entry = [
                    'nature' => $lib->nature,
                    'creditors_name' => $lib->creditors_name,
                    'outstanding_balance' => number_format($bal, 2)
                ];

                if ($i < 4) {
                    $liabilities_array[$i] = $entry;
                    $liabilities_total_a += $bal;
                } else {
                    $b_idx = $i - 4;
                    if ($b_idx < 4) {
                        $liabilities_b_array[$b_idx] = $entry;
                    } else {
                        $liabilities_b_array[] = $entry;
                    }
                    $liabilities_total_b += $bal;
                }
            }


            $spouse_liabilities_total = 0;
            $spouseLiabilitiesQuery = SpouseLiabilities::where('user_id', $user_id);
            if (!empty($complianceFor)) {
                if (strlen($complianceFor) === 4) {
                    $spouseLiabilitiesQuery->whereYear('compliance_for', $complianceFor);
                } else {
                    $spouseLiabilitiesQuery->where('compliance_for', $complianceFor);
                }
            }
            $spouse_liabilities_db = $spouseLiabilitiesQuery->get();

            foreach ($spouse_liabilities_db as $i => $lib) {
                $bal = (double) str_replace(',', '', $lib->outstanding_balance);
                $entry = [
                    'nature' => $lib->nature,
                    'creditors_name' => $lib->creditors_name,
                    'outstanding_balance' => number_format($bal, 2)
                ];

                if ($i < 4) {
                    $spouse_liabilities_array[$i] = $entry;
                } else {
                    $spouse_liabilities_array[] = $entry;
                }
                $spouse_liabilities_total += $bal;
            }


            $businessQuery = BusinessInterest::where('user_id', $user_id);
            if (!empty($complianceFor)) {
                if (strlen($complianceFor) === 4) {
                    $businessQuery->whereYear('compliance_for', $complianceFor);
                } else {
                    $businessQuery->where('compliance_for', $complianceFor);
                }
            }
            $all_business = $businessQuery->get();

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
                    $b_idx = $i - 3;
                    if ($b_idx < 3) {
                        $business_b_array[$b_idx] = $entry;
                    } else {
                        $business_b_array[] = $entry;
                    }
                }
            }


            $spouseBusinessQuery = SpouseBusinessInterest::where('user_id', $user_id);
            if (!empty($complianceFor)) {
                if (strlen($complianceFor) === 4) {
                    $spouseBusinessQuery->whereYear('compliance_for', $complianceFor);
                } else {
                    $spouseBusinessQuery->where('compliance_for', $complianceFor);
                }
            }
            $spouse_business_db = $spouseBusinessQuery->get();
            foreach ($spouse_business_db as $i => $bus) {
                $entry = [
                    'enterprise' => $bus->enterprise,
                    'address' => $bus->address,
                    'nature' => $bus->nature,
                    'date_acquisition' => date('m/d/Y', strtotime($bus->date_acquisition))
                ];

                if ($i < 3) {
                    $spouse_business_array[$i] = $entry;
                } else {
                    $spouse_business_array[] = $entry;
                }
            }


            $relativesQuery = GovernmentRelative::where('user_id', $user_id);
            if (!empty($complianceFor)) {
                if (strlen($complianceFor) === 4) {
                    $relativesQuery->whereYear('compliance_for', $complianceFor);
                } else {
                    $relativesQuery->where('compliance_for', $complianceFor);
                }
            }
            $all_relatives = $relativesQuery->get();
            foreach ($all_relatives as $i => $rel) {
                $entry = [
                    'name' => $rel->name,
                    'relationship' => $rel->relationship,
                    'position' => $rel->position,
                    'agency' => $rel->agency
                ];

                if ($i < 5) {
                    $relatives_array[$i] = $entry;
                } else {
                    $relatives_array[] = $entry;
                }
            }


            // Grand Totals for Summary (Family-wide)
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

            // Determine if Annex B is needed (Declarant Overflow)
            $show_annex_b = (count($all_real_properties) > $real_properties_limit || count($all_personal_properties) > $personal_properties_limit || count($all_liabilities) > 4 || count($all_business) > 3);

            // Determine if Annex C is needed (Spouse Proof Presence)
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
                'filing_type' => $request->filing_type ?? $declarant->filing_type ?? 'Not Applicable',
                'compliance_type' => $complianceType ?? $declarant->compliance_type ?? 'Annual Filing',
                'compliance_for' => $declarant->compliance_for ?? $complianceFor,
                'business_interest' => $request->business_interest,
                'government_relatives' => $request->government_relatives,
                'before_me' => $request->before_me,
                'day_of' => $request->day_of,
                'spouse_signature' => $request->spouse_signature,
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
            if ($request->has("admin")) {
                $user_id = $request->id;
            }

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

            if (count($survey) > 0)
                $surveys = $survey;

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

                    if ($eligiblity[$i]->examination_end_date) {
                        if (
                            date('Y-m-d', strtotime($eligiblity[$i]->examination_date)) ===
                            date('Y-m-d', strtotime($eligiblity[$i]->examination_end_date))
                        ) {
                            $eligibility_column_3 = date('m/d/Y', strtotime($eligiblity[$i]->examination_date));
                        } else {
                            $eligibility_column_3 =
                                date('m/d/Y', strtotime($eligiblity[$i]->examination_date)) .
                                " - " .
                                date('m/d/Y', strtotime($eligiblity[$i]->examination_end_date));
                        }
                    } else {
                        $eligibility_column_3 = date('m/d/Y', strtotime($eligiblity[$i]->examination_date));
                    }

                    $eligibility_column_4 = $eligiblity[$i]->examination_place;
                    $eligibility_column_5 = "N/A";
                    if ($eligiblity[$i]->license_no)
                        $eligibility_column_5 = $eligiblity[$i]->license_no;

                    $eligibility_column_6 = "N/A";
                    if ($eligiblity[$i]->license_expiry)
                        $eligibility_column_6 = date('d/m/Y', strtotime($eligiblity[$i]->license_expiry));
                }
                $eligibilities[$i] = [
                    'career_service' => $eligibility_column_1,
                    'rating' => $eligibility_column_2,
                    'examination_date' => $eligibility_column_3,
                    'examination_place' => $eligibility_column_4,
                    'license_no' => $eligibility_column_5,
                    'license_expiry' => $eligibility_column_6
                ];
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

                $work_experiences[$i] = [
                    'working_from' => $work_column_1,
                    'working_to' => $work_column_2,
                    'position' => $work_column_3,
                    'agency' => $work_column_4,
                    'monthly_salary' => $work_column_5,
                    'salary_grade' => $work_column_6,
                    'appointment_status' => $work_column_7,
                    'is_government' => $work_column_8
                ];
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

                    $next_page_work_inner_array[$work_counter++] = [
                        'working_from' => $work_column_1,
                        'working_to' => $work_column_2,
                        'position' => $work_column_3,
                        'agency' => $work_column_4,
                        'monthly_salary' => $work_column_5,
                        'salary_grade' => $work_column_6,
                        'appointment_status' => $work_column_7,
                        'is_government' => $work_column_8
                    ];

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
                    if ($voluntary_work[$i]->is_current == 1) {
                        $voluntary_column_3 = "Present";
                    } else {
                        $voluntary_column_3 = date('d/m/Y', strtotime($voluntary_work[$i]->working_to));
                    }
                    $voluntary_column_4 = $voluntary_work[$i]->number_hours;
                    $voluntary_column_5 = $voluntary_work[$i]->position;
                }

                $voluntary_works[$i] = [
                    'organization_address' => $voluntary_column_1,
                    'working_from' => $voluntary_column_2,
                    'working_to' => $voluntary_column_3,
                    'number_hours' => $voluntary_column_4,
                    'position' => $voluntary_column_5
                ];
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

                $learnings[$i] = [
                    'learning_title' => $learning_column_1,
                    'attendance_from' => $learning_column_2,
                    'attendance_to' => $learning_column_3,
                    'number_hours' => $learning_column_4,
                    'learning_type' => $learning_column_5,
                    'conducted_by' => $learning_column_6
                ];
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

                    $next_page_learning_inner_array[$counter++] = [
                        'learning_title' => $learning_column_1,
                        'attendance_from' => $learning_column_2,
                        'attendance_to' => $learning_column_3,
                        'number_hours' => $learning_column_4,
                        'learning_type' => $learning_column_5,
                        'conducted_by' => $learning_column_6
                    ];

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

            $pdf = Pdf::loadView('pdf.new.pds', [
                'user' => $user,
                'employee' => $employee,
                'residential' => $residential,
                'permanent' => $permanent,
                'family' => $family,
                'childrens' => $childrens,
                'educations' => $educations,
                'eligibility' => $eligibilities,
                'work_experience' => $work_experiences,
                'next_page_work' => $next_page_work_outer_array,
                'voluntary_work' => $voluntary_works,
                'learning' => $learnings,
                'next_page_learning' => $next_page_learning_outer_array,
                'skill' => $skills,
                'survey' => $surveys,
                'reference' => $references,
                'pds_date' => $request->pds_date,
                'sworn_date' => $request->sworn_date
            ]);

            $pdf->setPaper('A4', 'landscape');

            return $pdf->stream('PDS.pdf', array("Attachment" => false));
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
    }
}
