<?php

namespace App\Http\Controllers;

use App\Services\OrgHierarchyService;
use Illuminate\Support\Facades\Auth;

class ManagedEmployeeController extends Controller
{
    protected OrgHierarchyService $orgHierarchy;

    public function __construct(OrgHierarchyService $orgHierarchy)
    {
        $this->orgHierarchy = $orgHierarchy;
    }

    public function index()
    {
        return response()->json($this->orgHierarchy->getManagedEmployees(Auth::id()));
    }
}
