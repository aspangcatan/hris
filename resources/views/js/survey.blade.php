<script>
    function getSurvey() {
        fetch('{{route('getSurvey')}}', {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    alert(data.message);
                    return;
                }

                for (let i = 0; i < data.results.length; i++) {
                    const it = data.results[i];
                    const id = getIdByQuestion(it.question);
                    $("#" + id + "_details").val("");
                    $("#" + id + "_extra").val("");

                    if (it.answer == "YES") {
                        $("input[name='" + id + "']:eq(0)").prop("checked", true);
                        $("#" + id + "_details").val(it.details);
                        $("#" + id + "_extra").val(it.extra);
                    } else if (it.answer == "NO") {
                        $("input[name='" + id + "']:eq(1)").prop("checked", true);
                    } else {
                        $("input[name='" + id + "']").prop("checked", false);
                    }
                }

            }).catch(err => {
            console.log(err);
        });
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
                console.log(params.surveys.length);
                console.log(data);
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
