-- Run once in Supabase SQL Editor to speed up alert lookups and dashboards.
CREATE INDEX IF NOT EXISTS idx_alerts_user_detected_at
    ON alerts (user_id, detected_at DESC);

CREATE INDEX IF NOT EXISTS idx_alerts_user_unread_detected_at
    ON alerts (user_id, detected_at DESC)
    WHERE status = 'unread';

CREATE INDEX IF NOT EXISTS idx_alerts_detected_at
    ON alerts (detected_at DESC);
