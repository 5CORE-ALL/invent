-- Fix authentication for 5core_ai user
ALTER USER 5core_ai WITH SUPERUSER;

-- Create pg_hba.conf rules via SQL
\! echo 'local all all trust' > /var/lib/postgresql/data/pg_hba.conf
\! echo 'host all all 127.0.0.1/32 md5' >> /var/lib/postgresql/data/pg_hba.conf
\! echo 'host all all ::1/128 md5' >> /var/lib/postgresql/data/pg_hba.conf
\! echo 'host all all 0.0.0.0/0 md5' >> /var/lib/postgresql/data/pg_hba.conf

-- Enable pgvector extension
CREATE EXTENSION IF NOT EXISTS vector;

-- Verify
SELECT 'PostgreSQL initialized successfully' as status;
