<form>
    <div id="children">
        <div class="d-flex justify-content-end align-items-center">
            <button class="btn btn-primary">Create new <i class="bi bi-plus-lg ms-2"></i></button>
        </div>
        <div class="table-responsive mt-3">
            <table class="table text-center">
                <thead>
                <tr>
                    <th>Learning Title</th>
                    <th style="width: 5%">From</th>
                    <th style="width: 5%">To</th>
                    <th style="width: 5%">No. hours</th>
                    <th style="width: 10%">Type</th>
                    <th style="width: 20%">Conducted/Sponsored by</th>
                    <th colspan="2" style="width: 8%"></th>
                </tr>
                </thead>
                <tbody id="children_list">
                @for($i=0; $i<10; $i++)
                    <tr>
                        <td>Govmail End User Training</td>
                        <td>03/21/2019</td>
                        <td>03/21/2019</td>
                        <td>128</td>
                        <td>Technical</td>
                        <td>DICT</td>
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
