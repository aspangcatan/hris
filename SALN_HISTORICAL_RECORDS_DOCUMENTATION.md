# SALN Historical Records Implementation

## Overview
This document describes the implementation of historical record tracking for the SALN (Statement of Assets, Liabilities and Net Worth) module. The system now supports:
- Tracking SALN by compliance year
- Filing type management (Joint Filing, Separate Filing, Not Applicable)
- Historical net worth calculation
- Year-specific PDF report generation

## Database Changes

### Migration: `2026_02_13_094507_add_compliance_and_filing_type_to_saln_tables.php`

#### Fields Added to `saln_declarant` table:
- `compliance_for` (year, nullable) - The year for which the SALN is filed
- `filing_type` (enum) - Options: 'Joint Filing', 'Separate Filing', 'Not Applicable'
- `total_assets` (decimal 15,2) - Calculated total assets for the year
- `total_liabilities` (decimal 15,2) - Calculated total liabilities for the year
- `net_worth` (decimal 15,2) - Calculated net worth (assets - liabilities)
- `timestamps` - Created at and updated at tracking

#### Fields Added to `saln_spouse` table:
- `compliance_for` (year, nullable) - The year for which the spouse SALN is filed
- `total_assets` (decimal 15,2) - Spouse's total assets
- `total_liabilities` (decimal 15,2) - Spouse's total liabilities
- `net_worth` (decimal 15,2) - Spouse's net worth
- `timestamps` - Created at and updated at tracking

#### Fields Added to Related Tables:
All of the following tables now have a `compliance_for` (year) field for historical tracking:
- `saln_children`
- `real_properties`
- `personal_properties`
- `liabilities`
- `business_interests`
- `government_relatives`
- `marriages`
- `spouse_real_properties`
- `spouse_personal_properties`
- `spouse_liabilities`
- `spouse_business_interests`

## Controller Methods (SalnController.php)

### New Methods:

#### 1. `updateSalnCompliance(Request $request)`
**Purpose**: Save or update SALN information for a specific compliance year
**Parameters**:
- `compliance_for` (required) - Year (1900 to current year + 1)
- `filing_type` (required) - 'Joint Filing', 'Separate Filing', or 'Not Applicable'
- All existing declarant and spouse fields

**Functionality**:
- Creates or updates declarant record for the specified year
- Automatically calculates and stores total assets, liabilities, and net worth
- For Joint Filing: also creates/updates spouse record with calculations
- Returns calculated net worth data

#### 2. `calculateNetWorth($userId, $complianceYear, $filingType)` (Private)
**Purpose**: Calculate net worth for a specific year
**Returns**: Array containing:
- `declarant_assets` - Total declarant assets
- `declarant_liabilities` - Total declarant liabilities
- `spouse_assets` - Total spouse assets (if Joint Filing)
- `spouse_liabilities` - Total spouse liabilities (if Joint Filing)
- `total_assets` - Combined total assets
- `total_liabilities` - Combined total liabilities
- `net_worth` - Final net worth (total_assets - total_liabilities)
- `spouse_net_worth` - Spouse-only net worth

**Note**: For Joint Filing, net worth includes BOTH declarant AND spouse net worth

#### 3. `getSalnYears()`
**Purpose**: Retrieve all SALN compliance years for the authenticated user
**Returns**: List of years with filing type and net worth

#### 4. `getSalnByYear(Request $request)`
**Purpose**: Get complete SALN data for a specific year
**Parameters**:
- `compliance_for` (required) - The year to retrieve

**Returns**: Complete SALN data including:
- Declarant information
- Spouse information
- Children
- All properties (real and personal)
- Liabilities
- Business interests
- Government relatives
- Marriages

#### 5. `generateYearlySalnReport(Request $request)`
**Purpose**: Generate PDF report for a specific compliance year
**Parameters**:
- `compliance_for` (required) - The year to generate report for

**Returns**: PDF download with filename: `SALN_{year}_{fname}_{lname}.pdf`

#### 6. `getSalnDataForReport($complianceYear)` (Private)
**Purpose**: Format SALN data for PDF generation
**Note**: For Joint Filing, the PDF will include spouse's net worth in the total calculation

## Routes (web.php)

### New Routes:
```php
// SALN Historical Tracking Routes
Route::post('/update_saln_compliance', [SalnController::class, 'updateSalnCompliance'])->name('updateSalnCompliance');
Route::get('/get_saln_years', [SalnController::class, 'getSalnYears'])->name('getSalnYears');
Route::get('/get_saln_by_year', [SalnController::class, 'getSalnByYear'])->name('getSalnByYear');
Route::get('/generate_yearly_saln_report', [SalnController::class, 'generateYearlySalnReport'])->name('generateYearlySalnReport');
```

## Usage Examples

### 1. Saving SALN for a Specific Year
```javascript
// POST to /update_saln_compliance
{
    "compliance_for": 2024,
    "filing_type": "Joint Filing",
    "declarant_position": "Division Chief",
    "declarant_agency": "Department of Health",
    // ... other declarant fields
    "spouse_position": "Data Scientist",
    "spouse_agency": "Private Sector",
    // ... other spouse fields
}
```

### 2. Getting All SALN Years
```javascript
// GET /get_saln_years
// Returns:
{
    "code": 200,
    "results": [
        {
            "id": 1,
            "compliance_for": 2024,
            "filing_type": "Joint Filing",
            "net_worth": "5000000.00",
            "created_at": "2024-12-31"
        },
        {
            "id": 2,
            "compliance_for": 2023,
            "filing_type": "Separate Filing",
            "net_worth": "4500000.00",
           "created_at": "2023-12-31"
        }
    ]
}
```

### 3. Getting SALN Data for a Specific Year
```javascript
// GET /get_saln_by_year?compliance_for=2024
// Returns complete SALN data for 2024
```

### 4. Generating Yearly PDF Report
```javascript
// GET /generate_yearly_saln_report?compliance_for=2024
// Downloads: SALN_2024_John_Doe.pdf
```

## Important Notes

### Net Worth Calculation Logic:
1. **Separate Filing or Not Applicable**: 
   - Net Worth = Declarant's (Real Properties + Personal Properties) - Declarant's Liabilities
   
2. **Joint Filing**:
   - Net Worth = (Declarant's Assets + Spouse's Assets) - (Declarant's Liabilities + Spouse's Liabilities)
   - This follows the requirement: "IF JOINT FILING net worth will include also spouse networth"

### Historical Records:
- Each year's SALN is stored as a separate record
- You can go back to any year and view the exact SALN data as it was filed
- Properties, liabilities, and other items are linked to specific compliance years
- This allows tracking changes in net worth over time

### Data Integrity:
- All related data (properties, liabilities, etc.) must have the `compliance_for` field set
- When adding new items through existing insert methods, update them to include `compliance_for`

## Next Steps for Frontend Integration

1. **Add Year Selector**:
   - Add a dropdown/select field for "Compliance Year" in the SALN form
   - Add a dropdown for "Filing Type" (Joint/Separate/Not Applicable)

2. **Historical View**:
   - Create a list/table showing all past SALN years
   - Allow users to click on a year to view that year's SALN
   - Show net worth progression over years (could be a chart)

3. **Update Existing Forms**:
   - Modify all SALN-related forms to include `compliance_for` parameter
   - Update insert methods to pass the compliance year

4. **Report Generation UI**:
   - Add a "Generate Report" button for each year
   - Allow filtering by year in the SALN view

## Migration Instructions

1. Run the migration:
   ```bash
   php artisan migrate
   ```

2. Update existing SALN records (optional):
   ```sql
   -- Set compliance_for for existing records if needed
   UPDATE saln_declarant SET compliance_for = 2024 WHERE compliance_for IS NULL;
   ```

3. Test the new endpoints using Postman or your frontend

## Sample Report Reference
The system generates PDF reports using the existing `pdf.saln.blade.php` template, showing:
- Declarant and spouse information
- Filing type (checked appropriately)
- Compliance year (shown as "As Of Date")
- All assets, liabilities, and net worth calculations
- For Joint Filing: combined net worth of both declarant and spouse
