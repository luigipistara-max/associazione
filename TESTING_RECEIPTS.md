# Testing Receipt System

## Manual Testing Steps

### Prerequisites
1. Database must have the receipts table created (run migration 010_receipts_table.sql)
2. At least one member must exist in the system
3. At least one social year must exist
4. Admin account must be available

### Test 1: Create Receipt via Admin
**Steps:**
1. Login as admin to `/public/login.php`
2. Navigate to `/public/member_fees.php`
3. Click "Nuova Quota" to create a new fee for a member
4. Fill in the form with:
   - Member: Select any active member
   - Social Year: Select current year
   - Amount: Any amount (e.g., 50.00)
   - Due Date: Today's date or future
   - Status: pending
5. Click "Salva"
6. Find the newly created fee in the list
7. Click the green checkmark button (✓) to mark as paid
8. In the modal that opens:
   - Select a payment method (e.g., "In contanti presso la sede sociale")
   - Optionally add payment details
   - Click "Conferma Pagamento"
9. Verify success message shows "ricevuta generata automaticamente"

**Expected Results:**
- Fee status changes to "Pagato"
- A new receipt is created in the receipts table
- Receipt number format is YYYY/NNNN (e.g., 2026/0001)
- Income movement is created in the income table

**SQL Verification:**
```sql
SELECT * FROM receipts ORDER BY id DESC LIMIT 5;
-- Should show the newly created receipt with:
-- - receipt_number in format 2026/0001
-- - member_id matching the fee
-- - amount matching the fee
-- - payment_method matching selection
-- - description containing "Quota associativa"
```

### Test 2: Member Portal - View Receipts
**Steps:**
1. Ensure the member from Test 1 has portal access (portal_password set)
2. Login to portal at `/public/portal/login.php` using member's email and password
3. Navigate to "Ricevute" from the menu
4. Verify the receipt appears in the list

**Expected Results:**
- Receipt list shows all member's receipts
- Each receipt displays:
  - Receipt number (YYYY/NNNN format)
  - Issue date
  - Description
  - Amount (formatted as € XX,XX)
  - Payment method details
  - Download PDF button

### Test 3: Member Portal - Download PDF
**Steps:**
1. From the receipts list (Test 2)
2. Click "Scarica PDF" button
3. New tab opens with receipt in printable format

**Expected Results:**
- Receipt displays with:
  - Association header information
  - Receipt number prominent
  - Date of issue
  - Member information (name, address, fiscal code)
  - Amount in large font
  - Payment method details
  - Association footer
- Print button works
- Back button returns to receipts list

### Test 4: Security - Member Can Only See Own Receipts
**Steps:**
1. Login as Member A to portal
2. Navigate to receipts page
3. Note one of the receipt IDs in the URL when clicking "Scarica PDF"
4. Logout
5. Login as Member B (different member)
6. Try to manually access the receipt from Member A by entering URL:
   `/public/portal/receipt_pdf.php?id={Member_A_receipt_id}`

**Expected Results:**
- Access is denied
- Error message: "Ricevuta non trovata"
- Member B cannot see Member A's receipts

### Test 5: Receipt Number Sequence
**Steps:**
1. Mark 3 different fees as paid in sequence
2. Check receipt numbers generated

**Expected Results:**
- First receipt: 2026/0001
- Second receipt: 2026/0002
- Third receipt: 2026/0003
- No duplicates
- Sequential numbering within the year

### Test 6: Payment Method Variations
**Steps:**
1. Create fees and mark them as paid with different payment methods:
   - Cash
   - Bank transfer
   - Card
   - PayPal
   - Other
2. Check receipts in member portal

**Expected Results:**
- Each receipt shows correct payment method details:
  - Cash: "In contanti presso la sede sociale"
  - Bank transfer: "Bonifico bancario"
  - Card: "Pagamento con carta"
  - PayPal: "Pagamento PayPal"
  - Other: "Altro metodo di pagamento"

### Test 7: Duplicate Receipt Prevention
**Steps:**
1. Mark a fee as paid (generates receipt)
2. Try to generate another receipt for the same fee

**SQL Test:**
```sql
-- Assuming fee_id = 1
SELECT generateReceipt(1, 'cash', NULL, 1);
-- Call again
SELECT generateReceipt(1, 'cash', NULL, 1);
```

**Expected Results:**
- Second call returns FALSE
- No duplicate receipt is created
- Only one receipt exists for the fee

## Database Queries for Verification

### Check receipts table structure
```sql
DESCRIBE receipts;
```

### View all receipts
```sql
SELECT 
    r.receipt_number,
    r.issue_date,
    m.first_name,
    m.last_name,
    r.amount,
    r.payment_method,
    r.payment_method_details,
    r.description
FROM receipts r
JOIN members m ON r.member_id = m.id
ORDER BY r.issue_date DESC, r.id DESC;
```

### Check receipt-fee relationship
```sql
SELECT 
    r.receipt_number,
    mf.id as fee_id,
    mf.status,
    mf.paid_date,
    m.first_name,
    m.last_name
FROM receipts r
JOIN member_fees mf ON r.member_fee_id = mf.id
JOIN members m ON r.member_id = m.id;
```

## Known Limitations

1. **Old Receipt System**: The old system in `public/receipt.php` still uses member_fees.receipt_number. This is for backwards compatibility.
2. **Migration**: Existing receipts in member_fees.receipt_number are NOT automatically migrated to the new receipts table.
3. **Portal Menu**: The menu link "Ricevute" was already present in the portal header, pointing to the correct page.

## Notes

- All new receipts use the YYYY/NNNN format as specified
- The old RIC-YYYY-NNNNN format is still in use by the legacy system
- Payment methods are stored as ENUM values but displayed with friendly Italian text
- Receipt generation is automatic when marking a fee as paid through the admin interface
