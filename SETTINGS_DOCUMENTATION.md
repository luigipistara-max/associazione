# Settings Page Documentation

## Overview
The Settings page allows administrators to configure all association data that will be used throughout the system including emails, PDF receipts, and the website footer.

## Features Implemented

### 1. Settings Database Table
- **Table Name**: `settings`
- **Structure**: Key-value storage with grouping
- **Fields**:
  - `id`: Auto-increment primary key
  - `setting_key`: Unique setting identifier
  - `setting_value`: Setting value (TEXT)
  - `setting_group`: Logical grouping (association, legal, address, etc.)
  - `updated_at`: Last update timestamp

### 2. Helper Functions (src/functions.php)
All functions are available globally:

#### Basic CRUD Functions
- `getSetting($key, $default)` - Retrieve a single setting value
- `setSetting($key, $value, $group)` - Save a single setting
- `getAllSettings()` - Get all settings as associative array
- `getSettingsByGroup($group)` - Get settings filtered by group
- `setSettings($settings)` - Batch save multiple settings

#### Specialized Functions
- `getAssociationAddress()` - Returns formatted multiline address
- `getAssociationInfo()` - Returns array with all association data
- `getBankDetails()` - Returns array with banking information
- `getEmailFooter()` - Returns HTML formatted email footer

### 3. Settings Page (public/settings.php)
**Access**: Admin only (protected by `requireAdmin()`)

#### Tabs Available:
1. **Associazione** - Basic association data
   - Nome associazione
   - Ragione sociale completa
   - Logo (file upload)
   - Slogan/Motto

2. **Rappresentante** - Legal representative
   - Nome e Cognome
   - Ruolo/Carica (e.g., "Presidente")
   - Codice Fiscale

3. **Sede** - Address and contacts
   - Indirizzo sede legale
   - CAP, Città, Provincia
   - Telefono, Email, PEC
   - Sito web

4. **Fiscali** - Fiscal data
   - Partita IVA
   - Codice Fiscale associazione
   - Numero REA
   - Registro APS/ETS

5. **Banca** - Banking details
   - IBAN (with Italian format validation)
   - Intestatario conto
   - Banca/Istituto
   - BIC/SWIFT

6. **PayPal** - PayPal integration
   - Email PayPal
   - PayPal.me link

7. **Email** - Email customization
   - Firma email
   - Footer email (legal info)
   - Preview of how footer will appear

### 4. File Upload System
- **Directory**: `public/uploads/`
- **Security**: `.htaccess` prevents PHP execution
- **Allowed formats**: JPG, JPEG, PNG, GIF
- **Naming**: Logo saved as `logo.{extension}`

### 5. Integration Points

#### Email Footer (src/email.php)
- Automatically appended to all emails sent via `sendEmailFromTemplate()`
- Includes association info, address, contacts
- Custom signature and legal disclaimer support

#### Receipts (src/pdf.php)
- Association data shown in receipt header
- Address and contact information
- Fiscal data (CF, P.IVA, REA) in footer
- Banking details (IBAN, Bank name) included

#### Site Footer (public/inc/footer.php)
- Association name and address
- Contact information (email, phone)
- Replaces static footer with dynamic content

### 6. Menu Integration
- New menu item "Impostazioni" added to admin section
- Icon: gear (⚙️)
- Position: At bottom of admin menu section
- Visibility: Admin only

## Settings Keys Reference

### Association Group
- `association_name` - Association name
- `association_full_name` - Full legal name
- `association_logo` - Logo file path
- `association_slogan` - Motto/slogan

### Legal Group
- `legal_representative_name` - Representative full name
- `legal_representative_role` - Position/role
- `legal_representative_cf` - Representative fiscal code

### Address Group
- `address_street` - Street address
- `address_cap` - Postal code
- `address_city` - City
- `address_province` - Province code (2 letters)

### Contacts Group
- `contact_phone` - Phone number
- `contact_email` - Email address
- `contact_pec` - Certified email (PEC)
- `contact_website` - Website URL

### Fiscal Group
- `fiscal_piva` - VAT number
- `fiscal_cf` - Association fiscal code
- `fiscal_rea` - REA number
- `fiscal_registry` - Registry info (e.g., "RUNTS n. 12345")

### Banking Group
- `bank_iban` - IBAN code
- `bank_holder` - Account holder name
- `bank_name` - Bank name
- `bank_bic` - BIC/SWIFT code

### PayPal Group
- `paypal_email` - PayPal email
- `paypal_me_link` - PayPal.Me link

### Email Group
- `email_signature` - Email signature text
- `email_footer` - Legal disclaimer/footer

## Usage Examples

### Setting Association Name
```php
setSetting('association_name', 'AssoLife Community', 'association');
```

### Getting Association Info
```php
$info = getAssociationInfo();
echo $info['name']; // Association name
echo $info['address']; // Formatted address
echo $info['fiscal_cf']; // Fiscal code
```

### Using in Email Templates
```php
// Email footer is automatically added
sendEmailFromTemplate('user@example.com', 'welcome_member', [
    'nome' => 'Mario',
    'cognome' => 'Rossi'
]);
// Footer with association data will be appended
```

### Batch Update
```php
setSettings([
    ['association_name', 'New Name', 'association'],
    ['contact_email', 'info@example.com', 'contacts'],
    ['bank_iban', 'IT60X0542811101000000123456', 'banking']
]);
```

## Validation

### Client-Side
- IBAN format: Pattern validation for Italian IBAN
- CAP: 5 digits
- Province: 2 uppercase letters
- Email: Standard email validation
- URL: Standard URL validation

### Server-Side
- File upload type checking (images only)
- Directory permissions validation
- SQL injection prevention via prepared statements

## Security Features

1. **Admin-Only Access**: `requireAdmin()` check at page entry
2. **File Upload Security**: 
   - Type validation (images only)
   - .htaccess prevents PHP execution in uploads
   - Controlled filename (logo.ext)
3. **XSS Prevention**: All output uses `h()` function (htmlspecialchars)
4. **SQL Injection Prevention**: PDO with prepared statements

## Future Enhancements
- CSRF token protection for form submissions
- Settings backup/restore functionality
- Import/export settings to JSON
- Multi-language support for email templates
- Logo image cropping/resizing
- Settings change audit logging
