-- =====================================================
--  OTP Migration — run this once against your database
--  Adds email_verified, otp_code, otp_expires_at,
--  and otp_attempts columns to the users table.
-- =====================================================

ALTER TABLE users
  ADD COLUMN email_verified  TINYINT(1)   NOT NULL DEFAULT 0        AFTER provider_id,
  ADD COLUMN otp_code        VARCHAR(6)   NULL                      AFTER email_verified,
  ADD COLUMN otp_expires_at  DATETIME     NULL                      AFTER otp_code,
  ADD COLUMN otp_attempts    TINYINT      NOT NULL DEFAULT 0         AFTER otp_expires_at;

-- Mark all existing users as already verified
-- (so they are not locked out after the migration)
UPDATE users SET email_verified = 1 WHERE email_verified = 0;

-- Optional index for performance on OTP lookups
CREATE INDEX idx_users_otp ON users (id, otp_code, otp_expires_at);