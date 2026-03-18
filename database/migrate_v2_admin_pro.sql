-- Migración a Admin PRO (Ruta B)
-- Ejecuta esto UNA VEZ sobre tu BD existente.

-- 1) admin_users: roles + active
ALTER TABLE admin_users
  ADD COLUMN role ENUM('SUPERADMIN','SOPORTE','VENTAS','AUDITOR') NOT NULL DEFAULT 'SUPERADMIN' AFTER name,
  ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER role;

-- 2) contracts: token_hash + expiración + cancelación + rutas de archivos
ALTER TABLE contracts
  ADD COLUMN token_hash CHAR(64) NULL AFTER token,
  ADD COLUMN expires_at DATETIME NULL AFTER created_at,
  ADD COLUMN cancelled_at DATETIME NULL AFTER signed_at,
  ADD COLUMN signature_png_path VARCHAR(255) NULL AFTER evidence_hash,
  ADD COLUMN contract_pdf_path VARCHAR(255) NULL AFTER signature_png_path;

CREATE UNIQUE INDEX uniq_token_hash ON contracts(token_hash);
CREATE INDEX idx_status_created ON contracts(status, created_at);
CREATE INDEX idx_signer_email ON contracts(signer_email);

-- 3) audit_logs: vincular a contrato + admin
ALTER TABLE audit_logs
  ADD COLUMN contract_id INT NULL AFTER event,
  ADD COLUMN admin_user_id INT NULL AFTER contract_id;

CREATE INDEX idx_audit_contract ON audit_logs(contract_id);
CREATE INDEX idx_audit_admin ON audit_logs(admin_user_id);

ALTER TABLE audit_logs
  ADD CONSTRAINT audit_logs_contract_fk FOREIGN KEY (contract_id) REFERENCES contracts(id);

ALTER TABLE audit_logs
  ADD CONSTRAINT audit_logs_admin_fk FOREIGN KEY (admin_user_id) REFERENCES admin_users(id);

-- 4) Backfill: token_hash + expires_at para contratos existentes (si aplica)
UPDATE contracts
  SET token_hash = IFNULL(token_hash, SHA2(token, 256)),
      expires_at = IFNULL(expires_at, DATE_ADD(created_at, INTERVAL 48 HOUR))
WHERE token IS NOT NULL;
