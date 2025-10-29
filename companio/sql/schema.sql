-- Companio Database Schema
CREATE TABLE IF NOT EXISTS users (
  user_id       INT AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_name    VARCHAR(50) NOT NULL,
  last_name     VARCHAR(50) NOT NULL,
  role          ENUM('tourist','guide') NOT NULL,
  is_verified   TINYINT(1) DEFAULT 0,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS profiles (
  profile_id   INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  phone        VARCHAR(20),
  location     VARCHAR(100),
  bio          TEXT,
  travel_style VARCHAR(50),
  languages    VARCHAR(100),
  experience   INT,
  specialties  VARCHAR(255),
  rate         DECIMAL(6,2),
  availability VARCHAR(50),
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS interests (
  interest_id  INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(100) UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_interests (
  user_id     INT NOT NULL,
  interest_id INT NOT NULL,
  PRIMARY KEY(user_id, interest_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (interest_id) REFERENCES interests(interest_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS matches (
  match_id    INT AUTO_INCREMENT PRIMARY KEY,
  tourist_id  INT NOT NULL,
  guide_id    INT NOT NULL,
  match_score INT,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tourist_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (guide_id)   REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bookings (
  booking_id  INT AUTO_INCREMENT PRIMARY KEY,
  tourist_id  INT NOT NULL,
  guide_id    INT NOT NULL,
  tour_date   DATE NOT NULL,
  hours       INT,
  status      ENUM('pending','confirmed','completed','cancelled') DEFAULT 'confirmed',
  FOREIGN KEY (tourist_id) REFERENCES users(user_id),
  FOREIGN KEY (guide_id)   REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
  message_id  INT AUTO_INCREMENT PRIMARY KEY,
  sender_id   INT NOT NULL,
  receiver_id INT NOT NULL,
  message_text TEXT NOT NULL,
  sent_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sender_id)   REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reviews (
  review_id   INT AUTO_INCREMENT PRIMARY KEY,
  booking_id  INT NOT NULL,
  tourist_id  INT NOT NULL,
  guide_id    INT NOT NULL,
  rating      TINYINT NOT NULL,
  comment     TEXT,
  review_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE,
  FOREIGN KEY (tourist_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (guide_id)   REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
  payment_id  INT AUTO_INCREMENT PRIMARY KEY,
  booking_id  INT NOT NULL,
  amount      DECIMAL(8,2) NOT NULL,
  method      VARCHAR(50),
  account_info VARCHAR(100),
  paid_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
