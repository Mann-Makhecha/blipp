INSERT INTO users (
    username,
    email,
    password_hash,
    role,
    points,
    email_verified,
    is_active,
    created_at,
    updated_at
) VALUES (
    'premium_user',
    'premium@blipp.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: 'password'
    'admin',
    10000,
    1,
    1,
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
) ON DUPLICATE KEY UPDATE
    points = 10000,
    role = 'admin',
    email_verified = 1,
    is_active = 1,
    updated_at = CURRENT_TIMESTAMP; 