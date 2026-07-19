-- Migration: Gift system and user tracking
-- Run this in Supabase SQL Editor

-- Add gift tracking columns to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS gift_opened BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS gift_points INTEGER DEFAULT 0;

-- Create table for tracking QR code activations per user
CREATE TABLE IF NOT EXISTS user_qr_activations (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    qr_code TEXT NOT NULL,
    activated_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(user_id, qr_code)
);

-- Add index for fast lookups
CREATE INDEX IF NOT EXISTS idx_user_qr_activations_user_id ON user_qr_activations(user_id);
CREATE INDEX IF NOT EXISTS idx_user_qr_activations_qr_code ON user_qr_activations(qr_code);

-- RLS policies (Supabase)
ALTER TABLE user_qr_activations ENABLE ROW LEVEL SECURITY;

-- Allow service role full access (for backend)
CREATE POLICY "Service role full access" ON user_qr_activations
    FOR ALL USING (true);
