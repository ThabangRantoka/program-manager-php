CREATE TABLE IF NOT EXISTS login_attempts (
    username VARCHAR(50) NOT NULL,
    failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    blocked_until DATETIME DEFAULT NULL,
    last_attempt_at DATETIME DEFAULT NULL,
    PRIMARY KEY (username)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;