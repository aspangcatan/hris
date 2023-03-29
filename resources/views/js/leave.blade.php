<script>
    const LEAVE_TYPES = ["Vacation Leave", "Mandatory/Forced Leave", "Sick Leave", "Maternity Leave", "Paternity Leave", "Special Privilege",
        "Solo Parent Leave", "Study Leave", "10-Day VAWC Leave", "Rehabilitation Privilege", "Special Leave Benefits for Women",
        "Special Emergency (Calamity) Leave", "Adoption Leave", "Others"];

    const leave_modal = new bootstrap.Modal(document.getElementById('leave_modal'));
    $(document).ready(function () {
        $('.datepicker').datepicker({
            multidate: true
        });

        $("body").on("change", "input[name='leave_type']", function () {
            $("#leave_modal input:not(input[name='leave_type']):not('#dates_applied'):not(input[name='commutation'])").prop("disabled", true);
            $("#leave_modal input[type='radio']:not(input[name='leave_type'])").prop("checked", false);
            $("input[type='text']").val("");
            switch ($(this).next().text().trim()) {
                case "Vacation Leave":
                case "Special Leave":
                    $("input[name='vacation_leave']").prop("disabled", false);
                    $("#within_philippines").val("")
                    $("#within_philippines").prop("disabled", false)
                    $("#abroad").val("")
                    $("#abroad").prop("disabled", false)
                    break;
                case "Sick Leave":
                    $("input[name='vacation_leave']").prop("disabled", false);
                    $("#in_hospital").val("")
                    $("#in_hospital").prop("disabled", false)
                    $("#out_hospital").val("")
                    $("#out_hospital").prop("disabled", false)
                    break;
                case "Special Leave Benefits for Women":
                    $("#special_leave").val("")
                    $("#special_leave").prop("disabled", false)
                    break;
                case "Study Leave":
                    $("input[name='study_leave']").prop("disabled", false);
                    break;
                default:
                    //When Others are checked on type of Leave
                    $(this).next().children().eq(1).prop("disabled", false);
                    $("input[name='other_purpose']").prop("disabled", false);
                    break;
            }
        });

        $("input[name='vacation_leave']").change(function () {
            if ($(this).next().text().trim() == "Within the Philippines") {
                $("#within_philippines").prop("disabled", false);
                $("#abroad").prop("disabled", true);
                $("#abroad").val("");
            } else {
                $("#abroad").prop("disabled", false);
                $("#within_philippines").prop("disabled", true);
                $("#within_philippines").val("");
            }
        });

        $("input[name='sick_leave']").change(function () {
            if ($(this).next().text().trim() == "In Hospital (Specify Illness)") {
                $("#in_hospital").prop("disabled", false);
                $("#out_hospital").prop("disabled", true);
                $("#out_hospital").val("");
            } else {
                $("#out_hospital").prop("disabled", false);
                $("#in_hospital").prop("disabled", true);
                $("#in_hospital").val("");
            }
        });

        leave_modal.toggle();
        init_leave();
    });

    function init_leave() {
        LEAVE_TYPES.forEach(it => {
            let div = ` <div class="col-md-6">
                            <div class="form-check">
                                 <input class="form-check-input" type="radio" name="leave_type">
                                 <div class="d-flex align-items-center">
                                    <label class="form-check-label">` + it + `</label>`;
            if (it == "Others") {
                div += `<input class="ms-2 underline w-100" type="text" />`;
            }
            div += `</div></div></div>`;
            $("#leave_list").append(div);
        })
    }
</script>
