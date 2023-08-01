<div id="education_background">
    @if(Route::currentRouteName() == "profile")
        <div class="d-flex justify-content-end align-items-center">
            <button class="btn btn-sm btn-primary" id="btn_add_education">New</button>
        </div>
    @endif
    <div class="table-responsive mt-3">
        <table class="table text-center">
            <thead>
            <tr>
                <th style="width: 10%">Level</th>
                <th style="width: 25%">School Name</th>
                <th>Education/Degree/Course</th>
                <th>Attendance From</th>
                <th>Attendance To</th>
                <th>Highest Level/Units</th>
                <th>Year graduated</th>
                <th>Scholarship/Academic honors</th>
                <th colspan="2" style="width: 8%"></th>
            </tr>
            </thead>
            <tbody id="educational_list">
            </tbody>
        </table>
    </div>
</div>
