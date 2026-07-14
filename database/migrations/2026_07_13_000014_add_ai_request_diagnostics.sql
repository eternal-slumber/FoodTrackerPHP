ALTER TABLE ai_requests
    ADD COLUMN provider VARCHAR(60) DEFAULT NULL AFTER ai_model_id,
    ADD COLUMN model VARCHAR(180) DEFAULT NULL AFTER provider,
    ADD COLUMN http_status SMALLINT UNSIGNED DEFAULT NULL AFTER response_time_ms,
    ADD COLUMN trace_id VARCHAR(80) DEFAULT NULL AFTER error_message,
    ADD INDEX idx_ai_requests_trace_id (trace_id);
