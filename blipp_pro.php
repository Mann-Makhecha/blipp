<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/settings.php';

// Check if user is logged in
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Redirect to login if not logged in
include 'includes/checklogin.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blip Pro - Premium Features | Blipp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.0.0/dist/css/coreui.min.css">
    <link rel="icon" href="favicon (2).png" type="image/x-icon">

    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --background-primary: #000;
            --background-secondary: #1a1a1a;
            --text-primary: #fff;
            --text-secondary: #999;
            --border-primary: #333;
            --accent-primary: #1d9bf0;
            --accent-secondary: #ffd700;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-premium: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
        }

        html, body {
            height: 100%;
            background: var(--background-primary);
            color: var(--text-primary) !important;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        .sidebar {
            width: 300px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            background-color: var(--background-primary);
        }

        .main-content-area {
            display: flex;
            margin-left: 300px;
            padding-top: 20px;
            align-items: flex-start;
        }

        @media (max-width: 767px) {
            .main-content-area {
                margin-left: 0;
                flex-direction: column;
                padding-top: 0;
            }
        }

        .main-content {
            flex-grow: 1;
            padding: 1rem;
        }

        .main-content-inner {
            max-width: 900px;
            width: 100%;
            margin: 0 auto;
        }

        @media (min-width: 768px) {
            .main-content {
                padding: 2rem;
            }
        }

        @media (max-width: 767px) {
            .main-content {
                padding-bottom: 70px;
                width: 100%;
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }

        .right-sidebar-container {
            width: 300px;
            flex-shrink: 0;
            position: sticky;
            top: 20px;
            padding-left: 1rem;
            padding-right: 1rem;
        }

        @media (max-width: 991px) {
            .right-sidebar-container {
                display: none;
            }
        }

        /* Pro Page Specific Styles */
        .pro-hero {
            background: var(--gradient-premium);
            border-radius: 1.5rem;
            padding: 3rem 2rem;
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
            overflow: hidden;
        }

        .pro-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }

        .pro-hero h1 {
            color: #000;
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .pro-hero p {
            color: #333;
            font-size: 1.25rem;
            font-weight: 500;
            margin-bottom: 2rem;
            position: relative;
            z-index: 1;
        }

        .pro-badge {
            background: #000;
            color: var(--accent-secondary);
            padding: 0.5rem 1.5rem;
            border-radius: 2rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .feature-card {
            background: var(--background-secondary);
            border: 1px solid var(--border-primary);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border-color: var(--accent-primary);
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            color: white;
        }

        .feature-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .feature-description {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .feature-list li {
            padding: 0.5rem 0;
            color: var(--text-secondary);
            position: relative;
            padding-left: 1.5rem;
        }

        .feature-list li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--accent-primary);
            font-weight: bold;
        }

        .pricing-card {
            background: var(--background-secondary);
            border: 2px solid var(--border-primary);
            border-radius: 1.5rem;
            padding: 2.5rem;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
        }

        .pricing-card:hover {
            border-color: var(--accent-secondary);
            transform: scale(1.02);
        }

        .pricing-card.featured {
            border-color: var(--accent-secondary);
            background: linear-gradient(145deg, var(--background-secondary) 0%, rgba(255, 215, 0, 0.1) 100%);
        }

        .pricing-card.featured::before {
            content: 'MOST POPULAR';
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--accent-secondary);
            color: #000;
            padding: 0.5rem 1.5rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .price {
            font-size: 3rem;
            font-weight: 800;
            color: var(--accent-secondary);
            margin-bottom: 0.5rem;
        }

        .price-period {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-bottom: 2rem;
        }

        .btn-pro {
            background: var(--gradient-premium);
            border: none;
            color: #000;
            padding: 1rem 2rem;
            border-radius: 2rem;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-pro:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 215, 0, 0.3);
            color: #000;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 3rem 0;
        }

        .stat-card {
            background: var(--background-secondary);
            border: 1px solid var(--border-primary);
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--accent-secondary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .testimonial-card {
            background: var(--background-secondary);
            border: 1px solid var(--border-primary);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .testimonial-text {
            font-style: italic;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .author-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .author-info h5 {
            margin: 0;
            color: var(--text-primary);
        }

        .author-info p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .faq-item {
            background: var(--background-secondary);
            border: 1px solid var(--border-primary);
            border-radius: 1rem;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .faq-question {
            background: transparent;
            border: none;
            color: var(--text-primary);
            padding: 1.5rem;
            width: 100%;
            text-align: left;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .faq-question:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .faq-answer {
            padding: 0 1.5rem 1.5rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .pro-hero h1 {
                font-size: 2.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .pricing-card {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Left Sidebar -->
    <div class="sidebar">
        <?php include 'includes/sidebar.php'; ?>
    </div>

    <div class="main-content-area">
        <div class="main-content">
            <div class="main-content-inner">
                <!-- Hero Section -->
                <div class="pro-hero">
                    <div class="pro-badge">
                        <i class="fas fa-crown"></i> PREMIUM
                    </div>
                    <h1>Blip Pro</h1>
                    <p>Unlock the full potential of your social experience with exclusive features and enhanced capabilities</p>
                    <a href="#pricing" class="btn-pro">
                        <i class="fas fa-rocket"></i> Get Started
                    </a>
                </div>

                <!-- Stats Section -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number">50K+</div>
                        <div class="stat-label">Pro Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">99.9%</div>
                        <div class="stat-label">Uptime</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">Support</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">∞</div>
                        <div class="stat-label">Possibilities</div>
                    </div>
                </div>

                <!-- Features Section -->
                <h2 class="mb-4" style="color: var(--text-primary); font-weight: 700;">Premium Features</h2>
                
                <div class="row">
                    <div class="col-lg-6">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-infinity"></i>
                            </div>
                            <h3 class="feature-title">Unlimited Posts</h3>
                            <p class="feature-description">Post as much as you want without any daily limits. Share your thoughts, ideas, and content freely.</p>
                            <ul class="feature-list">
                                <li>No daily post limits</li>
                                <li>Priority post visibility</li>
                                <li>Enhanced post analytics</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-palette"></i>
                            </div>
                            <h3 class="feature-title">Custom Themes</h3>
                            <p class="feature-description">Personalize your experience with exclusive themes and customization options.</p>
                            <ul class="feature-list">
                                <li>Dark & Light themes</li>
                                <li>Custom color schemes</li>
                                <li>Premium animations</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h3 class="feature-title">Advanced Analytics</h3>
                            <p class="feature-description">Get detailed insights into your content performance and audience engagement.</p>
                            <ul class="feature-list">
                                <li>Post performance metrics</li>
                                <li>Audience insights</li>
                                <li>Engagement tracking</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h3 class="feature-title">Enhanced Security</h3>
                            <p class="feature-description">Advanced security features to protect your account and data.</p>
                            <ul class="feature-list">
                                <li>Two-factor authentication</li>
                                <li>Login activity monitoring</li>
                                <li>Priority support</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3 class="feature-title">Community Management</h3>
                            <p class="feature-description">Advanced tools for managing and growing your communities effectively.</p>
                            <ul class="feature-list">
                                <li>Advanced moderation tools</li>
                                <li>Community analytics</li>
                                <li>Custom community badges</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-gift"></i>
                            </div>
                            <h3 class="feature-title">Exclusive Perks</h3>
                            <p class="feature-description">Special features and perks available only to Pro members.</p>
                            <ul class="feature-list">
                                <li>Early access to features</li>
                                <li>Exclusive badges</li>
                                <li>Priority customer support</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Testimonials Section -->
                <h2 class="mb-4 mt-5" style="color: var(--text-primary); font-weight: 700;">What Pro Users Say</h2>
                
                <div class="row">
                    <div class="col-lg-4">
                        <div class="testimonial-card">
                            <p class="testimonial-text">"Blip Pro has completely transformed how I engage with my community. The analytics are incredible!"</p>
                            <div class="testimonial-author">
                                <div class="author-avatar">S</div>
                                <div class="author-info">
                                    <h5>Sarah Johnson</h5>
                                    <p>Community Leader</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="testimonial-card">
                            <p class="testimonial-text">"The unlimited posting and custom themes make my experience so much more enjoyable."</p>
                            <div class="testimonial-author">
                                <div class="author-avatar">M</div>
                                <div class="author-info">
                                    <h5>Mike Chen</h5>
                                    <p>Content Creator</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="testimonial-card">
                            <p class="testimonial-text">"Best investment I've made for my social media presence. The features are game-changing!"</p>
                            <div class="testimonial-author">
                                <div class="author-avatar">A</div>
                                <div class="author-info">
                                    <h5>Alex Rodriguez</h5>
                                    <p>Influencer</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pricing Section -->
                <div id="pricing" class="mt-5">
                    <h2 class="mb-4 text-center" style="color: var(--text-primary); font-weight: 700;">Choose Your Plan</h2>
                    
                    <div class="row justify-content-center">
                        <div class="col-lg-4 col-md-6">
                            <div class="pricing-card">
                                <h3 style="color: var(--text-primary); margin-bottom: 2rem;">Monthly</h3>
                                <div class="price">$9.99</div>
                                <div class="price-period">per month</div>
                                <a href="#" class="btn-pro">
                                    <i class="fas fa-credit-card"></i> Subscribe Now
                                </a>
                            </div>
                        </div>
                        
                        <div class="col-lg-4 col-md-6">
                            <div class="pricing-card featured">
                                <h3 style="color: var(--text-primary); margin-bottom: 2rem;">Yearly</h3>
                                <div class="price">$99.99</div>
                                <div class="price-period">per year (Save 17%)</div>
                                <a href="#" class="btn-pro">
                                    <i class="fas fa-gift"></i> Best Value
                                </a>
                            </div>
                        </div>
                        
                        <div class="col-lg-4 col-md-6">
                            <div class="pricing-card">
                                <h3 style="color: var(--text-primary); margin-bottom: 2rem;">Lifetime</h3>
                                <div class="price">$299</div>
                                <div class="price-period">one-time payment</div>
                                <a href="#" class="btn-pro">
                                    <i class="fas fa-infinity"></i> Forever Access
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ Section -->
                <div class="mt-5">
                    <h2 class="mb-4" style="color: var(--text-primary); font-weight: 700;">Frequently Asked Questions</h2>
                    
                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <i class="fas fa-plus me-2"></i>
                            What's included in Blip Pro?
                        </button>
                        <div class="faq-answer" style="display: none;">
                            Blip Pro includes unlimited posts, custom themes, advanced analytics, enhanced security features, community management tools, and exclusive perks like early access to new features and priority support.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <i class="fas fa-plus me-2"></i>
                            Can I cancel my subscription anytime?
                        </button>
                        <div class="faq-answer" style="display: none;">
                            Yes, you can cancel your subscription at any time. You'll continue to have access to Pro features until the end of your current billing period.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <i class="fas fa-plus me-2"></i>
                            Is there a free trial available?
                        </button>
                        <div class="faq-answer" style="display: none;">
                            Yes! We offer a 7-day free trial for all new Pro subscribers. You can try all the premium features before committing to a subscription.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <i class="fas fa-plus me-2"></i>
                            What payment methods do you accept?
                        </button>
                        <div class="faq-answer" style="display: none;">
                            We accept all major credit cards (Visa, MasterCard, American Express), PayPal, and Apple Pay for mobile users.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Sidebar -->
        <div class="right-sidebar-container d-none d-lg-block">
            <?php include 'includes/rightsidebar.php'; ?>
        </div>
    </div>

    <?php include 'includes/mobilemenu.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.0.0/dist/js/coreui.bundle.min.js"></script>
    
    <script>
        function toggleFAQ(button) {
            const answer = button.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (answer.style.display === 'none') {
                answer.style.display = 'block';
                icon.className = 'fas fa-minus me-2';
            } else {
                answer.style.display = 'none';
                icon.className = 'fas fa-plus me-2';
            }
        }

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html> 