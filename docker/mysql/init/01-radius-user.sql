-- Creates the restricted account FreeRADIUS authenticates with.
--
-- Only the account is created here. MySQL rejects table-level GRANTs against
-- tables that do not exist yet, and this script runs before the Laravel
-- migrations have created the rad* tables. The precise per-table grants are
-- applied afterwards by:
--
--   php artisan kasi:provision-radius-user
--
-- which is idempotent and part of the deploy runbook.

CREATE USER IF NOT EXISTS 'radius'@'%'
    IDENTIFIED BY 'radius_secret';

-- Nothing beyond connect rights until the provisioning command runs.
GRANT USAGE ON *.* TO 'radius'@'%';

FLUSH PRIVILEGES;
