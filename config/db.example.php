<?php
// ============================================================
//  Supabase (PostgreSQL) connection template.
//  Copy this file to config/db.local.php and fill in your own
//  values from: Supabase Dashboard → Project Settings → Database
//  → Connection string. config/db.local.php is gitignored and
//  must never be committed.
//
//  Hosted deployments can set these same names as environment
//  variables instead. DB_PASS is the database password variable;
//  DB_PASSWORD is also accepted as an environment-variable alias.
// ============================================================

const DB_HOST = 'your-project.pooler.supabase.com';
const DB_PORT = '6543';
const DB_NAME = 'postgres';
const DB_USER = 'postgres.your-project-ref';
const DB_PASS = 'your-database-password';

// Shared secret the ESP32 device sends in the X-API-Key header.
const ESP32_API_KEY = 'change-me';

// Supabase Storage — used to upload/replace profile photos (avatars bucket).
// Find these under Supabase Dashboard → Project Settings → API
// (Project URL, and the "service_role" key under "Legacy anon, service_role
// API keys"). Keep the service_role key server-side only — never expose it
// to the browser.
const SUPABASE_URL              = 'https://your-project.supabase.co';
const SUPABASE_SERVICE_ROLE_KEY = 'your-service-role-key';
const SUPABASE_AVATAR_BUCKET    = 'avatars';
