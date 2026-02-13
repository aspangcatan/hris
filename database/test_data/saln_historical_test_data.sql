-- SALN Historical Records - Sample Test Data
-- Run this after migration to populate test data for testing

-- Note: Replace USER_ID with your actual user ID from the users table
SET @USER_ID = 1; -- Change this to your user ID

-- ============================================
-- 2023 SALN Data (Separate Filing)
-- ============================================

-- Declarant 2023
INSERT INTO saln_declarant (user_id, position, agency, office, government_id_issued, government_id_no, date_issued, compliance_for, filing_type, total_assets, total_liabilities, net_worth, created_at, updated_at)
VALUES (@USER_ID, 'Division Chief', 'Department of Health', 'Main Office', 'UMID', '1234567890', '2020-01-01', 2023, 'Separate Filing', 3500000.00, 700000.00, 2800000.00, '2023-12-31 10:00:00', '2023-12-31 10:00:00');

-- Real Properties 2023
INSERT INTO real_properties (user_id, description, kind, exact_location, assessed_value, current_fair, acquisition_year, acquisition_mode, acquisition_cost, compliance_for)
VALUES (@USER_ID, 'House and Lot', 'Residential', '123 Main Street, Manila', 1500000, 2000000, 2018, 'Purchase', 1800000, 2023);

INSERT INTO real_properties (user_id, description, kind, exact_location, assessed_value, current_fair, acquisition_year, acquisition_mode, acquisition_cost, compliance_for)
VALUES (@USER_ID, 'Condominium Unit', 'Residential', 'Makati City', 800000, 1200000, 2020, 'Purchase', 1000000, 2023);

-- Personal Properties 2023
INSERT INTO personal_properties (user_id, gadget, various_years, acquisition_cost, compliance_for)
VALUES (@USER_ID, 'Toyota Vios 2020', 2020, 600000, 2023);

INSERT INTO personal_properties (user_id, gadget, various_years, acquisition_cost, compliance_for)
VALUES (@USER_ID, 'Jewelry and Electronics', 2019, 100000, 2023);

-- Liabilities 2023
INSERT INTO liabilities (user_id, nature, creditors_name, outstanding_balance, compliance_for)
VALUES (@USER_ID, 'Home Loan', 'BPI', 500000, 2023);

INSERT INTO liabilities (user_id, nature, creditors_name, outstanding_balance, compliance_for)
VALUES (@USER_ID, 'Car Loan', 'BDO', 200000, 2023);

-- Children 2023
INSERT INTO saln_children (user_id, name, birthdate, compliance_for)
VALUES (@USER_ID, 'John Doe Jr.', '2015-05-15', 2023);

INSERT INTO saln_children (user_id, name, birthdate, compliance_for)
VALUES (@USER_ID, 'Jane Doe', '2017-08-20', 2023);

-- ============================================
-- 2024 SALN Data (Joint Filing)
-- ============================================

-- Declarant 2024
INSERT INTO saln_declarant (user_id, position, agency, office, government_id_issued, government_id_no, date_issued, compliance_for, filing_type, total_assets, total_liabilities, net_worth, created_at, updated_at)
VALUES (@USER_ID, 'Division Chief', 'Department of Health', 'Main Office', 'UMID', '1234567890', '2020-01-01', 2024, 'Joint Filing', 5500000.00, 800000.00, 4700000.00, '2024-12-31 10:00:00', '2024-12-31 10:00:00');

-- Spouse 2024 (for Joint Filing)
INSERT INTO saln_spouse (user_id, position, agency, office, government_id_issued, government_id_no, date_issued, compliance_for, total_assets, total_liabilities, net_worth, created_at, updated_at)
VALUES (@USER_ID, 'Data Scientist', 'Private Sector Company', 'BGC Branch', 'PhilHealth ID', '0987654321', '2019-06-15', 2024, 2500000.00, 300000.00, 2200000.00, '2024-12-31 10:00:00', '2024-12-31 10:00:00');

-- Declarant Real Properties 2024
INSERT INTO real_properties (user_id, description, kind, exact_location, assessed_value, current_fair, acquisition_year, acquisition_mode, acquisition_cost, compliance_for)
VALUES (@USER_ID, 'House and Lot', 'Residential', '123 Main Street, Manila', 1500000, 2200000, 2018, 'Purchase', 1800000, 2024);

INSERT INTO real_properties (user_id, description, kind, exact_location, assessed_value, current_fair, acquisition_year, acquisition_mode, acquisition_cost, compliance_for)
VALUES (@USER_ID, 'Condominium Unit', 'Residential', 'Makati City', 800000, 1300000, 2020, 'Purchase', 1000000, 2024);

-- Declarant Personal Properties 2024
INSERT INTO personal_properties (user_id, gadget, various_years, acquisition_cost, compliance_for)
VALUES (@USER_ID, 'Toyota Vios 2020', 2020, 200000, 2024); -- Depreciated value

INSERT INTO personal_properties (user_id, gadget, various_years, acquisition_cost, compliance_for)
VALUES (@USER_ID, 'Jewelry and Electronics', 2019, 100000, 2024);

-- Declarant Liabilities 2024
INSERT INTO liabilities (user_id, nature, creditors_name, outstanding_balance, compliance_for)
VALUES (@USER_ID, 'Home Loan', 'BPI', 400000, 2024); -- Reduced from 2023

INSERT INTO liabilities (user_id, nature, creditors_name, outstanding_balance, compliance_for)
VALUES (@USER_ID, 'Car Loan', 'BDO', 100000, 2024); -- Reduced from 2023

-- Spouse Real Properties 2024
INSERT INTO spouse_real_properties (user_id, description, kind, exact_location, assessed_value, current_fair, acquisition_year, acquisition_mode, acquisition_cost, compliance_for)
VALUES (@USER_ID, 'Townhouse', 'Residential', 'Quezon City', 1000000, 1500000, 2019, 'Purchase', 1200000, 2024);

-- Spouse Personal Properties 2024
INSERT INTO spouse_personal_properties (user_id, gadget, various_years, acquisition_cost, compliance_for)
VALUES (@USER_ID, 'Honda City 2021', 2021, 800000, 2024);

INSERT INTO spouse_personal_properties (user_id, gadget, various_years, acquisition_cost, compliance_for)
VALUES (@USER_ID, 'Laptop and Gadgets', 2022, 150000, 2024);

INSERT INTO spouse_personal_properties (user_id, gadget, various_years, acquisition_cost, compliance_for)
VALUES (@USER_ID, 'Furniture and Appliances', 2020, 350000, 2024);

-- Spouse Liabilities 2024
INSERT INTO spouse_liabilities (user_id, nature, creditors_name, outstanding_balance, compliance_for)
VALUES (@USER_ID, 'Car Loan', 'Metrobank', 300000, 2024);

-- Children 2024
INSERT INTO saln_children (user_id, name, birthdate, compliance_for)
VALUES (@USER_ID, 'John Doe Jr.', '2015-05-15', 2024);

INSERT INTO saln_children (user_id, name, birthdate, compliance_for)
VALUES (@USER_ID, 'Jane Doe', '2017-08-20', 2024);

-- Business Interest 2024
INSERT INTO business_interests (user_id, enterprise, address, nature, date_acquisition, compliance_for)
VALUES (@USER_ID, 'Sample Consulting Services', 'Manila', 'Consultancy', '2023-03-15', 2024);

-- Government Relatives 2024
INSERT INTO government_relatives (user_id, name, relationship, position, agency, compliance_for)
VALUES (@USER_ID, 'Juan Dela Cruz', 'Uncle', 'Bureau Director', 'DOH', 2024);

-- ============================================
-- Summary Query to Verify Data
-- ============================================

-- View all SALN years for user
SELECT 
    compliance_for AS year,
    filing_type,
    CONCAT('₱', FORMAT(total_assets, 2)) AS total_assets,
    CONCAT('₱', FORMAT(total_liabilities, 2)) AS total_liabilities,
    CONCAT('₱', FORMAT(net_worth, 2)) AS net_worth,
    created_at
FROM saln_declarant
WHERE user_id = @USER_ID
ORDER BY compliance_for DESC;

-- ============================================
-- Cleanup (if needed)
-- ============================================

-- To remove test data:
-- DELETE FROM saln_declarant WHERE user_id = @USER_ID AND compliance_for IN (2023, 2024);
-- DELETE FROM saln_spouse WHERE user_id = @USER_ID AND compliance_for IN (2023, 2024);
-- DELETE FROM real_properties WHERE user_id = @USER_ID AND compliance_for IN (2023, 2024);
-- DELETE FROM personal_properties WHERE user_id = @USER_ID AND compliance_for IN (2023, 2024);
-- DELETE FROM liabilities WHERE user_id = @USER_ID AND compliance_for IN (2023, 2024);
-- DELETE FROM spouse_real_properties WHERE user_id = @USER_ID AND compliance_for IN (2023, 2024);
-- DELETE FROM spouse_personal_properties WHERE user_id = @USER_ID AND compliance_for IN (2023, 2024);
-- DELETE FROM spouse_liabilities WHERE user_id = @USER_ID AND compliance_for IN (2023, 2024);
-- DELETE FROM saln_children WHERE user_id = @USER_ID AND compliance_for IN (2023, 2024);
-- DELETE FROM business_interests WHERE user_id = @USER_ID AND compliance_for IN (2023, 2024);
-- DELETE FROM government_relatives WHERE user_id = @USER_ID AND compliance_for IN (2023, 2024);
