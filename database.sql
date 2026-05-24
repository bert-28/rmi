CREATE DATABASE IF NOT EXISTS resurrection_music;
USE resurrection_music;

-- USERS
CREATE TABLE users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(100)  NOT NULL,
    email         VARCHAR(150)  NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    role          ENUM('customer','staff','admin') NOT NULL DEFAULT 'customer',
    contact       VARCHAR(20),
    address       TEXT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- PRODUCTS
CREATE TABLE products (
    product_id   INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(150) NOT NULL,
    category     ENUM('Guitar','Bass','Amplifier','Strings','Pedal','Accessory','Other') NOT NULL,
    description  TEXT,
    price        DECIMAL(10,2) NOT NULL,
    stock_qty    INT NOT NULL DEFAULT 0,
    image_url    VARCHAR(255),
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- SALES
CREATE TABLE sales (
    sale_id        INT AUTO_INCREMENT PRIMARY KEY,
    customer_id    INT,
    staff_id       INT,
    total_amount   DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash','GCash','Card','Bank Transfer') NOT NULL DEFAULT 'Cash',
    sale_date      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes          TEXT,
    FOREIGN KEY (customer_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (staff_id)    REFERENCES users(user_id) ON DELETE SET NULL
);

-- SALE ITEMS (line items)
CREATE TABLE sale_items (
    item_id    INT AUTO_INCREMENT PRIMARY KEY,
    sale_id    INT NOT NULL,
    product_id INT NOT NULL,
    quantity   INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal   DECIMAL(10,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    FOREIGN KEY (sale_id)    REFERENCES sales(sale_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE RESTRICT
);

-- SERVICES
CREATE TABLE service_requests (
    service_id     INT AUTO_INCREMENT PRIMARY KEY,
    customer_id    INT,
    assigned_staff INT,
    service_type   ENUM('Setup','Repair','String Replacement','Electronics','Cleaning','Other') NOT NULL,
    description    TEXT,
    status         ENUM('Pending','Ongoing','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
    estimated_cost DECIMAL(10,2),
    actual_cost    DECIMAL(10,2),
    date_requested TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_completed TIMESTAMP NULL,
    notes          TEXT,
    FOREIGN KEY (customer_id)    REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_staff) REFERENCES users(user_id) ON DELETE SET NULL
);

-- APPOINTMENTS

CREATE TABLE appointments (
    appt_id      INT AUTO_INCREMENT PRIMARY KEY,
    customer_id  INT,
    staff_id     INT,
    appt_type    ENUM('Lesson','Repair','Consultation') NOT NULL DEFAULT 'Lesson',
    appt_date    DATE NOT NULL,
    appt_time    TIME NOT NULL,
    duration_min INT NOT NULL DEFAULT 60,
    status       ENUM('Pending','Confirmed','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
    notes        TEXT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id)    REFERENCES users(user_id) ON DELETE SET NULL
);


-- Admin user
INSERT INTO users (full_name, email, password_hash, role, contact) VALUES
('Admin User',       'admin@resurrection.ph',  '$2y$12$Y5h8u4n9VqJXkL2mP0eOzOWY3iJz3tqL5dK8uNqAk6v7rE1cBmH3e', 'admin',    '09171234567'),

-- Sample products
INSERT INTO products (product_name, category, description, price, stock_qty) VALUES
('Fender Stratocaster Standard',   'Guitar',     'Classic SSS pickup configuration, maple neck', 45000.00, 5),
('Gibson Les Paul Standard',       'Guitar',     'Mahogany body, dual humbucker pickups',         85000.00, 3),
('Boss DS-1 Distortion Pedal',     'Pedal',      'Classic distortion pedal, great for rock',       2500.00, 12),
('Elixir Nanoweb Light Strings',   'Strings',    '10-46 gauge, coated for longer life',             650.00, 30),
('Fender Frontman 10G Amp',        'Amplifier',  '10W practice amplifier, built-in overdrive',     4500.00, 7),
('Ernie Ball Regular Slinky',      'Strings',    '10-46 gauge nickel wound',                        450.00, 50),
('Dunlop Jazz III Picks (12pk)',   'Accessory',  'Jazz III red nylon picks 12-pack',                350.00, 40),
('Squier Classic Vibe 50s Tele',   'Guitar',     'Vintage-style Telecaster, great for beginners',  18500.00, 6);