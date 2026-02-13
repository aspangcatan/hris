<script>
    const employee_history_modal = new bootstrap.Modal(document.getElementById('employee_history_modal'));
    const saln_form_modal = new bootstrap.Modal(document.getElementById('saln_form_modal'));

    $(document).ready(function () {
        loadAdminEmployees();

        // Search and Filter Events
        $("#admin_filter_year").on('input change', function () {
            loadAdminEmployees();
        });

        $("#admin_search_employee").on('keydown', function (e) {
    if (e.key === "Enter") {
        e.preventDefault(); // optional: prevents form submission if inside a form
        loadAdminEmployees();
    }
});
    });

    function loadAdminEmployees(page = 1) {
        const search = $("#admin_search_employee").val();
        const year = $("#admin_filter_year").val();
        const container = $("#employee_grid");
        const paginationContainer = $("#pagination_container");

        // Basic loading state
        container.html('<div class="col-12 text-center py-5"><div class="spinner-border text-success"></div><p class="mt-2">Loading employees...</p></div>');
        paginationContainer.empty();

        fetch(`{{ route('saln.admin.employees') }}?search=${encodeURIComponent(search)}&year=${year}&page=${page}`)
            .then(res => res.json())
            .then(data => {
                container.empty();
                if (data.code !== 200 || !data.results || data.results.length === 0) {
                    container.append(`
                        <div class="col-12 text-center py-5">
                            <i class="bi bi-person-x text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">No employees found</h5>
                            <p class="text-muted">Try adjusting your search or filter criteria.</p>
                        </div>
                    `);
                    $("#total_employees").text("0");
                    return;
                }

                $("#total_employees").text(data.pagination.total);

                data.results.forEach(emp => {
                    const fullName = `${emp.fname} ${emp.lname}`.trim();
                    const initials = (emp.fname[0] || '') + (emp.lname[0] || '');
                    const hasSaln = emp.compliance_for !== null;
                    const netWorth = hasSaln ? parseFloat(emp.net_worth).toLocaleString(undefined, {minimumFractionDigits: 2}) : '0.00';
                    
                    const card = `
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="employee-card h-100">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="flex-shrink-0">
                                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-weight: 700;">
                                            ${initials}
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <div class="employee-name">${fullName}</div>
                                        <div class="employee-no">Employee ID: ${emp.id}</div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="saln-info-label">Compliance Status (${year})</div>
                                    <div class="mt-1">
                                        ${hasSaln ? 
                                            `<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                <i class="bi bi-check-circle me-1"></i>Compliant
                                            </span>` : 
                                            `<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                                <i class="bi bi-x-circle me-1"></i>No Record
                                            </span>`
                                        }
                                    </div>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="saln-info-label">Net Worth</div>
                                        <div class="fw-bold text-dark">₱${netWorth}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="saln-info-label">Filing Type</div>
                                        <div class="mt-1">
                                            ${hasSaln ? 
                                                `<span class="badge bg-light text-dark border border-secondary-subtle px-2 py-1 cursor-pointer" 
                                                    onclick="promptUpdateFilingType(${emp.id}, '${year}', '${emp.filing_type}')"
                                                    title="Click to update filing type">
                                                    ${emp.filing_type || 'N/A'} <i class="bi bi-pencil-fill ms-1 small opacity-50"></i>
                                                </span>` : 
                                                `<span class="text-muted small">N/A</span>`
                                            }
                                        </div>
                                    </div>
                                </div>

                                <div class="d-grid mt-auto">
                                    <button class="btn btn-outline-success btn-sm" onclick="showEmployeeHistory(${emp.id}, '${fullName}')">
                                        <i class="bi bi-clock-history me-1"></i>View Filing History
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                    container.append(card);
                });

                renderPagination(data.pagination);
            })
            .catch(err => {
                console.error(err);
                container.html('<div class="col-12 text-center py-5"><i class="bi bi-exclamation-triangle text-danger" style="font-size: 3rem;"></i><p class="mt-2">Error loading data. Please try again.</p></div>');
            });
    }

    function renderPagination(p) {
        const container = $("#pagination_container");
        container.empty();

        if (p.last_page <= 1) return;

        let html = `<nav aria-label="Page navigation"><ul class="pagination">`;

        // Previous
        html += `<li class="page-item ${p.current_page === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="javascript:void(0)" onclick="loadAdminEmployees(${p.current_page - 1})">Previous</a>
                </li>`;

        // Pages (Limited to 5 around current)
        let start = Math.max(1, p.current_page - 2);
        let end = Math.min(p.last_page, start + 4);
        if (end - start < 4) start = Math.max(1, end - 4);

        for (let i = start; i <= end; i++) {
            html += `<li class="page-item ${p.current_page === i ? 'active' : ''}">
                        <a class="page-link" href="javascript:void(0)" onclick="loadAdminEmployees(${i})">${i}</a>
                    </li>`;
        }

        // Next
        html += `<li class="page-item ${p.current_page === p.last_page ? 'disabled' : ''}">
                    <a class="page-link" href="javascript:void(0)" onclick="loadAdminEmployees(${p.current_page + 1})">Next</a>
                </li>`;

        html += `</ul></nav>`;
        container.append(html);
    }

    function showEmployeeHistory(userId, fullName) {
        $("#target_emp_name").text(fullName);
        const container = $("#history_list_container");
        container.html('<div class="text-center py-5"><div class="spinner-border text-success"></div><p class="mt-2">Loading historical records...</p></div>');
        employee_history_modal.show();

        fetch(`{{ url('saln-admin/history') }}/${userId}`)
            .then(res => res.json())
            .then(data => {
                container.empty();
                if (data.code !== 200) {
                    throw new Error(data.message || 'Failed to load history');
                }

                if (!data.results || data.results.length === 0) {
                    container.append(`
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-clock-history mb-3" style="font-size: 3rem; opacity: 0.5;"></i>
                            <h5>No Record Found</h5>
                            <p>This employee hasn't submitted any SALN records through the system yet.</p>
                        </div>
                    `);
                    return;
                }

                data.results.forEach(record => {
                    const compDate = new Date(record.compliance_for);
                    const year = compDate.getFullYear();
                    const formattedDate = compDate.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
                    const netWorth = parseFloat(record.net_worth || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
                    
                    const card = `
                        <div class="history-year-card d-flex align-items-center justify-content-between">
                            <div>
                                <div class="fw-bold text-success" style="font-size: 1.1rem;">${year} ${record.compliance_type || 'Compliance'}</div>
                                <div class="text-muted small">
                                    <i class="bi bi-calendar3 me-1"></i>Filed: ${formattedDate}
                                    <span class="mx-2">|</span>
                                    <i class="bi bi-file-earmark-check me-1"></i>${record.filing_type}
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-dark mb-1">₱${netWorth}</div>
                                <button class="btn btn-sm btn-light text-success border-success-subtle" onclick="viewSalnDetail('${record.compliance_for}', ${userId}, '${record.filing_type}')">
                                    <i class="bi bi-printer me-1"></i>Print View
                                </button>
                            </div>
                        </div>
                    `;
                    container.append(card);
                });
            })
            .catch(err => {
                console.error(err);
                container.html(`
                    <div class="text-center py-5 text-danger">
                        <i class="bi bi-exclamation-octagon" style="font-size: 3rem;"></i>
                        <h5 class="mt-3">Error Loading History</h5>
                        <p>${err.message}</p>
                    </div>
                `);
            });
    }

    // This function will handle viewing the full SALN details
    // We'll reuse the SALN form but set things to potentially readonly if needed
    // However, for now we just show it.
    window.viewSalnDetail = function(complianceDate, userId, filingType) {
        // We need special handling if we want to view OTHER users' SALN
        // The existing JS functions use Auth::id() or just fetch based on session
        // We should adjust the JS functions or the backend routes to allow Admin to fetch by User ID.
        // For now, I'll alert that this is coming soon or just show the print view.
        
        // Actually, let's just use the Print Route which already supports userid query param!
        const asOf = new Date(complianceDate);
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'];
            
        const spouseSignature = filingType === 'Joint Filing' ? 1 : 0;
            
        // Open Print View in new tab
        const printUrl = `{{ route('printSaln') }}?userid=${userId}&compliance_for=${complianceDate}&as_of_month=${monthNames[asOf.getMonth()]}&as_of_day=${asOf.getDate()}&as_of_year=${asOf.getFullYear()}&print_date=${new Date().toISOString().split('T')[0]}&sworn_date=${complianceDate}&filing_type=${encodeURIComponent(filingType)}&spouse_signature=${spouseSignature}`;
        window.open(printUrl, '_blank');
    };

    function promptUpdateFilingType(userId, year, currentType) {
        Swal.fire({
            title: 'Update Filing Type',
            text: `Selected Year: ${year}`,
            input: 'select',
            inputOptions: {
                'Joint Filing': 'Joint Filing',
                'Separate Filing': 'Separate Filing',
                'Not Applicable': 'Not Applicable'
            },
            inputValue: currentType || 'Not Applicable',
            showCancelButton: true,
            confirmButtonText: 'Update',
            confirmButtonColor: '#198754',
            showLoaderOnConfirm: true,
            preConfirm: (newFilingType) => {
                return fetch(`{{ route('saln.admin.updateFilingType') }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        user_id: userId,
                        year: year,
                        filing_type: newFilingType
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.code !== 200) {
                        throw new Error(data.message || 'Update failed');
                    }
                    return data;
                })
                .catch(error => {
                    Swal.showValidationMessage(`Request failed: ${error}`);
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                toastr.success(result.value.message);
                loadAdminEmployees(); // Refresh the grid
            }
        });
    }

    window.printSummaryReport = function() {
        const year = $("#admin_filter_year").val();
        window.open(`{{ route('saln.admin.summaryReport') }}?year=${year}`, '_blank');
    };
</script>
