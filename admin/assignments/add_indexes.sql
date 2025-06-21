-- Add indexes to improve assignment query performance

-- Index for checking duplicate subject assignments
ALTER TABLE assignments ADD INDEX idx_subject_id (subject_id);

-- Indexes for conflict checking
ALTER TABLE assignments ADD INDEX idx_teacher_id (teacher_id);
ALTER TABLE assignments ADD INDEX idx_time_start (time_start);
ALTER TABLE assignments ADD INDEX idx_time_end (time_end);

-- Composite index for teacher and time range queries
ALTER TABLE assignments ADD INDEX idx_teacher_time (teacher_id, time_start, time_end);

-- Index for notifications
ALTER TABLE notifications ADD INDEX idx_teacher_id (teacher_id); 