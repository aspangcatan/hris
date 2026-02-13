# SALN List & Modal UI Pattern - Implementation Guide

## 🎨 UI Pattern Overview

Your SALN module will follow this user-friendly pattern:

### Main Page (List View)
```
┌─────────────────────────────────────────────────────────────────┐
│  SALN Management                          [+ Create New SALN]   │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  My SALN Records                                                │
│  ─────────────────────────────────────────────────────────────  │
│  │ Year │ Filing Type   │ Net Worth      │ Filed On   │ Actions│
│  │ 2024 │ Joint Filing  │ ₱4,700,000.00 │ 12/31/2024 │ [E][P][D]│
│  │ 2023 │ Separate      │ ₱2,800,000.00 │ 12/31/2023 │ [E][P][D]│
│  │ 2022 │ Not Applicable│ ₱2,500,000.00 │ 12/31/2022 │ [E][P][D]│
│  ─────────────────────────────────────────────────────────────  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘

[E] = Edit Button → Opens Modal with form
[P] = PDF Button → Downloads PDF report
[D] = Delete Button → Deletes record (with confirmation)
```

### Modal (Form View)
```
┌────────────────── Create/Edit SALN ─────────────────────┐
│                                                 [Close X]│
├──────────────────────────────────────────────────────────┤
│  Basic Information                                       │
│  ┌─────────────────────┐  ┌─────────────────────┐      │
│  │ Compliance Year     │  │ Filing Type         │      │
│  │ [2024 ▼]            │  │ [Joint Filing ▼]    │      │
│  └─────────────────────┘  └─────────────────────┘      │
│                                                          │
│  Declarant Information                                   │
│  ┌──────────┐ ┌───────────┐ ┌─────────────┐            │
│  │ Position │ │ Agency    │ │ Office      │            │
│  └──────────┘ └───────────┘ └─────────────┘            │
│                                                          │
│  Spouse Information (if Joint Filing)                    │
│  ┌──────────┐ ┌───────────┐ ┌─────────────┐            │
│  │ Position │ │ Agency    │ │ Office      │            │
│  └──────────┘ └───────────┘ └─────────────┘            │
│                                                          │
│  [Note: Add properties, liabilities after saving]       │
│                                                          │
├──────────────────────────────────────────────────────────┤
│                           [Cancel] [Save SALN]           │
└──────────────────────────────────────────────────────────┘
```

## 🔄 User Flow

### Creating New SALN
1. User clicks **"Create New SALN"** button
2. Modal opens with empty form
3. User selects **Year** (e.g., 2024)
4. User selects **Filing Type** (Joint/Separate/NA)
5. If "Joint Filing" → Spouse section appears
6. User fills in declarant info (and spouse if applicable)
7. User clicks **"Save SALN"**
8. System:
   - Creates SALN record for that year
   - Calculates net worth
   - Shows success message with net worth
9. Modal closes, list refreshes
10. User can now add properties, liabilities, etc. by editing the record

### Editing Existing SALN  
1. User clicks **"Edit"** button on a row
2. Modal opens with that year's data pre-filled
3. Year field is **disabled** (can't change year)
4. User modifies information
5. User clicks **"Save SALN"**
6. System updates the record
7. Modal closes, list refreshes

### Deleting SALN
1. User clicks **"Delete"** button on a row
2. Confirmation dialog appears:
   > "Are you sure you want to delete the SALN record for 2024?
   > This will also delete all related properties, liabilities, and other data.
   > This action cannot be undone."
3. If user confirms:
   - System deletes **entire SALN record** for that year
   - Deletes all properties, liabilities, children, etc. for that year
   - Shows success message
4. List refreshes

### Generating PDF
1. User clicks **"PDF"** button on a row
2. System generates PDF for that year
3. Browser downloads: `SALN_2024_John_Doe.pdf`

## 📡 API Endpoints Summary

| Action | Method | Endpoint | Description |
|--------|--------|----------|-------------|
| List SALNs | GET | `/get_saln_years` | Get all SALN years |
| Create/Update | POST | `/update_saln_compliance` | Save SALN for a year |
| Load for Edit | GET | `/get_saln_by_year?compliance_for=2024` | Get SALN data |
| Delete | DELETE | `/delete_saln_record` | Delete SALN & all related data |
| Generate PDF | GET | `/generate_yearly_saln_report?compliance_for=2024` | Download PDF |

## 💾 What Gets Deleted?

When you delete a SALN record (e.g., for year 2024), the system removes:

✅ Declarant record for 2024  
✅ Spouse record for 2024  
✅ All real properties for 2024  
✅ All personal properties for 2024  
✅ All liabilities for 2024  
✅ All business interests for 2024  
✅ All government relatives for 2024  
✅ All children records for 2024  
✅ All marriages for 2024  
✅ All spouse properties for 2024  
✅ All spouse liabilities for 2024  
✅ All spouse business interests for 2024  

**Important:** Other years' data remains untouched!

## 📋 Implementation Steps

### Step 1: Update Your Main SALN Page

Replace your current SALN form page with the list view:

```html
<!-- Main page shows table of SALN records -->
<div class="container">
    <div class="header">
        <h2>SALN Management</h2>
        <button onclick="openCreateModal()">Create New SALN</button>
    </div>
    
    <table id="salnTable">
        <!-- SALN list will load here via AJAX -->
    </table>
</div>
```

### Step 2: Add Modal for Form

```html
<!-- Modal contains the SALN form -->
<div class="modal" id="salnModal">
    <div class="modal-content">
        <h3>Create/Edit SALN</h3>
        
        <!-- Year and Filing Type -->
        <select id="compliance_for">...</select>
        <select id="filing_type">...</select>
        
        <!-- Declarant fields -->
        <input id="declarant_position" />
        <input id="declarant_agency" />
        <!-- etc. -->
        
        <!-- Spouse fields (shown if Joint Filing) -->
        <div id="spouseSection" style="display:none">
            <input id="spouse_position" />
            <!-- etc. -->
        </div>
        
        <button onclick="saveSALN()">Save</button>
    </div>
</div>
```

### Step 3: JavaScript Functions

```javascript
// Load SALN list on page load
loadSALNRecords();

// Create new SALN
function openCreateModal() {
    clearForm();
    showModal();
}

// Edit existing SALN
function editSALN(year) {
    fetch(`/get_saln_by_year?compliance_for=${year}`)
        .then(response => response.json())
        .then(data => {
            populateForm(data.results);
            showModal();
        });
}

// Save SALN
function saveSALN() {
    const data = {
        compliance_for: $('#compliance_for').val(),
        filing_type: $('#filing_type').val(),
        // ... other fields
    };
    
    fetch('/update_saln_compliance', {
        method: 'POST',
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        alert('SALN saved!');
        closeModal();
        loadSALNRecords(); // Refresh list
    });
}

// Delete SALN
function deleteSALN(year) {
    if (confirm('Delete SALN for ' + year + '?')) {
        fetch('/delete_saln_record', {
            method: 'DELETE',
            body: JSON.stringify({ compliance_for: year })
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            loadSALNRecords(); // Refresh list
        });
    }
}
```

## 🎯 Benefits of This Pattern

✅ **Clean Interface** - Main page shows summary, details in modal  
✅ **Easy Navigation** - See all years at a glance  
✅ **Quick Actions** - Edit, PDF, Delete in one click  
✅ **Data Safety** - Confirmation before delete  
✅ **Flexible** - Add/edit SALN for any year  
✅ **Organized** - Each year is a separate record  

## 📦 Files Provided

1. **Complete Example:** `resources/views/examples/saln_list_modal_example.html`
   - Full working example with Bootstrap 5
   - Ready to integrate into your app
   - Includes all JavaScript functions

2. **Backend:** `SalnController.php`
   - All methods implemented
   - Delete method added
   - Tested and working

3. **Routes:** `routes/web.php`
   - All routes registered
   - DELETE route added

## 🚀 Next Steps

1. **Copy the example HTML** to your actual SALN view file
2. **Integrate with your existing form fields** (properties, liabilities, etc.)
3. **Style to match your app** (or keep Bootstrap look)
4. **Test each action:**
   - [ ] Create new SALN
   - [ ] Edit existing SALN
   - [ ] Delete SALN
   - [ ] Generate PDF
   - [ ] View net worth updates

## 💡 Tips

- **Add tabs inside the modal** for Properties, Liabilities, Children, etc.
- **Use AJAX** to load/save without page refresh
- **Show loading spinners** for better UX
- **Validate** year and filing type before saving
- **Disable year selector** when editing (year can't be changed)
- **Auto-calculate net worth** after adding properties/liabilities

## ✅ What's Already Done

✅ Database migration (compliance_for, filing_type, net_worth)  
✅ Backend methods (create, read, update, delete)  
✅ Net worth calculation (including spouse for Joint Filing)  
✅ PDF generation by year  
✅ Complete delete functionality  
✅ Routes registered  
✅ Example UI with modal pattern  

**You're ready to implement the frontend!** 🎉
