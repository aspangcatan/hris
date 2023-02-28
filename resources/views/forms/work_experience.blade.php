<form>
    <div id="children">
        <div class="d-flex justify-content-end align-items-center">
            <button class="btn btn-sm btn-primary">Create new <i class="bi bi-plus-lg ms-2"></i></button>
        </div>
        <div class="table-responsive mt-3">
            <table class="table text-center">
                <thead>
                <tr>
                    <th>Job Title</th>
                    <th style="width: 40%">Company</th>
                    <th style="width: 5%">From</th>
                    <th style="width: 5%">To</th>
                    <th style="width: 6%">Salary</th>
                    <th style="width: 5%">Status Appointment</th>
                    <th style="width: 4%">Gov't Service (Y/N)</th>
                    <th colspan="2" style="width: 8%"></th>
                </tr>
                </thead>
                <tbody id="children_list">
                @for($i=0; $i<10; $i++)
                    <tr>
                        <td>Computer Maintenance Technologist II</td>
                        <td>Cebu South Medical Center</td>
                        <td>03/21/2019</td>
                        <td>03/21/2019</td>
                        <td>36,500.00</td>
                        <td>Permanent</td>
                        <td>Y</td>
                        <td>
                            <button class="btn btn-sm" style="background: transparent">
                                <i class="bi bi-pencil-fill text-success"></i>
                            </button>
                        </td>
                        <td>
                            <button class="btn btn-sm" style="background: transparent">
                                <i class="bi bi-trash-fill text-danger"></i>
                            </button>
                        </td>
                    </tr>
                @endfor
                </tbody>
            </table>
        </div>
    </div>
</form>
