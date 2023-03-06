<script>
    function getAddress(id) {
        if (is_address_loaded) return;
        fetch('{{route('getAddress')}}?id=' + id, {
            method: "GET",
            headers: {
                "Content-Type": "application/json",
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        })
            .then(res => res.json())
            .then(data => {
                console.log(data);
                if (data.code == 500) {
                    alert(data.message);
                    return;
                }
                is_address_loaded = true;
                data.results.forEach(it => {
                    if (it.address_type == "Residential") {
                        $("#residential_house").val(it.house);
                        $("#residential_street").val(it.street);
                        $("#residential_subdivision_village").val(it.subdivision_village);
                        $("#residential_barangay").val(it.barangay);
                        $("#residential_city").val(it.city);
                        $("#residential_province").val(it.province);
                        $("#residential_zipcode").val(it.zip_code);
                    } else if (it.address_type == "Permanent") {
                        $("#permanent_house").val(it.house);
                        $("#permanent_street").val(it.street);
                        $("#permanent_subdivision_village").val(it.subdivision_village);
                        $("#permanent_barangay").val(it.barangay);
                        $("#permanent_city").val(it.city);
                        $("#permanent_province").val(it.province);
                        $("#permanent_zipcode").val(it.zip_code);
                    }
                });
            }).catch(err => {
            console.log(err);
        });
    }

    function insertUpdateAddress(params) {
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
