<?php
// ============================================================
//  Supabase (PostgreSQL) connection template.
//  Copy this file to config/db.local.php and fill in your own
//  values from: Supabase Dashboard → Project Settings → Database
//  → Connection string. config/db.local.php is gitignored and
//  must never be committed.
// ============================================================

const DB_HOST = 'your-project.pooler.supabase.com';
const DB_PORT = '6543';
const DB_NAME = 'postgres';
const DB_USER = 'postgres.your-project-ref';
const DB_PASS = 'your-database-password';

// Shared secret the ESP32 device sends in the X-API-Key header.
const ESP32_API_KEY = 'change-me';
