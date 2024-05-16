<script>
    async function getCredits(id) {
        if (is_survey_loaded) return;

        const response = await fetch('{{route('getCreditBalances')}}?id=' + id, {
            method: "GET"
        });

        try {
            const data = await response.json();
            if (response.ok) {
                $("#leave_credits_list").empty();
                data.forEach(it => {
                    let tr = "<tr id='" + it.id + "'>";
                    tr += "<td>" + it.description + "</td>"
                    tr += "<td><input type='number' class='form-control text-center input-base' value='" + it.base_credit + "' /></td>"
                    tr += "<td><input type='number' class='form-control text-center input-used' value='" + it.used_credit + "' /></td>"
                    tr += "<td><input type='number' class='form-control text-center' value='" + it.balance + "'  disabled/></td>"
                    tr += "</tr>";
                    $("#leave_credits_list").append(tr);
                });
            } else {
                toastr.error(data.error || 'Something went wrong, please try again');
            }
        } catch (err) {
            console.log(err);
        }
    }

    function insertUpdateSurvey(params) {
        fetch('{{ route('insertUpdateSurvey') }}', {
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
                    console.log(data.message);
                    toastr.error("Something went wrong", "Oops");
                    return;
                }

                toastr.success(data.message, "Success");
            }).catch(err => {
            console.log(err);
        });
    }

    function getIdByQuestion(question) {
        for (let i = 0; i < SURVEYS.length; i++) {
            if (SURVEYS[i].question == question) {
                return SURVEYS[i].id;
            }
        }
        return 0;
    }
</script>
