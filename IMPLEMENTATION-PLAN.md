# Franklin Air Arkansas - HVAC Design Order Portal Implementation Plan

## Status: COMPLETE

**Constraint**: InfinityFree hosting (PHP + MySQL, no SSH, no Composer, no cron, FTP-only). All PHP is zero-dependency vanilla PHP with PDO and cURL for Resend API.

---

## Phase 1: Order Wizard Page + API Endpoints - COMPLETE

- [x] `order.html` - 5-step wizard form page with service selection, project info, building details, file upload, review & submit
- [x] `api/submit-order.php` - Order submission endpoint (sanitize, validate, price server-side, create user, generate order number, handle files, send emails)
- [x] `api/upload-file.php` - Standalone file upload endpoint for portal use

## Phase 2: Customer Portal - COMPLETE

- [x] `portal/login.php` - Login form with CSRF, error handling, reset password link
- [x] `portal/dashboard.php` - Order list with status badges, pricing, timestamps
- [x] `portal/order.php` - Order detail: summary, project data, files, notes thread
- [x] `portal/logout.php` - Session destroy + redirect
- [x] `css/portal.css` - Portal-specific styles (login, dashboard, order detail, badges, notes)

## Phase 3: Admin Portal - COMPLETE

- [x] `admin/login.php` - Admin-only login with role check
- [x] `admin/index.php` - Dashboard with stats, order table, status filters
- [x] `admin/order.php` - Full order management (status update, file upload, notes, status history)
- [x] `css/admin.css` - Admin styles (header, stats, tables, detail cards, forms)

## Phase 4: Config Updates + File Protection - COMPLETE

- [x] `config.php` - Added MySQL credential placeholders (db_host, db_name, db_user, db_pass)
- [x] `.htaccess` - Added protection for schema.sql, includes/ directory, uploads/ directory
- [x] `.github/workflows/deploy.yml` - Added config.php, schema.sql, uploads/** to exclude list
- [x] `professionals.html` - Updated CTA buttons to link to order wizard with service params

## Phase 5: File Download + Password Reset - COMPLETE

- [x] `api/download-file.php` - Secure file download with access control (path traversal prevention, role-based access)
- [x] `portal/reset-password.php` - Dual-mode: request reset link (sends branded email) + set new password with token

---

## Complete File Inventory (31 files)

### Core Pages
| File | Type | Description |
|------|------|-------------|
| `index.html` | HTML | Homepage |
| `professionals.html` | HTML | Services page with pricing |
| `order.html` | HTML | 5-step order wizard |
| `contact.php` | PHP | Contact form handler |

### Database & Config
| File | Type | Description |
|------|------|-------------|
| `schema.sql` | SQL | 6 MySQL tables |
| `config.php` | PHP | API keys + DB credentials |
| `.htaccess` | Config | File/directory protection |
| `.github/workflows/deploy.yml` | YAML | FTP deploy with exclusions |

### PHP Includes (shared logic)
| File | Description |
|------|-------------|
| `includes/db.php` | PDO connection singleton |
| `includes/auth.php` | Session auth (login, register, password reset) |
| `includes/email.php` | Resend API (order notifications, confirmations, status updates) |
| `includes/validation.php` | Sanitization, CSRF, file validation |
| `includes/pricing.php` | Service pricing (single source of truth) |
| `includes/helpers.php` | Order numbers, file upload, formatters |

### API Endpoints
| File | Description |
|------|-------------|
| `api/submit-order.php` | Order submission (wizard form POST) |
| `api/upload-file.php` | File upload (portal) |
| `api/download-file.php` | Secure file download |

### Customer Portal
| File | Description |
|------|-------------|
| `portal/login.php` | Customer login |
| `portal/dashboard.php` | Order list |
| `portal/order.php` | Order detail + notes |
| `portal/logout.php` | Session destroy |
| `portal/reset-password.php` | Password reset flow |

### Admin Portal
| File | Description |
|------|-------------|
| `admin/login.php` | Admin login |
| `admin/index.php` | Admin dashboard (all orders) |
| `admin/order.php` | Order management |

### CSS & JS
| File | Description |
|------|-------------|
| `css/style.css` | Design system + global styles |
| `css/professionals.css` | Professionals page styles |
| `css/wizard.css` | Order wizard styles |
| `css/portal.css` | Customer portal styles |
| `css/admin.css` | Admin portal styles |
| `js/main.js` | Nav toggle, scroll animations, contact form |
| `js/wizard.js` | Wizard logic, validation, pricing calculator |

---

## Deployment Steps

1. **Create MySQL database** on InfinityFree via phpMyAdmin
2. **Run `schema.sql`** in phpMyAdmin to create all tables
3. **Update `config.php`** on server with actual DB credentials (host, name, user, password)
4. **Create admin user** via phpMyAdmin: INSERT into users with role='admin', password_hash from `password_hash('your-password', PASSWORD_DEFAULT)`
5. **Create `uploads/` directory** on server with write permissions
6. **Push to GitHub** - deploy.yml will auto-deploy via FTP (excluding config.php, schema.sql, uploads/)
7. **Verify** the Resend API key domain is configured for `franklinairarkansas.com`

---

*Implementation completed: February 22, 2026*
