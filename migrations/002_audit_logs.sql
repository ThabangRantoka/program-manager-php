CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id INT NOT NULL,
    actor_username VARCHAR(50) NOT NULL,
    action ENUM('add', 'edit', 'delete') NOT NULL,
    program_id INT NOT NULL,
    program_name VARCHAR(255) NOT NULL,
    source ENUM('php', 'api') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_audit_created (created_at, id),
    KEY idx_audit_actor (actor_user_id),
    KEY idx_audit_program (program_id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;