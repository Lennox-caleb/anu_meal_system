# ANU Meal Booking System — PHP/MySQL Edition

## Project Structure
```
anu_meal_system/
├── index.php               ← Root redirect
├── login.php               ← Login page
├── logout.php              ← Logout handler
├── install.php             ← ONE-TIME database setup
│
├── includes/
│   ├── db.php              ← MySQL connection
│   ├── auth.php            ← Session/auth helpers
│   ├── sidebar.php         ← Admin sidebar partial
│   └── student_sidebar.php ← Student sidebar partial
│
├── admin/
│   ├── dashboard.php       ← Admin dashboard (charts, stats)
│   ├── bookings.php        ← Booking management (approve/reject)
│   ├── menu.php            ← Menu CRUD
│   ├── validation.php      ← Meal validation + QR scanning
│   ├── reports.php         ← Analytics, CSV & PDF export
│   ├── users.php           ← User management (Super Admin only)
│   └── settings.php        ← System settings (Super Admin only)
│
├── student/
│   ├── dashboard.php       ← Student home
│   ├── menu.php            ← Browse menus & book meals
│   ├── my_bookings.php     ← View bookings + QR code display
│   └── profile.php         ← Edit profile & change password
│
└── public/
    ├── css/
    │   └── style.css       ← All custom styles
    └── images/
        └── anu-logo.png    ← Place your ANU logo here
```

## Setup Instructions

### 1. Requirements
- PHP 7.4+ or PHP 8.x
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx with mod_rewrite
- XAMPP / WAMP / Laragon (local dev)

### 2. Install Steps
1. Copy this folder to your web server root (e.g., `htdocs/anu_meal_system`)
2. Edit `includes/db.php` with your database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');     // your MySQL user
   define('DB_PASS', '');          // your MySQL password
   define('DB_NAME', 'anu_meal_booking');
   ```
3. Open browser → `http://localhost/anu_meal_system/install.php`
4. This creates the database, all tables, and default users
5. **Delete `install.php` after setup!**

### 3. Default Login Credentials
| Role        | Username   | Password   |
|-------------|------------|------------|
| Super Admin | superadmin | admin123   |
| Admin       | admin      | admin123   |
| Student     | student    | student123 |

### 4. Add Your Logo
Place your ANU logo image at: `public/images/anu-logo.png`

---

## Features by Role

### Super Admin
- Full access to all pages
- User management (create/edit/delete all users)
- System settings configuration
- All admin features

### Admin (Cafeteria Staff)
- Dashboard with real-time stats & charts
- Booking management (approve/reject)
- Menu management (add/edit/delete meals)
- Meal validation (manual code or QR scan)
- Reports with CSV & PDF export

### Student
- View today's and upcoming menus
- Book meals with one click
- View bookings with QR codes for validation
- Profile management

---

## Technologies Used
- **Backend:** PHP 8 (PDO/MySQLi)
- **Database:** MySQL
- **Frontend:** Bootstrap 5.3, Bootstrap Icons
- **Charts:** Chart.js
- **QR Scan:** Html5-QRCode library
- **QR Generate:** QRCodeJS
- **PDF Export:** jsPDF + AutoTable
- **Color Theme:** Red (#ff0000), Yellow (#fac823), Black (#000000)

---

## System Objectives Addressed
1. ✅ Cafeteria staff can upload, update, and manage daily menus
2. ✅ Students view menus and book meals via mobile-responsive web
3. ✅ Staff can monitor and manage bookings (approve/reject/filter)
4. ✅ Secure QR-based meal validation restricts access to valid bookings
5. ✅ Reports with charts, CSV & PDF export for decision-making
