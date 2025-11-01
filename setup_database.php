<?php
// Simple database setup/repair utility
require __DIR__ . '/conf.php';

header('Content-Type: text/plain');

try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$conf['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$conf['db_name']}`");

    $stmts = [
        // users table
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fullname VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            is_verified TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // email_verifications table
        "CREATE TABLE IF NOT EXISTS email_verifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            code VARCHAR(10) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id),
            CONSTRAINT fk_ev_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // events table (minimal fields used by app)
        "CREATE TABLE IF NOT EXISTS events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            event_date DATETIME NOT NULL,
            location VARCHAR(255) NOT NULL,
            image VARCHAR(255) NULL,
            ticket_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            available_tickets INT NOT NULL DEFAULT 0,
            status ENUM('upcoming','ongoing','past','cancelled') NOT NULL DEFAULT 'upcoming',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX (user_id),
            CONSTRAINT fk_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // attendee categories (for NotificationManager joins)
        "CREATE TABLE IF NOT EXISTS attendee_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // event_categories link table
        "CREATE TABLE IF NOT EXISTS event_categories (
            event_id INT NOT NULL,
            category_id INT NOT NULL,
            PRIMARY KEY (event_id, category_id),
            CONSTRAINT fk_ec_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            CONSTRAINT fk_ec_cat FOREIGN KEY (category_id) REFERENCES attendee_categories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // tickets
        "CREATE TABLE IF NOT EXISTS tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            user_id INT NOT NULL,
            ticket_code VARCHAR(100) NOT NULL UNIQUE,
            status ENUM('active','used','cancelled') NOT NULL DEFAULT 'active',
            purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_t_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            CONSTRAINT fk_t_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // payments
        "CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            event_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            payment_method VARCHAR(50) NOT NULL,
            transaction_id VARCHAR(100) NULL,
            status ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
            mpesa_receipt_number VARCHAR(100) NULL,
            phone_number VARCHAR(20) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_p_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_p_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            INDEX (transaction_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // payment_tickets link table
        "CREATE TABLE IF NOT EXISTS payment_tickets (
            payment_id INT NOT NULL,
            ticket_id INT NOT NULL,
            PRIMARY KEY (payment_id, ticket_id),
            CONSTRAINT fk_pt_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
            CONSTRAINT fk_pt_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // event_attendees
        "CREATE TABLE IF NOT EXISTS event_attendees (
            event_id INT NOT NULL,
            user_id INT NOT NULL,
            status ENUM('going','interested','not_going') NOT NULL DEFAULT 'interested',
            category_id INT NULL,
            quantity INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (event_id, user_id),
            CONSTRAINT fk_ea_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            CONSTRAINT fk_ea_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // feedback
        "CREATE TABLE IF NOT EXISTS feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            user_id INT NOT NULL,
            rating TINYINT NOT NULL,
            comment TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_f_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            CONSTRAINT fk_f_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // password resets
        "CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(150) NOT NULL,
            token VARCHAR(100) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // notifications
        "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            event_id INT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) NOT NULL,
            notification_type VARCHAR(50) NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_n_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_n_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // user notification preferences
        "CREATE TABLE IF NOT EXISTS user_notification_preferences (
            user_id INT PRIMARY KEY,
            email_new_events TINYINT(1) NOT NULL DEFAULT 1,
            email_event_reminders TINYINT(1) NOT NULL DEFAULT 1,
            email_rsvp_updates TINYINT(1) NOT NULL DEFAULT 1,
            CONSTRAINT fk_unp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    ];

    foreach ($stmts as $sql) {
        $pdo->exec($sql);
    }

    echo "Database '{$conf['db_name']}' is ready.\n";
    echo "Tables created or verified successfully.";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Setup error: ' . $e->getMessage();
}

?>

