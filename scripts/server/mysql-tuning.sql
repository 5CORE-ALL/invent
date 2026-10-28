-- Run once on the server as root:   mysql < scripts/server/mysql-tuning.sql
--
-- Why: MySQL is running with FastPanel's default 128 MB InnoDB buffer pool, while the database is 31 GB and the
-- server has 48 GB RAM (about 26 GB free or cache). Almost every query is read from disk (6.3 billion disk reads
-- since start), which is why mysqld sits at 240% CPU and every page is slow.
--
-- SET PERSIST survives a restart and wins over FastPanel's /etc/mysql/my.cnf.fastpanel/99-fastpanel.cnf,
-- which should not be edited by hand. The buffer pool resizes online, no restart needed.
-- To undo: RESET PERSIST;  and  SET GLOBAL innodb_buffer_pool_size = 134217728;

SET PERSIST innodb_buffer_pool_size = 12884901888;   -- 12 GB
SET PERSIST innodb_redo_log_capacity = 2147483648;   -- 2 GB (was 100 MB: constant checkpoint flushing)
SET PERSIST innodb_io_capacity = 2000;               -- was 200
SET PERSIST innodb_io_capacity_max = 4000;

-- Record anything slower than 2 seconds so the next slow query can be found by name.
SET PERSIST slow_query_log = ON;
SET PERSIST long_query_time = 2;
SET PERSIST log_queries_not_using_indexes = OFF;

SELECT @@innodb_buffer_pool_size / 1073741824 AS buffer_pool_gb, @@innodb_redo_log_capacity / 1048576 AS redo_mb;
