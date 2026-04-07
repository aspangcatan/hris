<?php

namespace App\Http\Controllers;

use App\Models\BusinessInterest;
use App\Models\Declarant;
use App\Models\FamilyBackground;
use App\Models\GovernmentRelative;
use App\Models\Liabilities;
use App\Models\Marriages;
use App\Models\PersonalProperties;
use App\Models\RealProperties;
use App\Models\SalnChildren;
use App\Models\Spouse;
use App\Models\SpouseBusinessInterest;
use App\Models\SpouseLiabilities;
use App\Models\SpousePersonalProperties;
use App\Models\SpouseRealProperties;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalnController extends Controller
{
    public function getDeclarant(Request $request)
    {
        try {
            $query = DB::table('tdh_user.users as u')
                ->leftJoin('hris.saln_declarant as d', function($join) use ($request) {
                    $join->on('u.id', '=', 'd.user_id');
                    if ($request->has('compliance_for')) {
                        $join->whereYear('d.compliance_for', '=', $request->compliance_for);
                    }
                })
                ->where('u.id', '=', Auth::id());
            
            $declarant = $query->get(['u.fname', 'u.mname', 'u.lname', 'd.position', 'd.agency',
                    'd.office', 'd.government_id_issued', 'd.government_id_no', 'd.date_issued']);
            return response(['code' => 200, 'results' => $declarant]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function updateDeclarant(Request $request)
    {
        try {
            $complianceDate = $request->compliance_for ?? date('Y-12-31');
            $complianceYear = date('Y', strtotime($complianceDate));

            $declarant = Declarant::where('user_id', Auth::id())
                ->whereYear('compliance_for', $complianceYear)
                ->first();

            if (!$declarant) {
                $declarant = new Declarant;
                $declarant->user_id = Auth::id();
                $declarant->compliance_for = $complianceDate;
            }

            $declarant->position = $request->declarant_position;
            $declarant->agency = $request->declarant_agency;
            $declarant->office = $request->declarant_office;
            $declarant->government_id_issued = $request->declarant_government_id_issued;
            $declarant->government_id_no = $request->declarant_government_id_no;
            $declarant->date_issued = $request->declarant_date_issued;
            $declarant->save();

            return response(['code' => 200, 'message' => 'Declarant Information updated']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function updateSpouse(Request $request)
    {
        try {
            $complianceDate = $request->compliance_for ?? date('Y-12-31');
            $complianceYear = date('Y', strtotime($complianceDate));

            $spouse = Spouse::where('user_id', Auth::id())
                ->whereYear('compliance_for', $complianceYear)
                ->first();
            $familyBackground = FamilyBackground::where('user_id', Auth::id())->first();

            if (!$spouse) {
                $spouse = new Spouse;
                $spouse->user_id = Auth::id();
                $spouse->compliance_for = $complianceDate;
            }

            if(!$familyBackground){
                $familyBackground = new FamilyBackground;
                $familyBackground->user_id = Auth::id();
            }

            $familyBackground->spouse_fname = $request->spouse_fname;
            $familyBackground->spouse_mname = $request->spouse_mname;
            $familyBackground->spouse_lname = $request->spouse_lname;
            $familyBackground->save();

            $spouse->position = $request->spouse_position;
            $spouse->agency = $request->spouse_agency;
            $spouse->office = $request->spouse_office;
            $spouse->government_id_issued = $request->spouse_government_id_issued;
            $spouse->government_id_no = $request->spouse_government_id_no;
            $spouse->date_issued = $request->spouse_date_issued;
            $spouse->save();
            return response(['code' => 200, 'message' => 'Spouse Information updated']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getDeclarantSpouse(Request $request)
    {
        try {
            $query = DB::table('hris.family_backgrounds as f')
                ->leftJoin('hris.saln_spouse as d', function($join) use ($request) {
                    $join->on('f.user_id', '=', 'd.user_id');
                    if ($request->has('compliance_for')) {
                        $join->whereYear('d.compliance_for', '=', $request->compliance_for);
                    }
                })
                ->where('f.user_id', '=', Auth::id());
                
            $declarant = $query->get(['f.spouse_fname', 'f.spouse_mname', 'f.spouse_lname', 'd.position', 'd.agency',
                    'd.office', 'd.government_id_issued', 'd.government_id_no', 'd.date_issued']);
            return response(['code' => 200, 'results' => $declarant]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }


    public function insertSalnChildren(Request $request)
    {
        try {
            $id = $request->id;
            $saln_children = SalnChildren::where(function ($query) use ($id) {
                $query->where('id', '=', $id);
                $query->where('user_id', '=', Auth::id());
            })->first();

            if (!$saln_children) {
                $saln_children = new SalnChildren;
                $saln_children->user_id = Auth::id();
            }

            $saln_children->name = $request->name;
            $saln_children->birthdate = $request->birthdate;
            $saln_children->compliance_for = $request->compliance_for ?? date('Y-12-31');
            $saln_children->save();
            return response(['code' => 200, 'message' => 'New item added']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getChildren(Request $request)
    {
        try {
            $query = SalnChildren::where('user_id', Auth::id());
            if ($request->has('compliance_for')) {
                $complianceFor = $request->compliance_for;
                if (strlen($complianceFor) > 4) {
                    $query->where('compliance_for', $complianceFor);
                } else {
                    $query->whereYear('compliance_for', $complianceFor);
                }
            }
            $results = $query->get();
            return response(['code' => 200, 'results' => $results]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function deleteSalnChildren(Request $request)
    {
        try {
            $id = $request->id;
            $children = SalnChildren::where(function ($query) use ($id) {
                $query->where('id', '=', $id);
                $query->where('user_id', '=', Auth::id());
            })->first();
            $children->forceDelete();
            return response(['code' => 200, 'message' => 'Item deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertRealProperties(Request $request)
    {
        try {
            $id = $request->id;
            $real_properties = RealProperties::where(function ($query) use ($id) {
                $query->where('id', '=', $id);
                $query->where('user_id', '=', Auth::id());
            })->first();

            if (!$real_properties) {
                $real_properties = new RealProperties;
                $real_properties->user_id = Auth::id();
            }
            $real_properties->description = $request->description;
            $real_properties->kind = $request->kind;
            $real_properties->exact_location = $request->exact_location;
            $real_properties->assessed_value = $request->assessed_value;
            $real_properties->current_fair = $request->current_fair;
            $real_properties->acquisition_year = $request->acquisition_year;
            $real_properties->acquisition_mode = $request->acquisition_mode;
            $real_properties->acquisition_cost = $request->acquisition_cost;
            $real_properties->compliance_for = $request->compliance_for ?? date('Y-12-31');
            $real_properties->save();
            return response(['code' => 200, 'message' => 'New item added']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getRealProperties(Request $request)
    {
        try {
            $query = RealProperties::where('user_id', Auth::id());
            if ($request->has('compliance_for')) {
                $complianceFor = $request->compliance_for;
                if (strlen($complianceFor) > 4) {
                    $query->where('compliance_for', $complianceFor);
                } else {
                    $query->whereYear('compliance_for', $complianceFor);
                }
            }
            $results = $query->get();
            return response(['code' => 200, 'results' => $results]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function deleteRealProperties(Request $request)
    {
        try {
            $id = $request->id;
            $real_properties = RealProperties::where(function ($query) use ($id) {
                $query->where('id', '=', $id);
                $query->where('user_id', '=', Auth::id());
            })->first();
            $real_properties->forceDelete();
            return response(['code' => 200, 'message' => 'Item deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertPersonalProperties(Request $request)
    {
        try {
            $id = $request->id;
            $personal_properties = PersonalProperties::where(function ($query) use ($id) {
                $query->where('id', '=', $id);
                $query->where('user_id', '=', Auth::id());
            })->first();

            if (!$personal_properties) {
                $personal_properties = new PersonalProperties;
                $personal_properties->user_id = Auth::id();
            }

            $personal_properties->gadget = $request->gadget;
            $personal_properties->various_years = $request->various_years;
            $personal_properties->acquisition_cost = $request->acquisition_cost;
            $personal_properties->compliance_for = $request->compliance_for ?? date('Y-12-31');
            $personal_properties->save();
            return response(['code' => 200, 'message' => 'New item added']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getPersonalProperties(Request $request)
    {
        try {
            $query = PersonalProperties::where('user_id', Auth::id());
            if ($request->has('compliance_for')) {
                $complianceFor = $request->compliance_for;
                if (strlen($complianceFor) > 4) {
                    $query->where('compliance_for', $complianceFor);
                } else {
                    $query->whereYear('compliance_for', $complianceFor);
                }
            }
            $results = $query->get();
            return response(['code' => 200, 'results' => $results]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function deletePersonalProperties(Request $request)
    {
        try {
            $id = $request->id;
            $personal_properties = PersonalProperties::where(function ($query) use ($id) {
                $query->where('id', '=', $id);
                $query->where('user_id', '=', Auth::id());
            })->first();
            $personal_properties->forceDelete();
            return response(['code' => 200, 'message' => 'Item deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertLiabilities(Request $request)
    {
        try {
            $id = $request->id;
            $liabilities = Liabilities::where(function ($query) use ($id) {
                $query->where('id', '=', $id);
                $query->where('user_id', '=', Auth::id());
            })->first();

            if (!$liabilities) {
                $liabilities = new Liabilities;
                $liabilities->user_id = Auth::id();
            }

            $liabilities->nature = $request->nature;
            $liabilities->creditors_name = $request->creditors_name;
            $liabilities->outstanding_balance = $request->outstanding_balance;
            $liabilities->compliance_for = $request->compliance_for ?? date('Y-12-31');
            $liabilities->save();
            return response(['code' => 200, 'message' => 'New item added']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getLiabilities(Request $request)
    {
        try {
            $query = Liabilities::where('user_id', Auth::id());
            if ($request->has('compliance_for')) {
                $complianceFor = $request->compliance_for;
                if (strlen($complianceFor) > 4) {
                    $query->where('compliance_for', $complianceFor);
                } else {
                    $query->whereYear('compliance_for', $complianceFor);
                }
            }
            $results = $query->get();
            return response(['code' => 200, 'results' => $results]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function deleteLiabilities(Request $request)
    {
        try {
            $id = $request->id;
            $liabilities = Liabilities::where(function ($query) use ($id) {
                $query->where('id', '=', $id);
                $query->where('user_id', '=', Auth::id());
            })->first();
            $liabilities->forceDelete();
            return response(['code' => 200, 'message' => 'Item deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertBusinessInterest(Request $request)
    {
        try {
            $id = $request->id;
            $business_interest = BusinessInterest::where(function ($query) use ($id) {
                $query->where('id', '=', $id);
                $query->where('user_id', '=', Auth::id());
            })->first();

            if (!$business_interest) {
                $business_interest = new BusinessInterest;
                $business_interest->user_id = Auth::id();
            }

            $business_interest->enterprise = $request->enterprise;
            $business_interest->address = $request->address;
            $business_interest->nature = $request->nature;
            $business_interest->date_acquisition = $request->date_acquisition;
            $business_interest->compliance_for = $request->compliance_for ?? date('Y-12-31');
            $business_interest->save();
            return response(['code' => 200, 'message' => 'New item added']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getBusinessInterest(Request $request)
    {
        try {
            $query = BusinessInterest::where('user_id', Auth::id());
            if ($request->has('compliance_for')) {
                $complianceFor = $request->compliance_for;
                if (strlen($complianceFor) > 4) {
                    $query->where('compliance_for', $complianceFor);
                } else {
                    $query->whereYear('compliance_for', $complianceFor);
                }
            }
            $result = $query->get();
            return response(['code' => 200, 'results' => $result]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function deleteBusinessInterest(Request $request)
    {
        try {
            $id = $request->id;
            $business_interest = BusinessInterest::where(function ($query) use ($id) {
                $query->where('id', '=', $id);
                $query->where('user_id', '=', Auth::id());
            })->first();
            $business_interest->forceDelete();
            return response(['code' => 200, 'message' => 'Item deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertGovernmentRelative(Request $request)
    {
        try {
            $id = $request->id;
            $government_relative = GovernmentRelative::where(function ($query) use ($id) {
                $query->where('id', '=', $id);
                $query->where('user_id', '=', Auth::id());
            })->first();

            if (!$government_relative) {
                $government_relative = new GovernmentRelative;
                $government_relative->user_id = Auth::id();
            }

            $government_relative->name = $request->name;
            $government_relative->relationship = $request->relationship;
            $government_relative->position = $request->position;
            $government_relative->agency = $request->agency;
            $government_relative->compliance_for = $request->compliance_for ?? date('Y-12-31');
            $government_relative->save();
            return response(['code' => 200, 'message' => 'New item added']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getGovernmentRelative(Request $request)
    {
        try {
            $query = GovernmentRelative::where('user_id', Auth::id());
            if ($request->has('compliance_for')) {
                $complianceFor = $request->compliance_for;
                if (strlen($complianceFor) > 4) {
                    $query->where('compliance_for', $complianceFor);
                } else {
                    $query->whereYear('compliance_for', $complianceFor);
                }
            }
            $result = $query->get();
            return response(['code' => 200, 'results' => $result]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function deleteGovernmentRelative(Request $request)
    {
        try {
            $id = $request->id;
            $government_relative = GovernmentRelative::where(function ($query) use ($id) {
                $query->where('id', '=', $id);
                $query->where('user_id', '=', Auth::id());
            })->first();
            $government_relative->forceDelete();
            return response(['code' => 200, 'message' => 'Item deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertMarriages(Request $request)
    {
        try {
            $id = $request->id;
            $marriages = Marriages::where(function ($query) use ($id) {
                $query->where('id', '=', $id);
                $query->where('userid', '=', Auth::id());
            })->first();

            if (!$marriages) {
                $marriages = new Marriages;
                $marriages->userid = Auth::id();
            }

            $marriages->spouse = $request->spouse;
            $marriages->compliance_for = $request->compliance_for ?? date('Y-12-31');
            $marriages->save();
            return response(['code' => 200, 'message' => 'New item added']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getMarriages(Request $request)
    {
        try {
            $query = Marriages::where('userid', Auth::id());
            if ($request->has('compliance_for')) {
                $complianceFor = $request->compliance_for;
                if (strlen($complianceFor) > 4) {
                    $query->where('compliance_for', $complianceFor);
                } else {
                    $query->whereYear('compliance_for', $complianceFor);
                }
            }
            $result = $query->get();
            return response(['code' => 200, 'results' => $result]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function deleteMarriage(Request $request)
    {
        try {
            $marriages = Marriages::where('id',$request->id)->first();
            $marriages->forceDelete();
            return response(['code' => 200, 'message' => 'Item deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }



    // SPOUSE REAL PROPERTIES
    public function insertSpouseRealProperties(Request $request)
    {
        try {
            $id = $request->id;
            $real_properties = SpouseRealProperties::where(function ($query) use ($id) {
                $query->where('id', '=', $id);
                $query->where('user_id', '=', Auth::id());
            })->first();

            if (!$real_properties) {
                $real_properties = new SpouseRealProperties;
                $real_properties->user_id = Auth::id();
            }

            $real_properties->description = $request->description;
            $real_properties->kind = $request->kind;
            $real_properties->exact_location = $request->exact_location;
            $real_properties->assessed_value = $request->assessed_value;
            $real_properties->current_fair = $request->current_fair;
            $real_properties->acquisition_year = $request->acquisition_year;
            $real_properties->acquisition_mode = $request->acquisition_mode;
            $real_properties->acquisition_cost = $request->acquisition_cost;
            $real_properties->compliance_for = $request->compliance_for ?? date('Y-12-31');
            $real_properties->save();
            return response(['code' => 200, 'message' => 'New item added']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getSpouseRealProperties(Request $request)
    {
        try {
            $query = SpouseRealProperties::where('user_id', Auth::id());
            if ($request->has('compliance_for')) {
                $complianceFor = $request->compliance_for;
                if (strlen($complianceFor) > 4) {
                    $query->where('compliance_for', $complianceFor);
                } else {
                    $query->whereYear('compliance_for', $complianceFor);
                }
            }
            $results = $query->get();
            return response(['code' => 200, 'results' => $results]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function deleteSpouseRealProperties(Request $request)
    {
        try {
            $id = $request->id;
            $real_properties = SpouseRealProperties::where(function ($query) use ($id) {
                $query->where('id', '=', $id);
                $query->where('user_id', '=', Auth::id());
            })->first();
            $real_properties->forceDelete();
            return response(['code' => 200, 'message' => 'Item deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    // SPOUSE PERSONAL PROPERTIES
    public function insertSpousePersonalProperties(Request $request)
    {
        try {
            $id = $request->id;
            $personal_properties = SpousePersonalProperties::where(function ($query) use ($id) {
                $query->where('id', '=', $id);
                $query->where('user_id', '=', Auth::id());
            })->first();

            if (!$personal_properties) {
                $personal_properties = new SpousePersonalProperties;
                $personal_properties->user_id = Auth::id();
            }

            $personal_properties->gadget = $request->gadget;
            $personal_properties->various_years = $request->various_years;
            $personal_properties->acquisition_cost = $request->acquisition_cost;
            $personal_properties->compliance_for = $request->compliance_for ?? date('Y-12-31');
            $personal_properties->save();
            return response(['code' => 200, 'message' => 'New item added']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    function getSpousePersonalProperties(Request $request)
    {
        try {
            $query = SpousePersonalProperties::where('user_id', Auth::id());
            if ($request->has('compliance_for')) {
                $complianceFor = $request->compliance_for;
                if (strlen($complianceFor) > 4) {
                    $query->where('compliance_for', $complianceFor);
                } else {
                    $query->whereYear('compliance_for', $complianceFor);
                }
            }
            $results = $query->get();
            return response(['code' => 200, 'results' => $results]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function deleteSpousePersonalProperties(Request $request)
    {
        try {
            $id = $request->id;
            $personal_properties = SpousePersonalProperties::where(function ($query) use ($id) {
                $query->where('id', '=', $id);
                $query->where('user_id', '=', Auth::id());
            })->first();
            $personal_properties->forceDelete();
            return response(['code' => 200, 'message' => 'Item deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    // SPOUSE LIABILITIES
    public function insertSpouseLiabilities(Request $request)
    {
        try {
            $id = $request->id;
            $liabilities = SpouseLiabilities::where(function ($query) use ($id) {
                $query->where('id', '=', $id);
                $query->where('user_id', '=', Auth::id());
            })->first();

            if (!$liabilities) {
                $liabilities = new SpouseLiabilities;
                $liabilities->user_id = Auth::id();
            }

            $liabilities->nature = $request->nature;
            $liabilities->creditors_name = $request->creditors_name;
            $liabilities->outstanding_balance = $request->outstanding_balance;
            $liabilities->compliance_for = $request->compliance_for ?? date('Y-12-31');
            $liabilities->save();
            return response(['code' => 200, 'message' => 'New item added']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getSpouseLiabilities(Request $request)
    {
        try {
            $query = SpouseLiabilities::where('user_id', Auth::id());
            if ($request->has('compliance_for')) {
                $complianceFor = $request->compliance_for;
                if (strlen($complianceFor) > 4) {
                    $query->where('compliance_for', $complianceFor);
                } else {
                    $query->whereYear('compliance_for', $complianceFor);
                }
            }
            $results = $query->get();
            return response(['code' => 200, 'results' => $results]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function deleteSpouseLiabilities(Request $request)
    {
        try {
            $id = $request->id;
            $liabilities = SpouseLiabilities::where(function ($query) use ($id) {
                $query->where('id', '=', $id);
                $query->where('user_id', '=', Auth::id());
            })->first();
            $liabilities->forceDelete();
            return response(['code' => 200, 'message' => 'Item deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    // SPOUSE BUSINESS INTEREST
    public function insertSpouseBusinessInterest(Request $request)
    {
        try {
            $id = $request->id;
            $business_interest = SpouseBusinessInterest::where(function ($query) use ($id) {
                $query->where('id', '=', $id);
                $query->where('user_id', '=', Auth::id());
            })->first();

            if (!$business_interest) {
                $business_interest = new SpouseBusinessInterest;
                $business_interest->user_id = Auth::id();
            }

            $business_interest->enterprise = $request->enterprise;
            $business_interest->address = $request->address;
            $business_interest->nature = $request->nature;
            $business_interest->date_acquisition = $request->date_acquisition;
            $business_interest->compliance_for = $request->compliance_for ?? date('Y-12-31');
            $business_interest->save();
            return response(['code' => 200, 'message' => 'New item added']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getSpouseBusinessInterest(Request $request)
    {
        try {
            $query = SpouseBusinessInterest::where('user_id', Auth::id());
            if ($request->has('compliance_for')) {
                $complianceFor = $request->compliance_for;
                if (strlen($complianceFor) > 4) {
                    $query->where('compliance_for', $complianceFor);
                } else {
                    $query->whereYear('compliance_for', $complianceFor);
                }
            }
            $result = $query->get();
            return response(['code' => 200, 'results' => $result]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function deleteSpouseBusinessInterest(Request $request)
    {
        try {
            $id = $request->id;
            $business_interest = SpouseBusinessInterest::where(function ($query) use ($id) {
                $query->where('id', '=', $id);
                $query->where('user_id', '=', Auth::id());
            })->first();
            $business_interest->forceDelete();
            return response(['code' => 200, 'message' => 'Item deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Update SALN compliance information (year and filing type)
     */
    public function updateSalnCompliance(Request $request)
    {
        try {
            $request->validate([
                'compliance_for' => 'required|date',
                'compliance_type' => 'required|in:Assumption of office,Annual Filing,Exit',
                'filing_type' => 'required|in:Joint Filing,Separate Filing,Not Applicable'
            ]);

            $complianceDate = $request->compliance_for; // Full date (YYYY-MM-DD)
            $complianceYear = date('Y', strtotime($complianceDate)); // Extract year for querying
            $filingType = $request->filing_type;

            // Update or create declarant record for this compliance year
            $declarant = Declarant::where('user_id', Auth::id())
                ->whereYear('compliance_for', $complianceYear)
                ->first();

            if (!$declarant) {
                $declarant = new Declarant;
                $declarant->user_id = Auth::id();
            }

            if ($request->has('declarant_position')) $declarant->position = $request->declarant_position;
            if ($request->has('declarant_agency')) $declarant->agency = $request->declarant_agency;
            if ($request->has('declarant_office')) $declarant->office = $request->declarant_office;
            if ($request->has('declarant_government_id_issued')) $declarant->government_id_issued = $request->declarant_government_id_issued;
            if ($request->has('declarant_government_id_no')) $declarant->government_id_no = $request->declarant_government_id_no;
            if ($request->has('declarant_date_issued')) $declarant->date_issued = $request->declarant_date_issued;
            
            $declarant->compliance_for = $complianceDate;
            $declarant->compliance_type = $request->compliance_type;
            $declarant->filing_type = $filingType;
            
            $declarant->save();

            // If joint filing, update spouse record as well
            if ($filingType === 'Joint Filing') {
                $spouse = Spouse::where('user_id', Auth::id())
                    ->whereYear('compliance_for', $complianceYear)
                    ->first();

                if (!$spouse) {
                    $spouse = new Spouse;
                    $spouse->user_id = Auth::id();
                }

                $spouse->position = $request->spouse_position;
                $spouse->agency = $request->spouse_agency;
                $spouse->office = $request->spouse_office;
                $spouse->government_id_issued = $request->spouse_government_id_issued;
                $spouse->government_id_no = $request->spouse_government_id_no;
                $spouse->date_issued = $request->spouse_date_issued;
                $spouse->compliance_for = $complianceDate;
                $spouse->save();
            }

            $netWorthData = $this->calculateNetWorth(Auth::id(), $complianceYear, $filingType);

            return response([
                'code' => 200, 
                'message' => 'SALN compliance information updated',
                'net_worth' => $netWorthData
            ]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Calculate net worth for a specific year
     */
    private function calculateNetWorth($userId, $complianceYear, $filingType)
    {
        // Declarant Assets
        $declarantRealProperties = RealProperties::where('user_id', $userId)
            ->whereYear('compliance_for', $complianceYear)
            ->sum('acquisition_cost');
        
        $declarantPersonalProperties = PersonalProperties::where('user_id', $userId)
            ->whereYear('compliance_for', $complianceYear)
            ->sum('acquisition_cost');

        $declarantAssets = $declarantRealProperties + $declarantPersonalProperties;

        // Declarant Liabilities
        $declarantLiabilities = Liabilities::where('user_id', $userId)
            ->whereYear('compliance_for', $complianceYear)
            ->sum('outstanding_balance');

        // Spouse Assets (if joint filing)
        $spouseAssets = 0;
        $spouseLiabilities = 0;
        
        if ($filingType === 'Joint Filing') {
            $spouseRealProperties = SpouseRealProperties::where('user_id', $userId)
                ->whereYear('compliance_for', $complianceYear)
                ->sum('acquisition_cost');
            
            $spousePersonalProperties = SpousePersonalProperties::where('user_id', $userId)
                ->whereYear('compliance_for', $complianceYear)
                ->sum('acquisition_cost');

            $spouseAssets = $spouseRealProperties + $spousePersonalProperties;

            $spouseLiabilities = SpouseLiabilities::where('user_id', $userId)
                ->whereYear('compliance_for', $complianceYear)
                ->sum('outstanding_balance');
        }

        // Combined totals
        $totalAssets = $declarantAssets + $spouseAssets;
        $totalLiabilities = $declarantLiabilities + $spouseLiabilities;
        $netWorth = $totalAssets - $totalLiabilities;

        return [
            'declarant_assets' => $declarantAssets,
            'declarant_liabilities' => $declarantLiabilities,
            'spouse_assets' => $spouseAssets,
            'spouse_liabilities' => $spouseLiabilities,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'net_worth' => $netWorth,
            'spouse_net_worth' => $spouseAssets - $spouseLiabilities
        ];
    }

    /**
     * Get all SALN compliance years for the authenticated user
     */
    public function getSalnYears()
    {
        try {
            $years = Declarant::where('user_id', Auth::id())
                ->whereNotNull('compliance_for')
                ->orderBy('compliance_for', 'desc')
                ->get(['id', 'compliance_for', 'filing_type', 'created_at']);

            // Calculate totals and year on-the-fly
            $years->transform(function ($item) {
                $compYear = date('Y', strtotime($item->compliance_for));
                $item->year = $compYear;
                
                $netWorthData = $this->calculateNetWorth(Auth::id(), $compYear, $item->filing_type);
                
                $item->total_assets = $netWorthData['total_assets'];
                $item->total_liabilities = $netWorthData['total_liabilities'];
                $item->net_worth = $netWorthData['net_worth'];
                
                return $item;
            });

            return response(['code' => 200, 'results' => $years]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Get SALN data for a specific year
     */
    public function getSalnByYear(Request $request)
    {
        try {
            $complianceYear = $request->compliance_for;
            
            $declarant = Declarant::where('user_id', Auth::id())
                ->whereYear('compliance_for', $complianceYear)
                ->first();

            if (!$declarant) {
                return response(['code' => 404, 'message' => 'No SALN found for this year']);
            }

            $data = [
                'declarant' => $declarant,
                'spouse' => Spouse::where('user_id', Auth::id())
                    ->whereYear('compliance_for', $complianceYear)
                    ->first(),
                'children' => SalnChildren::where('user_id', Auth::id())
                    ->whereYear('compliance_for', $complianceYear)
                    ->get(),
                'real_properties' => RealProperties::where('user_id', Auth::id())
                    ->whereYear('compliance_for', $complianceYear)
                    ->get(),
                'personal_properties' => PersonalProperties::where('user_id', Auth::id())
                    ->whereYear('compliance_for', $complianceYear)
                    ->get(),
                'liabilities' => Liabilities::where('user_id', Auth::id())
                    ->whereYear('compliance_for', $complianceYear)
                    ->get(),
                'business_interests' => BusinessInterest::where('user_id', Auth::id())
                    ->whereYear('compliance_for', $complianceYear)
                    ->get(),
                'government_relatives' => GovernmentRelative::where('user_id', Auth::id())
                    ->whereYear('compliance_for', $complianceYear)
                    ->get(),
                'marriages' => Marriages::where('userid', Auth::id())
                    ->whereYear('compliance_for', $complianceYear)
                    ->get(),
                'spouse_real_properties' => SpouseRealProperties::where('user_id', Auth::id())
                    ->whereYear('compliance_for', $complianceYear)
                    ->get(),
                'spouse_personal_properties' => SpousePersonalProperties::where('user_id', Auth::id())
                    ->whereYear('compliance_for', $complianceYear)
                    ->get(),
                'spouse_liabilities' => SpouseLiabilities::where('user_id', Auth::id())
                    ->whereYear('compliance_for', $complianceYear)
                    ->get(),
                'spouse_business_interests' => SpouseBusinessInterest::where('user_id', Auth::id())
                    ->whereYear('compliance_for', $complianceYear)
                    ->get(),
            ];

            return response(['code' => 200, 'results' => $data]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Generate SALN Report PDF for a specific year
     */
    public function generateYearlySalnReport(Request $request)
    {
        try {
            $complianceYear = $request->compliance_for;
            
            // Get all SALN data for the specific year
            $salnData = $this->getSalnDataForReport($complianceYear);
            
            if (!$salnData) {
                return response(['code' => 404, 'message' => 'No SALN found for year ' . $complianceYear]);
            }

            // Generate PDF using the existing blade template
            $pdf = \PDF::loadView('pdf.saln', $salnData);
            
            return $pdf->download('SALN_' . $complianceYear . '_' . Auth::user()->fname . '_' . Auth::user()->lname . '.pdf');
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Get formatted SALN data for report generation
     */
    private function getSalnDataForReport($complianceYear)
    {
        $declarantData = Declarant::where('user_id', Auth::id())
            ->whereYear('compliance_for', $complianceYear)
            ->first();

        if (!$declarantData) {
            return null;
        }

        $user = Auth::user();
        $spouseData = Spouse::where('user_id', Auth::id())
            ->whereYear('compliance_for', $complianceYear)
            ->first();

        $familyBg = FamilyBackground::where('user_id', Auth::id())->first();

        // Gather all related data
        $children = SalnChildren::where('user_id', Auth::id())
            ->whereYear('compliance_for', $complianceYear)
            ->get()
            ->map(function($child) {
                return [
                    'name' => $child->name,
                    'birthdate' => $child->birthdate,
                    'age' => \Carbon\Carbon::parse($child->birthdate)->age
                ];
            });

        $realProperties = RealProperties::where('user_id', Auth::id())
            ->whereYear('compliance_for', $complianceYear)
            ->get()->toArray();

        $personalProperties = PersonalProperties::where('user_id', Auth::id())
            ->whereYear('compliance_for', $complianceYear)
            ->get()->toArray();

        $liabilities = Liabilities::where('user_id', Auth::id())
            ->whereYear('compliance_for', $complianceYear)
            ->get()->toArray();

        $businessInterests = BusinessInterest::where('user_id', Auth::id())
            ->whereYear('compliance_for', $complianceYear)
            ->get()->toArray();

        $governmentRelatives = GovernmentRelative::where('user_id', Auth::id())
            ->whereYear('compliance_for', $complianceYear)
            ->get()->toArray();

        // Calculate totals
        $realPropertiesSubtotal = array_sum(array_column($realProperties, 'acquisition_cost'));
        $personalPropertiesSubtotal = array_sum(array_column($personalProperties, 'acquisition_cost'));
        $liabilitiesTotal = array_sum(array_column($liabilities, 'outstanding_balance'));

        // If joint filing, include spouse assets
        if ($declarantData->filing_type === 'Joint Filing') {
            $spouseRealProperties = SpouseRealProperties::where('user_id', Auth::id())
                ->whereYear('compliance_for', $complianceYear)
                ->get()->toArray();
            
            $spousePersonalProperties = SpousePersonalProperties::where('user_id', Auth::id())
                ->whereYear('compliance_for', $complianceYear)
                ->get()->toArray();
            
            $spouseLiabilities = SpouseLiabilities::where('user_id', Auth::id())
                ->whereYear('compliance_for', $complianceYear)
                ->get()->toArray();

            $realPropertiesSubtotal += array_sum(array_column($spouseRealProperties, 'acquisition_cost'));
            $personalPropertiesSubtotal += array_sum(array_column($spousePersonalProperties, 'acquisition_cost'));
            $liabilitiesTotal += array_sum(array_column($spouseLiabilities, 'outstanding_balance'));
        }

        return [
            'declarant' => (object)[
                'fname' => $user->fname,
                'mname' => $user->mname,
                'lname' => $user->lname,
                'suffix' => $user->suffix ?? null,
                'position' => $declarantData->position,
                'agency' => $declarantData->agency,
                'office' => $declarantData->office,
                'government_id_issued' => $declarantData->government_id_issued,
                'government_id_no' => $declarantData->government_id_no,
                'date_issued' => $declarantData->date_issued,
            ],
            'spouse' => $spouseData ? (object)[
                'spouse_fname' => $familyBg->spouse_fname ?? 'N/A',
                'spouse_mname' => $familyBg->spouse_mname ?? 'N/A',
                'spouse_lname' => $familyBg->spouse_lname ?? 'N/A',
                'position' => $spouseData->position,
                'agency' => $spouseData->agency,
                'office' => $spouseData->office,
                'government_id_issued' => $spouseData->government_id_issued,
                'government_id_no' => $spouseData->government_id_no,
                'date_issued' => $spouseData->date_issued,
            ] : null,
            'address' => $user->address ?? 'N/A',
            'filing_type' => $declarantData->filing_type,
            'compliance_type' => $declarantData->compliance_type,
            'compliance_for' => $declarantData->compliance_for,
            'as_of_year' => date('Y', strtotime($declarantData->compliance_for)),
            'as_of_month' => date('F', strtotime($declarantData->compliance_for)),
            'as_of_day' => date('j', strtotime($declarantData->compliance_for)),
            'children' => $children,
            'real_properties' => $realProperties,
            'personal_properties' => $personalProperties,
            'personal_properties_extended' => [],
            'liabilities' => $liabilities,
            'business' => $businessInterests,
            'relatives' => $governmentRelatives,
            'real_properties_subtotal' => $realPropertiesSubtotal,
            'personal_properties_subtotal' => $personalPropertiesSubtotal,
            'personal_properties_extended_subtotal' => 0,
            'liabilities_total' => $liabilitiesTotal,
            'business_interest' => count($businessInterests) > 0 ? '0' : '1',
            'government_relatives' => count($governmentRelatives) > 0 ? '0' : '1',
            'spouse_signature' => $declarantData->filing_type === 'Joint Filing' ? 1 : 0,
            'print_date' => now(),
            'before_me' => '',
            'day_of' => '',
        ];
    }

    /**
     * Delete SALN record for a specific year
     * This will remove all related data (properties, liabilities, children, etc.)
     */
    public function deleteSalnRecord(Request $request)
    {
        try {
            $request->validate([
                'compliance_for' => 'required|date'
            ]);

            $complianceDate = $request->compliance_for;
            $userId = Auth::id();

            // Check if the SALN record exists and belongs to the user
            $declarant = Declarant::where('user_id', $userId)
                ->where('compliance_for', $complianceDate)
                ->first();

            if (!$declarant) {
                return response(['code' => 404, 'message' => 'SALN record not found for date ' . $complianceDate]);
            }

            // Delete all related records for this specific compliance date
            $tables = [
                RealProperties::class,
                PersonalProperties::class,
                Liabilities::class,
                BusinessInterest::class,
                GovernmentRelative::class,
                SalnChildren::class,
                Marriages::class,
                Spouse::class,
                SpouseRealProperties::class,
                SpousePersonalProperties::class,
                SpouseLiabilities::class,
                SpouseBusinessInterest::class,
            ];

            foreach ($tables as $model) {
                $query = $model::where('compliance_for', $complianceDate);
                if ($model === Marriages::class) {
                    $query->where('userid', $userId);
                } else {
                    $query->where('user_id', $userId);
                }
                $query->delete();
            }

            // Finally delete the declarant record
            $declarant->delete();

            return response(['code' => 200, 'message' => 'SALN record and all related data deleted successfully.']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Clone data from the latest SALN to a new date
     */
    public function cloneLatestSalnData(Request $request)
    {
        try {
            $request->validate([
                'target_date' => 'required|date'
            ]);

            $targetDate = $request->target_date;
            $userId = Auth::id();

            // Find latest SALN (most recent before the target date)
            $latest = Declarant::where('user_id', $userId)
                ->where('compliance_for', '<', $targetDate)
                ->orderBy('compliance_for', 'desc')
                ->first();

            if (!$latest) {
                return response(['code' => 404, 'message' => 'No previous SALN records to clone from.']);
            }

            $sourceDate = $latest->compliance_for;

        // Define models to clone automatically (list items)
        $models = [
            SalnChildren::class,
            RealProperties::class,
            PersonalProperties::class,
            Liabilities::class,
            BusinessInterest::class,
            GovernmentRelative::class,
            Marriages::class,
            SpouseRealProperties::class,
            SpousePersonalProperties::class,
            SpouseLiabilities::class,
            SpouseBusinessInterest::class,
        ];

        DB::beginTransaction();

        foreach ($models as $modelClass) {
            // Check if target already has data for this model to avoid duplicates
            $existsQuery = $modelClass::where('compliance_for', $targetDate);
            if ($modelClass === Marriages::class) {
                $existsQuery->where('userid', $userId);
            } else {
                $existsQuery->where('user_id', $userId);
            }
            
            if ($existsQuery->exists()) {
                continue;
            }

            // Get source records
            $sourceQuery = $modelClass::where('compliance_for', $sourceDate);
            if ($modelClass === Marriages::class) {
                $sourceQuery->where('userid', $userId);
            } else {
                $sourceQuery->where('user_id', $userId);
            }
            
            $records = $sourceQuery->get();

            foreach ($records as $record) {
                $newRecord = $record->replicate();
                $newRecord->compliance_for = $targetDate;
                $newRecord->save();
            }
        }

        // Instead of cloning Declarant and Spouse (which would create the history record),
        // we just fetch them to return the values for the frontend to populate.
        $declarant = Declarant::where('user_id', $userId)->where('compliance_for', $sourceDate)->first();
        $spouse = Spouse::where('user_id', $userId)->where('compliance_for', $sourceDate)->first();
        $familyBackground = FamilyBackground::where('user_id', $userId)->first();

        DB::commit();

        return response([
            'code' => 200, 
            'message' => 'SALN data pre-filled from ' . date('M d, Y', strtotime($sourceDate)),
            'declarant' => $declarant,
            'spouse' => $spouse,
            'family_background' => $familyBackground
        ]);
    } catch (\Exception $exception) {
        DB::rollBack();
        return response(['code' => 500, 'message' => $exception->getMessage()]);
    }
}
}
