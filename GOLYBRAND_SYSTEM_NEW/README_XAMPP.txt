GOLYBRAND AGENCIES - LOCAL XAMPP BUILD

This package is configured for local testing with XAMPP before choosing a hosting provider.

1. Copy the GOLYBRAND_SYSTEM_NEW folder to:
   C:\xampp\htdocs\

2. Start Apache and MySQL in XAMPP.

3. Open:
   http://localhost/phpmyadmin/

4. Import:
   database/database.sql

   The database created is:
   golybrand_new

5. Open the user system:
   http://localhost/GOLYBRAND_SYSTEM_NEW/frontend/index.html

6. Open the admin panel:
   http://localhost/GOLYBRAND_SYSTEM_NEW/frontend/admin.html

LOCAL DATABASE CONFIG
The project is already configured for the normal XAMPP setup:
   DB_HOST = localhost
   DB_NAME = golybrand_new
   DB_USER = root
   DB_PASS = blank

ADMIN REGISTRATION
The local administrator setup key is:
   GolyBrandAdmin2026!

Use it on the Admin Panel -> Register New Admin page.
Create your own admin username and password.

IMPORTANT: Change ADMIN_REGISTRATION_KEY and the database credentials before public deployment.

USER FLOW
Register
 -> Payment instructions
 -> Submit M-Pesa reference
 -> Pending admin approval
 -> Admin approves
 -> User becomes active
 -> User can log in
 -> Dashboard

If an administrator approves the user while the user is waiting on the payment screen, refreshing the page now automatically detects the activation and opens the dashboard.

REFERRALS
Level 1 = Ksh 500
Level 2 = Ksh 300
Level 3 = Ksh 100

Only activated users count toward referral bonuses.
A user cannot refer themselves.

DASHBOARD
The dashboard now displays Available Balance only.
Team earnings and referral details remain available under My Team.

WITHDRAWALS
Minimum = Ksh 500.
The server calculates the available balance and validates every withdrawal request.
Pending and approved withdrawals reduce available balance; rejected withdrawals return to the available balance.

ADMIN
The admin panel can:
- View statistics
- View pending activations
- Approve/reject payment references
- View all users
- View payment references
- View withdrawals
- Approve/reject withdrawals

M-PESA
This project records the M-Pesa/payment reference for manual verification.
It does NOT connect to Safaricom APIs or independently verify payments.


CONTENT MANAGEMENT
The admin panel now includes Manage Content. The admin can create, edit, hide/show and delete Trivia Questions, Forex Classes, E-books, TikTok Videos and Best Agent Awards. Content tables are also created automatically by the PHP backend if they do not already exist.
