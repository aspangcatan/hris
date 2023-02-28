<form>
    <div id="children">
        <div class="d-flex justify-content-end align-items-center">
            <button class="btn btn-primary">Create new <i class="bi bi-plus-lg ms-2"></i></button>
        </div>
        <div class="table-responsive mt-3">
            <table class="table text-center">
                <thead>
                <tr>
                    <th>Career Service</th>
                    <th style="width: 10%">Rating</th>
                    <th style="width: 10%">Date of<br/>examination</th>
                    <th style="width: 20%">Place of <br/>examination</th>
                    <th style="width: 10%">License No.</th>
                    <th style="width: 10%">Date of<br/>validity</th>
                    <th colspan="2" style="width: 8%"></th>
                </tr>
                </thead>
                <tbody id="children_list">
                @for($i=0; $i<10; $i++)
                    <tr>
                        <td>CSE-COMEX PROFESSIONAL</td>
                        <td>81.25</td>
                        <td>03/21/2019</td>
                        <td>Civil Service Commission Regional Office VII</td>
                        <td>N/A</td>
                        <td>N/A</td>
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
