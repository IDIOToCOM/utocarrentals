-- Mark all project migrations as executed (use ONLY after importing forge-mysql-schema.sql
-- manually instead of running doctrine:migrations:migrate).
-- Safe to run on empty doctrine_migration_versions table.

INSERT INTO doctrine_migration_versions (version, executed_at, execution_time) VALUES
('DoctrineMigrations\\Version20251221030818', NOW(), 0),
('DoctrineMigrations\\Version20251221033222', NOW(), 0),
('DoctrineMigrations\\Version20260402072016', NOW(), 0),
('DoctrineMigrations\\Version20260519120000', NOW(), 0),
('DoctrineMigrations\\Version20260519130000', NOW(), 0),
('DoctrineMigrations\\Version20260520120000', NOW(), 0),
('DoctrineMigrations\\Version20260521120000', NOW(), 0),
('DoctrineMigrations\\Version20260521140000', NOW(), 0),
('DoctrineMigrations\\Version20260522120000', NOW(), 0),
('DoctrineMigrations\\Version20260523120000', NOW(), 0),
('DoctrineMigrations\\Version20260524120000', NOW(), 0),
('DoctrineMigrations\\Version20260525120000', NOW(), 0),
('DoctrineMigrations\\Version20260526120000', NOW(), 0);
