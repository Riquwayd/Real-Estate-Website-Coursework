<?php
session_start();

// Підключення конфігурації бази даних
$db_config_path = __DIR__ . '/db_config.php';
if (file_exists($db_config_path)) {
    require_once $db_config_path;
} else {
    error_log("FATAL ERROR in register_process.php: db_config.php NOT FOUND at " . $db_config_path);
    $_SESSION['reg_error'] = 'Critical server configuration error. Please contact administrator.';
    header('Location: index.php?action=register');
    exit;
}

// Перевірка наявності об'єкта PDO
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("FATAL ERROR in register_process.php: \$pdo is NOT SET or not a PDO instance after including db_config.php.");
    $_SESSION['reg_error'] = 'Critical database connection error. Please contact administrator.';
    header('Location: index.php?action=register');
    exit;
}

// Обробка POST-запиту для реєстрації
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $reg_login = trim($_POST['reg_login']);
    $reg_email = trim($_POST['reg_email']);
    $reg_password = $_POST['reg_password'];

    // Збереження введених даних у сесію для повторного заповнення форми у разі помилки
    $_SESSION['reg_data'] = [
        'login' => $reg_login,
        'email' => $reg_email
    ];

    // Валідація обов'язкових полів
    if (empty($reg_login) || empty($reg_email) || empty($reg_password)) {
        $_SESSION['reg_error'] = 'Login, email, and password fields are required.';
        header('Location: index.php?action=register');
        exit;
    }

    // Валідація формату email
    if (!filter_var($reg_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['reg_error'] = 'Invalid email format.';
        header('Location: index.php?action=register');
        exit;
    }

    // Валідація довжини та символів логіна
    if (strlen($reg_login) < 3 || strlen($reg_login) > 50) {
        $_SESSION['reg_error'] = 'Login must be between 3 and 50 characters.';
        header('Location: index.php?action=register');
        exit;
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $reg_login)) {
        $_SESSION['reg_error'] = 'Login can only contain letters, numbers, and underscores.';
        header('Location: index.php?action=register');
        exit;
    }

    // Валідація довжини пароля
    if (strlen($reg_password) < 6) {
        $_SESSION['reg_error'] = 'Password must be at least 6 characters long.';
        header('Location: index.php?action=register');
        exit;
    }

    // Перевірка унікальності логіна та email
    try {
        $stmt = $pdo->prepare("SELECT login, email FROM users WHERE login = :login OR email = :email LIMIT 1");
        $stmt->execute([':login' => $reg_login, ':email' => $reg_email]);
        $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_user) {
            if ($existing_user['login'] === $reg_login) {
                $_SESSION['reg_error'] = 'This login is already taken.';
            } elseif ($existing_user['email'] === $reg_email) {
                $_SESSION['reg_error'] = 'This email address is already registered.';
            }
            header('Location: index.php?action=register');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Registration Uniqueness Check PDOException in register_process.php: " . $e->getMessage());
        $_SESSION['reg_error'] = 'Database error during uniqueness check. Please try again.';
        header('Location: index.php?action=register');
        exit;
    }

    $hashed_password = password_hash($reg_password, PASSWORD_DEFAULT);
    $access_right = 1; // Права доступу за замовчуванням для нового користувача
    $default_name = $reg_login; // Ім'я за замовчуванням - логін

    // Додавання нового користувача в базу даних
    try {
        $stmt = $pdo->prepare("INSERT INTO users (login, email, pass, name, access_right) VALUES (?, ?, ?, ?, ?)");
        $stmt->bindParam(1, $reg_login);
        $stmt->bindParam(2, $reg_email);
        $stmt->bindParam(3, $hashed_password);
        $stmt->bindParam(4, $default_name);
        $stmt->bindParam(5, $access_right, PDO::PARAM_INT);

        if ($stmt->execute()) {
            unset($_SESSION['reg_data']);
            $_SESSION['reg_success'] = 'Account created successfully! Please login.';
            header('Location: index.php');
        } else {
            $_SESSION['reg_error'] = 'Failed to create account (execute returned false). Please try again.';
            header('Location: index.php?action=register');
        }
        exit;
    } catch (PDOException $e) {
        error_log("Registration Insert PDOException in register_process.php: " . $e->getMessage());
        if ($e->errorInfo[1] == 1062) {
            $_SESSION['reg_error'] = 'This login or email address is already registered.';
        } else {
            $_SESSION['reg_error'] = 'Database error during account creation. Please try again.';
        }
        header('Location: index.php?action=register');
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}