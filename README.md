# Restaurant Management System

A  restaurant management system built with **PHP**, **HTML**, and **CSS**   
Database: **Oracle**.



## Project Structure


RestaurantDB/
├── index.php                      ← Login page (entry point)
├── README.md
│
├── backend/
│   ├── config/
│   │   ├── db.php                 ← Oracle OCI8 connection
│   │   └── session.php            ← Session helpers
│   ├── includes/
│   │   ├── auth.php               ← Login / logout logic
│   │   ├── helpers.php            ← Utility functions
│   │   ├── customer.php           ← Customer CRUD
│   │   ├── category.php           ← Category CRUD
│   │   ├── menu_item.php          ← Menu Item CRUD
│   │   ├── employee.php           ← Employee CRUD
│   │   ├── app_user.php           ← App User CRUD
│   │   ├── order.php              ← Order + Order Items CRUD
│   │   ├── kitchen.php            ← Kitchen Order management
│   │   ├── payment.php            ← Payment CRUD
│   │   ├── inventory.php          ← Inventory CRUD
│   │   └── supplier.php           ← Supplier + Supplier-Inventory
│   └── database/
│       └── schema.sql             ← Oracle DDL (tables, sequences, sample data)
│
└── frontend/
    ├── assets/
    │   └── css/
    │       └── style.css          ← Global CSS (no frameworks)
    ├── includes/
    │   ├── header.php             ← Sidebar + topbar layout
    │   └── footer.php             ← Closing tags
    └── pages/
        ├── dashboard.php          ← Dashboard with stats
        ├── customers.php          ← Customer management
        ├── categories.php         ← Category management
        ├── menu_items.php         ← Menu item management
        ├── employees.php          ← Employee management
        ├── app_users.php          ← App user management
        ├── orders.php             ← Order management (create, view, status)
        ├── kitchen.php            ← Kitchen Kanban board
        ├── payments.php           ← Payment recording & tracking
        ├── inventory.php          ← Inventory with low-stock alerts
        ├── suppliers.php          ← Suppliers + inventory linking
        └── logout.php             ← Session destroy
```

---

## Setup Instructions

### 1. Oracle Database
1. Open `backend/database/schema.sql` in SQL*Plus or SQL Developer.
2. Run all statements against your Oracle schema.
3. Sequences are created automatically (CUSTOMER_SEQ, MENU_ITEM_SEQ, etc.)

### 2. Configure DB Connection
Edit `backend/config/db.php`:
```php
define('DB_HOST',     'localhost');
define('DB_PORT',     '1521');
define('DB_SERVICE',  'XE');          // your Oracle service name
define('DB_USER',     'RESTAURANT');  // your schema user
define('DB_PASSWORD', 'restaurant123');
```

### 3. PHP OCI8 Extension
Make sure PHP has the OCI8 extension enabled in `php.ini`:
```
extension=oci8
```
With XAMPP you may need to install Oracle Instant Client first.

### 4. Place files
Copy `RestaurantDB/` folder to `C:\xampp\htdocs\`.

### 5. Access
Open: `http://localhost/RestaurantDB/`

Default login (created by schema.sql):
- **Username:** `admin`
- **Password:** `admin123`

> After first login, go to **App Users → Edit** and change the password  
> (the system will hash it with `password_hash()`).

---

## Features

| Module | Features |
|---|---|
| Login / Logout | Session-based auth from APP_USER table |
| Dashboard | Live stats: customers, orders today, revenue, kitchen status, low stock |
| Customers | Add / Edit / Delete / Search |
| Categories | Add / Edit / Delete |
| Menu Items | Add / Edit / Delete, linked to category, availability toggle |
| Employees | Add / Edit / Delete, role & salary management |
| App Users | Create / Edit / Delete, bcrypt password hashing |
| Orders | Create with up to 8 items, status tracking, auto kitchen dispatch |
| Kitchen | Kanban board (Pending → Preparing → Ready), status updates |
| Payments | Record payments, status management (Paid / Unpaid / Refunded) |
| Inventory | Add / Edit / Delete, low-stock visual alerts |
| Suppliers | Add / Edit / Delete, inventory item linking |

---

## Technology Stack
- **Backend:** PHP 7.4+ with OCI8 (Oracle extension)
- **Frontend:** Pure HTML5 + CSS3 (no JavaScript, no frameworks)
- **Database:** Oracle (tested on Oracle XE 21c)
- **Server:** Apache via XAMPP
