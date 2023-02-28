<form>
    <div id="children">
        <div class="d-flex justify-content-end align-items-center">
            <button class="btn btn-sm btn-primary">Create new <i class="bi bi-plus-lg ms-2"></i></button>
        </div>
        <div class="table-responsive mt-3">
            <table class="table table-bordered text-center">
                <thead>
                <tr>
                    <th colspan="2" style="width:33.33% ">Skills/Hobbies</th>
                    <th colspan="2" style="width:33.33% ">Non-Academic Distinctions</th>
                    <th colspan="2" style="width:33.33% ">Association Membership</th>
                </tr>
                </thead>
                <tbody id="other_information_list">
                @for($i=0; $i<5; $i++)
                    <tr>
                        <td>
                            <div>
                                <input class="form-control" />
                            </div>
                        </td>
                        <td>
                           <i class="bi bi-trash text-danger"></i>
                        </td>
                    </tr>
                @endfor
                </tbody>
            </table>
        </div>
    </div>
</form>
