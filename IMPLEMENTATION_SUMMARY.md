# Settings Feature Implementation Summary

## Overview
Implemented a comprehensive settings management system for association configuration that integrates across the entire application (emails, receipts, website).

## Changes Made

### 1. Database Schema (`schema.sql`)
**Added new table:**
```sql
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(50) DEFAULT 'general',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key),
    INDEX idx_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Helper Functions (`src/functions.php`)
**Added 9 new functions:**

| Function | Purpose |
|----------|---------|
| `getSetting($key, $default)` | Retrieve single setting value |
| `getAllSettings()` | Get all settings as array |
| `getSettingsByGroup($group)` | Get settings by group |
| `setSetting($key, $value, $group)` | Save single setting |
| `setSettings($settings)` | Batch save multiple settings |
| `getAssociationAddress()` | Formatted multiline address |
| `getAssociationInfo()` | Complete association data array |
| `getBankDetails()` | Banking information array |
| `getEmailFooter()` | HTML formatted email footer |

### 3. Settings Page (`public/settings.php`)
**New admin-only page with 7 configuration tabs:**

1. **Associazione** - Name, legal name, logo, slogan
2. **Rappresentante** - Legal representative details
3. **Sede** - Address and contact information  
4. **Fiscali** - Tax and fiscal data
5. **Banca** - Banking details with IBAN
6. **PayPal** - PayPal integration options
7. **Email** - Email signature and footer with preview

**Features:**
- Bootstrap 5 tabbed interface
- File upload for logo
- Client-side validation (IBAN, CAP, etc.)
- Real-time email footer preview
- Responsive design

### 4. File Upload System
**Created:**
- `/public/uploads/` directory
- `.htaccess` - Prevents PHP execution, allows images
- `.gitkeep` - Preserves directory in git

**Security:**
- Only image files allowed (jpg, jpeg, png, gif)
- PHP execution blocked via .htaccess
- Standardized filename (logo.ext)

### 5. Email Integration (`src/email.php`)
**Modified `sendEmailFromTemplate()`:**
- Automatically appends `getEmailFooter()` to all template emails
- Includes association info, address, contacts
- Supports custom signature and legal disclaimer

### 6. Receipt Integration (`src/pdf.php`)
**Modified `generateReceiptHTML()`:**
- Loads association data from settings
- Displays association address and contact info
- Shows fiscal data (CF, P.IVA, REA)
- Includes banking details (IBAN, bank name)

### 7. Site Footer (`public/inc/footer.php`)
**Replaced static footer with dynamic content:**
- Association name
- Address (if configured)
- Email (if configured)
- Phone (if configured)
- Maintains "Powered by AssoLife" branding

### 8. Menu Navigation (`public/inc/header.php`)
**Added new menu item:**
- Title: "Impostazioni"
- Icon: Gear (⚙️)
- Position: Bottom of admin section
- Access: Admin only

### 9. Git Configuration (`.gitignore`)
**Updated rules:**
```
# Upload directories (if any)
uploads/
# But keep .htaccess and .gitkeep
!public/uploads/.htaccess
!public/uploads/.gitkeep
```

## Settings Schema

### All Available Settings

| Key | Group | Description |
|-----|-------|-------------|
| `association_name` | association | Association name |
| `association_full_name` | association | Full legal name |
| `association_logo` | association | Logo file path |
| `association_slogan` | association | Motto/slogan |
| `legal_representative_name` | legal | Representative name |
| `legal_representative_role` | legal | Position/role |
| `legal_representative_cf` | legal | Rep. fiscal code |
| `address_street` | address | Street address |
| `address_cap` | address | Postal code |
| `address_city` | address | City |
| `address_province` | address | Province (2 letters) |
| `contact_phone` | contacts | Phone number |
| `contact_email` | contacts | Email address |
| `contact_pec` | contacts | Certified email |
| `contact_website` | contacts | Website URL |
| `fiscal_piva` | fiscal | VAT number |
| `fiscal_cf` | fiscal | Fiscal code |
| `fiscal_rea` | fiscal | REA number |
| `fiscal_registry` | fiscal | Registry info |
| `bank_iban` | banking | IBAN code |
| `bank_holder` | banking | Account holder |
| `bank_name` | banking | Bank name |
| `bank_bic` | banking | BIC/SWIFT |
| `paypal_email` | paypal | PayPal email |
| `paypal_me_link` | paypal | PayPal.Me link |
| `email_signature` | email | Email signature |
| `email_footer` | email | Legal disclaimer |

## Integration Points

### Where Settings Are Used

1. **Email System**
   - Footer automatically added to all template emails
   - Shows association info and contacts
   - Includes custom signature and legal text

2. **PDF Receipts**
   - Association name in header
   - Full address and contact details
   - Fiscal data section (CF, P.IVA, REA)
   - Banking information (IBAN, bank)

3. **Website Footer**
   - Association name and branding
   - Contact information
   - Dynamic based on configured settings

## Security Measures

1. **Access Control**
   - Page protected by `requireAdmin()`
   - Only administrators can view/edit

2. **File Upload**
   - Type validation (images only)
   - .htaccess prevents PHP execution
   - Controlled naming convention

3. **XSS Prevention**
   - All output uses `h()` function
   - htmlspecialchars with ENT_QUOTES

4. **SQL Injection**
   - PDO with prepared statements
   - No direct query concatenation

5. **Input Validation**
   - Client-side patterns (IBAN, CAP, etc.)
   - Server-side type checking

## Testing Checklist

- [x] PHP syntax validation (all files pass)
- [ ] Database migration (settings table creation)
- [ ] Settings CRUD operations
- [ ] File upload functionality
- [ ] Email footer integration
- [ ] Receipt PDF generation
- [ ] Site footer display
- [ ] Menu item visibility (admin only)
- [ ] Form validation (client-side)
- [ ] Security measures (file upload, XSS, SQL)

## Usage Examples

### Setting Association Data
```php
setSetting('association_name', 'My Association', 'association');
setSetting('contact_email', 'info@myassoc.org', 'contacts');
setSetting('bank_iban', 'IT60X0542811101000000123456', 'banking');
```

### Getting Association Info
```php
$info = getAssociationInfo();
echo $info['name'];     // Association name
echo $info['address'];  // Formatted address
echo $info['fiscal_cf']; // Fiscal code
```

### Using in Templates
```php
// Email footer is automatically appended
sendEmailFromTemplate($email, 'welcome_member', $variables);

// Receipt includes association data automatically
$html = generateReceiptHTML($feeId);
```

## Files Changed Summary

| File | Lines Added | Lines Removed | Purpose |
|------|-------------|---------------|---------|
| `schema.sql` | 11 | 0 | Settings table |
| `src/functions.php` | 245 | 0 | Helper functions |
| `public/settings.php` | 420 | 0 | Settings page |
| `src/email.php` | 3 | 0 | Footer integration |
| `src/pdf.php` | 25 | 5 | Receipt integration |
| `public/inc/header.php` | 4 | 0 | Menu item |
| `public/inc/footer.php` | 20 | 3 | Dynamic footer |
| `.gitignore` | 3 | 1 | Upload rules |

**Total:** ~730 lines added, ~10 lines removed

## Next Steps for Deployment

1. Run database migration to create settings table
2. Access `/public/settings.php` as admin
3. Configure association data
4. Upload logo (optional)
5. Test email sending to verify footer
6. Generate a receipt to verify integration
7. Check site footer for dynamic content

## Documentation

Complete documentation available in:
- `SETTINGS_DOCUMENTATION.md` - Full API and usage guide
- Inline code comments in all modified files
- This summary document

## Compliance with Requirements

✅ All requirements from problem statement met:
- Settings page with 7 sections/tabs
- Database table with key-value structure  
- Helper functions for all operations
- Logo upload with security
- Email footer integration
- Receipt integration
- Site footer integration
- Admin-only menu item
- Security measures implemented
- Responsive Bootstrap UI
