# 💊 PharmaCare - Medicine Store Management System

PharmaCare is a professional, high-performance Pharmacy Management System designed to streamline inventory tracking, sales processing (POS), and business analytics. Built with Core PHP and MySQL, it offers a premium user experience with a focus on security and proactive inventory management.

![Main Dashboard](screenshots/dashboard.png)

## 🚀 Key Features

- **Proactive Dashboard:** Real-time stats for revenue, profit, low stock alerts, and upcoming medicine expiries.
- **Dynamic POS System:** Fast billing interface with automated stock deduction and invoice generation.
- **Inventory Management:** Comprehensive tracking of medicines, batches, categories, and expiry dates.
- **Detailed Analytics:** Visual reports for revenue, profit, and top-selling medicines using Chart.js.
- **User Management & RBAC:** Role-based access control for Admins and Staff members.
- **Secure Architecture:** Built using PDO prepared statements to prevent SQL Injection and CSRF protection for all forms.
- **Premium UI:** Modern, responsive design featuring glassmorphism elements and smooth animations.

## 🛠️ Technology Stack

- **Backend:** PHP 8.x (Core)
- **Database:** MySQL (MariaDB)
- **Frontend:** HTML5, CSS3 (Vanilla + Modern Design System), Bootstrap 5
- **Charts:** Chart.js
- **Icons:** FontAwesome 6

## 📦 Installation Guide

To get this project running locally on your machine (using XAMPP):

1. **Clone the Repository:**
   ```bash
   git clone https://github.com/jalalakbar47/medecne-store.git
   ```

2. **Setup Database:**
   - Open **phpMyAdmin**.
   - Create a new database named `pharmacy_db`.
   - Import the `database.sql` file provided in the root directory.

3. **Configure the App:**
   - Open `config.php`.
   - Update `DB_USER`, `DB_PASS`, and `APP_URL` if they differ from your local setup.

4. **Run the Project:**
   - Move the project folder to `C:\xampp\htdocs\`.
   - Access the system via `http://localhost/medecne store`.

## 🔐 Login Credentials

| Role | Username | Password |
| :--- | :--- | :--- |
| **Admin** | `admin` | `admin123` |

## 📸 Visuals & Gallery

Place your screenshots in the `/screenshots` folder using the exact names below:

### Login Interface
![Login Page](screenshots/login_page.png)

### Proactive Dashboard
![Dashboard](screenshots/dashboard.png)

### Point of Sale (POS)
![POS System](screenshots/pos_system.png)

### Inventory Management
![Inventory](screenshots/inventory_management.png)

### Analytics & Reports
![Analytics](screenshots/analytics_reports.png)

### User & Staff Management
![User Management](screenshots/user_management.png)

## 🤝 Contributing

Contributions are welcome! Feel free to open an issue or submit a pull request.

## 📄 License

This project is open-source and available under the [MIT License](LICENSE).
