-- REDWM FIRMA PRO (MySQL 8+ / MariaDB 10.4+)

CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(120) NOT NULL,
  role ENUM('SUPERADMIN','SOPORTE','VENTAS','AUDITOR') NOT NULL DEFAULT 'SUPERADMIN',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_name VARCHAR(200) NOT NULL,
  nit VARCHAR(60) NOT NULL,
  legal_rep VARCHAR(200) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(60) NULL,
  address VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_client (nit, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contracts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,

  -- token legacy (puede mantenerse para compatibilidad; no mostrar nunca)
  token CHAR(64) NOT NULL UNIQUE,

  -- token seguro (recomendado): guardar solo el hash y validar por hash
  token_hash CHAR(64) NULL,

  status ENUM('PENDING','SIGNED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  contract_version VARCHAR(40) NOT NULL,
  price_cop INT NOT NULL,
  term_months INT NOT NULL,
  created_at DATETIME NOT NULL,
  expires_at DATETIME NULL,
  signed_at DATETIME NULL,
  cancelled_at DATETIME NULL,

  signer_name VARCHAR(200) NULL,
  signer_id VARCHAR(80) NULL,
  signer_role VARCHAR(120) NULL,
  signer_email VARCHAR(190) NULL,

  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  evidence_hash CHAR(64) NULL,

  -- storage recomendado
  signature_png_path VARCHAR(255) NULL,
  contract_pdf_path VARCHAR(255) NULL,

  -- blobs (compatibilidad / opcional)
  signature_png LONGBLOB NULL,
  contract_pdf LONGBLOB NULL,

  UNIQUE KEY uniq_token_hash (token_hash),
  INDEX idx_status_created (status, created_at),
  INDEX idx_signer_email (signer_email),
  FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sign_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contract_id INT NOT NULL,
  code_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_contract (contract_id),
  FOREIGN KEY (contract_id) REFERENCES contracts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  event VARCHAR(80) NOT NULL,
  contract_id INT NULL,
  admin_user_id INT NULL,
  meta JSON NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_audit_contract (contract_id),
  INDEX idx_audit_admin (admin_user_id),
  FOREIGN KEY (contract_id) REFERENCES contracts(id),
  FOREIGN KEY (admin_user_id) REFERENCES admin_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
