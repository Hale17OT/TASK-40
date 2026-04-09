-- Enable extensions needed by HarborBite
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Immutable audit log protection function
CREATE OR REPLACE FUNCTION prevent_modification()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION 'Modification of audit log records is prohibited';
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;
