-- Insert maintenance mode settings
INSERT INTO admin_settings (setting_key, setting_value) VALUES
('maintenance_mode', '0'),
('maintenance_message', 'We are currently performing scheduled maintenance. We will be back shortly!')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value); 