BGS LPG INVENTORY AND SALES MANAGEMENT SYSTEM
================================================

REQUIREMENTS
- XAMPP with Apache, MySQL, and PHP (PHP 8+ recommended)
- Web browser

INSTALLATION
1. Extract the BGS_LPG_System folder into:
   C:\xampp\htdocs\BGS_LPG_System
2. Open XAMPP Control Panel and start Apache and MySQL.
3. Open http://localhost/phpmyadmin
4. Choose Import, select database.sql from this folder, and click Import/Go.
   (Alternatively open the SQL tab and paste the contents of database.sql.)
5. Open http://localhost/BGS_LPG_System/setup.php once.
6. Login at http://localhost/BGS_LPG_System/login.php
   Username: owner
   Password: password
7. DELETE setup.php after setup. Change the owner password by creating a new owner user in User Management, or update it through a secure password-reset process.

FEATURES
- Owner/staff login and staff registration
- Multiple branches
- Product catalog and minimum stock levels
- Stock-in recording
- Sales transactions with automatic inventory deduction
- Prevents sales when stock is insufficient
- Sales history and printable sale details
- Date-filtered sales and inventory reports
- Low-stock monitoring
- User management

IMPORTANT NOTES
- Set actual product prices in Products before recording sales.
- Add each branch under Branches, then create a staff account assigned to that branch.
- Owner accounts can see all branches. Staff are restricted to their assigned branch.
- This system records sales only. It does not collect cash/GCash payments, print official receipts, contact suppliers, place orders, or physically verify stock.
- This is a starter academic project. Test it with sample data before using for real business. Add CSRF protection, audit logging, backups, and stronger deployment security before production use.
