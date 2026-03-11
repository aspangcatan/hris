<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SALN Management - List & Modal View</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }

        .page-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .saln-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        .badge-joint {
            background-color: #0d6efd;
        }

        .badge-separate {
            background-color: #ffc107;
            color: #000;
        }

        .badge-na {
            background-color: #6c757d;
        }

        .net-worth {
            font-weight: bold;
            color: #198754;
            font-size: 1.1em;
        }

        .btn-action {
            padding: 5px 12px;
            font-size: 0.875rem;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .modal-header {
            background-color: #0d6efd;
            color: white;
        }

        .modal-header .btn-close {
            filter: invert(1);
        }

        .form-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .form-section-title {
            font-weight: 600;
            color: #0d6efd;
            margin-bottom: 15px;
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 5px;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="bi bi-file-earmark-text"></i> SALN Management</h2>
                    <p class="text-muted mb-0">Statement of Assets, Liabilities and Net Worth</p>
                </div>
                <button class="btn btn-primary" onclick="openCreateModal()">
                    <i class="bi bi-plus-circle"></i> Create New SALN
                </button>
            </div>
        </div>

        <!-- SALN List -->
        <div class="saln-card">
            <h5 class="mb-3"><i class="bi bi-list-ul"></i> My SALN Records</h5>

            <div class="loading" id="loading">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p>Loading SALN records...</p>
            </div>

            <div class="table-responsive" id="salnTableContainer" style="display:none;">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Compliance Year</th>
                            <th>Filing Type</th>
                            <th>Net Worth</th>
                            <th>Filed Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="salnTableBody">
                        <!-- Data will be loaded here -->
                    </tbody>
                </table>
            </div>

            <div class="empty-state" id="emptyState" style="display:none;">
                <i class="bi bi-inbox"></i>
                <h4>No SALN Records Found</h4>
                <p>You haven't created any SALN records yet. Click "Create New SALN" to get started.</p>
            </div>
        </div>
    </div>

    <!-- SALN Modal -->
    <div class="modal fade" id="salnModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">
                        <i class="bi bi-file-earmark-plus"></i> Create New SALN
                    </h5>
                    <button type="button" class="btn-close fs-1" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Year and Filing Type Selection -->
                    <div class="form-section">
                        <h6 class="form-section-title">Basic Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Compliance Year <span class="text-danger">*</span></label>
                                <select class="form-select" id="compliance_for" required>
                                    <option value="">Select Year</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Filing Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="filing_type" required>
                                    <option value="">Select Type</option>
                                    <option value="Joint Filing">Joint Filing</option>
                                    <option value="Separate Filing">Separate Filing</option>
                                    <option value="Not Applicable">Not Applicable</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Declarant Information -->
                    <div class="form-section">
                        <h6 class="form-section-title">Declarant Information</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Position</label>
                                <input type="text" class="form-control" id="declarant_position"
                                    placeholder="e.g., Division Chief">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Agency</label>
                                <input type="text" class="form-control" id="declarant_agency"
                                    placeholder="e.g., Department of Health">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Office/Address</label>
                                <input type="text" class="form-control" id="declarant_office"
                                    placeholder="e.g., Main Office">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Government ID Issued</label>
                                <input type="text" class="form-control" id="declarant_government_id_issued"
                                    placeholder="e.g., UMID">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ID Number</label>
                                <input type="text" class="form-control" id="declarant_government_id_no"
                                    placeholder="ID Number">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Date Issued</label>
                                <input type="date" class="form-control" id="declarant_date_issued">
                            </div>
                        </div>
                    </div>

                    <!-- Spouse Information (if Joint Filing) -->
                    <div class="form-section" id="spouseSection" style="display:none;">
                        <h6 class="form-section-title">Spouse Information</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Position</label>
                                <input type="text" class="form-control" id="spouse_position"
                                    placeholder="Spouse Position">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Agency</label>
                                <input type="text" class="form-control" id="spouse_agency" placeholder="Spouse Agency">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Office/Address</label>
                                <input type="text" class="form-control" id="spouse_office" placeholder="Spouse Office">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Government ID Issued</label>
                                <input type="text" class="form-control" id="spouse_government_id_issued"
                                    placeholder="e.g., SSS">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ID Number</label>
                                <input type="text" class="form-control" id="spouse_government_id_no"
                                    placeholder="ID Number">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Date Issued</label>
                                <input type="date" class="form-control" id="spouse_date_issued">
                            </div>
                        </div>
                    </div>

                    <!-- Note: Add more sections here for properties, liabilities, etc. -->
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Note:</strong> After saving the basic information, you can add properties, liabilities,
                        children, and other details using the respective tabs/sections in your complete SALN form.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveSALN()">
                        <i class="bi bi-save"></i> Save SALN
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let salnModal;
        let editingYear = null;

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function () {
            salnModal = new bootstrap.Modal(document.getElementById('salnModal'));
            initializeYearSelector();
            loadSALNRecords();

            // Show/hide spouse section based on filing type
            document.getElementById('filing_type').addEventListener('change', function () {
                const spouseSection = document.getElementById('spouseSection');
                spouseSection.style.display = this.value === 'Joint Filing' ? 'block' : 'none';
            });
        });

        // Initialize year selector (2020 to current year + 1)
        function initializeYearSelector() {
            const yearSelect = document.getElementById('compliance_for');
            const currentYear = new Date().getFullYear();

            for (let year = currentYear + 1; year >= 2020; year--) {
                const option = document.createElement('option');
                option.value = year;
                option.textContent = year;
                yearSelect.appendChild(option);
            }
        }

        // Load all SALN records
        async function loadSALNRecords() {
            document.getElementById('loading').style.display = 'block';
            document.getElementById('salnTableContainer').style.display = 'none';
            document.getElementById('emptyState').style.display = 'none';

            try {
                const response = await fetch('/get_saln_years');
                const data = await response.json();

                if (data.code === 200 && data.results.length > 0) {
                    displaySALNRecords(data.results);
                    document.getElementById('salnTableContainer').style.display = 'block';
                } else {
                    document.getElementById('emptyState').style.display = 'block';
                }
            } catch (error) {
                console.error('Error loading SALN records:', error);
                alert('Failed to load SALN records');
            } finally {
                document.getElementById('loading').style.display = 'none';
            }
        }

        // Display SALN records in table
        function displaySALNRecords(records) {
            const tbody = document.getElementById('salnTableBody');
            tbody.innerHTML = '';

            records.forEach(record => {
                const badgeClass =
                    record.filing_type === 'Joint Filing' ? 'badge-joint' :
                        record.filing_type === 'Separate Filing' ? 'badge-separate' : 'badge-na';

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${record.compliance_for}</strong></td>
                    <td><span class="badge ${badgeClass}">${record.filing_type}</span></td>
                    <td class="net-worth">₱ ${parseFloat(record.net_worth || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
                    <td>${new Date(record.created_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })}</td>
                    <td class="text-end">
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-primary btn-action" onclick="editSALN(${record.compliance_for})" title="Edit">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-outline-success btn-action" onclick="generatePDF(${record.compliance_for})" title="Generate PDF">
                                <i class="bi bi-file-pdf"></i> PDF
                            </button>
                            <button class="btn btn-sm btn-outline-danger btn-action" onclick="deleteSALN(${record.compliance_for})" title="Delete">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Open modal for creating new SALN
        function openCreateModal() {
            editingYear = null;
            document.getElementById('modalTitle').innerHTML = '<i class="bi bi-file-earmark-plus"></i> Create New SALN';
            clearForm();
            document.getElementById('compliance_for').disabled = false;
            salnModal.show();
        }

        // Edit existing SALN
        async function editSALN(year) {
            editingYear = year;
            document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil"></i> Edit SALN - ' + year;

            try {
                const response = await fetch(`/get_saln_by_year?compliance_for=${year}`);
                const data = await response.json();

                if (data.code === 200) {
                    populateForm(data.results);
                    document.getElementById('compliance_for').disabled = true;
                    document.getElementById('compliance_for').value = year;
                    salnModal.show();
                } else {
                    alert('Error loading SALN data');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to load SALN data');
            }
        }

        // Populate form with existing data
        function populateForm(data) {
            if (data.declarant) {
                document.getElementById('compliance_for').value = data.declarant.compliance_for;
                document.getElementById('filing_type').value = data.declarant.filing_type || '';
                document.getElementById('declarant_position').value = data.declarant.position || '';
                document.getElementById('declarant_agency').value = data.declarant.agency || '';
                document.getElementById('declarant_office').value = data.declarant.office || '';
                document.getElementById('declarant_government_id_issued').value = data.declarant.government_id_issued || '';
                document.getElementById('declarant_government_id_no').value = data.declarant.government_id_no || '';
                document.getElementById('declarant_date_issued').value = data.declarant.date_issued || '';

                // Trigger filing type change to show/hide spouse section
                document.getElementById('filing_type').dispatchEvent(new Event('change'));
            }

            if (data.spouse && data.declarant.filing_type === 'Joint Filing') {
                document.getElementById('spouse_position').value = data.spouse.position || '';
                document.getElementById('spouse_agency').value = data.spouse.agency || '';
                document.getElementById('spouse_office').value = data.spouse.office || '';
                document.getElementById('spouse_government_id_issued').value = data.spouse.government_id_issued || '';
                document.getElementById('spouse_government_id_no').value = data.spouse.government_id_no || '';
                document.getElementById('spouse_date_issued').value = data.spouse.date_issued || '';
            }
        }

        // Clear form
        function clearForm() {
            document.getElementById('compliance_for').value = '';
            document.getElementById('filing_type').value = '';
            document.getElementById('declarant_position').value = '';
            document.getElementById('declarant_agency').value = '';
            document.getElementById('declarant_office').value = '';
            document.getElementById('declarant_government_id_issued').value = '';
            document.getElementById('declarant_government_id_no').value = '';
            document.getElementById('declarant_date_issued').value = '';
            document.getElementById('spouse_position').value = '';
            document.getElementById('spouse_agency').value = '';
            document.getElementById('spouse_office').value = '';
            document.getElementById('spouse_government_id_issued').value = '';
            document.getElementById('spouse_government_id_no').value = '';
            document.getElementById('spouse_date_issued').value = '';
            document.getElementById('spouseSection').style.display = 'none';
        }

        // Save SALN
        async function saveSALN() {
            const complianceFor = document.getElementById('compliance_for').value;
            const filingType = document.getElementById('filing_type').value;

            if (!complianceFor) {
                alert('Please select a compliance year');
                return;
            }

            if (!filingType) {
                alert('Please select a filing type');
                return;
            }

            const salnData = {
                compliance_for: complianceFor,
                filing_type: filingType,
                declarant_position: document.getElementById('declarant_position').value,
                declarant_agency: document.getElementById('declarant_agency').value,
                declarant_office: document.getElementById('declarant_office').value,
                declarant_government_id_issued: document.getElementById('declarant_government_id_issued').value,
                declarant_government_id_no: document.getElementById('declarant_government_id_no').value,
                declarant_date_issued: document.getElementById('declarant_date_issued').value,
            };

            // Add spouse data if Joint Filing
            if (filingType === 'Joint Filing') {
                salnData.spouse_position = document.getElementById('spouse_position').value;
                salnData.spouse_agency = document.getElementById('spouse_agency').value;
                salnData.spouse_office = document.getElementById('spouse_office').value;
                salnData.spouse_government_id_issued = document.getElementById('spouse_government_id_issued').value;
                salnData.spouse_government_id_no = document.getElementById('spouse_government_id_no').value;
                salnData.spouse_date_issued = document.getElementById('spouse_date_issued').value;
            }

            try {
                const response = await fetch('/update_saln_compliance', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(salnData)
                });

                const data = await response.json();

                if (data.code === 200) {
                    alert('SALN saved successfully!\n\nNet Worth: ₱' + parseFloat(data.net_worth.net_worth).toLocaleString('en-PH', { minimumFractionDigits: 2 }));
                    salnModal.hide();
                    loadSALNRecords();
                } else {
                    alert('Error saving SALN: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to save SALN');
            }
        }

        // Delete SALN
        async function deleteSALN(year) {
            if (!confirm(`Are you sure you want to delete the SALN record for ${year}?\n\nThis will also delete all related properties, liabilities, and other data. This action cannot be undone.`)) {
                return;
            }

            try {
                const response = await fetch('/delete_saln_record', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ compliance_for: year })
                });

                const data = await response.json();

                if (data.code === 200) {
                    alert(data.message);
                    loadSALNRecords();
                } else {
                    alert('Error deleting SALN: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to delete SALN');
            }
        }

        // Generate PDF
        function generatePDF(year) {
            window.location.href = `/generate_yearly_saln_report?compliance_for=${year}`;
        }
    </script>
</body>

</html>