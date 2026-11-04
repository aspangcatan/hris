<?php

namespace App\Http\Controllers;

use App\Services\ReorientationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReorientationController extends Controller
{
    protected ReorientationService $service;

    public function __construct(ReorientationService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return view('reorientation', ['modules' => $this->service->modules()]);
    }

    // -----------------------------------------------------------------------
    // Employee
    // -----------------------------------------------------------------------

    public function getMyModules()
    {
        return response()->json($this->service->getModuleSummaries(Auth::id()));
    }

    public function getMyPostTest(Request $request)
    {
        $moduleNo = (int) $request->input('module_no');
        if (!$this->service->isValidModule($moduleNo)) {
            return response()->json(['error' => 'Invalid module.'], 422);
        }

        if (!$this->service->isModuleUnlocked(Auth::id(), $moduleNo)) {
            return response()->json(['error' => 'Please submit the previous module\'s post test first.'], 403);
        }

        return response()->json($this->service->getPostTest(Auth::id(), $moduleNo));
    }

    // View-only reader page, opened in a new tab from the Re-Orientation page
    public function readModule(int $moduleNo)
    {
        if (!$this->service->isValidModule($moduleNo) || !$this->service->isModuleUnlocked(Auth::id(), $moduleNo)) {
            abort(403);
        }

        return view('reorientation_reader', [
            'moduleNo' => $moduleNo,
            'module'   => $this->service->modules()[$moduleNo],
        ]);
    }

    // Streams the module PDF to the reader page only. The file is outside
    // public/ so it has no direct URL, and it is never sent as an attachment.
    public function modulePdf(int $moduleNo)
    {
        if (!$this->service->isValidModule($moduleNo) || !$this->service->isModuleUnlocked(Auth::id(), $moduleNo)) {
            abort(403);
        }

        return response()->file($this->service->pdfPath($moduleNo), [
            'Content-Type'           => 'application/pdf',
            'Content-Disposition'    => 'inline',
            'Cache-Control'          => 'no-store, no-cache, must-revalidate, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function submitPostTest(Request $request)
    {
        try {
            $request->validate([
                'module_no' => 'required|integer',
                'answers'   => 'required|array',
                'answers.*' => 'nullable|string|max:10000',
            ]);

            $moduleNo = (int) $request->input('module_no');
            if (!$this->service->isValidModule($moduleNo)) {
                throw new \Exception('Invalid module.');
            }

            $this->service->submitPostTest(Auth::id(), $moduleNo, $request->input('answers'));

            return response()->json(['message' => 'Post test submitted. HR will check your answers.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // -----------------------------------------------------------------------
    // Question management (HR)
    // -----------------------------------------------------------------------

    public function getQuestions(Request $request)
    {
        $moduleNo = (int) $request->input('module_no');
        if (!$this->service->isValidModule($moduleNo)) {
            return response()->json(['error' => 'Invalid module.'], 422);
        }

        return response()->json($this->service->getActiveQuestions($moduleNo));
    }

    public function saveQuestion(Request $request)
    {
        try {
            $request->validate([
                'module_no' => 'required|integer',
                'question'  => 'required|string|max:5000',
                'id'        => 'nullable|integer',
            ]);

            $moduleNo = (int) $request->input('module_no');
            if (!$this->service->isValidModule($moduleNo)) {
                throw new \Exception('Invalid module.');
            }

            $id = $request->input('id') ? (int) $request->input('id') : null;
            $this->service->saveQuestion($moduleNo, trim($request->input('question')), $id);

            return response()->json(['message' => 'Question saved.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function deleteQuestion(int $id)
    {
        $this->service->deleteQuestion($id);
        return response()->json(['message' => 'Question removed.']);
    }

    public function moveQuestion(Request $request, int $id)
    {
        try {
            $direction = $request->input('direction') === 'up' ? 'up' : 'down';
            $this->service->moveQuestion($id, $direction);
            return response()->json(['message' => 'Order updated.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // -----------------------------------------------------------------------
    // Checking (HR, from the Employee page)
    // -----------------------------------------------------------------------

    public function getEmployeeModules(Request $request)
    {
        return response()->json($this->service->getModuleSummaries((int) $request->input('user_id')));
    }

    public function getEmployeeSubmission(Request $request)
    {
        $moduleNo = (int) $request->input('module_no');
        if (!$this->service->isValidModule($moduleNo)) {
            return response()->json(['error' => 'Invalid module.'], 422);
        }

        $data = $this->service->getSubmissionForChecking((int) $request->input('user_id'), $moduleNo);
        if (!$data) {
            return response()->json(['error' => 'No submission for this module yet.'], 404);
        }

        return response()->json($data);
    }

    public function checkSubmission(Request $request)
    {
        try {
            $request->validate([
                'submission_id' => 'required|integer',
                'status'        => 'required|in:passed,failed',
                'remarks'       => 'nullable|string|max:5000',
            ]);

            if ($request->input('status') === 'failed' && trim((string) $request->input('remarks')) === '') {
                throw new \Exception('Please add remarks so the employee knows what to improve.');
            }

            $this->service->checkSubmission(
                (int) $request->input('submission_id'),
                $request->input('status'),
                $request->input('remarks') ? trim($request->input('remarks')) : null
            );

            return response()->json(['message' => 'Post test marked as ' . $request->input('status') . '.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
