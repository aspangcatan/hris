# SALN Historical Records - Quick Reference

## 🎯 Key Concept
Each year's SALN is now a separate historical record. Users can file SALN for different years and view their net worth history over time.

## 📌 Important Fields

### `compliance_for` (year)
- The year for which the SALN is being filed
- Example: 2024, 2023, 2022
- **Required** when saving SALN data
- **Optional** when viewing (defaults to current year if not provided)

### `filing_type` (enum)
- **Joint Filing**: Net worth = Declarant + Spouse combined
- **Separate Filing**: Net worth = Declarant only
- **Not Applicable**: Net worth = Declarant only

## 🔥 Quick Start

### 1. Save SALN for 2024
```javascript
POST /update_saln_compliance
{
  "compliance_for": 2024,
  "filing_type": "Joint Filing",
  "declarant_position": "...",
  "declarant_agency": "...",
  // ... other fields
}
```

### 2. Add Property for 2024
```javascript
POST /insert_real_properties
{
  "compliance_for": 2024,  // ← Add this
  "description": "House",
  "kind": "Residential",
  // ... other fields
}
```

### 3. View 2024 Properties
```javascript
GET /get_real_properties?compliance_for=2024
```

### 4. View All SALN Years
```javascript
GET /get_saln_years
// Returns: [{year: 2024, net_worth: 5000000}, {year: 2023, net_worth: 4500000}, ...]
```

### 5. Generate 2024 PDF
```javascript
GET /generate_yearly_saln_report?compliance_for=2024
// Downloads: SALN_2024_FirstName_LastName.pdf
```

### 6. Delete SALN Record
```javascript
DELETE /delete_saln_record
{
  "compliance_for": 2024
}
// Deletes the entire SALN record and all related data for 2024
```

## 📋 Frontend Integration Checklist

- [ ] Add year selector dropdown (2020-current year)
- [ ] Add filing type selector
- [ ] Add `compliance_for` to all insert AJAX calls
- [ ] Add `compliance_for` to all get AJAX calls
- [ ] Create history table showing past years
- [ ] Add "View" button for each year
- [ ] Add "Generate PDF" button for each year
- [ ] Show net worth calculation in UI
- [ ] Test Joint Filing vs Separate Filing

## 🎨 UI Elements Needed

```html
<!-- Year Selector -->
<select id="compliance_year" name="compliance_year">
  <option value="2024">2024</option>
  <option value="2023">2023</option>
</select>

<!-- Filing Type -->
<select id="filing_type" name="filing_type">
  <option value="Joint Filing">Joint Filing</option>
  <option value="Separate Filing">Separate Filing</option>
  <option value="Not Applicable">Not Applicable</option>
</select>

<!-- History Table -->
<table>
  <thead>
    <tr>
      <th>Year</th>
      <th>Filing Type</th>
      <th>Net Worth</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody id="history-data">
    <!-- Populated via AJAX -->
  </tbody>
</table>
```

## 💡 Tips

1. **Always pass `compliance_for`** when adding/updating SALN data
2. **Default behavior**: If `compliance_for` not provided, uses current year
3. **Joint Filing**: Automatically includes spouse in net worth calculation
4. **Historical data**: Each year is independent - editing 2024 doesn't affect 2023
5. **PDF generation**: Uses existing template, just filtered by year

## ⚠️ Migration Required

Run before using:
```bash
php artisan migrate
```

## 📖 Full Documentation

- **Technical Details**: `SALN_HISTORICAL_RECORDS_DOCUMENTATION.md`
- **Implementation Guide**: `SALN_IMPLEMENTATION_SUMMARY.md`
- **Frontend Example**: `resources/views/examples/saln_historical_example.html`

## 🚀 Ready to Use!

All backend functionality is complete. Just integrate the frontend elements and test!
