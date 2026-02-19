<div class="modal fade premium-modal" id="education_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-postcard-fill text-white text-5xl"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-white bg-danger rounded p-2 mb-2 d-none" id="education_error">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list"></ul>
                </div>
                <div class="modal-title-text">Educational Background</div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Level</label>
                        <select class="form-control premium-select" id="education_level">
                            <option value="">Select level</option>
                            <option value="Elementary">Elementary</option>
                            <option value="Secondary">Secondary</option>
                            <option value="Vocational/Trade Course">Vocational/Trade Course</option>
                            <option value="College">College</option>
                            <option value="Graduate Studies">Graduate Studies</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">School Name</label>
                        <input type="text" class="form-control premium-input" id="school_name"/>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Basic Education / Degree / Course</label>
                        <input type="text" class="form-control premium-input" id="education_degree"/>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Attendance From</label>
                        <select class="form-control premium-select" id="attendance_from">
                            <option value="" selected>From</option>
                            @for($year=1950; $year<=date("Y"); $year++)
                                <option>{{ $year }}</option>
                            @endfor
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Attendance To</label>
                        <select class="form-control premium-select" id="attendance_to">
                            <option value="" selected>To</option>
                            @for($year=1950; $year<=date("Y"); $year++)
                                <option>{{ $year }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Highest Level / Units Earned (if not graduated)</label>
                        <input type="text" class="form-control premium-input" id="highest_level"/>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Year Graduated</label>
                        <select class="form-control premium-select" id="year_graduated">
                            <option value="" selected>Select year</option>
                            @for($year=1950; $year<=date("Y"); $year++)
                                <option>{{ $year }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Scholarship / Academic Honors</label>
                        <input type="text" class="form-control premium-input" id="scholarship"/>
                    </div>
                </div>
                <div class="mt-5 flex items-center justify-end gap-2">
                    <button class="btn btn-secondary rounded-xl border-0 px-4 py-2" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-success rounded-xl border-0 px-4 py-2 font-semibold" id="btn_save_education">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>
