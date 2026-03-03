-- =====================================================
-- Database Migration: Optimize Search Indexes
-- 
-- Improves performance for:
-- 1. Organization search (adds TradingName to fulltext)
-- 2. Customer filtering (adds composite status index)
-- =====================================================

-- ===================
-- Organization Table Optimizations
-- ===================

-- Drop existing fulltext index on Name only
-- Note: Requires caution in production; in dev we can just drop/recreate
-- ALTER TABLE Organization DROP INDEX idx_organization_ft_search;

-- Create new fulltext index including TradingName
-- This allows searches to match against either Name OR TradingName efficiently
ALTER TABLE Organization 
ADD FULLTEXT INDEX idx_org_name_trading_ft (Name, TradingName);

-- ===================
-- Customer Table Optimizations
-- ===================

-- Create composite index for the most common filter pattern:
-- WHERE AccountDeactivationStatus = 'None' AND ApprovalStatus = 'Approved'
CREATE INDEX idx_customer_active_status 
ON Customer(AccountDeactivationStatus, ApprovalStatus);

-- =====================================================
-- Verification Queries
-- =====================================================

-- Verify Organization search uses new index
-- EXPLAIN SELECT * FROM Organization WHERE MATCH(Name, TradingName) AGAINST('+test*' IN BOOLEAN MODE);

-- Verify Customer filtering uses new index
-- EXPLAIN SELECT * FROM Customer WHERE AccountDeactivationStatus = 'None' AND ApprovalStatus = 'Approved';
