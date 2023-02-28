<script>
    function getFamilyBackground() {
        fetch('{{route('getFamilyBackground')}}', {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    alert(data.message);
                    return;
                }

                data.results.forEach(it => {
                   
                });
            }).catch(err => {
            console.log(err);
        });
    }

    function insertUpdateFamilyBackground(params) {
        fetch('{{ route('insertUpdateAddress') }}', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            body: JSON.stringify({address: params})
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    console.log(data.message)
                    toastr.error("Something went wrong", "Oops");
                    return;
                }

                toastr.success(data.message, "Success");
            }).catch(err => {
            console.log(err);
        });
    }
</script>
