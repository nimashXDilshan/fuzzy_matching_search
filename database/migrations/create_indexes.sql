-- =====================================================
-- Database Migration: Fuzzy Search Indexes
-- 
-- Run this script to create necessary indexes for
-- the fuzzy matching search functionality.
-- =====================================================

-- ===================
-- Customer Table Indexes
-- ===================

-- Full-text index for fuzzy search on name and email
-- Note: MySQL requires FULLTEXT indexes on MyISAM or InnoDB tables
ALTER TABLE Customer 
ADD FULLTEXT INDEX idx_customer_ft_search (FirstName, Surname, Email);

-- Performance index for approval status filtering
CREATE INDEX idx_customer_approval 
ON Customer(ApprovalStatus);

-- Index for NIC lookups
CREATE INDEX idx_customer_nic 
ON Customer(NIC);

-- Index for mobile phone lookups
CREATE INDEX idx_customer_mobile 
ON Customer(MobileTelephone);

-- Index for email lookups (if not already existing)
CREATE INDEX idx_customer_email 
ON Customer(Email);

-- Prefix indexes for faster LIKE 'search%' queries (reduces index size for large datasets)
CREATE INDEX idx_customer_firstname_prefix 
ON Customer(FirstName(15));

CREATE INDEX idx_customer_surname_prefix 
ON Customer(Surname(15));

-- Composite index for combined status + name search (optimizes filtered searches)
CREATE INDEX idx_customer_status_name 
ON Customer(ApprovalStatus, FirstName, Surname);


-- ===================
-- Organization Table Indexes
-- ===================

-- Full-text index for fuzzy search on organization name
ALTER TABLE Organization 
ADD FULLTEXT INDEX idx_organization_ft_search (Name);

-- Performance index for approval status filtering
CREATE INDEX idx_org_approval 
ON Organization(IsApproved);

-- Index for BR number lookups
CREATE INDEX idx_org_br_number 
ON Organization(RegistrationNumber);

-- Index for country filtering
CREATE INDEX idx_org_country 
ON Organization(CountryID);

-- Prefix index for faster LIKE 'search%' queries on organization name
CREATE INDEX idx_org_name_prefix 
ON Organization(Name(20));

-- Composite index for combined approval + country + name search
CREATE INDEX idx_org_status_country_name 
ON Organization(IsApproved, CountryID, Name(20));


-- ===================
-- OrganizationMember Table Indexes
-- ===================

-- Composite index for efficient membership lookups
-- Covers the common query pattern: WHERE CustomerID = ? AND OrganizationID = ? AND IsApproved = ?
CREATE INDEX idx_orgmember_composite 
ON OrganizationMember(CustomerID, OrganizationID, IsApproved);

-- Index for finding all organizations for a customer
CREATE INDEX idx_orgmember_customer 
ON OrganizationMember(CustomerID, IsApproved);

-- Index for finding all members of an organization
CREATE INDEX idx_orgmember_org 
ON OrganizationMember(OrganizationID, IsApproved);


-- ===================
-- DomainReason Table Indexes
-- ===================

-- Index for active domain reasons
CREATE INDEX idx_domainreason_active 
ON DomainReason(IsActive);


-- ===================
-- Country Table Indexes
-- ===================

-- Index for active countries
CREATE INDEX idx_country_active 
ON Country(IsActive);


-- =====================================================
-- Verification Queries
-- Test that indexes are working properly
-- =====================================================

-- Verify full-text search on Customer
-- SELECT * FROM Customer WHERE MATCH(FirstName, Surname, Email) AGAINST('+john*' IN BOOLEAN MODE) LIMIT 5;

-- Verify full-text search on Organization  
-- SELECT * FROM Organization WHERE MATCH(Name) AGAINST('+abc*' IN BOOLEAN MODE) LIMIT 5;

-- Verify composite index on OrganizationMember
-- EXPLAIN SELECT * FROM OrganizationMember WHERE CustomerID = 1 AND IsApproved = 1;

-- =====================================================
-- Rollback Script (if needed)
-- =====================================================

-- DROP INDEX idx_customer_ft_search ON Customer;
-- DROP INDEX idx_customer_approval ON Customer;
-- DROP INDEX idx_customer_nic ON Customer;
-- DROP INDEX idx_customer_mobile ON Customer;
-- DROP INDEX idx_customer_email ON Customer;
-- DROP INDEX idx_customer_firstname_prefix ON Customer;
-- DROP INDEX idx_customer_surname_prefix ON Customer;
-- DROP INDEX idx_customer_status_name ON Customer;
-- DROP INDEX idx_organization_ft_search ON Organization;
-- DROP INDEX idx_org_approval ON Organization;
-- DROP INDEX idx_org_br_number ON Organization;
-- DROP INDEX idx_org_country ON Organization;
-- DROP INDEX idx_org_name_prefix ON Organization;
-- DROP INDEX idx_org_status_country_name ON Organization;
-- DROP INDEX idx_orgmember_composite ON OrganizationMember;
-- DROP INDEX idx_orgmember_customer ON OrganizationMember;
-- DROP INDEX idx_orgmember_org ON OrganizationMember;
-- DROP INDEX idx_domainreason_active ON DomainReason;
-- DROP INDEX idx_country_active ON Country;
