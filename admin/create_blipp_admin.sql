-- Create Blipp admin user
INSERT INTO users (
    username,
    email,
    password_hash,
    role,
    created_at
) VALUES (
    'blipp_admin',
    'admin@blipp.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: 'admin123'
    'admin',
    NOW()
) ON DUPLICATE KEY UPDATE role = 'admin'; 