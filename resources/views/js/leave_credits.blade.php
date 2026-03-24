<script>
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
