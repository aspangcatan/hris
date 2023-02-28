<form>
    <div id="children">
        <div class="d-flex justify-content-end align-items-center">
            <button class="btn btn-primary">Create new <i class="bi bi-plus-lg ms-2"></i></button>
        </div>
        <div class="table-responsive mt-3">
            <table class="table text-center">
                <thead>
                <tr>
                    <th style="width: 40%">Name & Organization Address</th>
                    <th>Position/Work Nature</th>
                    <th style="width: 5%">From</th>
                    <th style="width: 5%">To</th>
                    <th style="width: 5%">No. hours</th>
                    <th colspan="2" style="width: 8%"></th>
                </tr>
                </thead>
                <tbody id="children_list">
                @for($i=0; $i<10; $i++)
                    <tr>
                        <td>Silhig Dakbayan</td>
                        <td>Community Service</td>
                        <td>03/21/2019</td>
                        <td>03/21/2019</td>
                        <td>128</td>
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
