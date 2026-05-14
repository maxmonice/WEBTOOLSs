DROP TABLE IF EXISTS user_promos;
DROP TABLE IF EXISTS promos;

CREATE TABLE promos (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    discount_percent INT NOT NULL DEFAULT 0,
    duration_days INT NULL,
    applicable_category VARCHAR(100) DEFAULT 'All Items',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_promos (
    user_id INT(10) UNSIGNED NOT NULL,
    promo_id INT(10) UNSIGNED NOT NULL,
    claimed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    PRIMARY KEY (user_id, promo_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (promo_id) REFERENCES promos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default promo
INSERT IGNORE INTO promos (code, discount_percent, duration_days) 
VALUES ('N3WUS3R', 30, NULL);
