-- Admin login throttling: failed attempts per username + IP.
CREATE TABLE IF NOT EXISTS admin_login_attempts (
  attempt_id    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(50)  NOT NULL,
  ip_address    VARCHAR(45)  NOT NULL,
  succeeded     TINYINT(1)   NOT NULL DEFAULT 0,
  attempted_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (attempt_id),
  KEY idx_login_attempts_lookup (username, ip_address, attempted_at)
) ENGINE=InnoDB;
