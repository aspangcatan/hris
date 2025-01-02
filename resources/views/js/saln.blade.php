<script>
    const children_modal = new bootstrap.Modal(document.getElementById('children_modal'));
    const real_properties_modal = new bootstrap.Modal(document.getElementById('real_properties_modal'));
    const personal_properties_modal = new bootstrap.Modal(document.getElementById('personal_properties_modal'));
    const liabilities_modal = new bootstrap.Modal(document.getElementById('liabilities_modal'));
    const business_interest_modal = new bootstrap.Modal(document.getElementById('business_interest_modal'));
    const government_relative_modal = new bootstrap.Modal(document.getElementById('government_relative_modal'));
    const saln_modal = new bootstrap.Modal(document.getElementById('saln_modal'));

    let children_id = 0;
    let real_properties_id = 0;
    let personal_properties_id = 0;
    let liabilities_id = 0;
    let business_interest_id = 0;
    let government_relative_id = 0;

    $(document).ready(function () {

        $("#btn_print_saln").click(function () {
            $("#print_date").val(moment().format("YYYY-MM-DD"));
            saln_modal.toggle();
        });

        $("#btn_confirm_print").click(function () {
            const filing_type = $("input[name='filing_type']:checked").val();
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
                '&business_interest=' + business_interest_checkbox +
                '&government_relatives=' + government_relatives_checkbox +
                '&as_of_year=' + as_of_year +
                '&before_me=' + before_me +
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

        $("#btn_add_personal_properties").click(function () {
            personal_properties_id = 0;
            $("#personal_gadget").val("");
            $("#personal_various_years").val("");
            $("#personal_acquisition_cost").val("");
            personal_properties_modal.toggle();
        });

        $("#btn_add_liabilities").click(function () {
            liabilities_id = 0;
            $("#liabilities_nature").val("");
            $("#liabilities_creditors_name").val("");
            $("#liabilities_outstanding_balance").val("");
            liabilities_modal.toggle();
        });

        $("#btn_add_business_interest").click(function () {
            business_interest_id = 0;
            $("#business_enterprise").val("");
            $("#business_address").val("");
            $("#business_nature").val("");
            $("#business_date_acquisition").val("");
            business_interest_modal.toggle();
        });

        $("#btn_add_government_relative").click(function () {
            government_relative_id = 0;
            $("#relative_name").val("");
            $("#relative_relationship").val("");
            $("#relative_position").val("");
            $("#relative_agency").val("");
            government_relative_modal.toggle();
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


            const spouse_fname = $("#spouse_fname").val().trim().toUpperCase();
            const spouse_mname = $("#spouse_mname").val().trim().toUpperCase();
            const spouse_lname = $("#spouse_lname").val().trim().toUpperCase();
            const spouse_position = $("#spouse_position").val().trim().toUpperCase();
            const spouse_agency = $("#spouse_agency").val().trim().toUpperCase();
            const spouse_office = $("#spouse_address").val().trim().toUpperCase();
            const spouse_government_issued_id = $("#spouse_issued_id").val().trim().toUpperCase();
            const spouse_government_id_no = $("#spouse_id_no").val().trim().toUpperCase();
            const spouse_date_issued = $("#spouse_date_issued").val().trim().toUpperCase();

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
                alert("Please fill in required fields");
                return;
            }

            const params = {
                declarant_position: declarant_position,
                declarant_agency: declarant_agency,
                declarant_office: declarant_office,
                declarant_government_id_issued: declarant_government_issued_id,
                declarant_government_id_no: declarant_government_id_no,
                declarant_date_issued: (declarant_date_issued == "") ? null : declarant_date_issued,
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

                    toastr.success(data.message, "Information");
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
                    getRealProperties()
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

        getDeclarant();
        getDeclarantSpouse();
        getChildren();
        getRealProperties();
        getPersonalProperties();
        getLiabilities();
        getBusinessInterest();
        getGovernmentRelative();
    });

    function getDeclarant() {
        fetch('{{ route('getDeclarant') }}', {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                console.log(data);
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

    function getDeclarantSpouse() {
        fetch('{{ route('getDeclarantSpouse') }}', {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                console.log(data);
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

    function getChildren() {
        fetch('{{ route('getChildren') }}', {
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

    function getRealProperties() {
        fetch('{{ route('getRealProperties') }}', {
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

    function getPersonalProperties() {
        fetch('{{ route('getPersonalProperties') }}', {
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

    function getLiabilities() {
        fetch('{{ route('getLiabilities') }}', {
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

    function getBusinessInterest() {
        fetch('{{ route('getBusinessInterest') }}', {
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

    function getGovernmentRelative() {
        fetch('{{ route('getGovernmentRelative') }}', {
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

    function deleteChildren(id) {
        if (confirm("Are you sure you want to delete this record?")) {
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

                    toastr.success(data.message, "Information");
                    getChildren();
                }).catch(err => {
                console.log(err);
            })
        }
    }

    function deleteRealProperties(id) {
        if (confirm("Are you sure you want to delete this record?")) {
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

                    toastr.success(data.message, "Information");
                    getRealProperties();
                }).catch(err => {
                console.log(err);
            })
        }
    }

    function deletePersonalProperties(id) {
        if (confirm("Are you sure you want to delete this record?")) {
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

                    toastr.success(data.message, "Information");
                    getPersonalProperties();
                }).catch(err => {
                console.log(err);
            })
        }
    }

    function deleteLiabilities(id) {
        if (confirm("Are you sure you want to delete this record?")) {
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

                    toastr.success(data.message, "Information");
                    getLiabilities();
                }).catch(err => {
                console.log(err);
            })
        }
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

    function editChildren(id) {
        const name = $("#children_" + id + " td:eq(0)").text();
        const birthdate = $("#children_" + id + " td:eq(1)").text();

        children_id = id;
        $("#children_name").val(name);
        $("#children_birthdate").val(moment(birthdate, "MM/DD/YYYY").format("YYYY-MM-DD"));
        children_modal.toggle();
    }

    function editRealProperties(id) {
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
</script>
