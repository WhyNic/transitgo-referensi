-- Apply once to the original TransitGo database. Existing schedules become drafts.
USE transitgo;

ALTER TABLE users
    ADD COLUMN company_logo VARCHAR(36) CHARACTER SET ascii DEFAULT NULL,
    ADD COLUMN legal_document VARCHAR(36) CHARACTER SET ascii DEFAULT NULL,
    ADD COLUMN verification_status ENUM('incomplete', 'pending', 'approved', 'rejected') NOT NULL DEFAULT 'incomplete',
    ADD COLUMN verification_note VARCHAR(250) DEFAULT NULL,
    ADD COLUMN verification_round INT UNSIGNED NOT NULL DEFAULT 0,
    ADD INDEX (role, verification_status);

ALTER TABLE vehicles
    ADD COLUMN vehicle_photo VARCHAR(36) CHARACTER SET ascii DEFAULT NULL,
    ADD COLUMN stnk_document VARCHAR(36) CHARACTER SET ascii DEFAULT NULL,
    ADD COLUMN kir_document VARCHAR(36) CHARACTER SET ascii DEFAULT NULL;

ALTER TABLE schedules
    ADD COLUMN is_published TINYINT UNSIGNED NOT NULL DEFAULT 0 CHECK (is_published IN (0, 1));
