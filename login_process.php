<?php
session_start(); // Запуск сесії

// --- НАЛАШТУВАННЯ ОБМЕЖЕННЯ СПРОБ ВХОДУ ---
const ATTEMPTS_BEFORE_LOCKOUT_STAGE_1 = 5; // Спроб до першого блокування
const LOCKOUT_DURATION_STAGE_1 = 5;      // Тривалість першого блокування в секундах

const ATTEMPTS_BEFORE_LOCKOUT_STAGE_2 = 5; // Спроб (після першого блокування) до другого блокування
const LOCKOUT_DURATION_STAGE_2 = 60;     // Тривалість другого блокування в секундах

const ATTEMPTS_BEFORE_LOCKOUT_STAGE_3 = 1; // Спроб (після другого блокування) до наступних блокувань
const LOCKOUT_DURATION_STAGE_3 = 60;     // Тривалість третього та наступних блокувань

$db_config_path = __DIR__ . '/db_config.php';
if (file_exists($db_config_path)) {
    require_once $db_config_path;
} else {
    error_log("FATAL ERROR in login_process.php: db_config.php NOT FOUND at " . $db_config_path);
    $_SESSION['login_error'] = 'Critical server configuration error. Please contact administrator.';
    header('Location: index.php');
    exit;
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("FATAL ERROR in login_process.php: \$pdo is NOT SET or not a PDO instance after including db_config.php.");
    $_SESSION['login_error'] = 'Critical database connection error. Please contact administrator.';
    header('Location: index.php');
    exit;
}

// Ініціалізація змінних сесії для відстеження спроб входу, якщо вони ще не встановлені
if (!isset($_SESSION['login_attempts_current_stage'])) { // Лічильник спроб для ПОТОЧНОГО етапу
    $_SESSION['login_attempts_current_stage'] = 0;
}
if (!isset($_SESSION['login_lockout_time'])) { // Час закінчення блокування
    $_SESSION['login_lockout_time'] = 0;
}
if (!isset($_SESSION['login_lockout_stage'])) { // Поточний етап блокування (0 - немає, 1 - перше, 2 - друге і т.д.)
    $_SESSION['login_lockout_stage'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    // 1. Перевірка на активне блокування
    if ($_SESSION['login_lockout_time'] > time()) {
        $remaining_lockout_time = $_SESSION['login_lockout_time'] - time();
        $_SESSION['login_error'] = "Too many failed login attempts. Please try again in " . $remaining_lockout_time . " seconds.";
        header('Location: index.php');
        exit;
    } else {
        // Якщо час блокування минув, але воно було встановлене
        if ($_SESSION['login_lockout_time'] > 0 && $_SESSION['login_lockout_time'] <= time()) {
            // Блокування щойно закінчилося. Скидаємо лічильник спроб для нового етапу.
            // `login_lockout_stage` не скидається, щоб наступне блокування було суворішим.
            $_SESSION['login_attempts_current_stage'] = 0;
            $_SESSION['login_lockout_time'] = 0;
        }
    }

    $login = trim($_POST['login']);
    $password = $_POST['password'];

    // Валідація введених даних
    if (empty($login) || empty($password)) {
        $_SESSION['login_error'] = 'Please enter both login and password.';
        header('Location: index.php');
        exit;
    }

    try {
        // Пошук користувача в базі даних
        $stmt = $pdo->prepare("SELECT id, login, pass, name, access_right, email FROM users WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['pass'])) {
            // Успішний вхід
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_login'] = $user['login'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['access_right'] = $user['access_right'];
            $_SESSION['user_email'] = $user['email'];

            // Скидання лічильників невдалих спроб та блокування
            $_SESSION['login_attempts_current_stage'] = 0;
            $_SESSION['login_lockout_time'] = 0;
            $_SESSION['login_lockout_stage'] = 0;
            unset($_SESSION['login_error']);

        } else {
            // Невдалий вхід: збільшуємо лічильник спроб
            $_SESSION['login_attempts_current_stage']++;
            $error_message_prefix = 'Invalid login or password.';
            $attempts_left_message = "";

            // Логіка застосування блокувань залежно від етапу
            if ($_SESSION['login_lockout_stage'] == 0) { // До першого блокування
                if ($_SESSION['login_attempts_current_stage'] >= ATTEMPTS_BEFORE_LOCKOUT_STAGE_1) {
                    $_SESSION['login_lockout_time'] = time() + LOCKOUT_DURATION_STAGE_1;
                    $_SESSION['login_lockout_stage'] = 1; // Перехід на наступний етап блокування
                    $_SESSION['login_attempts_current_stage'] = 0; // Скидання лічильника для нового етапу
                    $_SESSION['login_error'] = "Too many failed login attempts. You are locked out for " . LOCKOUT_DURATION_STAGE_1 . " seconds.";
                } else {
                    $_SESSION['login_error'] = $error_message_prefix;
                }
            } elseif ($_SESSION['login_lockout_stage'] == 1) { // Після першого блокування
                if ($_SESSION['login_attempts_current_stage'] >= ATTEMPTS_BEFORE_LOCKOUT_STAGE_2) {
                    $_SESSION['login_lockout_time'] = time() + LOCKOUT_DURATION_STAGE_2;
                    $_SESSION['login_lockout_stage'] = 2; // Перехід на наступний етап
                    $_SESSION['login_attempts_current_stage'] = 0;
                    $_SESSION['login_error'] = "Too many failed login attempts. You are locked out for " . LOCKOUT_DURATION_STAGE_2 . " seconds.";
                } else {
                    $attempts_left = ATTEMPTS_BEFORE_LOCKOUT_STAGE_2 - $_SESSION['login_attempts_current_stage'];
                    $attempts_left_message = " " . $attempts_left . " attempt" . ($attempts_left > 1 ? "s" : "") . " remaining before a longer lockout.";
                    $_SESSION['login_error'] = $error_message_prefix . $attempts_left_message;
                }
            } elseif ($_SESSION['login_lockout_stage'] >= 2) { // Після другого та наступних блокувань
                if ($_SESSION['login_attempts_current_stage'] >= ATTEMPTS_BEFORE_LOCKOUT_STAGE_3) {
                    $_SESSION['login_lockout_time'] = time() + LOCKOUT_DURATION_STAGE_3;
                    // Можна збільшувати login_lockout_stage для ще складніших правил, якщо потрібно
                    $_SESSION['login_attempts_current_stage'] = 0;
                    $_SESSION['login_error'] = "Too many failed login attempts. You are locked out for " . LOCKOUT_DURATION_STAGE_3 . " seconds.";
                } else {
                    $_SESSION['login_error'] = $error_message_prefix;
                }
            }
        }
        header('Location: index.php');
        exit;
    } catch (PDOException $e) {
        error_log("Login PDOException in login_process.php: " . $e->getMessage());
        $_SESSION['login_error'] = 'An error occurred during login. Please try again later.';
        header('Location: index.php');
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}