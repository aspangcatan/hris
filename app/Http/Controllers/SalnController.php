<?php

namespace App\Http\Controllers;

use App\Models\BusinessInterest;
use App\Models\Declarant;
use App\Models\FamilyBackground;
use App\Models\GovernmentRelative;
use App\Models\Liabilities;
use App\Models\PersonalProperties;
use App\Models\RealProperties;
use App\Models\SalnChildren;
use App\Models\Spouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalnController extends Controller
{
    public function getDeclarant(Request $request)
    {
        try {
            $declarant = DB::table('tdh_user.users as u')
                ->leftJoin('hris.saln_declarant as d', 'u.id', '=', 'd.user_id')
                ->where('u.id', '=', Auth::id())
                ->get(['u.fname', 'u.mname', 'u.lname', 'd.position', 'd.agency',
                    'd.office', 'd.government_id_issued', 'd.government_id_no', 'd.date_issued']);
            return response(['code' => 200, 'results' => $declarant]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function updateDeclarant(Request $request)
    {
        try {
            $declarant = Declarant::where('user_id',Auth::id())->first();
            $spouse = Spouse::where('user_id',Auth::id())->first();
            $familyBackground = FamilyBackground::where('user_id',Auth::id())->first();
            if (!$declarant) {
                $declarant = new Declarant;
                $declarant->user_id = Auth::id();
            }

            if (!$spouse) {
                $spouse = new Spouse;
                $spouse->user_id = Auth::id();
            }

            if(!$familyBackground){
                $familyBackground = new FamilyBackground;
                $familyBackground->user_id = Auth::id();
            }

            $declarant->position = $request->declarant_position;
            $declarant->agency = $request->declarant_agency;
            $declarant->office = $request->declarant_office;
            $declarant->government_id_issued = $request->declarant_government_id_issued;
            $declarant->government_id_no = $request->declarant_government_id_no;
            $declarant->date_issued = $request->declarant_date_issued;
            $declarant->save();

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
            return response(['code' => 200, 'message' => 'Information updated']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getDeclarantSpouse(Request $request)
    {
        try {
            $declarant = DB::table('hris.family_backgrounds as f')
                ->leftJoin('hris.saln_spouse as d', 'f.user_id', '=', 'd.user_id')
                ->where('f.user_id', '=', Auth::id())
                ->get(['f.spouse_fname', 'f.spouse_mname', 'f.spouse_lname', 'd.position', 'd.agency',
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
            $saln_children->save();
            return response(['code' => 200, 'message' => 'New item added']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getChildren(Request $request)
    {
        try {
            $results = SalnChildren::where('user_id', Auth::id())->get();
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
            $real_properties = RealProperties::find($request->id);
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
            $real_properties->save();
            return response(['code' => 200, 'message' => 'New item added']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getRealProperties(Request $request)
    {
        try {
            $results = RealProperties::where('user_id', Auth::id())->get();
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
            $personal_properties = PersonalProperties::find($request->id);
            if (!$personal_properties) {
                $personal_properties = new PersonalProperties;
                $personal_properties->user_id = Auth::id();
            }

            $personal_properties->gadget = $request->gadget;
            $personal_properties->various_years = $request->various_years;
            $personal_properties->acquisition_cost = $request->acquisition_cost;
            $personal_properties->save();
            return response(['code' => 200, 'message' => 'New item added']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getPersonalProperties(Request $request)
    {
        try {
            $results = PersonalProperties::where('user_id', Auth::id())->get();
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
            $liabilities->save();
            return response(['code' => 200, 'message' => 'New item added']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getLiabilities()
    {
        try {
            $results = Liabilities::where('user_id', Auth::id())->get();
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
            $business_interest->save();
            return response(['code' => 200, 'message' => 'New item added']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getBusinessInterest(Request $request)
    {
        try {
            $result = BusinessInterest::where('user_id', Auth::id())->get();
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
            $government_relative->save();
            return response(['code' => 200, 'message' => 'New item added']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getGovernmentRelative(Request $request)
    {
        try {
            $result = GovernmentRelative::where('user_id', Auth::id())->get();
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
}
