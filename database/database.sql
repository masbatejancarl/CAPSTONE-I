-- SonoBand Database Schema (PostgreSQL / Supabase)
-- Paste this in: Supabase Dashboard > SQL Editor > New Query

CREATE TABLE IF NOT EXISTS users (
    id            SERIAL PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(100) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    role          VARCHAR(20)  NOT NULL DEFAULT 'user'
                  CHECK (role IN ('user', 'admin', 'caregiver')),
    created_at    TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS alerts (
    id             SERIAL PRIMARY KEY,
    user_id        INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    sound_type     VARCHAR(50),
    alert_type     VARCHAR(10) NOT NULL DEFAULT 'medium'
                   CHECK (alert_type IN ('critical', 'high', 'medium', 'low')),
    status         VARCHAR(10) DEFAULT 'unread'
                   CHECK (status IN ('unread', 'read')),
    detected_at    TIMESTAMP DEFAULT NOW(),
    metadata       TEXT
);

-- Keep an existing alerts table compatible with the current alert payload.
ALTER TABLE alerts ALTER COLUMN sound_type DROP NOT NULL;
ALTER TABLE alerts ADD COLUMN IF NOT EXISTS alert_type VARCHAR(10);
ALTER TABLE alerts DROP COLUMN IF EXISTS decibel_level;
ALTER TABLE alerts DROP COLUMN IF EXISTS location;
UPDATE alerts
SET alert_type = 'medium'
WHERE alert_type IS NULL
   OR alert_type NOT IN ('critical', 'high', 'medium', 'low');
ALTER TABLE alerts ALTER COLUMN alert_type SET DEFAULT 'medium';
ALTER TABLE alerts ALTER COLUMN alert_type SET NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
                WHERE conname IN ('alerts_alert_type_check', 'alerts_type_check')
                    AND conrelid = 'alerts'::regclass
    ) THEN
        ALTER TABLE alerts
            ADD CONSTRAINT alerts_alert_type_check
            CHECK (alert_type IN ('critical', 'high', 'medium', 'low'));
    END IF;
END $$;

-- Links a caregiver account to the hearing-impaired user(s) they monitor.
CREATE TABLE IF NOT EXISTS caregiver_links (
    id             SERIAL PRIMARY KEY,
    caregiver_id   INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    patient_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at     TIMESTAMP DEFAULT NOW(),
    UNIQUE (caregiver_id, patient_id)
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id          SERIAL PRIMARY KEY,
    user_id     INT REFERENCES users(id) ON DELETE SET NULL,
    user_name   VARCHAR(100),
    user_email  VARCHAR(100),
    role        VARCHAR(20),
    action      VARCHAR(20) NOT NULL CHECK (action IN ('login', 'logout', 'login_failed', 'password_reset')),
    ip_address  VARCHAR(45),
    created_at  TIMESTAMP DEFAULT NOW()
);

-- Keep audit history limited to supported authentication events.
UPDATE audit_logs
SET action = LOWER(action)
WHERE LOWER(action) IN ('login', 'logout', 'login_failed', 'password_reset');

DELETE FROM audit_logs
WHERE action NOT IN ('login', 'logout', 'login_failed', 'password_reset');

ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check;
ALTER TABLE audit_logs
    ADD CONSTRAINT audit_logs_action_check
    CHECK (action IN ('login', 'logout', 'login_failed', 'password_reset'));

CREATE INDEX IF NOT EXISTS idx_audit_logs_created_at ON audit_logs(created_at DESC);

-- Default admin (password: Admin@123)
INSERT INTO users (name, email, password, role) VALUES (
    'Administrator',
    'admin@sonoband.com',
    '$2y$10$TnFVwmoLgTJrqTQJAIiwleIfXi.3X24YePXq8eRU9731ak.dveQLW',
    'admin'
) ON CONFLICT (email) DO NOTHING;

-- Sample hearing-impaired user (password: password)
INSERT INTO users (name, email, password, role) VALUES (
    'Juan Dela Cruz',
    'juan@example.com',
    '$2y$10$FkYpIzn/UYgEfEtlbpTePe0gBvQbKL3fjErjfm4ezj32FH1eCIbc.',
    'user'
) ON CONFLICT (email) DO NOTHING;

-- ============================================================
-- Row Level Security (RLS)
-- Blocks direct Supabase REST API / anon key access.
-- PHP backend connects as postgres (superuser) and bypasses RLS.
-- ============================================================

ALTER TABLE users            ENABLE ROW LEVEL SECURITY;
ALTER TABLE alerts           ENABLE ROW LEVEL SECURITY;
ALTER TABLE audit_logs       ENABLE ROW LEVEL SECURITY;
ALTER TABLE caregiver_links  ENABLE ROW LEVEL SECURITY;

-- Deny all anonymous/public access via Supabase REST API
CREATE POLICY "Block anon on users"
    ON users FOR ALL
    TO anon
    USING (false);

CREATE POLICY "Block anon on alerts"
    ON alerts FOR ALL
    TO anon
    USING (false);

CREATE POLICY "Block anon on audit_logs"
    ON audit_logs FOR ALL
    TO anon
    USING (false);

CREATE POLICY "Block anon on caregiver_links"
    ON caregiver_links FOR ALL
    TO anon
    USING (false);

-- Allow authenticated service role full access
CREATE POLICY "Service role access on users"
    ON users FOR ALL
    TO authenticated
    USING (true)
    WITH CHECK (true);

CREATE POLICY "Service role access on alerts"
    ON alerts FOR ALL
    TO authenticated
    USING (true)
    WITH CHECK (true);

CREATE POLICY "Service role access on audit_logs"
    ON audit_logs FOR ALL
    TO authenticated
    USING (true)
    WITH CHECK (true);

CREATE POLICY "Service role access on caregiver_links"
    ON caregiver_links FOR ALL
    TO authenticated
    USING (true)
    WITH CHECK (true);
