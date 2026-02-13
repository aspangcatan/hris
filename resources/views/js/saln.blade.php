<script>
    const children_modal = new bootstrap.Modal(document.getElementById('children_modal'));
    const real_properties_modal = new bootstrap.Modal(document.getElementById('real_properties_modal'));
    const spouse_real_properties_modal = new bootstrap.Modal(document.getElementById('spouse_real_properties_modal'));
    const personal_properties_modal = new bootstrap.Modal(document.getElementById('personal_properties_modal'));
    const spouse_personal_properties_modal = new bootstrap.Modal(document.getElementById('spouse_personal_properties_modal'));
    const liabilities_modal = new bootstrap.Modal(document.getElementById('liabilities_modal'));
    const spouse_liabilities_modal = new bootstrap.Modal(document.getElementById('spouse_liabilities_modal'));
    const business_interest_modal = new bootstrap.Modal(document.getElementById('business_interest_modal'));
    const spouse_business_interest_modal = new bootstrap.Modal(document.getElementById('spouse_business_interest_modal'));
    const government_relative_modal = new bootstrap.Modal(document.getElementById('government_relative_modal'));
    const marriages_modal = new bootstrap.Modal(document.getElementById('marriages_modal'));
    const saln_modal = new bootstrap.Modal(document.getElementById('saln_modal'));
    const saln_form_modal = new bootstrap.Modal(document.getElementById('saln_form_modal'));

    let currentEditingYear = null;

    let children_id = 0;
    let marriages_id = 0;
    let government_relative_id = 0;

    let real_properties_id = 0;
    let spouse_real_properties_id = 0;

    let personal_properties_id = 0;
    let spouse_personal_properties_id = 0;

    let liabilities_id = 0;
    let spouse_liabilities_id = 0;

    let business_interest_id = 0;
    let spouse_business_interest_id = 0;

    function getComplianceDate() {
        const as_of_month = $("#as_of_month").val();
        const as_of_day = $("#as_of_day").val();
        const as_of_year = $("#as_of_year").val();
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'];
        const monthIndex = monthNames.indexOf(as_of_month) + 1;
        if (monthIndex === 0) return as_of_year + '-12-31';
        return as_of_year + '-' + String(monthIndex).padStart(2, '0') + '-' + String(as_of_day).padStart(2, '0');
    }

    function getComplianceYear() {
        return $("#as_of_year").val();
    }


    $(document).ready(function () {

        // ==================== SALN HISTORICAL LIST ====================

        // Load SALN records on page load
        loadSalnRecords();

        // Year filter change
        $("#filter_compliance_year").change(function () {
            loadSalnRecords();
        });

        $("#as_of_month, #as_of_day, #as_of_year").change(function() {
            reloadAllSalnSections(getComplianceDate());
        });

        // Open Create New SALN modal
        $("#btn_open_create_saln").click(function () {
            currentEditingYear = null;
            $("#salnFormModalLabel").html('<i class="bi bi-file-earmark-text me-2"></i>SALN — Create New Statement');
            // Reset form fields for new entry
            $("input[name='compliance_for'][value='Annual Filing']").prop('checked', true);
            $("input[name='filing_type'][value='Not Applicable']").prop('checked', true);
            $("#as_of_year").val(new Date().getFullYear());
            $("#as_of_month").val('December');
            $("#as_of_day").val('31');

            const targetDate = getComplianceDate();
            const year = getComplianceYear();

            // Auto-populate from latest SALN
            const btn = $(this);
            const originalText = btn.html();
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Populating...');

            fetch('{{ route('cloneLatestSaln') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({ target_date: targetDate })
            })
            .then(res => res.json())
            .then(data => {
                btn.prop('disabled', false).html(originalText);
                if (data.code == 200) {
                    toastr.info(data.message, "Pre-filled");
                    
                    // Manually populate Declarant info
                    if (data.declarant) {
                        $("#declarant_position").val(data.declarant.position);
                        $("#declarant_agency").val(data.declarant.agency);
                        $("#declarant_address").val(data.declarant.office);
                        $("#declarant_issued_id").val(data.declarant.government_id_issued);
                        $("#declarant_id_no").val(data.declarant.government_id_no);
                        $("#declarant_date_issued").val(data.declarant.date_issued);
                    }
                    
                    // Manually populate Spouse info
                    if (data.spouse) {
                        $("#spouse_position").val(data.spouse.position);
                        $("#spouse_agency").val(data.spouse.agency);
                        $("#spouse_address").val(data.spouse.office);
                        $("#spouse_issued_id").val(data.spouse.government_id_issued);
                        $("#spouse_id_no").val(data.spouse.government_id_no);
                        $("#spouse_date_issued").val(data.spouse.date_issued);
                    }

                    if (data.family_background) {
                        $("#spouse_fname").val(data.family_background.spouse_fname);
                        $("#spouse_mname").val(data.family_background.spouse_mname);
                        $("#spouse_lname").val(data.family_background.spouse_lname);
                    }
                }
                
                saln_form_modal.show();
                $("#saln_loader_overlay").show();
                
                // Reload section lists (cloned sub-items), but skip declarant/spouse fetches as we already pre-filled them
                reloadAllSalnSections(getComplianceDate(), true).finally(() => {
                    $("#saln_loader_overlay").hide();
                });
            })
            .catch(err => {
                console.log(err);
                btn.prop('disabled', false).html(originalText);
                saln_form_modal.show();
            });
        });




        // ==================== END SALN HISTORICAL LIST ====================

        $("#btn_print_saln").click(function () {
            $("#print_date").val(moment().format("YYYY-MM-DD"));
            saln_modal.toggle();
        });

        $("#btn_confirm_print").click(function () {
            const filing_type = $("input[name='filing_type']:checked").val();
            const compliance_for = $("input[name='compliance_for']:checked").val();

            const spouse_signature = $("#spouse_signature").val();
            const as_of_month = $("#as_of_month").val();
            const as_of_day = $("#as_of_day").val();
            const as_of_year = $("#as_of_year").val();

            if (filing_type === undefined) {
                alert("Please specify Filing type");
                return;
            }

            const business_interest_checkbox = ($("#business_interest_checkbox").is(":checked")) ? 1 : 0;
            const government_relatives_checkbox = ($("#government_relatives_checkbox").is(":checked")) ? 1 : 0;

            const date = $("#print_date").val();
            const sworn_date = $("#saln_sworn_date").val();

            if (date == "") {
                alert("Please fill in date");
                return;
            }

            if (sworn_date == "") {
                alert("Please fill in sworn date");
                return;
            }

            const before_me = moment(sworn_date).format("Do");
            const day_of = moment(sworn_date).format("MMMM") + " " + moment(sworn_date).format("YYYY");

            window.open(
                '{{ route('printSaln') }}?id=0&print_date=' + date + '&filing_type=' + filing_type +
                '&compliance_for=' + compliance_for +
                '&business_interest=' + business_interest_checkbox +
                '&government_relatives=' + government_relatives_checkbox +
                '&as_of_month=' + as_of_month +
                '&as_of_day=' + as_of_day +
                '&as_of_year=' + as_of_year +
                '&before_me=' + before_me +
                '&spouse_signature=' + spouse_signature +
                '&day_of=' + day_of,
                '_blank' // <- This is what makes it open in a new window.
            );
        });

        $("#btn_add_children").click(function () {
            children_id = 0;
            $("#children_name").val("");
            $("#children_birthdate").val("")
            children_modal.toggle();
        });

        $("#btn_add_real_properties").click(function () {
            real_properties_id = 0;
            $("#real_description").val("");
            $("#real_kind").val("")
            $("#real_location").val("");
            $('#real_assessed_value').val("");
            $("#real_current_fair").val("");
            $("#real_acquisition_year").val("");
            $("#real_acquisition_mode").val("");
            $("#real_acquisition_cost").val("");
            real_properties_modal.toggle();
        });

        $("#spouse_btn_add_real_properties").click(function () {
            spouse_real_properties_id = 0;
            $("#spouse_real_description").val("");
            $("#spouse_real_kind").val("")
            $("#spouse_real_location").val("");
            $('#spouse_real_assessed_value').val("");
            $("#spouse_real_current_fair").val("");
            $("#spouse_real_acquisition_year").val("");
            $("#spouse_real_acquisition_mode").val("");
            $("#spouse_real_acquisition_cost").val("");
            spouse_real_properties_modal.toggle();
        });

        $("#btn_add_personal_properties").click(function () {
            personal_properties_id = 0;
            $("#personal_gadget").val("");
            $("#personal_various_years").val("");
            $("#personal_acquisition_cost").val("");
            personal_properties_modal.toggle();
        });

        $("#spouse_btn_add_personal_properties").click(function () {
            spouse_personal_properties_id = 0;
            $("#spouse_personal_gadget").val("");
            $("#spouse_personal_various_years").val("");
            $("#spouse_personal_acquisition_cost").val("");
            spouse_personal_properties_modal.toggle();
        });

        $("#btn_add_liabilities").click(function () {
            liabilities_id = 0;
            $("#liabilities_nature").val("");
            $("#liabilities_creditors_name").val("");
            $("#liabilities_outstanding_balance").val("");
            liabilities_modal.toggle();
        });

        $("#spouse_btn_add_liabilities").click(function () {
            spouse_liabilities_id = 0;
            $("#spouse_liabilities_nature").val("");
            $("#spouse_liabilities_creditors_name").val("");
            $("#spouse_liabilities_outstanding_balance").val("");
            spouse_liabilities_modal.toggle();
        });

        $("#btn_add_business_interest").click(function () {
            business_interest_id = 0;
            $("#business_enterprise").val("");
            $("#business_address").val("");
            $("#business_nature").val("");
            $("#business_date_acquisition").val("");
            business_interest_modal.toggle();
        });

        $("#spouse_btn_add_business_interest").click(function () {
            spouse_business_interest_id = 0;
            $("#spouse_business_enterprise").val("");
            $("#spouse_business_address").val("");
            $("#spouse_business_nature").val("");
            $("#spouse_business_date_acquisition").val("");
            spouse_business_interest_modal.toggle();
        });

        $("#btn_add_government_relative").click(function () {
            government_relative_id = 0;
            $("#relative_name").val("");
            $("#relative_relationship").val("");
            $("#relative_position").val("");
            $("#relative_agency").val("");
            government_relative_modal.toggle();
        });

        $("#btn_add_marriages").click(function () {
            marriages_id = 0;
            $("#spouse_name").val("");
            marriages_modal.toggle();
        });

        $("#btn_save_declarant").click(function () {
            let is_valid = true;

            $(".is-invalid").removeClass("is-invalid");

            const declarant_position = $("#declarant_position").val().trim().toUpperCase();
            const declarant_agency = $("#declarant_agency").val().trim().toUpperCase();
            const declarant_office = $("#declarant_address").val().trim().toUpperCase();
            const declarant_government_issued_id = $("#declarant_issued_id").val().trim().toUpperCase();
            const declarant_government_id_no = $("#declarant_id_no").val().trim().toUpperCase();
            const declarant_date_issued = $("#declarant_date_issued").val().trim().toUpperCase();

            if (declarant_position == "") {
                is_valid = false;
                $("#declarant_position").addClass("is-invalid");
            }

            if (declarant_agency == "") {
                is_valid = false;
                $("#declarant_agency").addClass("is-invalid");
            }

            if (declarant_office == "") {
                is_valid = false;
                $("#declarant_address").addClass("is-invalid");
            }

            if (declarant_government_issued_id == "") {
                is_valid = false;
                $("#declarant_issued_id").addClass("is-invalid");
            }

            if (declarant_government_id_no == "") {
                is_valid = false;
                $("#declarant_id_no").addClass("is-invalid");
            }


            if (!is_valid) {
                Swal.fire({
                    title: 'Incomplete Information',
                    text: 'Please fill in all the required profile information highlighted in red.',
                    icon: 'warning',
                    confirmButtonColor: 'var(--saln-primary)'
                });
                return;
            }

            const params = {
                compliance_for: getComplianceDate(),
                declarant_position: declarant_position,
                declarant_agency: declarant_agency,
                declarant_office: declarant_office,
                declarant_government_id_issued: declarant_government_issued_id,
                declarant_government_id_no: declarant_government_id_no,
                declarant_date_issued: (declarant_date_issued == "") ? null : declarant_date_issued,
            }

            fetch('{{ route('updateDeclarant') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(params)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }

                    Swal.fire({
                        title: 'Profile Updated',
                        text: 'Declarant information has been successfully saved.',
                        icon: 'success',
                        confirmButtonColor: 'var(--saln-primary)',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    getDeclarant();
                }).catch(err => {
                console.log(err);
            })
        });

        $("#spouse_btn_save_declarant").click(function () {
            let is_valid = true;

            $(".is-invalid").removeClass("is-invalid");

            const spouse_fname = $("#spouse_fname").val().trim().toUpperCase();
            const spouse_mname = $("#spouse_mname").val().trim().toUpperCase();
            const spouse_lname = $("#spouse_lname").val().trim().toUpperCase();
            const spouse_position = $("#spouse_position").val().trim().toUpperCase();
            const spouse_agency = $("#spouse_agency").val().trim().toUpperCase();
            const spouse_office = $("#spouse_address").val().trim().toUpperCase();
            const spouse_government_issued_id = $("#spouse_issued_id").val().trim().toUpperCase();
            const spouse_government_id_no = $("#spouse_id_no").val().trim().toUpperCase();
            const spouse_date_issued = $("#spouse_date_issued").val().trim().toUpperCase();

            // Validate Spouse Fields (Assuming similar required fields as declarant for now)
             if (spouse_position == "") {
                is_valid = false;
                $("#spouse_position").addClass("is-invalid");
            }

            if (spouse_agency == "") {
                is_valid = false;
                $("#spouse_agency").addClass("is-invalid");
            }

            if (spouse_office == "") {
                is_valid = false;
                $("#spouse_address").addClass("is-invalid");
            }

            if (spouse_government_issued_id == "") {
                is_valid = false;
                $("#spouse_issued_id").addClass("is-invalid");
            }

             if (spouse_government_id_no == "") {
                is_valid = false;
                $("#spouse_id_no").addClass("is-invalid");
            }


            if (!is_valid) {
                 Swal.fire({
                    title: 'Incomplete Information',
                    text: 'Please provide the required spouse information highlighted in red.',
                    icon: 'warning',
                    confirmButtonColor: 'var(--saln-primary)'
                });
                return;
             }

            const params = {
                compliance_for: getComplianceDate(),
                spouse_fname: spouse_fname,
                spouse_mname: spouse_mname,
                spouse_lname: spouse_lname,
                spouse_position: spouse_position,
                spouse_agency: spouse_agency,
                spouse_office: spouse_office,
                spouse_government_id_issued: spouse_government_issued_id,
                spouse_government_id_no: spouse_government_id_no,
                spouse_date_issued: (spouse_date_issued == "") ? null : spouse_date_issued,
            }

             fetch('{{ route('updateSpouse') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(params)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }

                    Swal.fire({
                        title: 'Spouse Profile Updated',
                        text: 'Spouse information has been successfully saved.',
                        icon: 'success',
                        confirmButtonColor: 'var(--saln-primary)',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    getDeclarantSpouse();
                }).catch(err => {
                console.log(err);
            })
        });

        $("#btn_save_business_interest").click(function () {
            const business_enterprise = $("#business_enterprise").val().trim().toUpperCase();
            const business_address = $("#business_address").val().trim().toUpperCase();
            const nature = $("#business_nature").val().trim().toUpperCase();
            const date_acquisition = $("#business_date_acquisition").val().trim().toUpperCase();

            const params = {
                compliance_for: getComplianceDate(),
                id: business_interest_id,
                enterprise: business_enterprise,
                address: business_address,
                nature: nature,
                date_acquisition: date_acquisition
            }

            fetch('{{ route('insertBusinessInterest') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(params)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }

                    business_interest_modal.toggle();
                    toastr.success(data.message, "Information");
                    getBusinessInterest();
                }).catch(err => {
                console.log(err);
                toastr.error(err, 'Something went wrong');
            });
        });

        $("#btn_save_spouse_business_interest").click(function () {
            const business_enterprise = $("#spouse_business_enterprise").val().trim().toUpperCase();
            const business_address = $("#spouse_business_address").val().trim().toUpperCase();
            const nature = $("#spouse_business_nature").val().trim().toUpperCase();
            const date_acquisition = $("#spouse_business_date_acquisition").val().trim().toUpperCase();

            const params = {
                compliance_for: getComplianceDate(),
                id: spouse_business_interest_id,
                enterprise: business_enterprise,
                address: business_address,
                nature: nature,
                date_acquisition: date_acquisition
            }

            fetch('{{ route('insertSpouseBusinessInterest') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(params)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }

                    spouse_business_interest_modal.toggle();
                    toastr.success(data.message, "Information");
                    getSpouseBusinessInterest();
                }).catch(err => {
                console.log(err);
                toastr.error(err, 'Something went wrong');
            });
        });

        $("#btn_save_children").click(function () {
            const name = $("#children_name").val().toUpperCase().trim();
            const birthdate = $("#children_birthdate").val();

            if (name == "") {
                alert("Name is required");
                return;
            }
            if (birthdate == "") {
                alert("Birthdate is required");
                return;
            }

            const params = {
                compliance_for: getComplianceDate(),
                id: children_id,
                name: name,
                birthdate: birthdate
            }

            fetch('{{ route('insertSalnChildren') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(params)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }

                    children_modal.toggle();
                    toastr.success(data.message, "Information");
                    getChildren();
                }).catch(err => {
                console.log(err);
            });
        });

        $("#btn_save_real").click(function () {
            const description = $("#real_description").val().toUpperCase().trim();
            const kind = $("#real_kind").val().toUpperCase().trim();
            const exact_location = $("#real_location").val().toUpperCase().trim();
            const assessed_value = $('#real_assessed_value').val().toUpperCase().trim();
            const current_fair = $("#real_current_fair").val().toUpperCase().trim();
            const acquisition_year = $("#real_acquisition_year").val().toUpperCase().trim();
            const acquisition_mode = $("#real_acquisition_mode").val().toUpperCase().trim();
            const acquisition_cost = $("#real_acquisition_cost").val().toUpperCase().trim();

            if (description == "") {
                alert("Description is required");
                return;
            }

            if (kind == "") {
                alert("Kind is required");
                return;
            }

            if (exact_location == "") {
                alert("Exact location is required");
                return;
            }

            if (assessed_value == "") {
                alert("Assessed value is required");
                return;
            }

            if (current_fair == "") {
                alert("Current fair is required");
                return;
            }

            if (acquisition_year == "") {
                alert("Acquisition year is required");
                return;
            }

            if (acquisition_mode == "") {
                alert("Acquisition mode is required");
                return;
            }

            if (acquisition_cost == "") {
                alert("Acquisition cost is required");
                return;
            }

            const params = {
                compliance_for: getComplianceDate(),
                id: real_properties_id,
                description: description,
                kind: kind,
                exact_location: exact_location,
                assessed_value: assessed_value,
                current_fair: current_fair,
                acquisition_year: acquisition_year,
                acquisition_mode: acquisition_mode,
                acquisition_cost: acquisition_cost
            }

            fetch('{{ route('insertRealProperties') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(params)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }

                    real_properties_modal.toggle();
                    toastr.success(data.message, "Information");
                    getRealProperties();
                }).catch(err => {
                console.log(err);
            })

        });

        $("#btn_save_spouse_real").click(function () {
            const description = $("#spouse_real_description").val().toUpperCase().trim();
            const kind = $("#spouse_real_kind").val().toUpperCase().trim();
            const exact_location = $("#spouse_real_location").val().toUpperCase().trim();
            const assessed_value = $('#spouse_real_assessed_value').val().toUpperCase().trim();
            const current_fair = $("#spouse_real_current_fair").val().toUpperCase().trim();
            const acquisition_year = $("#spouse_real_acquisition_year").val().toUpperCase().trim();
            const acquisition_mode = $("#spouse_real_acquisition_mode").val().toUpperCase().trim();
            const acquisition_cost = $("#spouse_real_acquisition_cost").val().toUpperCase().trim();

            if (description == "") {
                alert("Description is required");
                return;
            }

            if (kind == "") {
                alert("Kind is required");
                return;
            }

            if (exact_location == "") {
                alert("Exact location is required");
                return;
            }

            if (assessed_value == "") {
                alert("Assessed value is required");
                return;
            }

            if (current_fair == "") {
                alert("Current fair is required");
                return;
            }

            if (acquisition_year == "") {
                alert("Acquisition year is required");
                return;
            }

            if (acquisition_mode == "") {
                alert("Acquisition mode is required");
                return;
            }

            if (acquisition_cost == "") {
                alert("Acquisition cost is required");
                return;
            }

            const params = {
                compliance_for: getComplianceDate(),
                id: spouse_real_properties_id,
                description: description,
                kind: kind,
                exact_location: exact_location,
                assessed_value: assessed_value,
                current_fair: current_fair,
                acquisition_year: acquisition_year,
                acquisition_mode: acquisition_mode,
                acquisition_cost: acquisition_cost
            }

            fetch('{{ route('insertSpouseRealProperties') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(params)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }

                    spouse_real_properties_modal.toggle();
                    toastr.success(data.message, "Information");
                    getSpouseRealProperties();
                }).catch(err => {
                console.log(err);
            })

        });

        $("#btn_save_personal").click(function () {
            const gadget = $("#personal_gadget").val().trim().toUpperCase();
            const various_years = $("#personal_various_years").val().trim().toUpperCase();
            const acquisition_cost = $("#personal_acquisition_cost").val();

            if (gadget == "") {
                alert("Gadget/Appliances is required");
                return;
            }

            if (various_years == "") {
                alert("Various years is required");
                return;
            }

            if (acquisition_cost == "") {
                alert("Acquisition cost is required");
                return;
            }

            const params = {
                compliance_for: getComplianceDate(),
                id: personal_properties_id,
                gadget: gadget,
                various_years: various_years,
                acquisition_cost: acquisition_cost
            }

            fetch('{{ route('insertPersonalProperties') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(params)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }

                    personal_properties_modal.toggle();
                    toastr.success(data.message, "Information");
                    getPersonalProperties();
                }).catch(err => {
                console.log(err);
            });
        });

        $("#btn_save_spouse_personal").click(function () {
            const gadget = $("#spouse_personal_gadget").val().trim().toUpperCase();
            const various_years = $("#spouse_personal_various_years").val().trim().toUpperCase();
            const acquisition_cost = $("#spouse_personal_acquisition_cost").val();

            if (gadget == "") {
                alert("Gadget/Appliances is required");
                return;
            }

            if (various_years == "") {
                alert("Various years is required");
                return;
            }

            if (acquisition_cost == "") {
                alert("Acquisition cost is required");
                return;
            }

            const params = {
                compliance_for: getComplianceDate(),
                id: spouse_personal_properties_id,
                gadget: gadget,
                various_years: various_years,
                acquisition_cost: acquisition_cost
            }

            fetch('{{ route('insertSpousePersonalProperties') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(params)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }

                    spouse_personal_properties_modal.toggle();
                    toastr.success(data.message, "Information");
                    getSpousePersonalProperties();
                }).catch(err => {
                console.log(err);
            });
        });

        $("#btn_save_liabilities").click(function () {
            const nature = $("#liabilities_nature").val().trim().toUpperCase();
            const creditors_name = $("#liabilities_creditors_name").val().trim().toUpperCase();
            const outstanding_balance = $("#liabilities_outstanding_balance").val();

            if (nature == "") {
                alert("Nature is required");
                return;
            }

            if (creditors_name == "") {
                alert("Creditors name is required");
                return;
            }

            if (outstanding_balance == "") {
                alert("Outstanding balance is required");
                return;
            }

            const params = {
                compliance_for: getComplianceDate(),
                id: liabilities_id,
                nature: nature,
                creditors_name: creditors_name,
                outstanding_balance: outstanding_balance
            }

            fetch('{{ route('insertLiabilities') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(params)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }

                    liabilities_modal.toggle();
                    toastr.success(data.message, "Information");
                    getLiabilities();
                }).catch(err => {
                console.log(err);
            });
        });

        $("#btn_save_spouse_liabilities").click(function () {
            const nature = $("#spouse_liabilities_nature").val().trim().toUpperCase();
            const creditors_name = $("#spouse_liabilities_creditors_name").val().trim().toUpperCase();
            const outstanding_balance = $("#spouse_liabilities_outstanding_balance").val();

            if (nature == "") {
                alert("Nature is required");
                return;
            }

            if (creditors_name == "") {
                alert("Creditors name is required");
                return;
            }

            if (outstanding_balance == "") {
                alert("Outstanding balance is required");
                return;
            }

            const params = {
                compliance_for: getComplianceDate(),
                id: spouse_liabilities_id,
                nature: nature,
                creditors_name: creditors_name,
                outstanding_balance: outstanding_balance
            }

            fetch('{{ route('insertSpouseLiabilities') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(params)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }

                    spouse_liabilities_modal.toggle();
                    toastr.success(data.message, "Information");
                    getSpouseLiabilities();
                }).catch(err => {
                console.log(err);
            });
        });

        $("#btn_save_government_relative").click(function () {
            const name = $("#relative_name").val().trim().toUpperCase();
            const relationship = $("#relative_relationship").val().trim().toUpperCase();
            const position = $("#relative_position").val().trim().toUpperCase();
            const agency = $("#relative_agency").val().trim().toUpperCase();

            if (name == "") {
                alert("Relative name is required");
                return;
            }

            if (relationship == "") {
                alert("Relationship is required");
                return;
            }


            if (position == "") {
                alert("Position is required");
                return;
            }

            if (agency == "") {
                alert("Agency is required");
                return;
            }

            const params = {
                compliance_for: getComplianceDate(),
                id: government_relative_id,
                name: name,
                relationship: relationship,
                position: position,
                agency: agency
            }

            fetch('{{ route('insertGovernmentRelative') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(params)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }

                    government_relative_modal.toggle();
                    toastr.success(data.message, "Information");
                    getGovernmentRelative();
                }).catch(err => {
                console.log(err);
            });
        });

        $("#btn_save_marriage").click(function () {
            const spouse_name = $("#spouse_name").val().trim().toUpperCase();

            if (spouse_name == "") {
                alert("Name is required");
                return;
            }

            const params = {
                compliance_for: getComplianceDate(),
                id: marriages_id,
                spouse: spouse_name,
            }

            fetch('{{ route('insertMarriages') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(params)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }

                    marriages_modal.toggle();
                    toastr.success(data.message, "Information");
                    getMarriages();
                }).catch(err => {
                console.log(err);
            });
        });

        const year = getComplianceYear();
        reloadAllSalnSections(year);

        // Dynamic Footer Status Update
        $('input[name="compliance_for"], input[name="filing_type"]').on('change', function() {
            updateFooterStatusDisplay();
        });

        function updateFooterStatusDisplay() {
            const type = $('input[name="compliance_for"]:checked').val() || 'ANNUAL FILING';
            const filing = $('input[name="filing_type"]:checked').val() || 'NOT APPLICABLE';
            $("#footer_filing_mode_display").text(`${type.toUpperCase()} — ${filing.toUpperCase()}`);
        }
        
        // Initial call
        updateFooterStatusDisplay();

        // Finalize SALN Logic
        $("#btn_save_and_finalize").click(function() {
            const complianceDate = getComplianceDate();
            const complianceType = $('input[name="compliance_for"]:checked').val();
            const filingType = $('input[name="filing_type"]:checked').val();

            Swal.fire({
                title: 'Finalize SALN Declaration?',
                text: "This will save and synchronize all assets, liabilities, and personal information for the year " + getComplianceYear() + ". Ensure all sub-sections are saved before finalizing.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: 'var(--saln-primary)',
                cancelButtonColor: '#adb5bd',
                confirmButtonText: '<i class="bi bi-shield-check me-2"></i>Yes, Finalize Now',
                cancelButtonText: 'Review Again',
                reverseButtons: true,
                padding: '2rem',
                customClass: {
                    title: 'fw-800 text-dark',
                    confirmButton: 'btn btn-success px-4 py-2 rounded-3 fw-bold',
                    cancelButton: 'btn btn-light px-4 py-2 rounded-3 fw-bold'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const btn = $(this);
                    const originalHtml = btn.html();
                    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Processing...');

                    const params = {
                        compliance_for: complianceDate,
                        compliance_type: complianceType,
                        filing_type: filingType,
                        // Passing declarant and spouse fields as well to ensure they are synchronized in one go
                        declarant_position: $("#declarant_position").val(),
                        declarant_agency: $("#declarant_agency").val(),
                        declarant_office: $("#declarant_address").val(),
                        declarant_government_id_issued: $("#declarant_issued_id").val(),
                        declarant_government_id_no: $("#declarant_id_no").val(),
                        declarant_date_issued: $("#declarant_date_issued").val(),
                        
                        spouse_position: $("#spouse_position").val(),
                        spouse_agency: $("#spouse_agency").val(),
                        spouse_office: $("#spouse_address").val(),
                        spouse_government_id_issued: $("#spouse_issued_id").val(),
                        spouse_government_id_no: $("#spouse_id_no").val(),
                        spouse_date_issued: $("#spouse_date_issued").val(),
                        
                        spouse_fname: $("#spouse_fname").val(),
                        spouse_mname: $("#spouse_mname").val(),
                        spouse_lname: $("#spouse_lname").val()
                    };

                    fetch('{{ route('updateSalnCompliance') }}', {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        body: JSON.stringify(params)
                    })
                    .then(res => res.json())
                    .then(data => {
                        btn.prop('disabled', false).html(originalHtml);
                        if (data.code == 200) {
                            Swal.fire({
                                title: 'Declaration Saved!',
                                text: 'Your SALN for ' + getComplianceYear() + ' has been successfully synchronized.',
                                icon: 'success',
                                confirmButtonColor: 'var(--saln-primary)'
                            }).then(() => {
                                saln_form_modal.hide();
                                loadSalnRecords();
                            });
                        } else {
                            toastr.error(data.message || "Failed to finalize SALN", "Error");
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        btn.prop('disabled', false).html(originalHtml);
                        toastr.error("A network error occurred", "Error");
                    });
                }
            });
        });
    });

    function getDeclarant(year) {
        if (!year) year = getComplianceDate();
        return fetch('{{ route('getDeclarant') }}?compliance_for=' + year, {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    console.log(data.message)
                    return;
                }

                data.results.forEach(it => {
                    $("#declarant_fname").val(it.fname);
                    $("#declarant_mname").val(it.mname);
                    $("#declarant_lname").val(it.lname);
                    $("#declarant_position").val(it.position);
                    $("#declarant_agency").val(it.agency);
                    $("#declarant_address").val(it.office);
                    $("#declarant_issued_id").val(it.government_id_issued);
                    $("#declarant_id_no").val(it.government_id_no);
                    $("#declarant_date_issued").val(it.date_issued);
                });

            }).catch(err => {
            console.log(err);
        })
    }

    function getDeclarantSpouse(year) {
        if (!year) year = getComplianceDate();
        return fetch('{{ route('getDeclarantSpouse') }}?compliance_for=' + year, {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    console.log(data.message)
                    return;
                }

                data.results.forEach(it => {
                    $("#spouse_fname").val(it.spouse_fname);
                    $("#spouse_mname").val(it.spouse_mname);
                    $("#spouse_lname").val(it.spouse_lname);
                    $("#spouse_position").val(it.position);
                    $("#spouse_agency").val(it.agency);
                    $("#spouse_address").val(it.office);
                    $("#spouse_issued_id").val(it.government_id_issued);
                    $("#spouse_id_no").val(it.government_id_no);
                    $("#spouse_date_issued").val(it.date_issued);
                });

            }).catch(err => {
            console.log(err);
        })
    }

    function getChildren(year) {
        if (!year) year = getComplianceDate();
        return fetch('{{ route('getChildren') }}?compliance_for=' + year, {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    console.log()
                    return;
                }

                $("#children_list").empty();
                data.results.forEach(it => {
                    const tr = `
                   <tr id='children_` + it.id + `'>
                       <td>` + it.name + `</td>
                       <td>` + moment(it.birthdate).format("MM/DD/YYYY") + `</td>
                       <td>
                            <button class="btn btn-sm btn-success me-1" onClick='editChildren(` + it.id + `)'>
                                <span class="bi bi-pencil"></span>
                            </button>
                            <button class="btn btn-sm btn-danger ms-1" onClick='deleteChildren(` + it.id + `)'>
                                <span class="bi bi-trash"></span>
                            </button>
                       </td>
                   </tr>`;

                    $("#children_list").append(tr);
                });
            }).catch(err => {
            console.log(err);
        })
    }

    function getRealProperties(year) {
        if (!year) year = getComplianceDate();
        return fetch('{{ route('getRealProperties') }}?compliance_for=' + year, {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    console.log()
                    return;
                }

                $("#real_properties_list").empty();
                data.results.forEach(it => {
                    const tr = `
                   <tr id='real_properties_` + it.id + `'>
                       <td>` + it.description + `</td>
                       <td>` + it.kind + `</td>
                       <td>` + it.exact_location + `</td>
                       <td>` + it.assessed_value + `</td>
                       <td>` + it.current_fair + `</td>
                       <td>` + it.acquisition_year + `</td>
                       <td>` + it.acquisition_mode + `</td>
                       <td>` + it.acquisition_cost + `</td>
                       <td>
                            <button class="btn btn-sm btn-success me-1" onClick='editRealProperties(` + it.id + `)'>
                                <span class="bi bi-pencil"></span>
                            </button>
                            <button class="btn btn-sm btn-danger ms-1" onClick='deleteRealProperties(` + it.id + `)'>
                                <span class="bi bi-trash"></span>
                            </button>
                       </td>
                   </tr>`;

                    $("#real_properties_list").append(tr);
                });
            }).catch(err => {
            console.log(err);
        })
    }

    function getPersonalProperties(year) {
        if (!year) year = getComplianceDate();
        return fetch('{{ route('getPersonalProperties') }}?compliance_for=' + year, {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    console.log()
                    return;
                }

                $("#personal_properties_list").empty();
                data.results.forEach(it => {

                    const tr = `
                   <tr id='personal_properties_` + it.id + `'>
                       <td>` + it.gadget + `</td>
                       <td>` + it.various_years + `</td>
                       <td>` + it.acquisition_cost + `</td>
                       <td>
                            <button class="btn btn-sm btn-success me-1" onClick='editPersonalProperties(` + it.id + `)'>
                                <span class="bi bi-pencil"></span>
                            </button>
                            <button class="btn btn-sm btn-danger ms-1" onClick='deletePersonalProperties(` + it.id + `)'>
                                <span class="bi bi-trash"></span>
                            </button>
                       </td>
                   </tr>`;

                    $("#personal_properties_list").append(tr);
                });
            }).catch(err => {
            console.log(err);
        })
    }

    function getLiabilities(year) {
        if (!year) year = getComplianceDate();
        return fetch('{{ route('getLiabilities') }}?compliance_for=' + year, {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    console.log()
                    return;
                    7
                }

                $("#liabilities_list").empty();
                data.results.forEach(it => {
                    const tr = `
                   <tr id='liabilities_` + it.id + `'>
                       <td>` + it.nature + `</td>
                       <td>` + it.creditors_name + `</td>
                       <td>` + it.outstanding_balance + `</td>
                       <td>
                            <button class="btn btn-sm btn-success me-1" onClick='editLiabilities(` + it.id + `)'>
                                <span class="bi bi-pencil"></span>
                            </button>
                            <button class="btn btn-sm btn-danger ms-1" onClick='deleteLiabilities(` + it.id + `)'>
                                <span class="bi bi-trash"></span>
                            </button>
                       </td>
                   </tr>`;

                    $("#liabilities_list").append(tr);
                });
            }).catch(err => {
            console.log(err);
        })
    }

    function getBusinessInterest(year) {
        if (!year) year = getComplianceDate();
        return fetch('{{ route('getBusinessInterest') }}?compliance_for=' + year, {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    console.log(data.message);
                    return;
                }

                $("#business_interest_list").empty();
                data.results.forEach(it => {
                    const tr = `
                   <tr id='business_interest_` + it.id + `'>
                       <td>` + it.enterprise + `</td>
                       <td>` + it.address + `</td>
                       <td>` + it.nature + `</td>
                       <td>` + moment(it.date_acquisition).format("MM/DD/YYYY") + `</td>
                       <td>
                            <button class="btn btn-sm btn-success me-1" onClick='editBusinessInterest(` + it.id + `)'>
                                <span class="bi bi-pencil"></span>
                            </button>
                            <button class="btn btn-sm btn-danger ms-1" onClick='deleteBusinessInterest(` + it.id + `)'>
                                <span class="bi bi-trash"></span>
                            </button>
                       </td>
                   </tr>`;

                    $("#business_interest_list").append(tr);
                });
            }).catch(err => {
            console.log(err);
        })
    }

    function getGovernmentRelative(year) {
        if (!year) year = getComplianceDate();
        return fetch('{{ route('getGovernmentRelative') }}?compliance_for=' + year, {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    console.log(data.message);
                    return;
                }

                $("#government_relative_list").empty();
                data.results.forEach(it => {
                    const tr = `
                   <tr id='government_` + it.id + `'>
                       <td>` + it.name + `</td>
                       <td>` + it.relationship + `</td>
                       <td>` + it.position + `</td>
                       <td>` + it.agency + `</td>
                       <td>
                            <button class="btn btn-sm btn-success me-1" onClick='editGovernmentRelative(` + it.id + `)'>
                                <span class="bi bi-pencil"></span>
                            </button>
                            <button class="btn btn-sm btn-danger ms-1" onClick='deleteGovernmentRelative(` + it.id + `)'>
                                <span class="bi bi-trash"></span>
                            </button>
                       </td>
                   </tr>`;

                    $("#government_relative_list").append(tr);
                });
            }).catch(err => {
            console.log(err);
        })
    }

    function getMarriages(year) {
        if (!year) year = getComplianceDate();
        return fetch('{{ route('getMarriages') }}?compliance_for=' + year, {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    console.log(data.message);
                    return;
                }

                $("#marriages_list").empty();
                data.results.forEach(it => {
                    const tr = `
                   <tr id='marriage_` + it.id + `'>
                       <td>` + it.spouse + `</td>
                       <td>
                            <button class="btn btn-sm btn-success me-1" onClick='editMarriage(` + it.id + `)'>
                                <span class="bi bi-pencil"></span>
                            </button>
                            <button class="btn btn-sm btn-danger ms-1" onClick='deleteMarriage(` + it.id + `)'>
                                <span class="bi bi-trash"></span>
                            </button>
                       </td>
                   </tr>`;

                    $("#marriages_list").append(tr);
                });
            }).catch(err => {
            console.log(err);
        })
    }

    function deleteChildren(id) {
        Swal.fire({
            title: 'Delete Child Record?',
            text: "Are you sure you want to remove this child's information?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#adb5bd',
            confirmButtonText: 'Yes, Delete',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('{{ route('deleteSalnChildren') }}', {
                    method: "DELETE",
                    headers: {
                        "Content-Type": "application/json",
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify({id: id})
                })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }
                    toastr.success(data.message, "Deleted");
                    getChildren();
                }).catch(err => {
                    console.log(err);
                })
            }
        });
    }

    function deleteRealProperties(id) {
        Swal.fire({
            title: 'Remove Asset?',
            text: "Are you sure you want to delete this real property entry?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#adb5bd',
            confirmButtonText: 'Yes, Delete',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('{{ route('deleteRealProperties') }}', {
                    method: "DELETE",
                    headers: {
                        "Content-Type": "application/json",
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify({id: id})
                })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }
                    toastr.success(data.message, "Removed");
                    getRealProperties();
                }).catch(err => {
                    console.log(err);
                })
            }
        });
    }

    function deletePersonalProperties(id) {
        Swal.fire({
            title: 'Remove Personal Property?',
            text: "Are you sure you want to delete this entry?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#adb5bd',
            confirmButtonText: 'Yes, Delete',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('{{ route('deletePersonalProperties') }}', {
                    method: "DELETE",
                    headers: {
                        "Content-Type": "application/json",
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify({id: id})
                })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }
                    toastr.success(data.message, "Removed");
                    getPersonalProperties();
                }).catch(err => {
                    console.log(err);
                })
            }
        });
    }

    function deleteLiabilities(id) {
        Swal.fire({
            title: 'Remove Liability?',
            text: "Are you sure you want to delete this liability record?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#adb5bd',
            confirmButtonText: 'Yes, Delete',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('{{ route('deleteLiabilities') }}', {
                    method: "DELETE",
                    headers: {
                        "Content-Type": "application/json",
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify({id: id})
                })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }
                    toastr.success(data.message, "Removed");
                    getLiabilities();
                }).catch(err => {
                    console.log(err);
                })
            }
        });
    }

    function deleteBusinessInterest(id) {
        if (confirm("Are you sure you want to delete this record?")) {
            fetch('{{ route('deleteBusinessInterest') }}', {
                method: "DELETE",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({id: id})
            })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }

                    toastr.success(data.message, "Information");
                    getBusinessInterest();
                }).catch(err => {
                console.log(err);
            })
        }
    }

    function deleteGovernmentRelative(id) {
        if (confirm("Are you sure you want to delete this record?")) {
            fetch('{{ route('deleteGovernmentRelative') }}', {
                method: "DELETE",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({id: id})
            })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }

                    toastr.success(data.message, "Information");
                    getGovernmentRelative();
                }).catch(err => {
                console.log(err);
            })
        }
    }

    function deleteMarriage(id) {
        if (confirm("Are you sure you want to delete this record?")) {
            fetch('{{ route('deleteMarriage') }}', {
                method: "DELETE",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({id: id})
            })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }

                    toastr.success(data.message, "Information");
                    getMarriages();
                }).catch(err => {
                console.log(err);
            })
        }
    }

    function editChildren(id) {
        const name = $("#children_" + id + " td:eq(0)").text();
        const birthdate = $("#children_" + id + " td:eq(1)").text();

        children_id = id;
        $("#children_name").val(name);
        $("#children_birthdate").val(moment(birthdate, "MM/DD/YYYY").format("YYYY-MM-DD"));
        children_modal.toggle();
    }

    function editRealProperties(id) {
        is_spouse_real_properties = false;
        const description = $("#real_properties_" + id + " td:eq(0)").text();
        const kind = $("#real_properties_" + id + " td:eq(1)").text();
        const exact_location = $("#real_properties_" + id + " td:eq(2)").text();
        const assessed_value = $("#real_properties_" + id + " td:eq(3)").text();
        const current_fair = $("#real_properties_" + id + " td:eq(4)").text();
        const acquisition_year = $("#real_properties_" + id + " td:eq(5)").text();
        const acquisition_mode = $("#real_properties_" + id + " td:eq(6)").text();
        const acquisition_cost = $("#real_properties_" + id + " td:eq(7)").text();

        real_properties_id = id;
        $("#real_description").val(description);
        $("#real_kind").val(kind);
        $("#real_location").val(exact_location);
        $("#real_assessed_value").val(assessed_value);
        $("#real_current_fair").val(current_fair);
        $("#real_acquisition_year").val(acquisition_year);
        $("#real_acquisition_mode").val(acquisition_mode);
        $("#real_acquisition_cost").val(acquisition_cost);
        real_properties_modal.toggle();
    }

    function editPersonalProperties(id) {
        is_spouse_personal_properties = false;
        const gadget = $("#personal_properties_" + id + " td:eq(0)").text();
        const various_years = $("#personal_properties_" + id + " td:eq(1)").text();
        const acquisition_cost = $("#personal_properties_" + id + " td:eq(2)").text();

        personal_properties_id = id;
        $("#personal_gadget").val(gadget);
        $("#personal_various_years").val(various_years);
        $("#personal_acquisition_cost").val(acquisition_cost);
        personal_properties_modal.toggle();
    }

    function editLiabilities(id) {
        is_spouse_liabilities = false;
        const nature = $("#liabilities_" + id + " td:eq(0)").text();
        const creditors_name = $("#liabilities_" + id + " td:eq(1)").text();
        const oustanding_balance = $("#liabilities_" + id + " td:eq(2)").text();

        liabilities_id = id;
        $("#liabilities_nature").val(nature);
        $("#liabilities_creditors_name").val(creditors_name);
        $("#liabilities_outstanding_balance").val(oustanding_balance);
        liabilities_modal.toggle();
    }

    function editBusinessInterest(id) {
        is_spouse_business_interest = false;
        const enterprise = $("#business_interest_" + id + " td:eq(0)").text();
        const address = $("#business_interest_" + id + " td:eq(1)").text();
        const nature = $("#business_interest_" + id + " td:eq(2)").text();
        const date_acquisition = $("#business_interest_" + id + " td:eq(3)").text();

        business_interest_id = id;
        $("#business_enterprise").val(enterprise);
        $("#business_address").val(address);
        $("#business_nature").val(nature);
        $("#business_date_acquisition").val(moment(date_acquisition).format("YYYY-MM-DD"));
        business_interest_modal.toggle();
    }

    function editGovernmentRelative(id) {
        const name = $("#government_" + id + " td:eq(0)").text();
        const relationship = $("#government_" + id + " td:eq(1)").text();
        const position = $("#government_" + id + " td:eq(2)").text();
        const agency = $("#government_" + id + " td:eq(3)").text();

        government_relative_id = id;
        $("#relative_name").val(name);
        $("#relative_relationship").val(relationship);
        $("#relative_position").val(position);
        $("#relative_agency").val(agency);
        government_relative_modal.toggle();
    }

    function editMarriage(id) {
        const spouse = $("#marriage_" + id + " td:eq(0)").text();
        marriages_id = id;
        $("#spouse_name").val(spouse);
        marriages_modal.toggle();
    }
    // Redundant spouse functions removed

    function deleteSpouseRealProperties(id) {
        if (confirm("Are you sure you want to delete this record?")) {
            fetch('{{ route('deleteSpouseRealProperties') }}', {
                method: "DELETE",
                headers: { "Content-Type": "application/json", 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                body: JSON.stringify({id: id})
            }).then(res => res.json()).then(data => {
                if (data.code == 500) { toastr.error(data.message, "Ooops"); return; }
                toastr.success(data.message, "Information");
                getSpouseRealProperties();
            }).catch(e => console.log(e));
        }
    }

    function deleteSpousePersonalProperties(id) {
        if (confirm("Are you sure you want to delete this record?")) {
            fetch('{{ route('deleteSpousePersonalProperties') }}', {
                method: "DELETE",
                headers: { "Content-Type": "application/json", 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                body: JSON.stringify({id: id})
            }).then(res => res.json()).then(data => {
                if (data.code == 500) { toastr.error(data.message, "Ooops"); return; }
                toastr.success(data.message, "Information");
                getSpousePersonalProperties();
            }).catch(e => console.log(e));
        }
    }

    function deleteSpouseLiabilities(id) {
        if (confirm("Are you sure you want to delete this record?")) {
            fetch('{{ route('deleteSpouseLiabilities') }}', {
                method: "DELETE",
                headers: { "Content-Type": "application/json", 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                body: JSON.stringify({id: id})
            }).then(res => res.json()).then(data => {
                if (data.code == 500) { toastr.error(data.message, "Ooops"); return; }
                toastr.success(data.message, "Information");
                getSpouseLiabilities();
            }).catch(e => console.log(e));
        }
    }

    function deleteSpouseBusinessInterest(id) {
        if (confirm("Are you sure you want to delete this record?")) {
            fetch('{{ route('deleteSpouseBusinessInterest') }}', {
                method: "DELETE",
                headers: { "Content-Type": "application/json", 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                body: JSON.stringify({id: id})
            }).then(res => res.json()).then(data => {
                if (data.code == 500) { toastr.error(data.message, "Ooops"); return; }
                toastr.success(data.message, "Information");
                getSpouseBusinessInterest();
            }).catch(e => console.log(e));
        }
    }

    function editSpouseRealProperties(id) {
        const description = $("#spouse_real_properties_" + id + " td:eq(0)").text();
        const kind = $("#spouse_real_properties_" + id + " td:eq(1)").text();
        const exact_location = $("#spouse_real_properties_" + id + " td:eq(2)").text();
        const assessed_value = $("#spouse_real_properties_" + id + " td:eq(3)").text();
        const current_fair = $("#spouse_real_properties_" + id + " td:eq(4)").text();
        const acquisition_year = $("#spouse_real_properties_" + id + " td:eq(5)").text();
        const acquisition_mode = $("#spouse_real_properties_" + id + " td:eq(6)").text();
        const acquisition_cost = $("#spouse_real_properties_" + id + " td:eq(7)").text();

        spouse_real_properties_id = id;
        $("#spouse_real_description").val(description);
        $("#spouse_real_kind").val(kind);
        $("#spouse_real_location").val(exact_location);
        $("#spouse_real_assessed_value").val(assessed_value);
        $("#spouse_real_current_fair").val(current_fair);
        $("#spouse_real_acquisition_year").val(acquisition_year);
        $("#spouse_real_acquisition_mode").val(acquisition_mode);
        $("#spouse_real_acquisition_cost").val(acquisition_cost);
        spouse_real_properties_modal.toggle();
    }

    function editSpousePersonalProperties(id) {
        const gadget = $("#spouse_personal_properties_" + id + " td:eq(0)").text();
        const various_years = $("#spouse_personal_properties_" + id + " td:eq(1)").text();
        const acquisition_cost = $("#spouse_personal_properties_" + id + " td:eq(2)").text();

        spouse_personal_properties_id = id;
        $("#spouse_personal_gadget").val(gadget);
        $("#spouse_personal_various_years").val(various_years);
        $("#spouse_personal_acquisition_cost").val(acquisition_cost);
        spouse_personal_properties_modal.toggle();
    }

    function editSpouseLiabilities(id) {
        const nature = $("#spouse_liabilities_" + id + " td:eq(0)").text();
        const creditors_name = $("#spouse_liabilities_" + id + " td:eq(1)").text();
        const oustanding_balance = $("#spouse_liabilities_" + id + " td:eq(2)").text();

        spouse_liabilities_id = id;
        $("#spouse_liabilities_nature").val(nature);
        $("#spouse_liabilities_creditors_name").val(creditors_name);
        $("#spouse_liabilities_outstanding_balance").val(oustanding_balance);
        spouse_liabilities_modal.toggle();
    }

    function editSpouseBusinessInterest(id) {
        const enterprise = $("#spouse_business_interest_" + id + " td:eq(0)").text();
        const address = $("#spouse_business_interest_" + id + " td:eq(1)").text();
        const nature = $("#spouse_business_interest_" + id + " td:eq(2)").text();
        const date_acquisition = $("#spouse_business_interest_" + id + " td:eq(3)").text();

        spouse_business_interest_id = id;
        $("#spouse_business_enterprise").val(enterprise);
        $("#spouse_business_address").val(address);
        $("#spouse_business_nature").val(nature);
        $("#spouse_business_date_acquisition").val(moment(date_acquisition).format("YYYY-MM-DD"));
        spouse_business_interest_modal.toggle();
    }
    // ==================== SALN HISTORICAL LIST FUNCTIONS ====================

    function loadSalnRecords() {
        const filterYear = $("#filter_compliance_year").val();
        const container = $("#saln_records_container");
        const emptyState = $("#saln_empty_state");

        fetch('{{ route('getSalnYears') }}', { method: "GET" })
            .then(res => res.json())
            .then(data => {
                container.empty();

                if (data.code == 500 || !data.results || data.results.length === 0) {
                    container.append(`
                        <div class="empty-state" id="saln_empty_state">
                            <i class="bi bi-archive d-block"></i>
                            <h5>No SALN Records Found</h5>
                            <p>Click "Create New SALN" to start your first declaration.</p>
                        </div>
                    `);
                    $("#saln_count").text("0");
                    return;
                }

                let years = data.results;

                // Apply filter
                if (filterYear && filterYear !== "") {
                    years = years.filter(y => y.year == filterYear);
                }

                if (years.length === 0) {
                    container.append(`
                        <div class="empty-state">
                            <i class="bi bi-search d-block"></i>
                            <h5>No Records for ${filterYear}</h5>
                            <p>No SALN submissions found for the selected year.</p>
                        </div>
                    `);
                    $("#saln_count").text("0");
                    return;
                }

                $("#saln_count").text(years.length);

                years.forEach(record => {
                    const year = record.year || 'N/A';
                    const complianceDate = record.compliance_for || '';
                    const formattedDate = complianceDate ? new Date(complianceDate).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
                    const filingType = record.filing_type || 'Not Applicable';
                    const complianceType = record.compliance_type || 'Annual Filing';
                    const totalAssets = record.total_assets ? parseFloat(record.total_assets).toLocaleString('en-PH', {minimumFractionDigits: 2}) : '0.00';
                    const totalLiabilities = record.total_liabilities ? parseFloat(record.total_liabilities).toLocaleString('en-PH', {minimumFractionDigits: 2}) : '0.00';
                    const netWorth = record.net_worth ? parseFloat(record.net_worth).toLocaleString('en-PH', {minimumFractionDigits: 2}) : '0.00';

                    let filingBadgeClass = 'na';
                    if (filingType === 'Joint Filing') filingBadgeClass = 'joint';
                    else if (filingType === 'Separate Filing') filingBadgeClass = 'separate';

                    const card = `
                    <div class="saln-record-card">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="saln-year-badge">${year}</div>
                            </div>
                            <div class="col">
                                <div class="row gx-4">
                                    <div class="col-md-3">
                                        <div class="saln-info-label">Compliance Date</div>
                                        <div class="saln-info-value">${formattedDate}</div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="saln-info-label">Filing Mode</div>
                                        <div class="mt-1"><span class="filing-badge ${filingBadgeClass}">${filingType}</span></div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="saln-info-label">Total Assets</div>
                                        <div class="saln-info-value text-dark">₱${totalAssets}</div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="saln-info-label">Liabilities</div>
                                        <div class="saln-info-value text-danger">₱${totalLiabilities}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="saln-info-label text-success">Net Worth</div>
                                        <div class="saln-networth-value">₱${netWorth}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="d-flex gap-2">
                                    <button class="btn btn-action btn-edit" onclick="openSalnForEditing('${complianceDate}')" title="Edit Record">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button class="btn btn-action btn-delete" onclick="deleteSalnYear('${complianceDate}')" title="Delete Record">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>`;
                    container.append(card);
                });
            }).catch(err => {
                console.log(err);
                container.empty();
                container.append(`
                    <div class="empty-state">
                        <i class="bi bi-exclamation-triangle d-block"></i>
                        <h5>Error Loading Records</h5>
                        <p>Could not load SALN records. Please try refreshing the page.</p>
                    </div>
                `);
            });
    }

    function openSalnForEditing(complianceDate) {
        $("#salnFormModalLabel").html('<i class="bi bi-file-earmark-text me-2"></i>SALN — Edit Statement');
        saln_form_modal.show();
        $("#saln_loader_overlay").show();

        // Fetch full SALN data for this specific date to populate the As Of date fields
        fetch('{{ route('getSalnByYear') }}?compliance_for=' + complianceDate, { method: "GET" })
            .then(res => res.json())
            .then(data => {
                if (data.code == 200 && data.results && data.results.declarant) {
                    const compDate = data.results.declarant.compliance_for;
                    if (compDate) {
                        const dt = new Date(compDate);
                        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                            'July', 'August', 'September', 'October', 'November', 'December'];
                        $("#as_of_month").val(monthNames[dt.getMonth()]);
                        $("#as_of_day").val(dt.getDate());
                        $("#as_of_year").val(dt.getFullYear());
                    }
                    if (data.results.declarant.filing_type) {
                        $("input[name='filing_type'][value='" + data.results.declarant.filing_type + "']").prop('checked', true);
                    }
                    if (data.results.declarant.compliance_type) {
                        $("input[name='compliance_for'][value='" + data.results.declarant.compliance_type + "']").prop('checked', true);
                    }
                    
                    // After setting the as_of date fields, reload all dependent collections
                    reloadAllSalnSections(complianceDate).finally(() => {
                        $("#saln_loader_overlay").hide();
                    });
                } else {
                    $("#saln_loader_overlay").hide();
                }
            })
            .catch(() => {
                $("#saln_loader_overlay").hide();
            });
    }

    function reloadAllSalnSections(dateOrYear, skipDeclarant = false) {
        const promises = [];
        if (!skipDeclarant) {
            promises.push(getDeclarant(dateOrYear));
            promises.push(getDeclarantSpouse(dateOrYear));
        }
        promises.push(getChildren(dateOrYear));
        promises.push(getRealProperties(dateOrYear));
        promises.push(getPersonalProperties(dateOrYear));
        promises.push(getLiabilities(dateOrYear));
        promises.push(getBusinessInterest(dateOrYear));
        promises.push(getGovernmentRelative(dateOrYear));
        promises.push(getMarriages(dateOrYear));
        promises.push(getSpouseRealProperties(dateOrYear));
        promises.push(getSpousePersonalProperties(dateOrYear));
        promises.push(getSpouseLiabilities(dateOrYear));
        promises.push(getSpouseBusinessInterest(dateOrYear));
        
        return Promise.all(promises);
    }

    function deleteSalnYear(complianceDate) {
        if (confirm("Are you sure you want to delete this SALN record? This action cannot be undone.")) {
            fetch('{{ route('deleteSalnRecord') }}', {
                method: "DELETE",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({ compliance_for: complianceDate })
            })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    toastr.error(data.message, "Ooops");
                    return;
                }
                toastr.success("SALN record for year " + year + " has been deleted.", "Deleted");
                loadSalnRecords();
            }).catch(err => {
                console.log(err);
                toastr.error("Failed to delete SALN record", "Error");
            });
        }
    }

    function getSpouseRealProperties(year) {
        if (!year) year = getComplianceDate();
        return fetch('{{ route('getSpouseRealProperties') }}?compliance_for=' + year, { method: "GET" })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) return;
                $("#spouse_real_properties_list").empty();
                data.results.forEach(it => {
                    $("#spouse_real_properties_list").append(`
                        <tr id='spouse_real_properties_${it.id}'>
                            <td>${it.description}</td>
                            <td>${it.kind}</td>
                            <td>${it.exact_location}</td>
                            <td>${it.assessed_value}</td>
                            <td>${it.current_fair}</td>
                            <td>${it.acquisition_year}</td>
                            <td>${it.acquisition_mode}</td>
                            <td>${it.acquisition_cost}</td>
                            <td>
                                <button class="btn btn-sm btn-success me-1" onClick='editSpouseRealProperties(${it.id})'><span class="bi bi-pencil"></span></button>
                                <button class="btn btn-sm btn-danger ms-1" onClick='deleteSpouseRealProperties(${it.id})'><span class="bi bi-trash"></span></button>
                            </td>
                        </tr>`);
                });
            });
    }

    function getSpousePersonalProperties(year) {
        if (!year) year = getComplianceDate();
        return fetch('{{ route('getSpousePersonalProperties') }}?compliance_for=' + year, { method: "GET" })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) return;
                $("#spouse_personal_properties_list").empty();
                data.results.forEach(it => {
                    $("#spouse_personal_properties_list").append(`
                        <tr id='spouse_personal_properties_${it.id}'>
                            <td>${it.gadget}</td>
                            <td>${it.various_years}</td>
                            <td>${it.acquisition_cost}</td>
                            <td>
                                <button class="btn btn-sm btn-success me-1" onClick='editSpousePersonalProperties(${it.id})'><span class="bi bi-pencil"></span></button>
                                <button class="btn btn-sm btn-danger ms-1" onClick='deleteSpousePersonalProperties(${it.id})'><span class="bi bi-trash"></span></button>
                            </td>
                        </tr>`);
                });
            });
    }

    function getSpouseLiabilities(year) {
        if (!year) year = getComplianceDate();
        return fetch('{{ route('getSpouseLiabilities') }}?compliance_for=' + year, { method: "GET" })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) return;
                $("#spouse_liabilities_list").empty();
                data.results.forEach(it => {
                    $("#spouse_liabilities_list").append(`
                        <tr id='spouse_liabilities_${it.id}'>
                            <td>${it.nature}</td>
                            <td>${it.creditors_name}</td>
                            <td>${it.outstanding_balance}</td>
                            <td>
                                <button class="btn btn-sm btn-success me-1" onClick='editSpouseLiabilities(${it.id})'><span class="bi bi-pencil"></span></button>
                                <button class="btn btn-sm btn-danger ms-1" onClick='deleteSpouseLiabilities(${it.id})'><span class="bi bi-trash"></span></button>
                            </td>
                        </tr>`);
                });
            });
    }

    function getSpouseBusinessInterest(year) {
        if (!year) year = getComplianceDate();
        return fetch('{{ route('getSpouseBusinessInterest') }}?compliance_for=' + year, { method: "GET" })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) return;
                $("#spouse_business_interest_list").empty();
                data.results.forEach(it => {
                    $("#spouse_business_interest_list").append(`
                        <tr id='spouse_business_interest_${it.id}'>
                            <td>${it.enterprise}</td>
                            <td>${it.address}</td>
                            <td>${it.nature}</td>
                            <td>${moment(it.date_acquisition).format("MM/DD/YYYY")}</td>
                            <td>
                                <button class="btn btn-sm btn-success me-1" onClick='editSpouseBusinessInterest(${it.id})'><span class="bi bi-pencil"></span></button>
                                <button class="btn btn-sm btn-danger ms-1" onClick='deleteSpouseBusinessInterest(${it.id})'><span class="bi bi-trash"></span></button>
                            </td>
                        </tr>`);
                });
            });
    }
</script>
