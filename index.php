<?php
session_start();

// Підключення файлу конфігурації бази даних
$db_config_path = __DIR__ . '/db_config.php';
if (file_exists($db_config_path)) {
    require_once $db_config_path;
} else {
    error_log("FATAL ERROR in index.php: db_config.php NOT FOUND at " . $db_config_path);
    die('Critical server configuration error: Database configuration file is missing. Please contact administrator.');
}

// Перевірка, чи об'єкт PDO було успішно створено в db_config.php
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("FATAL ERROR in index.php: \$pdo is NOT SET or not a PDO instance after including db_config.php. This usually means an error occurred within db_config.php itself (e.g., bad credentials, PDO extension not loaded, or a die() statement was hit).");
    die('Critical database connection error: The database connection object (PDO) is not available. Please contact administrator and check server logs.');
}

// Визначення поточної дії (сторінки/розділу) з GET-параметра
$action = $_GET['action'] ?? '';

// Отримання та очищення повідомлень про помилки/успіх для форми входу
$login_error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);

// Отримання та очищення повідомлень про помилки/успіх та даних для форми реєстрації
$reg_error = $_SESSION['reg_error'] ?? null;
$reg_success = $_SESSION['reg_success'] ?? null;
$reg_data = $_SESSION['reg_data'] ?? [];
unset($_SESSION['reg_error']);
unset($_SESSION['reg_data']);


// Перевірка, чи користувач авторизований
$is_logged_in = isset($_SESSION['user_id']);
$user_name_display = '';
$user_id_for_photo = null;
$user_access_right = $_SESSION['access_right'] ?? 0;
$user_session_email = $_SESSION['user_email'] ?? '';

if ($is_logged_in) {
    $user_name_display = htmlspecialchars($_SESSION['user_name']); // Захист від XSS
    $user_id_for_photo = $_SESSION['user_id'];
}

function display_info_box_news(PDO $pdo_func, $setting_key, $default_title, $default_placeholder_text, $box_class, $user_access_right_func) {
    $news_item = null;
    $news_id_for_banner = null;

    try {
        // Отримання ID новини з налаштувань сайту
        $stmt_setting = $pdo_func->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $stmt_setting->execute([$setting_key]);
        $setting = $stmt_setting->fetch(PDO::FETCH_ASSOC);

        if ($setting && !empty($setting['setting_value'])) {
            $news_id_for_banner = (int)$setting['setting_value'];
            if ($pdo_func instanceof PDO) {
                // Отримання даних самої новини
                $stmt_news_banner = $pdo_func->prepare("SELECT id, title, text, photo FROM news WHERE id = ?");
                $stmt_news_banner->execute([$news_id_for_banner]);
                $news_item = $stmt_news_banner->fetch(PDO::FETCH_ASSOC);
            } else {
                error_log("PDO object is not valid within display_info_box_news for key: " . htmlspecialchars($setting_key));
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching info box news for " . htmlspecialchars($setting_key) . ": " . $e->getMessage());
    }
    ?>
    <div class="info-box <?php echo htmlspecialchars($box_class); ?>">
        <h4>
            <span><?php echo htmlspecialchars($news_item ? $news_item['title'] : $default_title); ?></span>
            <?php if ($user_access_right_func == 10): // Кнопка зміни новини для адміністратора ?>
                <button type="button" class="select-news-btn" data-boxkey="<?php echo htmlspecialchars($setting_key); ?>" data-currentnewsid="<?php echo $news_id_for_banner ?? ''; ?>">(Change)</button>
            <?php endif; ?>
        </h4>

        <div class="info-box-body-content">
            <?php if ($news_item && !empty($news_item['photo'])): // Відображення фото, якщо є ?>
                <img src="<?php echo htmlspecialchars($news_item['photo']); ?>" alt="<?php echo htmlspecialchars($news_item['title']); ?>" class="info-box-content-image">
            <?php endif; ?>

            <div class="info-box-content-text">
                <p data-fulltext="<?php echo htmlspecialchars($news_item ? strip_tags($news_item['text']) : $default_placeholder_text); ?>">
                    <?php
                    // Логіка скорочення тексту для попереднього перегляду
                    if ($news_item) {
                        $php_max_limit = 750; // Максимальна довжина тексту
                        $text_content = strip_tags($news_item['text']);

                        if (mb_strlen($text_content) > $php_max_limit) {
                            $display_text = mb_substr($text_content, 0, $php_max_limit);
                            // Обрізка до останнього пробілу для уникнення розриву слів
                            $last_space = mb_strrpos($display_text, ' ');
                            if ($last_space !== false && $last_space > $php_max_limit - 50) { // Умова, щоб не обрізати занадто коротко
                                $display_text = mb_substr($display_text, 0, $last_space);
                            }
                            $display_text .= "...";
                        } else {
                            $display_text = $text_content;
                        }
                        echo htmlspecialchars($display_text);
                    } else {
                        echo htmlspecialchars($default_placeholder_text);
                    }
                    ?>
                </p>
            </div>
        </div>

        <?php if ($news_item): ?>
            <a href="news_detail.php?id=<?php echo (int)($news_item['id']); ?>" class="read-more-infobox-standalone">• read more</a>
        <?php endif; ?>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real Estate</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""></script>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="site-layout">
    <aside class="left-column">
        <div class="logo-container">
            <div class="logo">
                <h1><span class="logo-real">Real</span> Estate</h1>
                <p>BEST ONLINE SERVICES</p>
            </div>
        </div>

        <div class="welcome-section">
            <div class="welcome-content">
                <h3 class="welcome-title">Welcome to our site!</h3>
                <p class="welcome-text-body">Finding your perfect space is simple with us. Explore properties and connect with our team today.</p>
            </div>
            <img src="images/ico/hello-logo.png" alt="Welcome Logo" class="welcome-image-right">
        </div>

        <div class="news-and-events">
            <h2>News and Events:</h2>
            <?php
            // Блок відображення останніх новин у лівій колонці
            try {
                if (!isset($pdo) || !$pdo instanceof PDO) {
                    echo "<p>Database connection is not available for news.</p>";
                    error_log("PDO object not available for news block in index.php");
                } else {
                    // Запит на отримання останніх 5 новин
                    $stmt_news_list = $pdo->prepare("SELECT id, title, text, photo, date, source FROM news ORDER BY date DESC, id DESC LIMIT 5");
                    $stmt_news_list->execute();
                    $news_items_list = $stmt_news_list->fetchAll(PDO::FETCH_ASSOC);

                    if ($news_items_list) {
                        foreach ($news_items_list as $item) {
                            $news_date_formatted = date("m/d/Y", strtotime($item['date'])); // Форматування дати
                            ?>
                            <div class="news-item">
                                <?php if (!empty($item['photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['photo']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" style="width: 70px; height: 90px; object-fit: cover;">
                                <?php endif; ?>
                                <div class="news-text <?php if (empty($item['photo'])) echo 'news-text-full-width'; ?>">
                                    <span class="news-date"><?php echo $news_date_formatted; ?></span>
                                    <h4><?php echo htmlspecialchars($item['title']); ?></h4>
                                    <p><?php
                                        // Скорочений текст новини
                                        $short_text = mb_substr(strip_tags($item['text']), 0, 100);
                                        if (mb_strlen(strip_tags($item['text'])) > 100) {
                                            $short_text .= "...";
                                        }
                                        echo htmlspecialchars($short_text);
                                        ?></p>
                                    <?php if (!empty($item['source'])): // Джерело новини, якщо є ?>
                                        <small class="news-source">Source: <?php echo htmlspecialchars($item['source']); ?></small><br>
                                    <?php endif; ?>
                                    <a href="news_detail.php?id=<?php echo (int)$item['id']; ?>" class="read-more">• read more</a>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo "<p>No news items to display at the moment.</p>"; // Якщо новин немає
                    }
                }
            } catch (PDOException $e) {
                error_log("Error fetching news in index.php: " . $e->getMessage());
                echo "<p>Could not retrieve news items due to a server error.</p>";
            }
            ?>
        </div>

        <div class="left-column-bottom-content">
            <div class="members-area">
                <?php
                // Отримання та очищення сесійних змінних для процесу відновлення пароля
                $fp_error = $_SESSION['fp_error'] ?? null;
                $fp_success = $_SESSION['fp_success'] ?? null;
                $fp_email_for_code = $_SESSION['fp_email_for_code'] ?? ''; // Email, на який надіслано код
                if(isset($_SESSION['fp_error'])) unset($_SESSION['fp_error']);
                if(isset($_SESSION['fp_success']) && $action !== 'forgot_code' && $action !== 'forgot_new_password') unset($_SESSION['fp_success']);

                // --- Логіка відображення блоку "Members Area" ---
                if ($is_logged_in):
                    ?>
                    <h3>Members Area:</h3> <div class="logged-in-user-info"> <div class="user-photo-container"> <img src="get_user_photo.php?id=<?php echo $user_id_for_photo; ?>" alt="User Photo" class="user-photo"> </div> <span class="user-name-display"><?php echo $user_name_display; ?></span> </div> <div class="members-actions-loggedin"> <a href="logout.php" class="member-action-button">Log out</a> <a href="edit_profile.php" class="member-action-button">Edit Profile</a> <?php if ($user_access_right == 10): ?> <button type="button" id="openCreateNewsModalBtn" class="member-action-button">Create News</button> <?php endif; ?> <button type="button" id="openMyListingsModalBtn" class="member-action-button">My Listings</button> </div>
                <?php elseif ($action === 'register'): ?>
                    <h3>Create Account</h3> <?php if ($reg_error): ?><p class="error-message"><?php echo htmlspecialchars($reg_error); ?></p><?php endif; ?> <form action="register_process.php" method="POST"> <div><label for="reg_login">Login:</label><input type="text" id="reg_login" name="reg_login" value="<?php echo htmlspecialchars($reg_data['login'] ?? ''); ?>" required></div> <div><label for="reg_email">Email:</label><input type="text" id="reg_email" name="reg_email" value="<?php echo htmlspecialchars($reg_data['email'] ?? ''); ?>" required></div> <div><label for="reg_password">Password:</label><input type="password" id="reg_password" name="reg_password" required></div> <div class="members-actions"><button type="submit" name="register">Register</button></div> <p class="auth-switch-link"><a href="index.php">Already have an account? Login</a></p> </form>
                <?php

                elseif ($action === 'forgot_email' || ($action === 'forgot_code' && empty($fp_email_for_code)) || ($action === 'forgot_new_password' && empty($_SESSION['fp_code_verified'])) ):
                    ?>
                    <h3>Forgot Password</h3> <p>Enter your email address, and we'll send you a code to reset your password.</p> <?php if ($fp_error): ?><p class="error-message"><?php echo htmlspecialchars($fp_error); ?></p><?php endif; ?> <?php if ($fp_success && $action !== 'forgot_code' && $action !== 'forgot_new_password'): ?><p class="success-message"><?php echo htmlspecialchars($fp_success); ?></p><?php endif; ?> <form action="forgot_password_process.php" method="POST"> <input type="hidden" name="step" value="send_code"> <div><label for="fp_email">Your Email:</label><input type="email" id="fp_email" name="fp_email" required value="<?php echo htmlspecialchars($_POST['fp_email'] ?? ''); ?>"></div> <div class="members-actions"><button type="submit">Send Code</button></div> <p class="auth-switch-link"><a href="index.php">Back to Login</a></p> </form>
                <?php elseif ($action === 'forgot_code' && !empty($fp_email_for_code)): ?>
                    <h3>Enter Verification Code</h3>
                    <p>A 6-digit code has been sent to <strong><?php echo htmlspecialchars($fp_email_for_code); ?></strong>. Please enter it below.</p>
                    <?php if ($fp_error): ?><p class="error-message"><?php echo htmlspecialchars($fp_error); ?></p><?php endif; ?>
                    <?php if ($fp_success): ?><p class="success-message"><?php echo htmlspecialchars($fp_success); ?></p><?php endif; ?>
                    <form action="forgot_password_process.php" method="POST"> <input type="hidden" name="step" value="verify_code">
                        <div>
                            <label for="fp_code">Verification Code:</label>
                            <input type="text" id="fp_code" name="fp_code" required maxlength="6" pattern="\d{6}" title="Enter 6-digit code">
                        </div>
                        <div class="members-actions"><button type="submit">Verify Code</button></div>
                        <p class="auth-switch-link" style="margin-top: 5px;"><a href="index.php?action=forgot_email">Resend Code?</a> | <a href="index.php">Back to Login</a></p>
                    </form>
                <?php elseif ($action === 'forgot_new_password' && isset($_SESSION['fp_code_verified']) && $_SESSION['fp_code_verified'] === true): ?>
                    <h3>Set New Password</h3> <p>Enter your new password for <strong><?php echo htmlspecialchars($fp_email_for_code); ?></strong>.</p>
                    <?php if ($fp_error): ?><p class="error-message"><?php echo htmlspecialchars($fp_error); ?></p><?php endif; ?>
                    <form action="forgot_password_process.php" method="POST"> <input type="hidden" name="step" value="reset_password">
                        <div><label for="fp_new_password">New Password:</label><input type="password" id="fp_new_password" name="fp_new_password" required></div>
                        <div><label for="fp_confirm_password">Confirm New Password:</label><input type="password" id="fp_confirm_password" name="fp_confirm_password" required></div>
                        <div class="members-actions"><button type="submit">Reset Password</button></div>
                        <p class="auth-switch-link"><a href="index.php">Back to Login</a></p>
                    </form>
                <?php else: ?>
                    <h3>Members Area:</h3> <?php if ($login_error): ?><p class="error-message"><?php echo htmlspecialchars($login_error); ?></p><?php endif; ?>
                    <?php if ($reg_success): ?><p class="success-message"><?php echo htmlspecialchars($reg_success); unset($_SESSION['reg_success']); ?></p><?php endif; ?>
                    <form action="login_process.php" method="POST"> <div><label for="login_left">Login:</label><input type="text" id="login_left" name="login" required></div>
                        <div><label for="password_left">Password:</label><input type="password" id="password_left" name="password" required></div>
                        <div class="members-actions"><button type="submit" name="login_submit">Submit</button></div>
                        <div class="auth-links">
                            <a href="index.php?action=forgot_email" class="forgot-password">Forgot password?</a>
                            <a href="index.php?action=register" class="create-account-link">Create Account</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <div class="copyright-footer-left">
                <p>Copyright ©. All rights reserved.</p>
            </div>
        </div>
    </aside>

    <div class="right-column">
        <header class="right-column-header">
            <nav class="main-nav">
                <ul>
                    <li><a href="#" class="main-nav-home-link">HOME</a></li>
                    <li><a href="#" id="navBtnBuyHeader"  class="nav-filter-trigger" data-listing-type="sale">BUY</a></li>
                    <li><a href="#" id="navBtnRentHeader" class="nav-filter-trigger" data-listing-type="rent">RENT</a></li>
                    <li><a href="#" id="sellPropertyBtn" class="nav-sell-trigger">SELL</a></li>
                    <li class="nav-item-with-popover">
                        <a href="#">CONTACTS</a>
                        <div class="popover-content popover-top-nav contacts-popover">
                            <h4>Contact Us</h4>
                            <p><strong>Phone:</strong><br>
                                <a href="tel:+15551234567">+1 (555) 123-4567</a> (Sales)<br>
                                <a href="tel:+15551234568">+1 (555) 123-4568</a> (Support)<br>
                                <a href="tel:+15551234569">+1 (555) 123-4569</a> (General)
                            </p>
                            <p><strong>Email:</strong><br><a href="mailto:support@realestate.com">support@realestate.com</a></p>
                            <p><strong>Telegram Bot:</strong><br><a href="https://t.me/YourRealEstateBot" target="_blank">@YourRealEstateBot</a></p>
                        </div>
                    </li>
                </ul>
            </nav>
        </header>
        <?php
        $unique_bedrooms = [];
        $unique_bathrooms = [];
        $unique_cities = [];
        $unique_states = [];
        $unique_zips = [];

        try {
            // Отримання унікальних значень міст
            $stmt_cities = $pdo->query("SELECT DISTINCT city FROM properties WHERE city IS NOT NULL AND city != '' ORDER BY city ASC");
            $unique_cities = $stmt_cities->fetchAll(PDO::FETCH_COLUMN);

            // Отримання унікальних значень штатів/регіонів
            $stmt_states = $pdo->query("SELECT DISTINCT state FROM properties WHERE state IS NOT NULL AND state != '' ORDER BY state ASC");
            $unique_states = $stmt_states->fetchAll(PDO::FETCH_COLUMN);

            // Отримання унікальних значень поштових індексів
            $stmt_zips = $pdo->query("SELECT DISTINCT zip_code FROM properties WHERE zip_code IS NOT NULL AND zip_code != '' ORDER BY zip_code ASC");
            $unique_zips = $stmt_zips->fetchAll(PDO::FETCH_COLUMN);

            // Отримання унікальних значень кількості спалень
            $stmt_bedrooms = $pdo->query("SELECT DISTINCT bedrooms FROM properties WHERE bedrooms IS NOT NULL AND bedrooms > 0 ORDER BY bedrooms ASC");
            $unique_bedrooms = $stmt_bedrooms->fetchAll(PDO::FETCH_COLUMN);

            // Отримання унікальних значень кількості ванних кімнат
            $stmt_bathrooms = $pdo->query("SELECT DISTINCT bathrooms FROM properties WHERE bathrooms IS NOT NULL AND bathrooms > 0 ORDER BY bathrooms ASC");
            $unique_bathrooms = $stmt_bathrooms->fetchAll(PDO::FETCH_COLUMN);

        } catch (PDOException $e) {
            error_log("Error fetching unique city/state/zip/bedroom/bathroom counts: " . $e->getMessage());
        }
        ?>
        <main class="right-column-content">
            <section class="featured-properties-section">
                <?php
                // Відображення рекомендованих/останніх об'єктів нерухомості
                try {
                    // Запит на отримання об'єктів з їх головним/першим фото
                    $stmt_props = $pdo->prepare(
                        "SELECT p.id, p.title, p.description, p.price, p.currency, p.listing_type,  p.status, p.agent_id,
                                    (SELECT pp.image_path FROM property_photos pp WHERE pp.property_id = p.id AND pp.is_primary = 1 LIMIT 1) as primary_image,
                                    (SELECT pp.image_path FROM property_photos pp WHERE pp.property_id = p.id ORDER BY pp.sort_order ASC LIMIT 1) as first_image
                             FROM properties p
                             ORDER BY p.date_listed DESC, p.id DESC 
                             LIMIT 9"
                    );
                    $stmt_props->execute();
                    $properties = $stmt_props->fetchAll(PDO::FETCH_ASSOC);

                    if ($properties) {
                        foreach ($properties as $prop) {
                            // Визначення фото для відображення (головне, перше або заглушка)
                            $image_to_display = $prop['primary_image'] ?: ($prop['first_image'] ?: '/images/ico/nophotoproperty.png');
                            // Перевірка, чи доступний об'єкт для взаємодії (купівля/оренда)
                            $is_available_for_interaction = ($prop['status'] === 'active' || $prop['status'] === 'pending');
                            // Тексти для відображення статусу
                            $status_text_overlay = '';
                            $status_text_beside_price = '';
                            $price_strikethrough_class = '';

                            // Логіка визначення текстів статусу
                            switch ($prop['status']) {
                                case 'pending':
                                    $status_text_overlay = 'PENDING';
                                    break;
                                case 'purchased':
                                    if ($prop['listing_type'] === 'sale') {
                                        $status_text_overlay = 'SOLD';
                                        $status_text_beside_price = 'SOLD';
                                    } elseif ($prop['listing_type'] === 'rent') {
                                        $status_text_overlay = 'RENTED';
                                        $status_text_beside_price = 'RENTED';
                                    }
                                    $price_strikethrough_class = 'price-strikethrough';
                                    break;
                                case 'inactive':
                                    $status_text_overlay = 'OFF MARKET';
                                    break;
                                case 'active':
                                default:
                                    break;
                            }
                            ?>
                            <div class="property-card <?php if (!$is_available_for_interaction) echo 'property-not-available status-' . htmlspecialchars($prop['status']); ?>">
                                <a href="property_detail.php?id=<?php echo (int)$prop['id']; ?>"
                                   class="property-card-link"
                                   data-property-id="<?php echo (int)$prop['id']; ?>"
                                   data-listing-type="<?php echo htmlspecialchars($prop['listing_type']); ?>"
                                   data-property-status="<?php echo htmlspecialchars($prop['status']); ?>"
                                   data-agent-id="<?php echo htmlspecialchars($prop['agent_id'] ?? ''); ?>">
                                    <div class="property-image-container">
                                        <img src="<?php echo htmlspecialchars($image_to_display); ?>" alt="<?php echo htmlspecialchars($prop['title']); ?>">
                                        <?php if (!empty($status_text_overlay)): // Відображення статусу на фото ?>
                                            <div class="property-status-overlay status-<?php echo htmlspecialchars($prop['status']);
                                            // Додатковий клас для проданих/орендованих
                                            if ($prop['status'] === 'purchased') {
                                                echo ($prop['listing_type'] === 'sale' ? ' status-sold' : ' status-rented');
                                            }
                                            ?>">
                                                <?php echo $status_text_overlay; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="property-card-content">
                                        <h3><?php echo htmlspecialchars($prop['title']); ?></h3>
                                        <p class="property-price">
                                            <span class="<?php echo $price_strikethrough_class; // Клас для перекреслення ?>">
                                                <?php echo ($prop['listing_type'] == 'rent' ? 'Rent: ' : ''); // Префікс для оренди ?>
                                                $<?php echo number_format((float)$prop['price'], 2); // Форматування ціни ?>
                                                <?php echo ($prop['listing_type'] == 'rent' ? '/ month' : ''); // Суфікс для оренди ?>
                                            </span>
                                            <?php if (!empty($status_text_beside_price) && $prop['status'] !== 'active'): // Статус біля ціни (крім 'active') ?>
                                                <span class="status-text-inline status-<?php echo htmlspecialchars($prop['status']);
                                                if ($prop['status'] === 'purchased') {
                                                    echo ($prop['listing_type'] === 'sale' ? ' status-sold' : ' status-rented');
                                                }
                                                ?>">
                                                    <?php echo $status_text_beside_price; ?>
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="property-summary">
                                            <?php
                                            // Скорочений опис об'єкта
                                            $summary = mb_substr(strip_tags($prop['description'] ?? ''), 0, 80);
                                            if (mb_strlen(strip_tags($prop['description'] ?? '')) > 80) {
                                                $summary .= "...";
                                            }
                                            echo htmlspecialchars($summary);
                                            ?>
                                        </p>
                                    </div>
                                </a>
                                <div class="property-card-actions">
                                    <a href="property_detail.php?id=<?php echo (int)$prop['id']; ?>#gallery" class="view-gallery-btn">• view gallery / details</a>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo "<p>No properties found at the moment.</p>"; // Якщо об'єктів немає
                    }
                } catch (PDOException $e) {
                    error_log("Error fetching featured properties: " . $e->getMessage());
                    echo "<p>Could not retrieve properties due to a server error.</p>"; // Повідомлення про помилку
                }
                ?>
            </section>
            <section class="search-and-cta-section">
                <div class="search-section">
                    <h2>Search Form</h2>
                    <form class="search-form">
                        <div class="form-fields-wrapper">
                            <div class="form-left">
                                <div><label for="mls">MLS:</label> <input type="text" id="mls" name="mls"></div>
                                <div class="filter-control-wrapper">
                                    <label for="city_display">City:</label>
                                    <div id="city_display" class="filter-display-button" tabindex="0" aria-haspopup="true" aria-expanded="false" data-filter-type="city">
                                        Any City
                                    </div>
                                    <div id="city_popover" class="filter-popover">
                                        <input type="text" class="filter-popover-search" data-filter-type="city" placeholder="Search cities...">
                                        <div class="checkbox-group city-checkbox-group">
                                            <?php if (empty($unique_cities)): // Якщо немає міст для фільтра ?>
                                                <small>N/A</small>
                                            <?php else: ?>
                                                <?php foreach ($unique_cities as $city_val): // Виведення чекбоксів для кожного міста ?>
                                                    <div class="checkbox-item">
                                                        <input type="checkbox" id="filter_city_<?php echo htmlspecialchars(urlencode(strtolower($city_val))); ?>" name="city[]" value="<?php echo htmlspecialchars($city_val); ?>" data-filter-type="city">
                                                        <label for="filter_city_<?php echo htmlspecialchars(urlencode(strtolower($city_val))); ?>"><?php echo htmlspecialchars($city_val); ?></label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="filter-popover-apply-btn" data-filter-type="city">Apply</button>
                                    </div>
                                </div>
                                <div class="filter-control-wrapper">
                                    <label for="state_display">State/Region:</label>
                                    <div id="state_display" class="filter-display-button" tabindex="0" aria-haspopup="true" aria-expanded="false" data-filter-type="state">
                                        Any State/Region
                                    </div>
                                    <div id="state_popover" class="filter-popover">
                                        <input type="text" class="filter-popover-search" data-filter-type="state" placeholder="Search states...">
                                        <div class="checkbox-group state-checkbox-group">
                                            <?php if (empty($unique_states)): // Якщо немає штатів ?>
                                                <small>N/A</small>
                                            <?php else: ?>
                                                <?php foreach ($unique_states as $state_val): ?>
                                                    <div class="checkbox-item">
                                                        <input type="checkbox" id="filter_state_<?php echo htmlspecialchars(urlencode(strtolower($state_val))); ?>" name="state[]" value="<?php echo htmlspecialchars($state_val); ?>" data-filter-type="state">
                                                        <label for="filter_state_<?php echo htmlspecialchars(urlencode(strtolower($state_val))); ?>"><?php echo htmlspecialchars($state_val); ?></label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="filter-popover-apply-btn" data-filter-type="state">Apply</button>
                                    </div>
                                </div>
                                <div class="filter-control-wrapper">
                                    <label for="zip_display">ZIP Code(s):</label>
                                    <div id="zip_display" class="filter-display-button" tabindex="0" aria-haspopup="true" aria-expanded="false" data-filter-type="zip">
                                        Any ZIP Code
                                    </div>
                                    <div id="zip_popover" class="filter-popover">
                                        <input type="text" class="filter-popover-search" data-filter-type="zip" placeholder="Search ZIP codes...">
                                        <div class="checkbox-group zip-checkbox-group">
                                            <?php if (empty($unique_zips)): // Якщо немає поштових індексів ?>
                                                <small>N/A</small>
                                            <?php else: ?>
                                                <?php foreach ($unique_zips as $zip_val): ?>
                                                    <div class="checkbox-item">
                                                        <input type="checkbox" id="filter_zip_<?php echo htmlspecialchars($zip_val); ?>" name="zip[]" value="<?php echo htmlspecialchars($zip_val); ?>" data-filter-type="zip">
                                                        <label for="filter_zip_<?php echo htmlspecialchars($zip_val); ?>"><?php echo htmlspecialchars($zip_val); ?></label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="filter-popover-apply-btn" data-filter-type="zip">Apply</button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-right">
                                <div class="filter-control-wrapper">
                                    <label for="search_area_display">Search Area:</label>
                                    <div id="search_area_display" class="filter-display-button" tabindex="0" aria-haspopup="true" aria-expanded="false" data-filter-type="search_area">
                                        Select Area on Map
                                    </div>
                                    <div id="search_area_popover" class="filter-popover search-area-popover">
                                        <div id="searchAreaMap" style="height: 250px; width: 100%; border-radius: 4px; margin-bottom: 10px;">
                                            <!-- Карта для вибору області пошуку буде ініціалізована тут JS -->
                                        </div>
                                        <div class="search-area-controls">
                                            <label for="search_radius_input">Radius (km):</label>
                                            <input type="number" id="search_radius_input" name="search_radius" value="15" min="1" step="1" style="width: 70px; margin-left: 5px;">
                                            <input type="hidden" id="selected_lat" name="selected_lat">
                                            <input type="hidden" id="selected_lng" name="selected_lng">
                                        </div>
                                        <button type="button" class="filter-popover-apply-btn" data-filter-type="search_area" style="margin-top: 10px;">Apply Area</button>
                                    </div>
                                </div>
                                <div class="filter-control-wrapper">
                                    <label for="bedrooms_display">Bedroom(s):</label>
                                    <div id="bedrooms_display" class="filter-display-button" tabindex="0" aria-haspopup="true" aria-expanded="false" data-filter-type="bedrooms"> Any Bedrooms</div>
                                    <div id="bedrooms_popover" class="filter-popover">
                                        <div class="checkbox-group bedrooms-checkbox-group">
                                            <?php if (empty($unique_bedrooms)): ?>
                                                <small>N/A</small>
                                            <?php else: ?>
                                                <?php foreach ($unique_bedrooms as $beds_val): ?>
                                                    <?php $beds = (int)$beds_val; ?>
                                                    <div class="checkbox-item">
                                                        <input type="checkbox" id="filter_beds_<?php echo $beds; ?>" name="bedrooms[]" value="<?php echo $beds; ?>" data-filter-type="bedrooms">
                                                        <label for="filter_beds_<?php echo $beds; ?>"><?php echo $beds; ?> bed<?php echo ($beds !== 1 ? 's' : ''); // Правильне закінчення для 'bed' ?></label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="filter-popover-apply-btn" data-filter-type="bedrooms">Apply</button>
                                    </div>
                                </div>
                                <div class="filter-control-wrapper">
                                    <label for="bathrooms_display">Bathroom(s):</label>
                                    <div id="bathrooms_display" class="filter-display-button" tabindex="0" aria-haspopup="true" aria-expanded="false" data-filter-type="bathrooms">
                                        Any Bathrooms
                                    </div>
                                    <div id="bathrooms_popover" class="filter-popover">
                                        <div class="checkbox-group bathrooms-checkbox-group">
                                            <?php if (empty($unique_bathrooms)): ?>
                                                <small>N/A</small>
                                            <?php else: ?>
                                                <?php foreach ($unique_bathrooms as $baths_val): ?>
                                                    <?php
                                                    // Обробка як цілих, так і дробових значень для ванних кімнат
                                                    $bath_value_attr = htmlspecialchars((string)$baths_val);
                                                    $bath_display_text = (float)$baths_val;
                                                    if ($bath_display_text == floor($bath_display_text)) { // Якщо ціле число
                                                        $bath_display_text = (int)$bath_display_text;
                                                    }
                                                    $id_safe_bath_val = str_replace('.', '_', (string)$baths_val); // Для безпечного ID
                                                    ?>
                                                    <div class="checkbox-item">
                                                        <input type="checkbox" id="filter_baths_<?php echo $id_safe_bath_val; ?>" name="bathrooms[]" value="<?php echo $bath_value_attr; ?>" data-filter-type="bathrooms">
                                                        <label for="filter_baths_<?php echo $id_safe_bath_val; ?>"><?php echo $bath_display_text; ?> bath<?php echo ($bath_display_text != 1 ? 's' : ''); // Правильне закінчення для 'bath' ?></label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="filter-popover-apply-btn" data-filter-type="bathrooms">Apply</button>
                                    </div>
                                </div>
                                <div><label for="price_range_min">Price Range:</label><div class="price-range-input-container"><input type="text" id="price_range_min" name="price_range_min" class="price-range-input" placeholder="Min"><input type="text" id="price_range_max" name="price_range_max" class="price-range-input" placeholder="Max"></div></div>
                            </div>
                            <div class="form-status-filter-wrapper">
                                <label>Property Status:</label>
                                <div class="checkbox-group status-checkbox-group">
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="filter_status_active" name="status[]" value="active">
                                        <label for="filter_status_active">Active</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="filter_status_pending" name="status[]" value="pending">
                                        <label for="filter_status_pending">Pending</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="filter_status_purchased" name="status[]" value="purchased">
                                        <label for="filter_status_purchased">Sold/Rented</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="filter_status_inactive" name="status[]" value="inactive">
                                        <label for="filter_status_inactive">Off Market</label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-listing-type-filter-wrapper">
                                <label>Listing Type:</label>
                                <div class="checkbox-group listing-type-checkbox-group">
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="filter_type_sale" name="listing_type[]" value="sale">
                                        <label for="filter_type_sale">For Sale</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="filter_type_rent" name="listing_type[]" value="rent">
                                        <label for="filter_type_rent">For Rent</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-actions-search sidebar-search-button-wrapper">
                            <button type="submit" class="search-form-submit-btn">Apply Filters</button>
                            <button type="button" id="clearAllSearchFiltersBtn" class="clear-all-filters-button" style="margin-top: 10px;">Clear All Filters</button>
                        </div>
                    </form>
                </div>
                <div class="search-cta">
                    <img src="images/ico/Building+Icon.png" alt="Search icon">
                    <button type="button" class="search-now-button-cta">Search NOW!</button>
                </div>
            </section>
        </main>

        <div class="right-column-bottom-wrapper">
            <section class="info-boxes-section">
                <?php
                // Відображення інформаційних блоків з новинами
                // Перевірка $pdo перед викликом функції
                if (isset($pdo) && $pdo instanceof PDO) {
                    display_info_box_news($pdo, 'banner1_id', 'No News selected', 'No News selected', 'home-improvement', $user_access_right);
                    display_info_box_news($pdo, 'banner2_id', 'No News selected', 'No News selected', 'securing-mortgage', $user_access_right);
                } else {
                    // Повідомлення, якщо $pdo недоступний
                    echo '<div class="info-box home-improvement"><h4>Home Improvement</h4><p>Content currently unavailable.</p></div>';
                    echo '<div class="info-box securing-mortgage"><h4>Securing a Mortgage</h4><p>Content currently unavailable.</p></div>';
                    error_log("PDO object not available for info-boxes in index.php");
                }
                ?>
            </section>
            <footer class="right-column-footer">
                <nav class="footer-nav">
                    <ul> <li><a href="#" class="main-nav-home-link">Home</a></li>
                        <li><a href="#" id="navBtnBuyFooter" class="nav-filter-trigger" data-listing-type="sale">Buy</a></li>
                        <li><a href="#" id="navBtnRentFooter" class="nav-filter-trigger" data-listing-type="rent">Rent</a></li>
                        <li><a href="#" id="sellPropertyBtnFooter" class="nav-sell-trigger">Sell</a></li>
                        <li class="nav-item-with-popover">
                            <a href="#">Contacts</a>
                            <div class="popover-content contacts-popover">
                                <h4>Contact Us</h4>
                                <p><strong>Phone:</strong><br>
                                    <a href="tel:+15551234567">+1 (555) 123-4567</a> (Sales)<br>
                                    <a href="tel:+15551234568">+1 (555) 123-4568</a> (Support)<br>
                                    <a href="tel:+15551234569">+1 (555) 123-4569</a> (General)
                                </p>
                                <p><strong>Email:</strong><br><a href="mailto:support@realestate.com">support@realestate.com</a></p>
                                <p><strong>Telegram Bot:</strong><br><a href="https://t.me/YourRealEstateBot" target="_blank">@YourRealEstateBot</a></p>
                            </div>
                        </li>
                        <li class="nav-item-with-popover">
                            <a href="#">Services</a>
                            <div class="popover-content services-popover">
                                <h4>Useful Services</h4>
                                <ul>
                                    <li><a href="https://www.mls.com" target="_blank">Official MLS Site</a></li>
                                    <li><a href="#" target="_blank">Local Mortgage Calculator</a></li>
                                    <li><a href="#" target="_blank">Neighborhood Info</a></li>
                                    <li><a href="#" target="_blank">Moving Services Portal</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item-with-popover">
                            <a href="#">Links</a>
                            <div class="popover-content links-popover">
                                <h4>Follow Us</h4>
                                <ul class="social-links-popover">
                                    <li><a href="https://t.me/yourchannel" target="_blank">Telegram Channel</a></li>
                                    <li><a href="https://instagram.com/yourprofile" target="_blank">Instagram</a></li>
                                    <li><a href="https://facebook.com/yourpage" target="_blank">Facebook</a></li>
                                    <li><a href="https://twitter.com/yourhandle" target="_blank">Twitter (X)</a></li>
                                </ul>
                            </div>
                        </li>
                        <li><a href="#" id="openAllNewsModalBtn">News</a></li>
                        <li><a href="#" id="openSiteMapModalBtn">Site Map</a></li> </ul>
                </nav>
            </footer>
        </div>
    </div>
</div>

<!-- HTML для модальних вікон -->
<div id="allNewsModal" class="modal">
    <div class="modal-content all-news-modal-content">
        <span class="close-modal-btn" data-modal-id="allNewsModal">×</span>
        <h2>All News and Events</h2>
        <div id="allNewsContainer" class="all-news-items-container">
            <p>Loading news...</p> <!-- Заглушка під час завантаження -->
        </div>
    </div>
</div>
<div id="siteMapModal" class="modal site-map-modal">
    <div class="modal-content site-map-modal-content">
        <span class="close-modal-btn" data-modal-id="siteMapModal">×</span>
        <h2>Site Map - Property Locations</h2>
        <div id="siteMapDisplay" style="height: 500px; width: 100%; border-radius: 4px; border: 1px solid #ccc; margin-top: 15px;">
            <!-- Карта сайту буде ініціалізована тут JS -->
        </div>
        <div class="map-legend" style="margin-top: 15px; font-size: 0.9em;">
            <strong>Legend:</strong>
            <span style="display:inline-block; margin-right:10px; margin-left:5px;"><img src="images/markers/marker-icon-blue.png" alt="Active" style="height:18px; vertical-align:middle;"> Active</span>
            <span style="display:inline-block; margin-right:10px;"><img src="images/markers/marker-icon-red.png" alt="Purchased" style="height:18px; vertical-align:middle;"> Sold/Rented</span>
            <span style="display:inline-block;"><img src="images/markers/marker-icon-yellow.png" alt="Pending" style="height:18px; vertical-align:middle;"> Pending</span>
        </div>
    </div>
</div>
<div id="propertyDetailModal" class="modal">
    <div class="modal-content property-detail-modal-content">
        <span class="close-modal-btn" data-modal-id="propertyDetailModal">×</span>
        <div id="propertyDetailContent">
            <!-- Контент для деталей об'єкта буде завантажено сюди JS -->
            <h2 id="propDetailTitle"></h2>
            <p class="property-detail-price" id="propDetailPrice"></p>
            <p class="property-detail-meta">
                <span id="propDetailType"></span>
                <span id="propDetailLocation"></span>
            </p>
            <hr>
            <div id="propDetailGallery" class="property-gallery">
                <div class="main-image-container">
                    <img id="galleryMainImage" src="" alt="Property image">
                </div>
                <div class="thumbnail-container">
                    <!-- Мініатюри будуть додані сюди JS -->
                </div>
            </div>
            <div id="noPropertyImages" style="text-align:center; padding: 20px; display:none;">No images available for this property.</div>
            <hr>
            <h3>Description</h3>
            <div id="propDetailDescription" class="property-detail-text-content"></div>
            <hr>
            <h3>Property Details</h3>
            <table class="property-details-table">
                <tr data-field="mls_number"><td>MLS Number:</td><td id="propDetailMLS"></td></tr>
                <tr data-field="bedrooms"><td>Bedrooms:</td><td id="propDetailBedrooms"></td></tr>
                <tr data-field="bathrooms"><td>Bathrooms:</td><td id="propDetailBathrooms"></td></tr>
                <tr data-field="area_sqm"><td>Area:</td><td id="propDetailArea"></td></tr>
                <tr data-field="lot_size_sqm"><td>Lot Size:</td><td id="propDetailLotSize"></td></tr>
                <tr data-field="year_built"><td>Year Built:</td><td id="propDetailYearBuilt"></td></tr>
                <tr data-field="date_available"><td>Date Available:</td><td id="propDetailDateAvailable"></td></tr>
            </table>
            <hr>
            <div id="propDetailContactSection" style="display:none;">
                <h3>Contact Information</h3>
                <p data-field="contact_name">Contact: <strong id="propDetailContactName"></strong></p>
                <p data-field="contact_email">Email: <a id="propDetailContactEmail" href=""></a></p>
                <p data-field="contact_phone">Phone: <strong id="propDetailContactPhone"></strong></p>
            </div>
            <hr id="mapSeparator" style="display:none;">
            <div id="propertyDetailMapContainer" style="display:none;">
                <h3>Location Map</h3>
                <div id="propertyDetailMap" style="height: 300px; width: 100%; border-radius: 4px; border: 1px solid #ccc;">
                    <!-- Карта деталей об'єкта буде ініціалізована тут JS -->
                </div>
            </div>
            <div class="modal-actions" style="margin-top: 20px; text-align: right; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" id="purchasePropertyBtn" class="member-action-button action-purchase" style="display: none;">Purchase</button>
                <button type="button" id="editPropertyFromDetailBtn" class="member-action-button action-edit" style="display: none;">Edit Property</button>
            </div>
        </div>
    </div>
</div>

<div id="createPropertyModal" class="modal"> <!-- Модальне вікно для створення/редагування об'єкта -->
    <div class="modal-content create-property-modal-content">
        <span class="close-modal-btn" data-modal-id="createPropertyModal">×</span>
        <h2>List a New Property</h2>
        <form id="createPropertyForm" action="create_property_process.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="editing_property_id" value=""> <!-- Для передачі ID при редагуванні -->

            <div class="form-group editing-only-field" style="display: none;"> <!-- Поле статусу, видиме тільки при редагуванні -->
                <label for="prop_status">Status:</label>
                <select id="prop_status" name="prop_status">
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="purchased">Purchased (Sold/Rented)</option>
                    <option value="inactive">Inactive (Off Market)</option>
                </select>
            </div>
            <!-- Поля форми для даних об'єкта -->
            <div>
                <label for="prop_title">Title / Catchy Headline:</label>
                <input type="text" id="prop_title" name="prop_title" required>
            </div>
            <div>
                <label for="prop_mls_number">MLS Number (optional):</label>
                <input type="text" id="prop_mls_number" name="prop_mls_number">
            </div>
            <div>
                <label for="prop_listing_type">Listing Type:</label>
                <select id="prop_listing_type" name="prop_listing_type" required>
                    <option value="sale">For Sale</option>
                    <option value="rent">For Rent</option>
                </select>
            </div>
            <div>
                <label for="prop_price">Price (USD):</label>
                <input type="number" id="prop_price" name="prop_price" step="0.01" min="0" required placeholder="e.g., 250000 or 1200 for rent/month">
            </div>
            <div>
                <label for="prop_description">Description:</label>
                <textarea id="prop_description" name="prop_description" rows="5" required></textarea>
            </div>
            <fieldset style="margin-bottom: 15px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                <legend style="padding: 0 5px;">Location Details</legend>
                <div>
                    <label for="prop_address1">Address Line 1 (Street, Number):</label>
                    <input type="text" id="prop_address1" name="prop_address1">
                </div>
                <div>
                    <label for="prop_address2">Address Line 2 (Apt, Suite, etc. - optional):</label>
                    <input type="text" id="prop_address2" name="prop_address2">
                </div>
                <div>
                    <label for="prop_city">City:</label>
                    <input type="text" id="prop_city" name="prop_city" required>
                </div>
                <div>
                    <label for="prop_state">State/Region:</label>
                    <input type="text" id="prop_state" name="prop_state">
                </div>
                <div>
                    <label for="prop_zip">ZIP Code:</label>
                    <input type="text" id="prop_zip" name="prop_zip">
                </div>
                <input type="hidden" id="prop_latitude_hidden" name="prop_latitude">
                <input type="hidden" id="prop_longitude_hidden" name="prop_longitude">
                <div id="mapPickerContainer" style="height: 250px; background-color: #f0f0f0; margin-top:10px; border:1px solid #ccc; border-radius: 4px; cursor: pointer; text-align:center; line-height:250px;">
                    Map will be here (Click to select location) <!-- Карта для вибору координат -->
                </div>
            </fieldset>
            <fieldset style="margin-bottom: 15px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                <legend style="padding: 0 5px;">Property Details</legend>
                <div>
                    <label for="prop_type">Property Type:</label>
                    <input type="text" id="prop_type" name="prop_type" placeholder="e.g., House, Apartment, Condo">
                </div>
                <div style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label for="prop_bedrooms">Bedrooms:</label>
                        <input type="number" id="prop_bedrooms" name="prop_bedrooms" min="0" step="1" value="1">
                    </div>
                    <div style="flex:1;">
                        <label for="prop_bathrooms">Bathrooms:</label>
                        <input type="number" id="prop_bathrooms" name="prop_bathrooms" min="0" step="0.5" value="1">
                    </div>
                </div>
                <div style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label for="prop_area_sqm">Area (sqm):</label>
                        <input type="number" id="prop_area_sqm" name="prop_area_sqm" min="0">
                    </div>
                    <div style="flex:1;">
                        <label for="prop_lot_size_sqm">Lot Size (sqm - if applicable):</label>
                        <input type="number" id="prop_lot_size_sqm" name="prop_lot_size_sqm" min="0">
                    </div>
                </div>
                <div style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label for="prop_year_built">Year Built:</label>
                        <input type="number" id="prop_year_built" name="prop_year_built" min="1800" max="<?php echo date('Y'); // Максимальний рік - поточний ?>">
                    </div>
                    <div style="flex:1;">
                        <label for="prop_date_available">Date Available (optional):</label>
                        <input type="date" id="prop_date_available" name="prop_date_available">
                    </div>
                </div>
            </fieldset>
            <fieldset style="margin-bottom: 15px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                <legend style="padding: 0 5px;">Contact Information</legend>
                <div>
                    <label for="prop_contact_name">Contact Name (optional, defaults to your profile name):</label>
                    <input type="text" id="prop_contact_name" name="prop_contact_name" placeholder="Leave blank to use your profile name">
                </div>
                <div>
                    <label for="prop_contact_email">Contact Email (optional, defaults to your account email):</label>
                    <input type="email" id="prop_contact_email" name="prop_contact_email">
                </div>
                <div>
                    <label for="prop_contact_phone">Contact Phone (optional):</label>
                    <input type="tel" id="prop_contact_phone" name="prop_contact_phone" placeholder="e.g., +1-555-123-4567">
                </div>
            </fieldset>
            <div>
                <label for="prop_photos">Property Photos (can select multiple, max 2MB per photo):</label>
                <input type="file" id="prop_photos" name="prop_photos[]" accept="image/*" multiple> <!-- Дозволяє завантаження декількох файлів -->
                <small>First selected image will be primary by default. You can upload up to 5 photos.</small>
            </div>
            <div class="form-actions" style="margin-top: 20px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="create_property_submit" class="form-action-btn action-main">List Property</button>
                </div>
                <div>
                    <button type="button" id="deletePropertyBtn" class="form-action-btn action-delete" style="display: none;">Delete Property</button> <!-- Кнопка видалення, видима при редагуванні -->
                </div>
            </div>
            <div id="createPropertyMessage" style="margin-top: 10px;"></div> <!-- Для повідомлень про успіх/помилку -->
        </form>
    </div>
</div>
<div id="myListingsModal" class="modal"> <!-- Модальне вікно "Мої оголошення" -->
    <div class="modal-content my-listings-modal-content">
        <span class="close-modal-btn" data-modal-id="myListingsModal">×</span>
        <h2>My Property Listings</h2>
        <div id="myListingsContainer">
            <p>Loading your listings...</p> <!-- Заглушка під час завантаження -->
        </div>
    </div>
</div>
<div id="editProfileModal" class="modal"> <!-- Модальне вікно редагування профілю -->
    <div class="modal-content edit-profile-modal-content">
        <span class="close-modal-btn" data-modal-id="editProfileModal">×</span>
        <h2>Edit Profile</h2>
        <div id="editProfileFormContainer">
            <form id="editProfileForm" method="POST" enctype="multipart/form-data">
                <div class="profile-info-display">
                    Current Access Level: <strong id="profileAccessLevel"></strong>
                </div>
                <hr style="margin: 15px 0;">
                <div>
                    <label for="profile_login">Login (cannot be changed):</label>
                    <input type="text" id="profile_login" name="profile_login" readonly style="background-color: #e9ecef;">
                </div>
                <div>
                    <label for="profile_name">Display Name:</label>
                    <input type="text" id="profile_name" name="profile_name" required>
                </div>
                <div>
                    <label for="profile_email">Email:</label>
                    <input type="email" id="profile_email" name="profile_email" required>
                </div>
                <div style="margin-bottom: 5px;">
                    <label>Current Photo:</label>
                    <img id="currentProfilePhoto" src="" alt="Current Profile Photo" style="max-width: 100px; max-height: 100px; display: block; margin-bottom: 5px; border-radius: 50%;">
                    <span id="noCurrentPhotoMsg" style="display:none; font-style:italic;">No photo uploaded.</span>
                </div>
                <div>
                    <label for="profile_photo">Change Photo (optional):</label>
                    <input type="file" id="profile_photo" name="profile_photo" accept="image/*">
                    <small>Leave blank to keep current photo. Max 2MB (JPG, PNG, GIF).</small>
                </div>
                <hr style="margin: 15px 0;">
                <p style="font-size: 0.9em; color: #555;">To change your password, leave the fields below blank if you do not wish to change it.</p>
                <div>
                    <label for="profile_current_password">Current Password (required to change password or email):</label>
                    <input type="password" id="profile_current_password" name="profile_current_password">
                </div>
                <div>
                    <label for="profile_new_password">New Password:</label>
                    <input type="password" id="profile_new_password" name="profile_new_password">
                </div>
                <div>
                    <label for="profile_confirm_password">Confirm New Password:</label>
                    <input type="password" id="profile_confirm_password" name="profile_confirm_password">
                </div>
                <div class="form-actions" style="margin-top: 20px;">
                    <button type="submit" name="update_profile_submit">Save Changes</button>
                </div>
                <div id="editProfileMessage" style="margin-top: 10px;"></div>
            </form>
        </div>
    </div>
</div>
<div id="newsDetailModal" class="modal"> <!-- Модальне вікно деталей новини -->
    <div class="modal-content news-detail-modal-content">
        <span class="close-modal-btn" data-modal-id="newsDetailModal">×</span>
        <div id="newsDetailContent">
            <input type="hidden" id="newsDetailIdHidden"> <!-- Для зберігання ID новини при редагуванні -->
            <h2 id="newsDetailTitle"></h2>
            <p class="news-detail-meta">
                <span id="newsDetailDate"></span>
                <span id="newsDetailSourceContainer" style="display:none;">| Source: <span id="newsDetailSource"></span></span>
            </p>
            <div id="newsDetailPhotoContainer" style="margin-bottom: 15px;">
                <img id="newsDetailPhoto" src="" alt="News Photo" style="max-width: 100%; height: auto; border-radius: 4px; display: none;">
            </div>
            <div id="newsDetailText" class="news-detail-text-content"></div>
            <?php if ($user_access_right == 10): // Кнопки для адміністратора ?>
                <div class="modal-actions" style="margin-top: 20px; text-align: right;">
                    <button type="button" id="editNewsFromDetailBtn" class="member-action-button">Edit News</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php if ($user_access_right == 10): // Модальні вікна, доступні тільки адміністратору ?>
    <div id="selectNewsModal" class="modal"> <!-- Вибір новини для банерів -->
        <div class="modal-content">
            <span class="close-modal-btn">×</span>
            <h2>Select News Article</h2>
            <input type="text" id="newsSearchInput" placeholder="Search news by title...">
            <div id="newsListContainer"></div> <!-- Сюди завантажується список новин -->
            <input type="hidden" id="currentBoxKeyInput"> <!-- Для зберігання ключа банера -->
        </div>
    </div>
    <div id="createNewsModal" class="modal"> <!-- Створення/редагування новини -->
        <div class="modal-content">
            <span class="close-modal-btn" data-modal-id="createNewsModal">×</span>
            <h2>Create New News Article</h2>
            <form id="createNewsForm" action="create_news_process.php" method="POST" enctype="multipart/form-data">
                <div>
                    <label for="news_title">Title:</label>
                    <input type="text" id="news_title" name="news_title" required>
                </div>
                <div>
                    <label for="news_text">Text:</label>
                    <textarea id="news_text" name="news_text" rows="6" required></textarea>
                </div>
                <div>
                    <label for="news_photo">Photo (optional):</label>
                    <input type="file" id="news_photo" name="news_photo" accept="image/*">
                </div>
                <div>
                    <label for="news_date">Publication Date:</label>
                    <input type="date" id="news_date" name="news_date" required value="<?php echo date('Y-m-d'); // За замовчуванням поточна дата ?>">
                </div>
                <div>
                    <label for="news_source">Source (optional):</label>
                    <input type="text" id="news_source" name="news_source">
                </div>
                <div class="form-actions" style="margin-top: 20px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" name="create_news_submit" class="form-action-btn action-main">Create News</button>
                        <button type="button" id="previewNewsBtn" class="form-action-btn action-secondary">Preview News</button>
                    </div>
                    <div>
                        <button type="button" id="deleteNewsBtn" class="form-action-btn action-delete" style="display: none;">Delete News</button> <!-- Кнопка видалення, видима при редагуванні -->
                    </div>
                </div>
                <div id="createNewsMessage" style="margin-top: 10px;"></div>
            </form>
        </div>
    </div>
    <div id="previewNewsModal" class="modal"> <!-- Попередній перегляд новини -->
        <div class="modal-content news-preview-modal-content">
            <span class="close-modal-btn" data-modal-id="previewNewsModal">×</span>
            <h2>News Preview</h2>
            <div id="previewNewsContent">
                <h3 id="previewNewsTitle" style="margin-top:0;"></h3>
                <p class="news-detail-meta">
                    <span id="previewNewsDate"></span>
                    <span id="previewNewsSourceContainer" style="display:none;">| Source: <span id="previewNewsSource"></span></span>
                </p>
                <div id="previewNewsPhotoContainer" style="margin-bottom: 15px;">
                    <img id="previewNewsPhoto" src="" alt="News Preview Photo" style="max-width: 100%; height: auto; border-radius: 4px; display: none;">
                </div>
                <div id="previewNewsText" class="news-detail-text-content" style="border: 1px dashed #ccc; padding:10px; min-height: 50px;"></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const featuredSection = document.querySelector('.featured-properties-section');
        const propertyCards = document.querySelectorAll('.featured-properties-section .property-card');

        function adjustVisiblePropertyCards() {
            if (!featuredSection || propertyCards.length === 0) {
                return;
            }

            const sectionWidth = featuredSection.offsetWidth;
            let totalCardsWidth = 0;
            let visibleCardsCount = 0;

            const gapStyle = window.getComputedStyle(featuredSection).gap;
            const gapValue = gapStyle && gapStyle !== 'normal' ? parseFloat(gapStyle) : 20;

            for (let i = 0; i < propertyCards.length; i++) {
                const card = propertyCards[i];
                const cardStyle = window.getComputedStyle(card);
                const cardOuterWidth = card.offsetWidth + (i < propertyCards.length - 1 ? gapValue : 0);

                if (totalCardsWidth + card.offsetWidth <= sectionWidth) {
                    totalCardsWidth += card.offsetWidth;
                    if (i > 0) {
                        totalCardsWidth += gapValue;
                    }
                    if (totalCardsWidth <= sectionWidth) {
                        card.style.display = 'flex';
                        visibleCardsCount++;
                    } else {
                        card.style.display = 'none';
                    }
                } else {
                    card.style.display = 'none';
                }
            }

        }

        if (featuredSection) {
            adjustVisiblePropertyCards();
            window.addEventListener('resize', adjustVisiblePropertyCards);

            const propertyObserver = new MutationObserver(adjustVisiblePropertyCards);
            propertyObserver.observe(featuredSection, { childList: true });
        }

        const editProfileModal = document.getElementById('editProfileModal');
        const openEditProfileBtn = document.querySelector('a.member-action-button[href="edit_profile.php"]');
        const closeEditProfileModalBtn = document.querySelector('#editProfileModal .close-modal-btn');
        const editProfileForm = document.getElementById('editProfileForm');
        const editProfileMessageDiv = document.getElementById('editProfileMessage');

        const profileAccessLevelEl = document.getElementById('profileAccessLevel');
        const profileLoginEl = document.getElementById('profile_login');
        const profileNameEl = document.getElementById('profile_name');
        const profileEmailEl = document.getElementById('profile_email');
        const currentProfilePhotoEl = document.getElementById('currentProfilePhoto');
        const noCurrentPhotoMsgEl = document.getElementById('noCurrentPhotoMsg');
        if (editProfileModal && openEditProfileBtn && closeEditProfileModalBtn && editProfileForm) {
            openEditProfileBtn.addEventListener('click', function(event) {
                event.preventDefault();
                editProfileMessageDiv.innerHTML = '';
                editProfileForm.reset();

                fetch('get_user_profile.php')
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok.');
                        return response.json();
                    })
                    .then(data => {
                        if (data.success && data.user) {
                            const user = data.user;
                            profileLoginEl.value = user.login || '';
                            profileNameEl.value = user.name || '';
                            profileEmailEl.value = user.email || '';

                            let accessLevelText = 'Unknown';
                            if (user.access_right == 1) accessLevelText = 'Member';
                            else if (user.access_right == 10) accessLevelText = 'Administrator';
                            else if (user.access_right == 0) accessLevelText = 'Guest (Error?)';
                            profileAccessLevelEl.textContent = accessLevelText;

                            if (user.has_photo) {
                                currentProfilePhotoEl.src = `get_user_photo.php?id=${user.id}&t=${new Date().getTime()}`;
                                currentProfilePhotoEl.style.display = 'block';
                                noCurrentPhotoMsgEl.style.display = 'none';
                            } else {
                                currentProfilePhotoEl.src = '';
                                currentProfilePhotoEl.style.display = 'none';
                                noCurrentPhotoMsgEl.style.display = 'block';
                            }
                            editProfileModal.style.display = 'block';
                        } else {
                            editProfileMessageDiv.innerHTML = `<p style="color: red;">Error: ${data.message || 'Could not load profile data.'}</p>`;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching profile data:', error);
                        editProfileMessageDiv.innerHTML = `<p style="color: red;">An error occurred while loading profile data: ${error.message}</p>`;
                    });
            });

            closeEditProfileModalBtn.onclick = function() {
                editProfileModal.style.display = 'none';
            }

            editProfileForm.addEventListener('submit', function(event) {
                event.preventDefault();
                editProfileMessageDiv.innerHTML = '<p>Saving changes...</p>';

                const formData = new FormData(this);
                formData.append('update_profile_submit', 'true');

                fetch('update_user_profile.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => {
                        if (!response.ok) {
                            return response.text().then(text => { throw new Error(`Server error: ${response.status} ${text || ''}`); });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            editProfileMessageDiv.innerHTML = `<p style="color: green;">${data.message || 'Profile updated successfully!'}</p>`;
                            if (data.new_name) {
                                const userNameDisplay = document.querySelector('.user-name-display');
                                if(userNameDisplay) userNameDisplay.textContent = data.new_name;
                            }
                            const userPhotoInArea = document.querySelector('.logged-in-user-info .user-photo');
                            if(userPhotoInArea && data.photo_updated) {
                                userPhotoInArea.src = `get_user_photo.php?id=<?php echo $_SESSION['user_id'] ?? 0; ?>&t=${new Date().getTime()}`;
                            }
                            setTimeout(() => {
                                if (editProfileModal) editProfileModal.style.display = 'none';
                            }, 1500);
                        } else {
                            editProfileMessageDiv.innerHTML = `<p style="color: red;">Error: ${data.message || 'Failed to update profile.'}</p>`;
                        }
                    })
                    .catch(error => {
                        console.error('Error updating profile:', error);
                        editProfileMessageDiv.innerHTML = `<p style="color: red;">An error occurred: ${error.message}</p>`;
                    });
            });
        } else {
            if (!openEditProfileBtn && <?php echo json_encode($is_logged_in); ?>) {
                console.warn('Button to open Edit Profile modal not found, but user is logged in.');
            }
        }
        let membersAreaGlobal = null;
        let infoBoxesGlobal = null;
        let observerGlobal = null;

        function syncInfoBoxHeights() {
            const currentMembersArea = document.querySelector('.members-area');
            const currentInfoBoxes = document.querySelectorAll('.info-boxes-section .info-box');

            if (currentMembersArea && currentInfoBoxes && currentInfoBoxes.length > 0) {
                requestAnimationFrame(() => {
                    const membersAreaHeight = currentMembersArea.offsetHeight;
                    let targetHeight = membersAreaHeight;
                    const minBannerHeight = 150;
                    if (targetHeight < minBannerHeight) {
                        targetHeight = minBannerHeight;
                    }
                    currentInfoBoxes.forEach(box => {
                        box.style.height = targetHeight + 'px';
                    });
                });
            } else {
                if (!currentMembersArea) console.warn('[syncInfoBoxHeights] .members-area not found.');
                if (!currentInfoBoxes || currentInfoBoxes.length === 0) console.warn('[syncInfoBoxHeights] .info-box not found.');
            }
        }

        function initializeHeightSyncAndObserver() {
            membersAreaGlobal = document.querySelector('.members-area');
            infoBoxesGlobal = document.querySelectorAll('.info-boxes-section .info-box');

            if (membersAreaGlobal) {
                syncInfoBoxHeights();
                window.addEventListener('resize', syncInfoBoxHeights);

                if (observerGlobal) { observerGlobal.disconnect(); }
                observerGlobal = new MutationObserver(function(mutationsList, obs) {
                    syncInfoBoxHeights();
                });
                observerGlobal.observe(membersAreaGlobal, {
                    childList: true, subtree: true, characterData: true, attributes: true
                });
            } else {
                if (observerGlobal) { observerGlobal.disconnect(); }
            }
        }

        initializeHeightSyncAndObserver();
        window.addEventListener('load', function() {
            initializeHeightSyncAndObserver();
            setTimeout(syncInfoBoxHeights, 250);
            setTimeout(syncInfoBoxHeights, 750);
        });

        const createPropertyModal = document.getElementById('createPropertyModal');
        const closeCreatePropertyModalBtn = document.querySelector('#createPropertyModal .close-modal-btn');
        const deletePropertyBtn = document.getElementById('deletePropertyBtn');
        const createPropertyForm = document.getElementById('createPropertyForm');
        const createPropertyMessageDiv = document.getElementById('createPropertyMessage');
        const isUserLoggedInForProperty = <?php echo json_encode($is_logged_in); ?>;
        const currentUserEmail = <?php echo json_encode($user_session_email); ?>;
        console.log('PHP session email passed to JS (currentUserEmail):', currentUserEmail);
        const currentUserName = <?php echo json_encode($is_logged_in ? $_SESSION['user_name'] : ''); ?>;

        const propertyDetailModal = document.getElementById('propertyDetailModal');
        const closePropertyDetailModalBtn = document.querySelector('#propertyDetailModal .close-modal-btn');

        const propDetailTitle = document.getElementById('propDetailTitle');
        const propDetailPrice = document.getElementById('propDetailPrice');
        const propDetailType = document.getElementById('propDetailType');
        const propDetailLocation = document.getElementById('propDetailLocation');
        const galleryMainImage = document.getElementById('galleryMainImage');
        const thumbnailContainer = document.querySelector('#propDetailGallery .thumbnail-container');
        const noPropertyImagesMsg = document.getElementById('noPropertyImages');
        const propDetailDescription = document.getElementById('propDetailDescription');

        const propDetailTableRows = document.querySelectorAll('.property-details-table tr[data-field]');

        const propDetailContactSection = document.getElementById('propDetailContactSection');
        const propDetailContactName = document.getElementById('propDetailContactName');
        const propDetailContactEmail = document.getElementById('propDetailContactEmail');
        const propDetailContactPhone = document.getElementById('propDetailContactPhone');
        const editPropertyFromDetailBtn = document.getElementById('editPropertyFromDetailBtn');
        const purchasePropertyBtn = document.getElementById('purchasePropertyBtn');

        if (propertyDetailModal && closePropertyDetailModalBtn) {

            document.body.addEventListener('click', function(event) {
                let targetElement = event.target;
                while (targetElement && targetElement !== document.body) {
                    if (targetElement.matches('a.view-gallery-btn')) {
                        event.preventDefault();
                        const propIdHref = targetElement.getAttribute('href');
                        const propIdMatch = propIdHref ? propIdHref.match(/id=(\d+)/) : null;

                        if (propIdMatch && propIdMatch[1]) {
                            loadPropertyDetail(propIdMatch[1]);
                        } else {
                            console.error('Could not extract property ID from href:', propIdHref);
                        }
                        return;
                    }
                    targetElement = targetElement.parentNode;
                }
            });

            closePropertyDetailModalBtn.onclick = function() {
                propertyDetailModal.style.display = 'none';
            }
        }

        const propertyCardSection = document.querySelector('.featured-properties-section');

        if (propertyCardSection) {
            propertyCardSection.addEventListener('click', function(event) {
                const cardLink = event.target.closest('.property-card-link');
                if (cardLink) {
                    event.preventDefault();

                    const propertyId = cardLink.dataset.propertyId;
                    const listingType = cardLink.dataset.listingType;
                    const propertyStatus = cardLink.dataset.propertyStatus;
                    const agentId = cardLink.dataset.agentId;

                    const currentUserSessionId = <?php echo json_encode($_SESSION['user_id'] ?? null); ?>;
                    const currentUserAccessRight = <?php echo json_encode($_SESSION['access_right'] ?? 0); ?>;

                    if (!currentUserSessionId) {
                        alert('Please log in to proceed.');


                        return;
                    }

                    if (propertyStatus !== 'active') {
                        alert('This property is not currently available for purchase or rent.');


                        return;
                    }

                    if (currentUserAccessRight < 10 && agentId && parseInt(agentId) === parseInt(currentUserSessionId)) {
                        alert("You cannot purchase or rent your own listing. Opening details instead.");
                        window.location.href = cardLink.href;
                        return;
                    }

                    const actionText = (listingType === 'rent') ? 'rent' : 'purchase';
                    if (confirm(`Are you sure you want to proceed to ${actionText} this property (ID: ${propertyId})?`)) {
                        const formData = new FormData();
                        formData.append('property_id', propertyId);
                        formData.append('new_status', 'pending');
                        formData.append('action_type', actionText);
                        formData.append('mark_as_pending_submit', 'true');

                        fetch('update_property_status.php', {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert(`Your request to ${actionText} property ID ${propertyId} has been submitted! The status is now PENDING. You will be contacted shortly.`);
                                    window.location.reload();
                                } else {
                                    alert(`Failed to submit your request: ${data.message || 'Unknown error'}`);
                                }
                            })
                            .catch(error => {
                                console.error(`Error during ${actionText} process from card click:`, error);
                                alert(`An error occurred while trying to ${actionText} the property.`);
                            });
                    }

                }
            });
        }
        let propertyDetailMapInstance = null;
        const propertyDetailMapDiv = document.getElementById('propertyDetailMap');
        const propertyDetailMapContainer = document.getElementById('propertyDetailMapContainer');
        const mapSeparator = document.getElementById('mapSeparator');

        function initializeOrUpdatePropertyDetailMap(latitude, longitude, propertyTitle) {
            if (!propertyDetailMapDiv) {
                console.error("Map div 'propertyDetailMap' not found!");
                return;
            }

            if (propertyDetailMapContainer) propertyDetailMapContainer.style.display = 'block';
            if (mapSeparator && document.getElementById('propDetailContactSection').style.display === 'block') {
                mapSeparator.style.display = 'block';
            }

            if (propertyDetailMapInstance) {

                propertyDetailMapInstance.setView([latitude, longitude], 15);

                propertyDetailMapInstance.eachLayer(function (layer) {
                    if (layer instanceof L.Marker) {
                        propertyDetailMapInstance.removeLayer(layer);
                    }
                });
                L.marker([latitude, longitude]).addTo(propertyDetailMapInstance)
                    .bindPopup(propertyTitle || "Property Location")
                    .openPopup();
            } else {

                propertyDetailMapInstance = L.map(propertyDetailMapDiv).setView([latitude, longitude], 15);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(propertyDetailMapInstance);

                L.marker([latitude, longitude]).addTo(propertyDetailMapInstance)
                    .bindPopup(propertyTitle || "Property Location")
                    .openPopup();
            }

            setTimeout(function() {
                if (propertyDetailMapInstance) {
                    propertyDetailMapInstance.invalidateSize();

                }
            }, 100);
        }
        function hidePropertyDetailMap() {
            if (propertyDetailMapContainer) propertyDetailMapContainer.style.display = 'none';
            if (mapSeparator) mapSeparator.style.display = 'none';
        }

        function loadPropertyDetail(propertyId) {
            if (!propertyDetailModal) return;

            propDetailTitle.textContent = 'Loading Property...';
            propDetailPrice.textContent = '';
            propDetailType.textContent = '';
            propDetailLocation.textContent = '';
            galleryMainImage.src = ''; galleryMainImage.style.display = 'none';
            thumbnailContainer.innerHTML = ''; noPropertyImagesMsg.style.display = 'block';
            document.getElementById('propDetailGallery').style.display = 'none';
            propDetailDescription.innerHTML = '';
            propDetailTableRows.forEach(row => {
                const cellValue = row.cells[1];
                if (cellValue) cellValue.textContent = 'N/A';
                row.style.display = 'none';
            });
            propDetailContactSection.style.display = 'none';
            propDetailContactName.textContent = '';
            propDetailContactEmail.textContent = ''; propDetailContactEmail.href = '';
            propDetailContactPhone.textContent = '';

            if (editPropertyFromDetailBtn) editPropertyFromDetailBtn.style.display = 'none';
            if (purchasePropertyBtn) purchasePropertyBtn.style.display = 'none';

            hidePropertyDetailMap()
            propertyDetailModal.style.display = 'block';

            fetch(`get_property_detail.php?id=${propertyId}`)
                .then(response => {
                    if (!response.ok) throw new Error(`Network error: ${response.status} ${response.statusText}`);
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.property) {
                        const prop = data.property;
                        currentEditingPropertyData = prop;
                        propDetailTitle.textContent = prop.title || 'N/A';

                        const priceElement = document.getElementById('propDetailPrice');
                        priceElement.classList.remove('price-strikethrough');
                        priceElement.innerHTML = ''; // Очистити попередній вміст

                        propDetailType.textContent = `${prop.property_type || 'Property'} for ${prop.listing_type || 'N/A'}`;

                        let addressStringParts = [];
                        let cityStateZipParts = [];

                        let fullAddressLine = '';
                        if (prop.address_line1 && prop.address_line1.trim() !== '') {
                            fullAddressLine += prop.address_line1.trim();
                        }
                        if (prop.address_line2 && prop.address_line2.trim() !== '') {
                            if (fullAddressLine !== '') {
                                fullAddressLine += ', ';
                            }
                            fullAddressLine += prop.address_line2.trim();
                        }

                        if (fullAddressLine !== '') {
                            addressStringParts.push(fullAddressLine);
                        }

                        if (prop.city && prop.city.trim() !== '') {
                            cityStateZipParts.push(prop.city.trim());
                        }
                        if (prop.state && prop.state.trim() !== '') {
                            cityStateZipParts.push(prop.state.trim());
                        }
                        if (prop.zip_code && prop.zip_code.trim() !== '') {
                            cityStateZipParts.push(prop.zip_code.trim());
                        }

                        if (cityStateZipParts.length > 0) {
                            addressStringParts.push(cityStateZipParts.join(', '));
                        }

                        propDetailLocation.textContent = addressStringParts.join(' | ').trim();
                        if (propDetailLocation.textContent.startsWith(' | ')) {
                            propDetailLocation.textContent = propDetailLocation.textContent.substring(3);
                        }
                        if (propDetailLocation.textContent.endsWith(' | ')) {
                            propDetailLocation.textContent = propDetailLocation.textContent.substring(0, propDetailLocation.textContent.length - 3);
                        }

                        if (prop.latitude && prop.longitude) {
                            const lat = parseFloat(prop.latitude);
                            const lon = parseFloat(prop.longitude);
                            if (!isNaN(lat) && !isNaN(lon)) {

                                initializeOrUpdatePropertyDetailMap(lat, lon, prop.title);
                            } else {

                                hidePropertyDetailMap();
                            }
                        } else {

                            hidePropertyDetailMap();
                        }

                        const currentUserSessionId = <?php echo json_encode($_SESSION['user_id'] ?? null); ?>;
                        const currentUserAccessRight = <?php echo json_encode($_SESSION['access_right'] ?? 0); ?>;
                        const propertyAgentId = prop.agent_id ? parseInt(prop.agent_id) : null;

                        if (editPropertyFromDetailBtn && prop.agent_id && currentUserSessionId) {
                            if (currentUserAccessRight == 10 || parseInt(prop.agent_id) === parseInt(currentUserSessionId)) {
                                editPropertyFromDetailBtn.style.display = 'inline-block';
                                editPropertyFromDetailBtn.dataset.propertyId = propertyId;
                            } else {
                                editPropertyFromDetailBtn.style.display = 'none';
                            }
                        } else if (editPropertyFromDetailBtn) {
                            editPropertyFromDetailBtn.style.display = 'none';
                        }

                        if (purchasePropertyBtn) {

                            if (currentUserSessionId && prop.status === 'active') {
                                if (currentUserAccessRight == 10) {
                                    purchasePropertyBtn.style.display = 'inline-block';
                                } else if (propertyAgentId !== parseInt(currentUserSessionId)) {

                                    purchasePropertyBtn.style.display = 'inline-block';
                                } else {
                                    purchasePropertyBtn.style.display = 'none';
                                }
                                purchasePropertyBtn.dataset.propertyId = prop.id;
                                purchasePropertyBtn.dataset.listingType = prop.listing_type;
                            } else {
                                purchasePropertyBtn.style.display = 'none';
                            }
                        }

                        if (prop.photos && prop.photos.length > 0) {
                            document.getElementById('propDetailGallery').style.display = 'block';
                            noPropertyImagesMsg.style.display = 'none';
                            galleryMainImage.src = prop.photos[0].image_path;
                            galleryMainImage.alt = prop.photos[0].caption || prop.title;
                            galleryMainImage.style.display = 'block';
                            thumbnailContainer.innerHTML = '';
                            prop.photos.forEach(photo => {
                                const thumb = document.createElement('img');
                                thumb.src = photo.image_path;
                                thumb.alt = photo.caption || `Thumbnail for ${prop.title}`;
                                thumb.classList.add('gallery-thumbnail');
                                thumb.onclick = () => { galleryMainImage.src = photo.image_path; galleryMainImage.alt = photo.caption || prop.title; };
                                thumbnailContainer.appendChild(thumb);
                            });
                        } else {
                            document.getElementById('propDetailGallery').style.display = 'none';
                            noPropertyImagesMsg.style.display = 'block';
                            galleryMainImage.style.display = 'none';
                        }

                        propDetailDescription.innerHTML = nl2br_js(prop.description || 'No description available.');

                        propDetailTableRows.forEach(row => {
                            const field = row.dataset.field;
                            const cell = row.cells[1];
                            if (prop.hasOwnProperty(field) && prop[field] !== null && prop[field] !== '') {
                                let value = prop[field];
                                if (field === 'area_sqm' || field === 'lot_size_sqm') value += ' sqm';
                                else if (field === 'date_available' && value) {

                                    const dateParts = value.split('-');
                                    if (dateParts.length === 3) {

                                        const dateObj = new Date(dateParts[0], dateParts[1] - 1, dateParts[2]);
                                        if (!isNaN(dateObj.getTime())) {
                                            value = dateObj.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
                                        } else {
                                            value = 'Invalid Date';
                                        }
                                    } else {
                                        value = 'Invalid Date Format';
                                    }
                                }
                                cell.textContent = value;
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        });

                        let contactInfoAvailable = false;
                        const contactNameP = propDetailContactSection.querySelector('p[data-field="contact_name"]');
                        const contactEmailP = propDetailContactSection.querySelector('p[data-field="contact_email"]');
                        const contactPhoneP = propDetailContactSection.querySelector('p[data-field="contact_phone"]');

                        if(prop.contact_name && prop.contact_name.trim() !== ''){
                            propDetailContactName.textContent = prop.contact_name;
                            if(contactNameP) contactNameP.style.display = '';
                            contactInfoAvailable = true;
                        } else {
                            if(contactNameP) contactNameP.style.display = 'none';
                        }

                        // Відображення ціни та статусу для модального режиму деталізації властивостей
                        priceElement.classList.remove('price-strikethrough');
                        let basePriceText = `$${parseFloat(prop.price || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                        if (prop.listing_type === 'rent') basePriceText += ' / month';

                        let fullPriceDisplay = (prop.listing_type == 'rent' ? 'Rent: ' : '') + basePriceText;
                        let statusBadgeHTML = '';

                        switch (prop.status) {
                            case 'pending':
                                statusBadgeHTML = ' <span class="property-status-badge status-pending">PENDING</span>';
                                break;
                            case 'purchased':
                                let purchasedText = (prop.listing_type === 'sale') ? 'SOLD' : 'RENTED';
                                statusBadgeHTML = ` <span class="property-status-badge status-${purchasedText.toLowerCase()}">${purchasedText}</span>`;
                                priceElement.classList.add('price-strikethrough');
                                break;
                            case 'inactive':
                                statusBadgeHTML = ' <span class="property-status-badge status-inactive">OFF MARKET</span>';
                                priceElement.classList.add('price-strikethrough');
                                break;
                            case 'active':
                            default:
                                priceElement.classList.remove('price-strikethrough');
                                break;
                        }
                        priceElement.innerHTML = fullPriceDisplay + statusBadgeHTML;

                        if(prop.contact_email && prop.contact_email.trim() !== ''){
                            propDetailContactEmail.textContent = prop.contact_email;
                            propDetailContactEmail.href = `mailto:${prop.contact_email}`;
                            if(contactEmailP) contactEmailP.style.display = '';
                            contactInfoAvailable = true;
                        } else {
                            if(contactEmailP) contactEmailP.style.display = 'none';
                        }

                        if(prop.contact_phone && prop.contact_phone.trim() !== ''){
                            propDetailContactPhone.textContent = prop.contact_phone;
                            if(contactPhoneP) contactPhoneP.style.display = '';
                            contactInfoAvailable = true;
                        } else {
                            if(contactPhoneP) contactPhoneP.style.display = 'none';
                        }
                        propDetailContactSection.style.display = contactInfoAvailable ? 'block' : 'none';

                    } else {
                        propDetailTitle.textContent = 'Property Not Found';
                        propDetailDescription.innerHTML = `<p style="color:red;">${data.message || 'Could not load property details.'}</p>`;
                        if (editPropertyFromDetailBtn) editPropertyFromDetailBtn.style.display = 'none';
                        if (purchasePropertyBtn) purchasePropertyBtn.style.display = 'none';
                        hidePropertyDetailMap();
                    }
                })
                .catch(error => {
                    console.error('Error loading property detail:', error);
                    propDetailTitle.textContent = 'Error';
                    propDetailDescription.innerHTML = `<p style="color:red;">Failed to load content: ${error.message}</p>`;
                    if (editPropertyFromDetailBtn) editPropertyFromDetailBtn.style.display = 'none';
                    if (purchasePropertyBtn) purchasePropertyBtn.style.display = 'none';
                });
        }

        if (editPropertyFromDetailBtn && createPropertyModal && createPropertyForm && deletePropertyBtn) {
            editPropertyFromDetailBtn.addEventListener('click', function() {
                const propertyIdToEdit = this.dataset.propertyId;
                if (!propertyIdToEdit || !currentEditingPropertyData || currentEditingPropertyData.id != propertyIdToEdit) {
                    alert('Error: Property data not available for editing. Please reopen the details.');
                    console.error("Mismatch or missing currentEditingPropertyData for ID:", propertyIdToEdit, currentEditingPropertyData);
                    return;
                }

                if (propertyDetailModal) propertyDetailModal.style.display = 'none';

                createPropertyForm.reset();
                const modalTitleEl = createPropertyModal.querySelector('h2');
                if (modalTitleEl) modalTitleEl.textContent = 'Edit Property Listing';
                const submitBtn = createPropertyForm.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.textContent = 'Save Changes';
                const statusFieldWrapperEdit = createPropertyForm.querySelector('.editing-only-field');
                if (statusFieldWrapperEdit) statusFieldWrapperEdit.style.display = 'block';

                let editingIdField = createPropertyForm.querySelector('input[name="editing_property_id"]');
                if (!editingIdField) {
                    editingIdField = document.createElement('input');
                    editingIdField.type = 'hidden';
                    editingIdField.name = 'editing_property_id';
                    createPropertyForm.appendChild(editingIdField);
                }
                editingIdField.value = propertyIdToEdit;

                const propData = currentEditingPropertyData;
                createPropertyForm.querySelector('#prop_title').value = propData.title || '';
                createPropertyForm.querySelector('#prop_mls_number').value = propData.mls_number || '';
                createPropertyForm.querySelector('#prop_listing_type').value = propData.listing_type || 'sale';
                createPropertyForm.querySelector('#prop_price').value = propData.price || '';
                createPropertyForm.querySelector('#prop_description').value = propData.description || '';
                createPropertyForm.querySelector('#prop_address1').value = propData.address_line1 || '';
                createPropertyForm.querySelector('#prop_address2').value = propData.address_line2 || '';
                createPropertyForm.querySelector('#prop_city').value = propData.city || '';
                createPropertyForm.querySelector('#prop_state').value = propData.state || '';
                createPropertyForm.querySelector('#prop_zip').value = propData.zip_code || '';

                const latHiddenInput = createPropertyForm.querySelector('#prop_latitude_hidden');
                const lngHiddenInput = createPropertyForm.querySelector('#prop_longitude_hidden');
                if(latHiddenInput) latHiddenInput.value = propData.latitude || '';
                if(lngHiddenInput) lngHiddenInput.value = propData.longitude || '';

                createPropertyForm.querySelector('#prop_type').value = propData.property_type || '';
                createPropertyForm.querySelector('#prop_bedrooms').value = propData.bedrooms || '';
                createPropertyForm.querySelector('#prop_bathrooms').value = propData.bathrooms || '';
                createPropertyForm.querySelector('#prop_area_sqm').value = propData.area_sqm || '';
                createPropertyForm.querySelector('#prop_lot_size_sqm').value = propData.lot_size_sqm || '';
                createPropertyForm.querySelector('#prop_year_built').value = propData.year_built || '';
                createPropertyForm.querySelector('#prop_date_available').value = propData.date_available ? propData.date_available.split(' ')[0] : '';

                createPropertyForm.querySelector('#prop_contact_name').value = propData.contact_name || '';
                createPropertyForm.querySelector('#prop_contact_email').value = propData.contact_email || '';
                createPropertyForm.querySelector('#prop_contact_phone').value = propData.contact_phone || '';

                const photoInput = createPropertyForm.querySelector('#prop_photos');
                if (photoInput) photoInput.value = '';

                deletePropertyBtn.style.display = 'inline-block';
                deletePropertyBtn.dataset.propertyIdToDelete = currentEditingPropertyData.id;


                if (createPropertyModal) {
                    createPropertyModal.style.display = 'block';

                    if (typeof initializeOrUpdateMap === 'function' && propData.latitude && propData.longitude) {
                        const editLat = parseFloat(propData.latitude);
                        const editLng = parseFloat(propData.longitude);
                        if (!isNaN(editLat) && !isNaN(editLng)) {
                            initializeOrUpdateMap(editLat, editLng, 15);
                            setTimeout(() => { if (map) map.invalidateSize(); }, 50);
                        }
                    } else if (typeof initializeOrUpdateMap === 'function') {
                        initializeOrUpdateMap(50.4501, 30.5234, 7);
                        setTimeout(() => { if (map) map.invalidateSize(); }, 50);
                    }
                }
                createPropertyForm.querySelector('#prop_status').value = propData.status || 'active';
            });
        }

        if (purchasePropertyBtn) {
            purchasePropertyBtn.addEventListener('click', function() {
                const propertyId = this.dataset.propertyId;
                const listingType = this.dataset.listingType;
                const actionText = (listingType === 'rent') ? 'rent' : 'purchase';

                if (!propertyId) {
                    alert('Error: Property ID not found for purchase/rent action.');
                    return;
                }

                if (confirm(`Are you sure you want to proceed to ${actionText} this property (ID: ${propertyId})?`)) {
                    const formData = new FormData();
                    formData.append('property_id', propertyId);
                    formData.append('new_status', 'pending');
                    formData.append('action_type', actionText);
                    formData.append('mark_as_pending_submit', 'true');

                    fetch('update_property_status.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert(`Your request to ${actionText} property ID ${propertyId} has been submitted! The status is now PENDING. You will be contacted shortly.`);

                                if (propertyDetailModal) propertyDetailModal.style.display = 'none';
                                window.location.reload();
                            } else {
                                alert(`Failed to submit your request: ${data.message || 'Unknown error'}`);
                            }
                        })
                        .catch(error => {
                            console.error(`Error during ${actionText} process:`, error);
                            alert(`An error occurred while trying to ${actionText} the property.`);
                        });

                }
            });
        }
        let currentEditingPropertyData = null;

        const newsDetailModal = document.getElementById('newsDetailModal');
        const closeNewsDetailModalBtn = document.querySelector('#newsDetailModal .close-modal-btn');
        const newsDetailContent = document.getElementById('newsDetailContent');
        const newsDetailTitleElem = document.getElementById('newsDetailTitle');
        const newsDetailDateElem = document.getElementById('newsDetailDate');
        const newsDetailSourceContainerElem = document.getElementById('newsDetailSourceContainer');
        const newsDetailSourceElem = document.getElementById('newsDetailSource');
        const newsDetailPhotoContainerElem = document.getElementById('newsDetailPhotoContainer');
        const newsDetailPhotoElem = document.getElementById('newsDetailPhoto');
        const newsDetailTextElem = document.getElementById('newsDetailText');
        const newsDetailIdHiddenInput = document.getElementById('newsDetailIdHidden');
        const editNewsFromDetailBtn = document.getElementById('editNewsFromDetailBtn');

        if (newsDetailModal && closeNewsDetailModalBtn && newsDetailContent &&
            newsDetailTitleElem && newsDetailDateElem && newsDetailSourceContainerElem &&
            newsDetailSourceElem && newsDetailPhotoContainerElem && newsDetailPhotoElem && newsDetailTextElem &&
            newsDetailIdHiddenInput) {

            document.body.addEventListener('click', function(event) {
                let targetElement = event.target;
                while (targetElement && targetElement !== document.body) {
                    if (targetElement.matches('a.read-more, a.read-more-infobox, a.read-more-infobox-standalone')) {
                        event.preventDefault();
                        const newsIdHref = targetElement.getAttribute('href');
                        const newsIdMatch = newsIdHref ? newsIdHref.match(/id=(\d+)/) : null;
                        if (newsIdMatch && newsIdMatch[1]) {
                            loadNewsDetail(newsIdMatch[1]);
                        } else { console.error('Could not extract news ID from href:', newsIdHref); }
                        return;
                    }
                    targetElement = targetElement.parentNode;
                }
            });
            closeNewsDetailModalBtn.onclick = function() {
                newsDetailModal.style.display = 'none';
            }

            if (editNewsFromDetailBtn) {
                editNewsFromDetailBtn.addEventListener('click', function() {
                    const newsIdToEdit = newsDetailIdHiddenInput.value;
                    if (newsIdToEdit) {
                        newsDetailModal.style.display = 'none';

                        const createNewsModal = document.getElementById('createNewsModal');
                        const createNewsForm = document.getElementById('createNewsForm');
                        const createNewsMessageDiv = document.getElementById('createNewsMessage');
                        const deleteBtn = document.getElementById('deleteNewsBtn');

                        if (createNewsModal && createNewsForm && deleteBtn) {
                            createNewsMessageDiv.innerHTML = '';
                            createNewsForm.reset();

                            createNewsModal.querySelector('h2').textContent = 'Edit News Article';
                            createNewsForm.querySelector('button[type="submit"]').textContent = 'Save Changes';

                            let existingNewsIdField = createNewsForm.querySelector('input[name="editing_news_id"]');
                            if (!existingNewsIdField) {
                                existingNewsIdField = document.createElement('input');
                                existingNewsIdField.type = 'hidden';
                                existingNewsIdField.name = 'editing_news_id';
                                createNewsForm.appendChild(existingNewsIdField);
                            }
                            existingNewsIdField.value = newsIdToEdit;

                            deleteBtn.style.display = 'inline-block';

                            fetch(`get_news_detail.php?id=${newsIdToEdit}`)
                                .then(response => {
                                    if (!response.ok) throw new Error('Failed to fetch news details for editing');
                                    return response.json();
                                })
                                .then(data => {
                                    if (data.success && data.news) {
                                        const news = data.news;
                                        createNewsForm.querySelector('#news_title').value = news.title || '';
                                        createNewsForm.querySelector('#news_text').value = news.text || '';

                                        createNewsForm.querySelector('#news_date').value = news.date ? news.date.split(' ')[0] : '';
                                        createNewsForm.querySelector('#news_source').value = news.source || '';

                                    } else {
                                        alert('Error loading news data for editing: ' + (data.message || 'Unknown error'));
                                    }
                                })
                                .catch(error => {
                                    console.error('Error fetching news for edit:', error);
                                    alert('Failed to load news data for editing.');
                                });

                            createNewsModal.style.display = 'block';
                        } else {
                            console.error('Create/Edit news modal, its form, or delete button not found.');
                        }
                    } else {
                        console.error('No news ID found in newsDetailIdHiddenInput for editing.');
                    }
                });
            }
        }

        function loadNewsDetail(newsId) {

            if (!newsDetailModal || !newsDetailContent || !newsDetailIdHiddenInput ||
                !newsDetailTitleElem || !newsDetailTextElem || !newsDetailDateElem ||
                !newsDetailSourceContainerElem || !newsDetailSourceElem ||
                !newsDetailPhotoContainerElem || !newsDetailPhotoElem) {
                console.error("[loadNewsDetail] Essential modal elements for displaying news details are missing.");
                return;
            }

            newsDetailTitleElem.textContent = 'Loading...';
            newsDetailTextElem.innerHTML = '';
            newsDetailDateElem.textContent = '';
            newsDetailSourceContainerElem.style.display = 'none';
            newsDetailSourceElem.textContent = '';
            newsDetailPhotoElem.style.display = 'none';
            newsDetailPhotoElem.src = '';
            newsDetailPhotoElem.alt = '';

            newsDetailIdHiddenInput.value = newsId;
            newsDetailModal.style.display = 'block';

            fetch(`get_news_detail.php?id=${newsId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Network response was not ok: ${response.status} ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.news) {
                        const news = data.news;
                        newsDetailTitleElem.textContent = news.title || 'No Title';
                        newsDetailTextElem.innerHTML = nl2br_js(news.text || 'No content available.');

                        if (news.hasOwnProperty('date') && news.date && typeof news.date === 'string' && news.date.trim() !== '') {
                            const dateStringFromServer = news.date.trim();

                            // Спроба розібрати рядок дати типу "YYYY-MM-DD HH:MM:SS" або "YYYY-MM-DD"
                            const dateObj = new Date(dateStringFromServer.replace(/-/g, '/'));

                            if (dateObj && !isNaN(dateObj.getTime())) {
                                const options = { year: 'numeric', month: '2-digit', day: '2-digit' };
                                try {
                                    newsDetailDateElem.textContent = `Date: ${dateObj.toLocaleDateString(undefined, options)}`;
                                } catch (e) {
                                    console.error('[loadNewsDetail] Error formatting date with toLocaleDateString:', e);
                                    newsDetailDateElem.textContent = 'Date: Formatting Error';
                                }
                            } else {
                                newsDetailDateElem.textContent = 'Date: Invalid Date (JS parsing failed)';
                            }
                        } else {
                            newsDetailDateElem.textContent = 'Date: Not Provided';
                        }

                        if (news.source) {
                            newsDetailSourceElem.textContent = news.source;
                            newsDetailSourceContainerElem.style.display = 'inline';
                        } else {
                            newsDetailSourceContainerElem.style.display = 'none';
                        }

                        if (news.photo) {
                            newsDetailPhotoElem.src = news.photo;
                            newsDetailPhotoElem.alt = news.title || 'News Image';
                            newsDetailPhotoElem.style.display = 'block';
                        } else {
                            newsDetailPhotoElem.style.display = 'none';
                        }
                    } else {
                        newsDetailTitleElem.textContent = 'Error';
                        newsDetailTextElem.innerHTML = `<p style="color:red;">${data.message || 'Could not load news details.'}</p>`;
                    }
                })
                .catch(error => {
                    console.error('[loadNewsDetail] Fetch error:', error);
                    newsDetailTitleElem.textContent = 'Error';
                    newsDetailTextElem.innerHTML = `<p style="color:red;">Failed to load content: ${error.message}</p>`;
                });
        }

        function nl2br_js(str) {
            if (typeof str === 'undefined' || str === null) { return ''; }
            return (str + '').replace(/(\r\n|\n\r|\r|\n)/g, '<br>' + '$1');
        }

        const mapPickerContainer = document.getElementById('mapPickerContainer');
        const latInput = document.getElementById('prop_latitude_hidden');
        const lngInput = document.getElementById('prop_longitude_hidden');
        let map = null; // Головний екземпляр мапи для створення/редагування властивостей
        let marker = null; // Маркер для головної мапи
        let createPropertyModalMapInitialized = false;


        function initializeOrUpdateMap(targetLat, targetLng, targetZoom = 13) {
            if (!mapPickerContainer) {
                console.error("Map container not found!");
                return;
            }

            if (!map) {
                console.log("[Map] Initializing map for the first time.");
                map = L.map(mapPickerContainer, {
                });

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);

                map.on('click', function(e) {
                    const clickedLat = e.latlng.lat;
                    const clickedLng = e.latlng.lng;
                    if (marker) {
                        marker.setLatLng(e.latlng);
                    } else {
                        marker = L.marker(e.latlng, { draggable: true }).addTo(map);
                        addMarkerDragListener();
                    }
                    updateCoordinateInputs(clickedLat, clickedLng);
                });
                createPropertyModalMapInitialized = true;
            }

            map.setView([targetLat, targetLng], targetZoom);
            if (marker) {
                marker.setLatLng([targetLat, targetLng]);
            } else {
                marker = L.marker([targetLat, targetLng], { draggable: true }).addTo(map);
                addMarkerDragListener();
            }

            if (latInput.value === '' && lngInput.value === '') {
                updateCoordinateInputs(targetLat, targetLng);
            }
        }

        function addMarkerDragListener() {
            if(marker) {
                marker.on('dragend', function(event) {
                    const position = marker.getLatLng();
                    updateCoordinateInputs(position.lat, position.lng);
                });
            }
        }

        function updateCoordinateInputs(lat, lng) { // Оновлює приховані вхідні дані lat/lng
            if (latInput && lngInput) {
                latInput.value = lat.toFixed(8);
                lngInput.value = lng.toFixed(8);
            }
        }
        const openCreatePropertyBtns = document.querySelectorAll('.nav-sell-trigger');
        if (openCreatePropertyBtns.length > 0 && createPropertyModal && closeCreatePropertyModalBtn && createPropertyForm && createPropertyMessageDiv && deletePropertyBtn && latInput && lngInput) {
            openCreatePropertyBtns.forEach(btn => {
                btn.addEventListener('click', function(event) {

                    event.preventDefault();

                    if (!isUserLoggedInForProperty) {
                        alert('Please log in to list a property.');
                        return;
                    }

                    createPropertyForm.reset();
                    createPropertyMessageDiv.innerHTML = '';
                    createPropertyForm.querySelector('input[name="editing_property_id"]').value = '';
                    createPropertyModal.querySelector('h2').textContent = 'List a New Property';
                    createPropertyForm.querySelector('button[type="submit"]').textContent = 'List Property';
                    const statusFieldWrapperCreate = createPropertyForm.querySelector('.editing-only-field');
                    if (statusFieldWrapperCreate) statusFieldWrapperCreate.style.display = 'none';
                    deletePropertyBtn.style.display = 'none';

                    // Встановити дату за замовчуванням у списку (якщо застосовно і не встановлено іншою логікою)
                    const dateListedInput = createPropertyForm.querySelector('#prop_date_listed');
                    if (dateListedInput && !dateListedInput.value) {
                        const today = new Date();
                        const year = today.getFullYear();
                        const month = ('0' + (today.getMonth() + 1)).slice(-2);
                        const day = ('0' + today.getDate()).slice(-2);
                        dateListedInput.value = `${year}-${month}-${day}`;
                    }

                    const contactEmailInput = createPropertyForm.querySelector('#prop_contact_email');
                    if (contactEmailInput) {
                        if (isUserLoggedInForProperty && currentUserEmail) {
                            contactEmailInput.value = currentUserEmail;
                            contactEmailInput.placeholder = "Defaults to your account email";
                        } else {
                            contactEmailInput.value = '';
                            contactEmailInput.placeholder = 'Contact Email (optional)';
                        }
                    }

                    const contactNameInput = createPropertyForm.querySelector('#prop_contact_name');
                    if (contactNameInput) {
                        if (isUserLoggedInForProperty && currentUserName) {
                            contactNameInput.placeholder = `Defaults to: ${currentUserName}`;
                            contactNameInput.value = '';
                        } else {
                            contactNameInput.value = '';
                            contactNameInput.placeholder = 'Your Contact Name';
                        }
                    }

                    createPropertyModal.style.display = 'block';

                    let initialLat = (latInput.value && parseFloat(latInput.value)) ? parseFloat(latInput.value) : 50.4501;
                    let initialLng = (lngInput.value && parseFloat(lngInput.value)) ? parseFloat(lngInput.value) : 30.5234;
                    let initialZoom = (latInput.value && lngInput.value) ? 15 : 7;

                    if (typeof initializeOrUpdateMap === 'function') {
                        initializeOrUpdateMap(initialLat, initialLng, initialZoom);
                    } else {
                        console.error('initializeOrUpdateMap function is not defined.');
                    }

                    setTimeout(() => {if (map && typeof map.invalidateSize === 'function') {map.invalidateSize();}}, 50);
                });
            });

            closeCreatePropertyModalBtn.onclick = function() {
                createPropertyModal.style.display = 'none';
            }

            createPropertyForm.addEventListener('submit', function(event) {
                event.preventDefault();
                createPropertyMessageDiv.innerHTML = '<p>Submitting property listing...</p>';
                const formData = new FormData(this);

                formData.append('create_property_submit', 'true');

                fetch('create_property_process.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => {
                        if (!response.ok) {
                            return response.text().then(text => { throw new Error(`Server error: ${response.status} ${text || ''}`); });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            createPropertyMessageDiv.innerHTML = `<p style="color: green;">${data.message || 'Property listed successfully!'}</p>`;
                            this.reset();
                            setTimeout(() => {
                                if (createPropertyModal) createPropertyModal.style.display = 'none';
                                window.location.reload();
                            }, 2000);
                        } else {
                            createPropertyMessageDiv.innerHTML = `<p style="color: red;">Error: ${data.message || 'Failed to list property.'}</p>`;
                        }
                    })
                    .catch(error => {
                        console.error('Error listing property:', error);
                        createPropertyMessageDiv.innerHTML = `<p style="color: red;">An error occurred: ${error.message}</p>`;
                    });
            });
        }

        if (deletePropertyBtn) {
            deletePropertyBtn.addEventListener('click', function() {
                const propertyId = this.dataset.propertyIdToDelete;
                const propertyTitle = createPropertyForm.querySelector('#prop_title').value || `Property ID: ${propertyId}`;

                if (!propertyId) {
                    alert('Error: Property ID for deletion not found.');
                    return;
                }

                if (confirm(`Are you sure you want to delete the property: "${propertyTitle}"? This action cannot be undone.`)) {
                    if(createPropertyMessageDiv) createPropertyMessageDiv.innerHTML = '<p>Deleting property...</p>';

                    const formData = new FormData();
                    formData.append('property_id', propertyId);
                    formData.append('delete_property_submit', 'true');

                    fetch('delete_property_process.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => {
                            if (!response.ok) {
                                return response.text().then(text => { throw new Error(`Server error: ${response.status} ${text || ''}`); });
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                if(createPropertyMessageDiv) createPropertyMessageDiv.innerHTML = `<p style="color: green;">${data.message || 'Property deleted successfully!'}</p>`;
                                setTimeout(() => {
                                    if (createPropertyModal) createPropertyModal.style.display = 'none';
                                    window.location.reload();
                                }, 1500);
                            } else {
                                if(createPropertyMessageDiv) createPropertyMessageDiv.innerHTML = `<p style="color: red;">Error: ${data.message || 'Failed to delete property.'}</p>`;
                            }
                        })
                        .catch(error => {
                            console.error('Error deleting property:', error);
                            if(createPropertyMessageDiv) createPropertyMessageDiv.innerHTML = `<p style="color: red;">An error occurred: ${error.message}</p>`;
                        });
                }
            });
        }

        const myListingsModal = document.getElementById('myListingsModal');
        const openMyListingsModalBtn = document.getElementById('openMyListingsModalBtn');
        const closeMyListingsModalBtn = document.querySelector('#myListingsModal .close-modal-btn');
        const myListingsContainer = document.getElementById('myListingsContainer');
        const isUserLoggedInForMyListings = <?php echo json_encode($is_logged_in); ?>;

        if (isUserLoggedInForMyListings && myListingsModal && openMyListingsModalBtn && closeMyListingsModalBtn && myListingsContainer) {
            openMyListingsModalBtn.addEventListener('click', function() {
                myListingsContainer.innerHTML = '<p>Loading your listings...</p>';
                myListingsModal.style.display = 'block';
                loadUserListings();
            });

            closeMyListingsModalBtn.onclick = function() {
                myListingsModal.style.display = 'none';
            }


        }

        function loadUserListings() {
            if (!myListingsContainer) return;

            fetch('get_user_listings.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok while fetching user listings.');
                    }
                    return response.json();
                })
                .then(data => {
                    myListingsContainer.innerHTML = '';
                    if (data.success && data.listings && data.listings.length > 0) {
                        const ul = document.createElement('ul');
                        ul.classList.add('user-listings-list');
                        data.listings.forEach(listing => {
                            const li = document.createElement('li');
                            li.classList.add('user-listing-item');

                            let statusText = listing.status.charAt(0).toUpperCase() + listing.status.slice(1);
                            if (listing.status === 'purchased') {
                                statusText = (listing.listing_type === 'sale') ? 'Sold' : 'Rented';
                            }


                            let imageHTML = '';
                            if (listing.first_image) {
                                imageHTML = `<img src="${escapeHtml(listing.first_image)}" alt="${escapeHtml(listing.title)}" class="listing-item-thumbnail"> `;
                            }


                            li.innerHTML = `
                            <div class="listing-item-info">
                                ${imageHTML}
                                <div>
                                    <strong>${escapeHtml(listing.title)}</strong> (ID: ${listing.id})<br>
                                    <small>Type: ${escapeHtml(listing.listing_type)} | Price: $${parseFloat(listing.price || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})} | Status: <span class="listing-status status-${listing.status.toLowerCase()}">${escapeHtml(statusText)}</span></small>
                                </div>
                            </div>
                            <div class="listing-item-actions">

                                <button type="button" class="action-btn view-btn" data-property-id="${listing.id}">View Details</button>
                                <button type="button" class="action-btn edit-btn" data-property-id="${listing.id}">Edit</button>
                            </div>
                        `;
                            ul.appendChild(li);
                            const viewBtnInList = li.querySelector('.view-btn');
                            if (viewBtnInList) {
                                viewBtnInList.addEventListener('click', function() {
                                    const propId = this.dataset.propertyId;
                                    if (typeof loadPropertyDetail === 'function') {
                                        loadPropertyDetail(propId);
                                        if(myListingsModal) myListingsModal.style.display = 'none';
                                    } else {
                                        console.error('Function loadPropertyDetail is not defined.');
                                    }
                                });
                            }
                            const editBtnInList = li.querySelector('.edit-btn');
                            if (editBtnInList) {
                                editBtnInList.addEventListener('click', function() {
                                    const propId = this.dataset.propertyId;
                                    if (typeof openEditPropertyModalWithData === 'function') {
                                        openEditPropertyModalWithData(propId);
                                        if(myListingsModal) myListingsModal.style.display = 'none';
                                    } else {
                                        console.error('Function openEditPropertyModalWithData is not defined.');
                                    }
                                });
                            }

                        });
                        myListingsContainer.appendChild(ul);
                    } else if (data.success && data.listings && data.listings.length === 0) {
                        myListingsContainer.innerHTML = '<p>You have not listed any properties yet.</p>';
                    } else {
                        myListingsContainer.innerHTML = `<p style="color:red;">${data.message || 'Could not load your listings.'}</p>`;
                    }
                })
                .catch(error => {
                    console.error('Error loading user listings:', error);
                    myListingsContainer.innerHTML = `<p style="color:red;">An error occurred: ${error.message}</p>`;
                });
        }

        function openEditPropertyModalWithData(propertyId) {
            fetch(`get_property_detail.php?id=${propertyId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.property) {
                        currentEditingPropertyData = data.property;

                        if (!currentEditingPropertyData) {
                            alert('Error: Could not fetch property data for editing.'); return;
                        }
                        if (createPropertyModal && createPropertyForm && deletePropertyBtn) {
                            if (propertyDetailModal) propertyDetailModal.style.display = 'none';

                            createPropertyForm.reset();
                            createPropertyModal.querySelector('h2').textContent = 'Edit Property Listing';
                            createPropertyForm.querySelector('button[type="submit"]').textContent = 'Save Changes';
                            createPropertyForm.querySelector('.editing-only-field').style.display = 'block';

                            let editingIdField = createPropertyForm.querySelector('input[name="editing_property_id"]');
                            if (!editingIdField) {
                                editingIdField = document.createElement('input');
                                editingIdField.type = 'hidden';
                                editingIdField.name = 'editing_property_id';
                                createPropertyForm.appendChild(editingIdField);
                            }
                            editingIdField.value = currentEditingPropertyData.id;

                            const propData = currentEditingPropertyData;
                            createPropertyForm.querySelector('#prop_title').value = propData.title || '';
                            createPropertyForm.querySelector('#prop_mls_number').value = propData.mls_number || '';
                            createPropertyForm.querySelector('#prop_listing_type').value = propData.listing_type || 'sale';
                            createPropertyForm.querySelector('#prop_price').value = propData.price || '';
                            createPropertyForm.querySelector('#prop_description').value = propData.description || '';
                            createPropertyForm.querySelector('#prop_address1').value = propData.address_line1 || '';
                            createPropertyForm.querySelector('#prop_address2').value = propData.address_line2 || '';
                            createPropertyForm.querySelector('#prop_city').value = propData.city || '';
                            createPropertyForm.querySelector('#prop_state').value = propData.state || '';
                            createPropertyForm.querySelector('#prop_zip').value = propData.zip_code || '';
                            const latHiddenInput = createPropertyForm.querySelector('#prop_latitude_hidden');
                            const lngHiddenInput = createPropertyForm.querySelector('#prop_longitude_hidden');
                            if(latHiddenInput) latHiddenInput.value = propData.latitude || '';
                            if(lngHiddenInput) lngHiddenInput.value = propData.longitude || '';
                            createPropertyForm.querySelector('#prop_type').value = propData.property_type || '';
                            createPropertyForm.querySelector('#prop_bedrooms').value = propData.bedrooms || '';
                            createPropertyForm.querySelector('#prop_bathrooms').value = propData.bathrooms || '';
                            createPropertyForm.querySelector('#prop_area_sqm').value = propData.area_sqm || '';
                            createPropertyForm.querySelector('#prop_lot_size_sqm').value = propData.lot_size_sqm || '';
                            createPropertyForm.querySelector('#prop_year_built').value = propData.year_built || '';
                            createPropertyForm.querySelector('#prop_date_available').value = propData.date_available ? propData.date_available.split(' ')[0] : '';
                            createPropertyForm.querySelector('#prop_contact_name').value = propData.contact_name || '';
                            createPropertyForm.querySelector('#prop_contact_email').value = propData.contact_email || '';
                            createPropertyForm.querySelector('#prop_contact_phone').value = propData.contact_phone || '';
                            createPropertyForm.querySelector('#prop_status').value = propData.status || 'active';

                            const photoInput = createPropertyForm.querySelector('#prop_photos');
                            if (photoInput) photoInput.value = '';

                            deletePropertyBtn.style.display = 'inline-block';
                            deletePropertyBtn.dataset.propertyIdToDelete = currentEditingPropertyData.id;

                            createPropertyModal.style.display = 'block';
                            if (typeof initializeOrUpdateMap === 'function' && propData.latitude && propData.longitude) {
                                initializeOrUpdateMap(parseFloat(propData.latitude), parseFloat(propData.longitude), 15);
                                setTimeout(() => { if (map) map.invalidateSize(); }, 50);
                            } else if (typeof initializeOrUpdateMap === 'function') {
                                initializeOrUpdateMap(50.4501, 30.5234, 7);
                                setTimeout(() => { if (map) map.invalidateSize(); }, 50);
                            }
                        } else {
                            console.error("Create property modal components not found when trying to open for edit from My Listings.");
                        }
                    } else {
                        alert('Could not load property details for editing.');
                    }
                })
                .catch(error => {
                    console.error('Error fetching property details for edit:', error);
                    alert('An error occurred while loading property data for editing.');
                });
        }
        function escapeHtml(unsafe) {
            if (typeof unsafe !== 'string') return '';
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        const siteMapModal = document.getElementById('siteMapModal');
        const openSiteMapModalBtn = document.getElementById('openSiteMapModalBtn');
        const closeSiteMapModalBtn = document.querySelector('#siteMapModal .close-modal-btn');
        const siteMapDisplayDiv = document.getElementById('siteMapDisplay');
        let siteMapInstance = null;
        let searchAreaMapInstance = null;
        let searchAreaMarker = null;
        const selectedLngInput = document.getElementById('selected_lng');
        const searchRadiusInput = document.getElementById('search_radius_input');
        const searchAreaDisplayButton = document.getElementById('search_area_display');
        const selectedLatInput = document.getElementById('selected_lat');

        const blueIcon = L.icon({
            iconUrl: 'images/markers/marker-icon-blue.png',
            iconRetinaUrl: 'images/markers/marker-icon-2x-blue.png',
            shadowUrl: 'images/markers/marker-shadow.png',
            iconSize:    [25, 41], iconAnchor:  [12, 41], popupAnchor: [1, -34], shadowSize:  [41, 41]
        });
        const redIcon = L.icon({
            iconUrl: 'images/markers/marker-icon-red.png',
            iconRetinaUrl: 'images/markers/marker-icon-2x-red.png',
            shadowUrl: 'images/markers/marker-shadow.png',
            iconSize:    [25, 41], iconAnchor:  [12, 41], popupAnchor: [1, -34], shadowSize:  [41, 41]
        });
        const yellowIcon = L.icon({
            iconUrl: 'images/markers/marker-icon-yellow.png',
            iconRetinaUrl: 'images/markers/marker-icon-2x-yellow.png',
            shadowUrl: 'images/markers/marker-shadow.png',
            iconSize:    [25, 41], iconAnchor:  [12, 41], popupAnchor: [1, -34], shadowSize:  [41, 41]
        });

        if (siteMapModal && openSiteMapModalBtn && closeSiteMapModalBtn && siteMapDisplayDiv) {
            openSiteMapModalBtn.addEventListener('click', function(event) {
                event.preventDefault();
                siteMapDisplayDiv.innerHTML = '<p style="text-align:center; padding-top:50px;">Loading map data...</p>';
                siteMapModal.style.display = 'block';
                loadAllPropertiesForMap();
            });

            closeSiteMapModalBtn.onclick = function() {
                siteMapModal.style.display = 'none';
                if (siteMapInstance) {
                    siteMapInstance.remove();
                    siteMapInstance = null;
                }
            };
        }

        function loadAllPropertiesForMap() {
            if (!siteMapDisplayDiv) return;

            fetch('get_all_properties_for_map.php')
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok for map data.');
                    return response.json();
                })
                .then(data => {
                    siteMapDisplayDiv.innerHTML = '';
                    if (data.success && data.properties && data.properties.length > 0) {
                        if (!siteMapInstance) { // Ініціалізувати мапу тільки якщо це ще не зроблено
                            siteMapInstance = L.map(siteMapDisplayDiv);
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                maxZoom: 18,
                                attribution: '© <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                            }).addTo(siteMapInstance);
                        } else {
                            siteMapInstance.eachLayer(function (layer) {
                                if (layer instanceof L.Marker || layer instanceof L.LayerGroup) {
                                    siteMapInstance.removeLayer(layer);
                                }
                            });
                        }

                        const markers = [];
                        data.properties.forEach(prop => {
                            if (prop.latitude && prop.longitude && prop.status !== 'inactive') {
                                const lat = parseFloat(prop.latitude);
                                const lon = parseFloat(prop.longitude);

                                let iconToUse = blueIcon;
                                let statusLabelForPopup = 'Active';

                                if (prop.status === 'purchased') {
                                    iconToUse = redIcon;
                                    statusLabelForPopup = (prop.listing_type === 'sale') ? 'Sold' : 'Rented';
                                } else if (prop.status === 'pending') {
                                    iconToUse = yellowIcon;
                                    statusLabelForPopup = 'Pending';
                                }

                                let popupContentHTML = `
                                    <div style="min-width: 150px;">
                                        <div style="font-weight: bold; margin-bottom: 3px; font-size: 1.1em;">
                                            <a href="property_detail.php?id=${prop.id}"
                                               onclick="openPropertyDetailFromMap(${prop.id}); return false;"
                                               style="color: #0056b3; text-decoration: none;">
                                                ${escapeHtml(prop.title)}
                                            </a>
                                        </div>
                                        <div style="font-size: 0.9em; color: #555;">
                                            Status: ${escapeHtml(statusLabelForPopup)}<br>
                                            Type: ${escapeHtml(prop.listing_type)}
                                        </div>
                                    </div>`;

                                const marker = L.marker([lat, lon], {icon: iconToUse});
                                marker.bindPopup(popupContentHTML);
                                marker.on('mouseover', function (e) { this.openPopup(); });
                                markers.push(marker);
                            }
                        });

                        if (markers.length > 0) {
                            const featureGroup = L.featureGroup(markers).addTo(siteMapInstance);
                            siteMapInstance.fitBounds(featureGroup.getBounds().pad(0.1));
                        } else {
                            siteMapInstance.setView([50.45, 30.52], 6);
                            siteMapDisplayDiv.innerHTML = '<p style="text-align:center; padding:20px;">No properties with coordinates found to display on the map.</p>';
                        }


                        setTimeout(function() {if (siteMapInstance) {siteMapInstance.invalidateSize();}}, 100);

                    } else if (data.success && data.properties && data.properties.length === 0) {
                        siteMapDisplayDiv.innerHTML = '<p style="text-align:center; padding:20px;">No properties available to display on the map.</p>';
                        if (siteMapInstance) siteMapInstance.setView([50.45, 30.52], 6); // Example: Kyiv
                    } else {
                        siteMapDisplayDiv.innerHTML = `<p style="color:red; text-align:center; padding:20px;">${data.message || 'Could not load property data for the map.'}</p>`;
                    }
                })
                .catch(error => {
                    console.error('Error loading properties for map:', error);
                    siteMapDisplayDiv.innerHTML = `<p style="color:red; text-align:center; padding:20px;">Failed to load map data: ${error.message}</p>`;
                });
        }

        const allNewsModal = document.getElementById('allNewsModal');
        const openAllNewsModalBtn = document.getElementById('openAllNewsModalBtn');
        const closeAllNewsModalBtn = document.querySelector('#allNewsModal .close-modal-btn');
        const allNewsContainer = document.getElementById('allNewsContainer');

        if (allNewsModal && openAllNewsModalBtn && closeAllNewsModalBtn && allNewsContainer) {
            openAllNewsModalBtn.addEventListener('click', function(event) {
                event.preventDefault();
                allNewsContainer.innerHTML = '<p>Loading news...</p>';
                allNewsModal.style.display = 'block';
                loadAllNews();
            });

            closeAllNewsModalBtn.onclick = function() {
                allNewsModal.style.display = 'none';
            }
        }
        function loadAllNews() {
            if (!allNewsContainer) return;

            fetch('get_all_news.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok while fetching all news.');
                    }
                    return response.json();
                })
                .then(data => {
                    allNewsContainer.innerHTML = '';
                    if (data.success && data.news && data.news.length > 0) {
                        const newsListDiv = document.createElement('div');
                        data.news.forEach(news_entry => {
                            const newsEntryDiv = document.createElement('div');
                            newsEntryDiv.classList.add('news-entry-item');

                            let imageHTML = '';
                            if (news_entry.photo) {
                                imageHTML = `<img src="${escapeHtml(news_entry.photo)}" alt="${escapeHtml(news_entry.title)}" class="news-entry-thumbnail">`;
                            }

                            let dateFormatted = '';
                            if (news_entry.date) {
                                try {
                                    const dateObj = new Date(news_entry.date.replace(/-/g, '/') + 'T00:00:00');
                                    dateFormatted = dateObj.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
                                } catch (e) { dateFormatted = news_entry.date;}
                            }

                            newsEntryDiv.innerHTML = `
                            ${imageHTML}
                            <div class="news-entry-content">
                                <h4 class="news-entry-title">${escapeHtml(news_entry.title)}</h4>
                                <p class="news-entry-meta"><small>Published: ${escapeHtml(dateFormatted)} ${news_entry.source ? '| Source: ' + escapeHtml(news_entry.source) : ''}</small></p>
                                <p class="news-entry-summary">${escapeHtml(mb_substr_js(strip_tags_js(news_entry.text), 0, 150))}...</p>
                                <button type="button" class="action-btn view-news-detail-btn" data-news-id="${news_entry.id}">Read More</button>
                            </div>
                        `;

                            const readMoreBtn = newsEntryDiv.querySelector('.view-news-detail-btn');
                            if (readMoreBtn) {
                                readMoreBtn.addEventListener('click', function() {
                                    const newsId = this.dataset.newsId;
                                    if (typeof loadNewsDetail === 'function') {
                                        loadNewsDetail(newsId);
                                        if(allNewsModal) allNewsModal.style.display = 'none';
                                    } else {
                                        console.error('Function loadNewsDetail is not defined.');
                                    }
                                });
                            }
                            newsListDiv.appendChild(newsEntryDiv);
                        });
                        allNewsContainer.appendChild(newsListDiv);
                    } else if (data.success && data.news && data.news.length === 0) {
                        allNewsContainer.innerHTML = '<p>No news articles found.</p>';
                    } else {
                        allNewsContainer.innerHTML = `<p style="color:red;">${data.message || 'Could not load news.'}</p>`;
                    }
                })
                .catch(error => {
                    console.error('Error loading all news:', error);
                    allNewsContainer.innerHTML = `<p style="color:red;">An error occurred: ${error.message}</p>`;
                });
        }

        function strip_tags_js(input, allowed) {
            allowed = (((allowed || '') + '').toLowerCase().match(/<[a-z][a-z0-9]*>/g) || []).join('');
            var tags = /<\/?([a-z][a-z0-9]*)\b[^>]*>/gi,
                commentsAndPhpTags = /<!--[\s\S]*?-->|<\?(?:php)?[\s\S]*?\?>/gi;
            return input.replace(commentsAndPhpTags, '').replace(tags, function($0, $1) {
                return allowed.indexOf('<' + $1.toLowerCase() + '>') > -1 ? $0 : '';
            });
        }
        function mb_substr_js(str, start, length) {
            return String(str).substring(start, start + length);
        }

        const popoverItems = document.querySelectorAll('.nav-item-with-popover');
        console.log('Found popoverItems:', popoverItems.length);

        popoverItems.forEach(item => {
            const link = item.querySelector('a');
            const content = item.querySelector('.popover-content');

            if (link && content) {
                link.addEventListener('click', function(event) {
                    event.preventDefault();
                    console.log('Link clicked:', this.textContent, 'Associated popover:', content.id);

                    const isActive = content.style.display === 'block';

                    // Закрийте всі інші попвери
                    document.querySelectorAll('.popover-content').forEach(pc => {
                        if (pc !== content) {
                            pc.style.display = 'none';
                        }
                    });

                    // Перемикання поточного спливаючого вікна
                    if (isActive) {
                        content.style.display = 'none';
                    } else {
                        content.style.display = 'block';
                    }

                    event.stopPropagation();
                });
            }
        });

        document.addEventListener('click', function(event) {
            popoverItems.forEach(item => {
                const content = item.querySelector('.popover-content');
                if (content && content.style.display === 'block' && !item.contains(event.target)) {
                    content.style.display = 'none';
                    item.classList.remove('popover-active');
                }
            });
        });

        const rightColumn = document.querySelector('.right-column');
        const searchFormElement = document.querySelector('.search-form');
        const clearAllSearchFiltersBtn = document.getElementById('clearAllSearchFiltersBtn');
        const searchNowButtonCTA = document.querySelector('.search-cta .search-now-button-cta');
        const featuredPropertiesSection = document.querySelector('.featured-properties-section');

        const navFilterTriggerButtons = document.querySelectorAll('.nav-filter-trigger');

        if (searchNowButtonCTA && rightColumn && featuredPropertiesSection) {
            searchNowButtonCTA.addEventListener('click', function() {

                if (!rightColumn.classList.contains('search-results-active')) {
                    rightColumn.classList.add('search-results-active');
                }
                performSearch();
            });
        }

        if (searchFormElement && rightColumn && featuredPropertiesSection) {
            searchFormElement.addEventListener('submit', function(event) {
                event.preventDefault();

                if (!rightColumn.classList.contains('search-results-active')) {
                    rightColumn.classList.add('search-results-active');
                }
                performSearch();
            });
        }

        function performSearch() {
            const formData = new FormData(searchFormElement);
            const params = new URLSearchParams();
            for (const pair of formData) {
                if (pair[1]) {
                    params.append(pair[0], pair[1]);
                }
            }
            const queryString = params.toString();
            console.log('Search Query:', queryString);

            // Show loading state
            featuredPropertiesSection.innerHTML = '<p style="text-align:center; width:100%;">Searching properties...</p>';
            fetch(`filter_properties.php?${queryString}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok for filtered properties.');
                    return response.json();
                })
                .then(data => {
                    featuredPropertiesSection.innerHTML = '';
                    if (data.success && data.properties && data.properties.length > 0) {
                        data.properties.forEach(prop => {
                            const image_to_display = prop.primary_image || prop.first_image || 'images/ico/nophoto.png';

                            let price_strikethrough_class = '';
                            let status_text_overlay = '';
                            if (prop.status === 'purchased') {
                                price_strikethrough_class = 'price-strikethrough';
                                status_text_overlay = (prop.listing_type === 'sale') ? 'SOLD' : 'RENTED';
                            } else if (prop.status === 'pending') {
                                status_text_overlay = 'PENDING';
                            } else if (prop.status === 'inactive') {
                                status_text_overlay = 'OFF MARKET';
                            }

                            const cardHTML = `
                                <div class="property-card ${prop.status !== 'active' ? 'property-not-available status-' + escapeHtml(prop.status) : ''}">
                                    <a href="property_detail.php?id=${prop.id}"
                                       class="property-card-link"
                                       data-property-id="${prop.id}"
                                       data-listing-type="${escapeHtml(prop.listing_type)}"
                                       data-property-status="${escapeHtml(prop.status)}"
                                       data-agent-id="${escapeHtml(prop.agent_id || '')}">
                                        <div class="property-image-container">
                                            <img src="${escapeHtml(image_to_display)}" alt="${escapeHtml(prop.title)}">
                                            ${status_text_overlay ? `<div class="property-status-overlay status-${escapeHtml(prop.status)}">${status_text_overlay}</div>` : ''}
                                        </div>
                                        <div class="property-card-content">
                                            <h3>${escapeHtml(prop.title)}</h3>
                                            <p class="property-price ${price_strikethrough_class}">
                                                ${prop.listing_type == 'rent' ? 'Rent: ' : ''}
                                                $${parseFloat(prop.price).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                                                ${prop.listing_type == 'rent' ? ' / month' : ''}
                                            </p>
                                            <p class="property-summary">${escapeHtml(mb_substr_js(strip_tags_js(prop.description || ''), 0, 80))}...</p>
                                        </div>
                                    </a>
                                    <div class="property-card-actions">
                                        <a href="property_detail.php?id=${prop.id}#gallery" class="view-gallery-btn">• view gallery / details</a>
                                    </div>
                                </div>`;
                            featuredPropertiesSection.insertAdjacentHTML('beforeend', cardHTML);
                        });
                    } else if (data.success && data.properties && data.properties.length === 0) {
                        featuredPropertiesSection.innerHTML = '<p style="text-align:center; width:100%;">No properties found matching your criteria.</p>';
                    } else {
                        featuredPropertiesSection.innerHTML = `<p style="color:red; text-align:center; width:100%;">${data.message || 'Could not load properties.'}</p>`;
                    }
                })
                .catch(error => {
                    console.error('Error fetching filtered properties:', error);
                    featuredPropertiesSection.innerHTML = `<p style="color:red; text-align:center; width:100%;">Error: ${error.message}</p>`;
                });
        }

        const mainNavHomeButtons = document.querySelectorAll('.main-nav-home-link');
        if (mainNavHomeButtons.length > 0 && rightColumn) {
            mainNavHomeButtons.forEach(homeBtn => {
                homeBtn.addEventListener('click', function(event) {
                    if (!rightColumn.classList.contains('search-results-active')) { return; }
                    event.preventDefault();
                    console.log('HOME button clicked, resetting to initial state');
                    rightColumn.classList.remove('search-results-active');
                    if(searchFormElement) searchFormElement.reset();
                    window.location.href = 'index.php';
                });
            });
        }

        const filterDisplayButtons = document.querySelectorAll('.filter-display-button');

        filterDisplayButtons.forEach(button => {
            const filterType = button.dataset.filterType;
            const popover = document.getElementById(`${filterType}_popover`);

            if (!popover) {
                console.warn(`Popover for filter type "${filterType}" (ID: ${filterType}_popover) NOT FOUND.`);
                return;
            } else {
                console.log(`Popover for filter type "${filterType}" FOUND.`);
            }

            button.addEventListener('click', function(event) {
                event.stopPropagation();
                const isActive = popover.classList.contains('active');


                document.querySelectorAll('.filter-popover.active').forEach(openPopover => {
                    if (openPopover !== popover) {
                        openPopover.classList.remove('active');
                        const otherButton = document.querySelector(`.filter-display-button[data-filter-type="${openPopover.id.replace('_popover','')}"]`);
                        if(otherButton) {
                            otherButton.classList.remove('popover-open');
                            otherButton.setAttribute('aria-expanded', 'false');
                        }
                    }
                });

                if (!isActive) {
                    popover.classList.add('active');
                    this.classList.add('popover-open');
                    this.setAttribute('aria-expanded', 'true');

                    if (filterType === 'search_area') {
                        initializeSearchAreaMap();
                    } else {
                        const searchInput = popover.querySelector('.filter-popover-search');
                        if (searchInput) searchInput.focus();
                    }
                } else {
                    popover.classList.remove('active');
                    this.classList.remove('popover-open');
                    this.setAttribute('aria-expanded', 'false');
                }
            });

            const applyBtn = popover.querySelector('.filter-popover-apply-btn');
            const checkboxes = popover.querySelectorAll(`input[type="checkbox"][name="${filterType}[]"]`);
            const searchInput = popover.querySelector('.filter-popover-search');

            if (applyBtn) {
                applyBtn.addEventListener('click', function() {
                    if (filterType !== 'search_area') {
                        const checkboxes = popover.querySelectorAll(`input[type="checkbox"][name="${filterType}[]"]`);
                        updateFilterDisplay(button, checkboxes, filterType);
                    } else {
                        const displayButtonForMap = document.getElementById('search_area_display');
                    }
                    popover.classList.remove('active');
                    button.classList.remove('popover-open');
                    button.setAttribute('aria-expanded', 'false');
                });
            }

            function initializeSearchAreaMap() {
                const mapDiv = document.getElementById('searchAreaMap');
                if (!mapDiv) {
                    console.error("Map div 'searchAreaMap' not found for filter!");
                    return;
                }


                let initialLat = parseFloat(selectedLatInput.value) || 50.4501;
                let initialLng = parseFloat(selectedLngInput.value) || 30.5234;
                let initialZoom = (selectedLatInput.value && selectedLngInput.value) ? 13 : 6;

                if (searchAreaMapInstance) {
                    searchAreaMapInstance.setView([initialLat, initialLng], initialZoom);
                    if (selectedLatInput.value && selectedLngInput.value) {
                        if (searchAreaMarker) {
                            searchAreaMarker.setLatLng([initialLat, initialLng]);
                        } else {
                            searchAreaMarker = L.marker([initialLat, initialLng], { draggable: true }).addTo(searchAreaMapInstance);
                            addSearchAreaMarkerDragListener();
                        }
                    } else if (searchAreaMarker) {
                        searchAreaMapInstance.removeLayer(searchAreaMarker);
                        searchAreaMarker = null;
                    }
                } else {
                    searchAreaMapInstance = L.map(mapDiv).setView([initialLat, initialLng], initialZoom);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 18,
                        attribution: '© <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                    }).addTo(searchAreaMapInstance);

                    if (selectedLatInput.value && selectedLngInput.value) {
                        searchAreaMarker = L.marker([initialLat, initialLng], { draggable: true }).addTo(searchAreaMapInstance);
                        addSearchAreaMarkerDragListener();
                    }

                    searchAreaMapInstance.on('click', function(e) {
                        const clickedLat = e.latlng.lat;
                        const clickedLng = e.latlng.lng;
                        if (searchAreaMarker) {
                            searchAreaMarker.setLatLng(e.latlng);
                        } else {
                            searchAreaMarker = L.marker(e.latlng, { draggable: true }).addTo(searchAreaMapInstance);
                            addSearchAreaMarkerDragListener();
                        }
                        updateSelectedCoordinates(clickedLat, clickedLng);
                        if(searchAreaDisplayButton) searchAreaDisplayButton.textContent = `Lat: ${clickedLat.toFixed(4)}, Lng: ${clickedLng.toFixed(4)}`;
                    });
                }
                setTimeout(() => { if (searchAreaMapInstance) searchAreaMapInstance.invalidateSize(); }, 150);
            }

            function addSearchAreaMarkerDragListener() {
                if (searchAreaMarker) {
                    searchAreaMarker.on('dragend', function(event) {
                        const position = searchAreaMarker.getLatLng();
                        updateSelectedCoordinates(position.lat, position.lng);
                        if(searchAreaDisplayButton) searchAreaDisplayButton.textContent = `Lat: ${position.lat.toFixed(4)}, Lng: ${position.lng.toFixed(4)}`;
                    });
                }
            }

            function updateSelectedCoordinates(lat, lng) {
                if (selectedLatInput && selectedLngInput) {
                    selectedLatInput.value = lat.toFixed(6);
                    selectedLngInput.value = lng.toFixed(6);

                }
            }

            // Кнопка "Застосувати" для спливаючого вікна пошуку
            const searchAreaPopover = document.getElementById('search_area_popover');
            if (searchAreaPopover) {
                const applyAreaBtn = searchAreaPopover.querySelector('.filter-popover-apply-btn');
                if (applyAreaBtn && searchAreaDisplayButton && selectedLatInput && selectedLngInput && searchRadiusInput) {
                    applyAreaBtn.addEventListener('click', function() {
                        const lat = selectedLatInput.value;
                        const lng = selectedLngInput.value;
                        const radius = searchRadiusInput.value;

                        if (lat && lng && radius) {
                            searchAreaDisplayButton.textContent = `Area: Lat ${parseFloat(lat).toFixed(3)}, Lng ${parseFloat(lng).toFixed(3)} ±${radius}km`;
                        } else if (lat && lng) {
                            searchAreaDisplayButton.textContent = `Point: Lat ${parseFloat(lat).toFixed(3)}, Lng ${parseFloat(lng).toFixed(3)}`;
                        } else {
                            searchAreaDisplayButton.textContent = "Select Area on Map";
                        }

                        popover.classList.remove('active');
                        const displayButton = document.getElementById('search_area_display');
                        if (displayButton) {
                            displayButton.classList.remove('popover-open');
                            displayButton.setAttribute('aria-expanded', 'false');
                        }
                        const saPopover = document.getElementById('search_area_popover');
                        if (saPopover) saPopover.classList.remove('active');

                    });
                }
            }

            // Пошук всередині popover
            if (searchInput && checkboxes.length > 0) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    checkboxes.forEach(cb => {
                        const label = popover.querySelector(`label[for="${cb.id}"]`);
                        const itemDiv = cb.closest('.checkbox-item');
                        if (label && itemDiv) {
                            if (label.textContent.toLowerCase().includes(searchTerm)) {
                                itemDiv.style.display = 'flex';
                            } else {
                                itemDiv.style.display = 'none';
                            }
                        }
                    });
                });
            }
            button.addEventListener('click', function() {
                if (popover.classList.contains('active') && searchInput) {
                    searchInput.value = '';
                    checkboxes.forEach(cb => {
                        const itemDiv = cb.closest('.checkbox-item');
                        if (itemDiv) itemDiv.style.display = 'flex';
                    });
                }
            });
        });

        function resetAllSearchFiltersUI() {
            if (searchFormElement) {
                searchFormElement.reset();
            }

            // Скинути візуальне відображення кнопок фільтрів та зняти прапорці у спливаючих вікнах
            document.querySelectorAll('.filter-display-button').forEach(button => {
                const filterType = button.dataset.filterType;
                const popover = document.getElementById(`${filterType}_popover`);
                if (popover) {
                    const checkboxes = popover.querySelectorAll(`input[type="checkbox"][name="${filterType}[]"]`);
                    checkboxes.forEach(cb => {
                        cb.checked = false;
                    });
                    const searchInputInPopover = popover.querySelector('.filter-popover-search');
                    if (searchInputInPopover) {
                        searchInputInPopover.value = '';
                        const itemsToReset = popover.querySelectorAll('.checkbox-item');
                        itemsToReset.forEach(item => item.style.display = 'flex');
                    }
                    if (typeof updateFilterDisplay === 'function') {
                        updateFilterDisplay(button, checkboxes, filterType);
                    } else if (filterType === 'search_area' && searchAreaDisplayButton) {
                        searchAreaDisplayButton.textContent = 'Select Area on Map';
                    }
                }
            });

            // Очистити прості поля введення, які не обробляються спливаючими вікнами
            const priceMinInput = document.getElementById('price_range_min');
            const priceMaxInput = document.getElementById('price_range_max');
            if (priceMinInput) priceMinInput.value = '';
            if (priceMaxInput) priceMaxInput.value = '';
            const mlsInput = document.getElementById('mls');
            if (mlsInput) mlsInput.value = '';
            if (searchAreaDisplayButton) {
                searchAreaDisplayButton.textContent = 'Select Area on Map';
            }
            if (selectedLatInput) selectedLatInput.value = '';
            if (selectedLngInput) selectedLngInput.value = '';
            if (searchRadiusInput) searchRadiusInput.value = '15';
            if (searchAreaMapInstance && searchAreaMarker) {
                searchAreaMapInstance.removeLayer(searchAreaMarker);
                searchAreaMarker = null;
                searchAreaMapInstance.setView([50.4501, 30.5234], 6);
            }
        }

        if (clearAllSearchFiltersBtn && searchFormElement) {
            clearAllSearchFiltersBtn.addEventListener('click', function() {
                resetAllSearchFiltersUI();
                searchFormElement.reset();

                filterDisplayButtons.forEach(button => {
                    const filterType = button.dataset.filterType;
                    const popover = document.getElementById(`${filterType}_popover`);
                    if(popover){
                        const checkboxes = popover.querySelectorAll(`input[type="checkbox"][name="${filterType}[]"]`);
                        checkboxes.forEach(cb => { cb.checked = false; });
                        updateFilterDisplay(button, checkboxes, filterType);
                    }
                });

                const statusCheckboxes = searchFormElement.querySelectorAll('input[type="checkbox"][name="status[]"]');
                statusCheckboxes.forEach(cb => { cb.checked = false; });

                const listingTypeCheckboxes = searchFormElement.querySelectorAll('input[type="checkbox"][name="listing_type[]"]');
                listingTypeCheckboxes.forEach(cb => { cb.checked = false; });

                const priceMinInput = document.getElementById('price_range_min');
                const priceMaxInput = document.getElementById('price_range_max');
                if (priceMinInput) priceMinInput.value = '';
                if (priceMaxInput) priceMaxInput.value = '';

                const searchAreaInput = document.getElementById('search_area_main');
                if (searchAreaInput) searchAreaInput.value = '';

                performSearch();
                console.log('All search filters cleared.');
            });
        }

        navFilterTriggerButtons.forEach(btn => {
            btn.addEventListener('click', function(event) {
                event.preventDefault();
                const listingTypeToFilter = this.dataset.listingType;

                console.log(`Nav filter trigger clicked: ${listingTypeToFilter}`);

                if (rightColumn) rightColumn.classList.add('search-results-active');

                resetAllSearchFiltersUI();

                const listingTypeCheckboxes = searchFormElement.querySelectorAll(`input[type="checkbox"][name="listing_type[]"]`);
                let listingTypeDisplayButton = null;

                document.querySelectorAll('.filter-display-button').forEach(dispBtn => {
                    if (dispBtn.dataset.filterType === 'listing_type') {
                        listingTypeDisplayButton = dispBtn;
                    }
                });

                listingTypeCheckboxes.forEach(cb => {
                    cb.checked = cb.value === listingTypeToFilter;
                });


                if (listingTypeDisplayButton && typeof updateFilterDisplay === 'function') {
                    updateFilterDisplay(listingTypeDisplayButton, listingTypeCheckboxes, 'listing_type');
                } else if (!listingTypeDisplayButton) {
                    console.warn("Display button for 'listing_type' filter not found.");
                }

                performSearch();
            });
        });

        function updateFilterDisplay(displayButton, checkboxes, filterType) {
            const selectedLabels = [];
            checkboxes.forEach(cb => {
                if (cb.checked) {
                    const label = document.querySelector(`label[for="${cb.id}"]`);
                    if (label) selectedLabels.push(label.textContent.trim());
                }
            });

            let defaultTextStart = "Any";
            if (filterType === 'bedrooms') defaultTextStart = 'Any Bedrooms';
            else if (filterType === 'bathrooms') defaultTextStart = 'Any Bathrooms';
            else if (filterType === 'city') defaultTextStart = 'Any City';
            else if (filterType === 'state') defaultTextStart = 'Any State/Region';
            else if (filterType === 'zip') defaultTextStart = 'Any ZIP Code';

            if (selectedLabels.length > 0) {
                const maxVisible = 2;
                if (selectedLabels.length > maxVisible) {
                    displayButton.textContent = selectedLabels.slice(0, maxVisible).join(', ') + `... (+${selectedLabels.length - maxVisible} more)`;
                } else {
                    displayButton.textContent = selectedLabels.join(', ');
                }
            } else {
                displayButton.textContent = defaultTextStart;
            }
        }

        document.addEventListener('click', function(event) {
            filterDisplayButtons.forEach(button => {
                const filterType = button.dataset.filterType;
                const popover = document.getElementById(`${filterType}_popover`);
                if (popover && popover.classList.contains('active') &&
                    !button.contains(event.target) && !popover.contains(event.target)) {
                    popover.classList.remove('active');
                    button.classList.remove('popover-open');
                    button.setAttribute('aria-expanded', 'false');
                }
            });
        });

        window.addEventListener('click', function(event) {
            if (propertyDetailModal && event.target == propertyDetailModal) propertyDetailModal.style.display = 'none';
            if (siteMapModal && event.target == siteMapModal) {
                siteMapModal.style.display = 'none';
                if (siteMapInstance) {
                    siteMapInstance.remove();
                    siteMapInstance = null;
                }
            }
            if (allNewsModal && event.target == allNewsModal) allNewsModal.style.display = 'none';
            //if (createPropertyModal && event.target == createPropertyModal) {createPropertyModal.style.display = 'none';} // Для більш комфортного користування
            if (myListingsModal && event.target == myListingsModal) {myListingsModal.style.display = 'none';}
            //if (event.target == editProfileModal) { editProfileModal.style.display = 'none'; }
            const newsDetailM = document.getElementById('newsDetailModal');
            if (newsDetailM && event.target == newsDetailM) newsDetailM.style.display = 'none';

            <?php if ($user_access_right == 10): ?>
            const modalSelectNewsJS = document.getElementById('selectNewsModal');
            if (modalSelectNewsJS && event.target == modalSelectNewsJS) { modalSelectNewsJS.style.display = 'none'; }
            const createNewsModalJS = document.getElementById('createNewsModal');
            //if (createNewsModalJS && event.target == createNewsModalJS) { createNewsModalJS.style.display = 'none'; }
            const previewNewsModalJS = document.getElementById('previewNewsModal');
            if (previewNewsModalJS && event.target == previewNewsModalJS) { previewNewsModalJS.style.display = 'none'; }
            <?php endif; ?>
        });

        <?php if ($user_access_right == 10): ?>

        const modalSelectNews = document.getElementById('selectNewsModal');
        const selectNewsBtns = document.querySelectorAll('.select-news-btn');
        const closeModalSelectBtn = document.querySelector('#selectNewsModal .close-modal-btn');
        const newsListContainer = document.getElementById('newsListContainer');
        const newsSearchInput = document.getElementById('newsSearchInput');
        const currentBoxKeyInput = document.getElementById('currentBoxKeyInput');

        if (modalSelectNews && selectNewsBtns.length > 0 && closeModalSelectBtn && newsListContainer && newsSearchInput && currentBoxKeyInput) {
            selectNewsBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const boxKeyFromButton = this.dataset.boxkey;
                    const currentNewsId = this.dataset.currentnewsid || '';
                    if (!boxKeyFromButton) { console.error('[Open Modal Select] Error: boxKeyFromButton is undefined.'); alert('Error: Banner to update not identified.'); return; }
                    currentBoxKeyInput.value = boxKeyFromButton;
                    newsSearchInput.value = '';
                    loadNewsForAdminSelection(currentNewsId, '');
                    modalSelectNews.style.display = "block";
                });
            });
            closeModalSelectBtn.onclick = function() { modalSelectNews.style.display = "none"; }

            let searchTimeoutSelect;
            newsSearchInput.addEventListener('keyup', function() {
                clearTimeout(searchTimeoutSelect);
                const searchTerm = this.value;
                const activeBoxKey = currentBoxKeyInput.value;
                let currentSelectedNewsId = '';
                if (activeBoxKey) {
                    const activeButton = document.querySelector(`.select-news-btn[data-boxkey="${activeBoxKey}"]`);
                    if (activeButton) { currentSelectedNewsId = activeButton.dataset.currentnewsid || ''; }
                }
                searchTimeoutSelect = setTimeout(() => { loadNewsForAdminSelection(currentSelectedNewsId, searchTerm); }, 300);
            });
        }

        function loadNewsForAdminSelection(currentNewsIdForBox, searchTerm = '') {
            if (!newsListContainer) return;
            newsListContainer.innerHTML = '<p>Loading news...</p>';
            fetch(`get_news_for_selection.php?search=${encodeURIComponent(searchTerm)}`)
                .then(response => { if (!response.ok) { throw new Error('Network response was not ok: ' + response.statusText); } return response.json(); })
                .then(data => {
                    newsListContainer.innerHTML = '';
                    if (data.success && data.news.length > 0) {
                        data.news.forEach(news_item => {
                            const itemDiv = document.createElement('div');
                            itemDiv.classList.add('news-item-selectable');
                            itemDiv.textContent = `${news_item.title} (ID: ${news_item.id}) | Date: ${news_item.date_formatted}`;
                            itemDiv.dataset.newsid = news_item.id;
                            if (news_item.id == currentNewsIdForBox) {
                                itemDiv.classList.add('selected-news');
                            }
                            itemDiv.onclick = function() {
                                const boxKeyForUpdate = currentBoxKeyInput.value;
                                if (!boxKeyForUpdate) { console.error('[itemDiv.onclick AdminSelect] Error: boxKeyForUpdate is undefined.'); alert('Error: Banner to update not identified.'); return; }
                                selectNewsForBoxAdmin(boxKeyForUpdate, news_item.id, news_item.title);
                            };
                            newsListContainer.appendChild(itemDiv);
                        });
                    } else if (data.success && data.news.length === 0) { newsListContainer.innerHTML = '<p>No news found.</p>';
                    } else { newsListContainer.innerHTML = `<p>Error: ${data.message || 'Unknown error'}</p>`; }
                })
                .catch(error => { console.error('[loadNewsForAdminSelection] Error:', error); newsListContainer.innerHTML = '<p>Error loading list.</p>'; });
        }

        function selectNewsForBoxAdmin(boxKey, newsId, newsTitle) {
            if (!boxKey) { console.error('[selectNewsForBoxAdmin] Error: boxKey is empty.'); alert('Error: Banner to update not identified.'); return; }
            const formData = new FormData();
            formData.append('box_key', boxKey);
            formData.append('news_id', newsId);
            fetch('update_selected_news.php', { method: 'POST', body: formData })
                .then(response => { if (!response.ok) { return response.text().then(text => { throw new Error(`Server responded ${response.status}: ${text || 'No message'}`); }); } return response.json(); })
                .then(data => {
                    if (data.success) {
                        alert(`'${newsTitle}' (ID: ${newsId}) selected for ${boxKey.replace('_news_id','').replace('_',' ')} banner.`);
                        if (modalSelectNews) modalSelectNews.style.display = "none";
                        window.location.reload();
                    } else { alert(`Failed: ${data.message || 'Unknown error'}`); }
                })
                .catch(error => { console.error('[selectNewsForBoxAdmin] Error:', error); alert(`Error: ${error.message}.`); });
        }

        const createNewsModal = document.getElementById('createNewsModal');
        const openCreateNewsModalBtn = document.getElementById('openCreateNewsModalBtn');
        const closeCreateNewsModalBtn = document.querySelector('#createNewsModal .close-modal-btn');
        const createNewsForm = document.getElementById('createNewsForm');
        const createNewsMessageDiv = document.getElementById('createNewsMessage');
        const deleteNewsBtn = document.getElementById('deleteNewsBtn');

        const previewNewsModal = document.getElementById('previewNewsModal');
        const previewNewsBtn = document.getElementById('previewNewsBtn');
        const closePreviewNewsModalBtn = document.querySelector('#previewNewsModal .close-modal-btn');
        const previewNewsTitleElem = document.getElementById('previewNewsTitle');
        const previewNewsDateElem = document.getElementById('previewNewsDate');
        const previewNewsSourceContainerElem = document.getElementById('previewNewsSourceContainer');
        const previewNewsSourceElem = document.getElementById('previewNewsSource');
        const previewNewsPhotoContainerElem = document.getElementById('previewNewsPhotoContainer');
        const previewNewsPhotoElem = document.getElementById('previewNewsPhoto');
        const previewNewsTextElem = document.getElementById('previewNewsText');

        if (createNewsModal && openCreateNewsModalBtn && closeCreateNewsModalBtn && createNewsForm && createNewsMessageDiv && deleteNewsBtn) {
            openCreateNewsModalBtn.addEventListener('click', function() {
                createNewsMessageDiv.innerHTML = ''; createNewsForm.reset();
                createNewsModal.querySelector('h2').textContent = 'Create New News Article';
                createNewsForm.querySelector('button[type="submit"]').textContent = 'Create News';
                const oldEditingIdField = createNewsForm.querySelector('input[name="editing_news_id"]');
                if (oldEditingIdField) oldEditingIdField.remove();
                const dateInput = createNewsForm.querySelector('#news_date');
                deleteNewsBtn.style.display = 'none';
                if (dateInput && !dateInput.value) {
                    const today = new Date(); const year = today.getFullYear(); const month = ('0' + (today.getMonth() + 1)).slice(-2); const day = ('0' + today.getDate()).slice(-2);
                    dateInput.value = `${year}-${month}-${day}`;
                }
                createNewsModal.style.display = 'block';
            });
            closeCreateNewsModalBtn.onclick = function() { createNewsModal.style.display = 'none'; }

            createNewsForm.addEventListener('submit', function(event) {
                event.preventDefault(); createNewsMessageDiv.innerHTML = '<p>Creating news...</p>';
                const formData = new FormData(this); formData.append('create_news_submit', '1');
                fetch('create_news_process.php', { method: 'POST', body: formData })
                    .then(response => { if (!response.ok) { return response.text().then(text => { throw new Error(`Server error: ${response.status} ${text || ''}`); }); } return response.json(); })
                    .then(data => {
                        if (data.success) { createNewsMessageDiv.innerHTML = `<p style="color: green;">${data.message || 'News created!'}</p>`; this.reset(); setTimeout(() => { if (createNewsModal) createNewsModal.style.display = 'none'; window.location.reload(); }, 1500);
                        } else { createNewsMessageDiv.innerHTML = `<p style="color: red;">Error: ${data.message || 'Failed.'}</p>`; }
                    })
                    .catch(error => { console.error('Error creating news:', error); createNewsMessageDiv.innerHTML = `<p style="color: red;">Error: ${error.message}</p>`; });
            });

            deleteNewsBtn.addEventListener('click', function() {
                const newsIdField = createNewsForm.querySelector('input[name="editing_news_id"]');
                if (!newsIdField || !newsIdField.value) {
                    alert('Error: News ID for deletion not found.');
                    return;
                }
                const newsIdToDelete = newsIdField.value;
                const newsTitle = createNewsForm.querySelector('#news_title').value || `News ID: ${newsIdToDelete}`;

                if (confirm(`Are you sure you want to delete the news article: "${newsTitle}"? This action cannot be undone.`)) {
                    createNewsMessageDiv.innerHTML = '<p>Deleting news article...</p>';
                    const formData = new FormData();
                    formData.append('news_id', newsIdToDelete);
                    formData.append('delete_news_submit', 'true');

                    fetch('delete_news_process.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => {
                            if (!response.ok) { return response.text().then(text => { throw new Error(`Server error: ${response.status} ${text || ''}`); }); }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                createNewsMessageDiv.innerHTML = `<p style="color: green;">${data.message || 'News deleted successfully!'}</p>`;
                                setTimeout(() => {
                                    if (createNewsModal) createNewsModal.style.display = 'none';
                                    window.location.reload();
                                }, 1500);
                            } else {
                                createNewsMessageDiv.innerHTML = `<p style="color: red;">Error: ${data.message || 'Failed to delete news.'}</p>`;
                            }
                        })
                        .catch(error => {
                            console.error('Error deleting news:', error);
                            createNewsMessageDiv.innerHTML = `<p style="color: red;">An error occurred: ${error.message}</p>`;
                        });
                }
            });
        }

        if (previewNewsModal && previewNewsBtn && closePreviewNewsModalBtn && createNewsForm &&
            previewNewsTitleElem && previewNewsDateElem && previewNewsSourceContainerElem && previewNewsSourceElem &&
            previewNewsPhotoContainerElem && previewNewsPhotoElem && previewNewsTextElem) {

            previewNewsBtn.addEventListener('click', function() {
                const title = createNewsForm.querySelector('#news_title').value; const text = createNewsForm.querySelector('#news_text').value;
                const dateStr = createNewsForm.querySelector('#news_date').value; const source = createNewsForm.querySelector('#news_source').value;
                const photoFile = createNewsForm.querySelector('#news_photo').files[0];
                previewNewsTitleElem.textContent = title || "(No Title)";
                if (dateStr) { try { const dateObj = new Date(dateStr.replace(/-/g, '/') + 'T00:00:00'); previewNewsDateElem.textContent = `Date: ${dateObj.toLocaleDateString()}`; } catch (e) { previewNewsDateElem.textContent = `Date: (Invalid Date)`; } } else { previewNewsDateElem.textContent = "Date: (Not set)"; }
                if (source) { previewNewsSourceElem.textContent = source; previewNewsSourceContainerElem.style.display = 'inline'; } else { previewNewsSourceContainerElem.style.display = 'none'; }
                if (photoFile) {
                    const reader = new FileReader();
                    reader.onload = function(e) { previewNewsPhotoElem.src = e.target.result; previewNewsPhotoElem.alt = title || "Preview"; previewNewsPhotoElem.style.display = 'block'; }
                    reader.readAsDataURL(photoFile); previewNewsPhotoContainerElem.style.display = 'block';
                } else { previewNewsPhotoElem.style.display = 'none'; previewNewsPhotoElem.src = ''; previewNewsPhotoContainerElem.style.display = 'none'; }
                previewNewsTextElem.innerHTML = nl2br_js(text || "(No Text)");
                previewNewsModal.style.display = 'block';
            });
            closePreviewNewsModalBtn.onclick = function() { previewNewsModal.style.display = 'none'; }
        }
        <?php endif;?>
    });
</script>

</body>
</html>