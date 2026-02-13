# SALN Historical Records - System Flow Diagram

```
┌───────────────────────────────────────────────────────────────────────┐
│                         SALN HISTORICAL TRACKING                       │
│                         User Interface Layer                           │
└───────────────────────────────────────────────────────────────────────┘
                                    │
                    ┌───────────────┼───────────────┐
                    ▼               ▼               ▼
            ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
            │ Year Selector│ │Filing Type   │ │History View  │
            │   (2020-2026)│ │ Selector     │ │ (Table)      │
            └──────────────┘ └──────────────┘ └──────────────┘
                    │               │               │
                    └───────────────┼───────────────┘
                                    ▼
        ┌───────────────────────────────────────────────────────┐
        │              Backend API Endpoints                      │
        │                                                         │
        │  POST /update_saln_compliance                          │
        │  GET  /get_saln_years                                  │
        │  GET  /get_saln_by_year?compliance_for={year}         │
        │  GET  /generate_yearly_saln_report?compliance_for={year}│
        │                                                         │
        │  Updated Insert Methods (with compliance_for):         │
        │  - POST /insert_real_properties                        │
        │  - POST /insert_personal_properties                    │
        │  - POST /insert_liabilities                            │
        │  - etc...                                              │
        │                                                         │
        │  Updated Get Methods (with compliance_for filter):     │
        │  - GET /get_real_properties?compliance_for={year}     │
        │  - GET /get_personal_properties?compliance_for={year} │
        │  - GET /get_liabilities?compliance_for={year}         │
        │  - etc...                                              │
        └───────────────────────────────────────────────────────┘
                                    │
                                    ▼
        ┌───────────────────────────────────────────────────────┐
        │           SalnController.php Methods                   │
        │                                                         │
        │  ┌──────────────────────────────────────────────┐     │
        │  │  updateSalnCompliance()                      │     │
        │  │  • Validates compliance_for & filing_type    │     │
        │  │  • Calls calculateNetWorth()                │     │
        │  │  • Saves declarant & spouse data            │     │
        │  └──────────────────────────────────────────────┘     │
        │                      │                                 │
        │                      ▼                                 │
        │  ┌──────────────────────────────────────────────┐     │
        │  │  calculateNetWorth()                         │     │
        │  │  • Sums declarant assets & liabilities       │     │
        │  │  • If Joint Filing:                          │     │
        │  │    - Includes spouse assets & liabilities    │     │
        │  │  • Returns complete net worth breakdown      │     │
        │  └──────────────────────────────────────────────┘     │
        │                                                         │
        │  ┌──────────────────────────────────────────────┐     │
        │  │  getSalnYears()                              │     │
        │  │  • Returns all years with net worth          │     │
        │  └──────────────────────────────────────────────┘     │
        │                                                         │
        │  ┌──────────────────────────────────────────────┐     │
        │  │  getSalnByYear($year)                        │     │
        │  │  • Retrieves all data for specific year      │     │
        │  └──────────────────────────────────────────────┘     │
        │                                                         │
        │  ┌──────────────────────────────────────────────┐     │
        │  │  generateYearlySalnReport($year)             │     │
        │  │  • Formats data                              │     │
        │  │  • Generates PDF                             │     │
        │  │  • Downloads SALN_{year}_{name}.pdf          │     │
        │  └──────────────────────────────────────────────┘     │
        └───────────────────────────────────────────────────────┘
                                    │
                                    ▼
        ┌───────────────────────────────────────────────────────┐
        │                  Database Tables                       │
        │                                                         │
        │  ┌────────────────────────────────────────────┐        │
        │  │ saln_declarant (Master Record)             │        │
        │  │ ┌──────────────────────────────────────┐   │        │
        │  │ │ id, user_id, position, agency, ...   │   │        │
        │  │ │ compliance_for ← YEAR                │   │        │
        │  │ │ filing_type ← Joint/Separate/NA      │   │        │
        │  │ │ total_assets ← Calculated            │   │        │
        │  │ │ total_liabilities ← Calculated       │   │        │
        │  │ │ net_worth ← Calculated               │   │        │
        │  │ └──────────────────────────────────────┘   │        │
        │  └────────────────────────────────────────────┘        │
        │                                                         │
        │  ┌────────────────────────────────────────────┐        │
        │  │ saln_spouse                                │        │
        │  │ ┌──────────────────────────────────────┐   │        │
        │  │ │ id, user_id, position, agency, ...   │   │        │
        │  │ │ compliance_for ← YEAR                │   │        │
        │  │ │ total_assets, total_liabilities,     │   │        │
        │  │ │ net_worth                            │   │        │
        │  │ └──────────────────────────────────────┘   │        │
        │  └────────────────────────────────────────────┘        │
        │                                                         │
        │  ┌────────────────────────────────────────────┐        │
        │  │ Related Tables (all have compliance_for):  │        │
        │  │ • real_properties                          │        │
        │  │ • personal_properties                      │        │
        │  │ • liabilities                              │        │
        │  │ • business_interests                       │        │
        │  │ • government_relatives                     │        │
        │  │ • saln_children                            │        │
        │  │ • marriages                                │        │
        │  │ • spouse_real_properties                   │        │
        │  │ • spouse_personal_properties               │        │
        │  │ • spouse_liabilities                       │        │
        │  │ • spouse_business_interests                │        │
        │  └────────────────────────────────────────────┘        │
        └───────────────────────────────────────────────────────┘
```

## Data Flow Example: Filing 2024 SALN with Joint Filing

```
1. USER selects:
   ┌─────────────────────┐
   │ Year: 2024          │
   │ Type: Joint Filing  │
   └─────────────────────┘
           │
           ▼
2. USER enters SALN data and saves
           │
           ▼
3. POST /update_saln_compliance
   {
     compliance_for: 2024,
     filing_type: "Joint Filing",
     declarant_position: "...",
     spouse_position: "..."
   }
           │
           ▼
4. SalnController::updateSalnCompliance()
           │
           ├─► Save Declarant record (compliance_for = 2024)
           │
           ├─► Save Spouse record (compliance_for = 2024)
           │
           ▼
5. SalnController::calculateNetWorth(user_id, 2024, "Joint Filing")
           │
           ├─► Query all properties WHERE compliance_for = 2024
           │   • Declarant real properties: ₱2,000,000
           │   • Declarant personal properties: ₱1,000,000
           │   • Spouse real properties: ₱1,500,000
           │   • Spouse personal properties: ₱1,000,000
           │
           ├─► Query all liabilities WHERE compliance_for = 2024
           │   • Declarant liabilities: ₱500,000
           │   • Spouse liabilities: ₱300,000
           │
           ▼
6. Calculate Net Worth:
   ┌─────────────────────────────────────────┐
   │ Declarant Assets:      ₱3,000,000       │
   │ Spouse Assets:         ₱2,500,000       │
   │ ────────────────────────────────────    │
   │ Total Assets:          ₱5,500,000       │
   │                                          │
   │ Declarant Liabilities: ₱  500,000       │
   │ Spouse Liabilities:    ₱  300,000       │
   │ ────────────────────────────────────    │
   │ Total Liabilities:     ₱  800,000       │
   │                                          │
   │ ════════════════════════════════════    │
   │ NET WORTH:             ₱4,700,000       │
   │ ════════════════════════════════════    │
   └─────────────────────────────────────────┘
           │
           ▼
7. Update Database:
   • saln_declarant.net_worth = ₱4,700,000
   • saln_declarant.total_assets = ₱5,500,000
   • saln_declarant.total_liabilities = ₱800,000
   • saln_spouse.net_worth = ₱2,200,000
           │
           ▼
8. Return to USER:
   {
     code: 200,
     message: "SALN saved",
     net_worth: {
       total_assets: ₱5,500,000,
       total_liabilities: ₱800,000,
       net_worth: ₱4,700,000
     }
   }
```

## Historical View Example

```
USER requests: GET /get_saln_years

Response:
┌────────┬──────────────┬──────────────┬─────────────┐
│ Year   │ Filing Type  │ Net Worth    │ Filed On    │
├────────┼──────────────┼──────────────┼─────────────┤
│ 2024   │ Joint Filing │ ₱4,700,000   │ 2024-12-31  │
│ 2023   │ Joint Filing │ ₱4,200,000   │ 2023-12-31  │
│ 2022   │ Separate     │ ₱2,800,000   │ 2022-12-31  │
└────────┴──────────────┴──────────────┴─────────────┘

USER clicks "View 2023"
   │
   ▼
GET /get_saln_by_year?compliance_for=2023
   │
   ▼
Returns ALL 2023 data:
• Declarant info as of 2023
• Spouse info as of 2023
• All 2023 properties
• All 2023 liabilities
• All 2023 children info
• etc.

USER clicks "PDF 2024"
   │
   ▼
GET /generate_yearly_saln_report?compliance_for=2024
   │
   ▼
Downloads: SALN_2024_John_Doe.pdf
```

## Key Benefits

✅ **Complete History**: Each year is independent and preserved  
✅ **Accurate Tracking**: Net worth calculated per year  
✅ **Joint Filing Support**: Spouse included when filing jointly  
✅ **Easy Comparison**: View trends across years  
✅ **Audit Trail**: Timestamps on all records  
✅ **Flexible Reports**: Generate PDF for any year  
