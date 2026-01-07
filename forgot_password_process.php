<?php
session_start();

$db_config_path = __DIR__ . '/db_config.php';
if (file_exists($db_config_path)) {
    require_once $db_config_path;
} else {
    error_log("FATAL ERROR in forgot_password_process.php: db_config.php NOT FOUND at " . $db_config_path);
    if (isset($_POST['step'])) {
        $_SESSION['fp_error'] = 'Critical server configuration error (DB Config). Please contact administrator.';
        if ($_POST['step'] === 'send_code') header('Location: index.php?action=forgot_email');
        elseif ($_POST['step'] === 'verify_code') header('Location: index.php?action=forgot_code');
        elseif ($_POST['step'] === 'reset_password') header('Location: index.php?action=forgot_new_password');
        else header('Location: index.php?action=forgot_email');
    } else {
        die('Critical server configuration error (DB Config).');
    }
    exit;
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("FATAL ERROR in forgot_password_process.php: \$pdo is NOT SET or not a PDO instance immediately after including db_config.php.");
    if (isset($_POST['step'])) {
        $_SESSION['fp_error'] = 'Critical database connection error (PDO Object). Please contact administrator.';
        if ($_POST['step'] === 'send_code') header('Location: index.php?action=forgot_email');
        elseif ($_POST['step'] === 'verify_code') header('Location: index.php?action=forgot_code');
        elseif ($_POST['step'] === 'reset_password') header('Location: index.php?action=forgot_new_password');
        else header('Location: index.php?action=forgot_email');
    } else {
        die('Critical database connection error (PDO Object).');
    }
    exit;
}
// Підключення PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

const FP_CODE_EXPIRATION_TIME = 60 * 10;
const FP_MAX_CODE_ATTEMPTS = 5;

function generateNumericOTP(int $length = 6): string {return (string)random_int(pow(10, $length -1), pow(10, $length) -1);}

if (!isset($_SESSION['fp_code'])) { $_SESSION['fp_code'] = null; }
if (!isset($_SESSION['fp_code_sent_time'])) { $_SESSION['fp_code_sent_time'] = 0; }
if (!isset($_SESSION['fp_email_for_code'])) { $_SESSION['fp_email_for_code'] = null; }
if (!isset($_SESSION['fp_code_attempts'])) { $_SESSION['fp_code_attempts'] = 0; }
if (!isset($_SESSION['fp_code_verified'])) { $_SESSION['fp_code_verified'] = false; }

function unset_fp_session_vars() {
    unset($_SESSION['fp_code']);
    unset($_SESSION['fp_code_sent_time']);
    unset($_SESSION['fp_code_attempts']);
    unset($_SESSION['fp_code_verified']);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step'])) {
    $step = $_POST['step'];

    // Етап 1: Надсилання коду на email
    if ($step === 'send_code') {
        $email_to_send_to = trim($_POST['fp_email'] ?? '');
        if (empty($email_to_send_to) || !filter_var($email_to_send_to, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['fp_error'] = empty($email_to_send_to) ? 'Email address is required.' : 'Invalid email format.';
            header('Location: index.php?action=forgot_email');
            exit;
        }
        $reset_code = generateNumericOTP(6);
        $_SESSION['fp_code'] = $reset_code;
        $_SESSION['fp_code_sent_time'] = time();
        $_SESSION['fp_email_for_code'] = $email_to_send_to;
        $_SESSION['fp_code_attempts'] = 0;
        $_SESSION['fp_code_verified'] = false;

        // Налаштування та посилання листа через PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP(); $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true;
            $mail->Username = 'realestate18052025@gmail.com'; $mail->Password = 'sdbgwlqnkswwjpkp';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; $mail->Port = 587;
            $mail->setFrom('realestate18052025@gmail.com', 'Real Estate Support');
            $mail->addAddress($email_to_send_to);
            $mail->isHTML(false); $mail->CharSet = 'UTF-8';
            $mail->Subject = 'Password Reset Code - Real Estate';
            $message_body  = "Hello,\n\nYour password reset code is: " . $reset_code . "\nThis code is valid for " . (FP_CODE_EXPIRATION_TIME / 60) . " minutes.\n\nIf you did not request a password reset, please ignore this email.\n\nRegards,\nThe Real Estate Team";
            $mail->Body = $message_body;
            $mail->send();
            $_SESSION['fp_success'] = "A password reset code has been sent to your email address.";
        } catch (Exception $e) {
            error_log("PHPMailer Message could not be sent. Mailer Error: $mail->ErrorInfo --- Exception: {$e->getMessage()}");
            $_SESSION['fp_error'] = "Could not send the reset code. Please check your email or try again later. (Error code: PM1)";
        }
        header('Location: index.php?action=forgot_code');
        exit;
    }

    // Етап 2: Перевірка введеного коду
    elseif ($step === 'verify_code') {
        if (empty($_SESSION['fp_email_for_code']) || empty($_SESSION['fp_code'])) {
            $_SESSION['fp_error'] = 'Session expired or invalid request. Please start by entering your email again.';
            unset_fp_session_vars();
            header('Location: index.php?action=forgot_email');
            exit;
        }

        $entered_code = trim($_POST['fp_code'] ?? '');

        // Перевірка, чи не минув термін дії коду
        if (time() > ($_SESSION['fp_code_sent_time'] + FP_CODE_EXPIRATION_TIME)) {
            $_SESSION['fp_error'] = 'The verification code has expired. Please request a new one.';
            unset_fp_session_vars();
            header('Location: index.php?action=forgot_email');
            exit;
        }

        $_SESSION['fp_code_attempts']++;

        // Перевірка, чи не вичерпано ліміт спроб при невірному коді
        if ($_SESSION['fp_code_attempts'] >= FP_MAX_CODE_ATTEMPTS && $entered_code !== $_SESSION['fp_code']) {
            $_SESSION['fp_error'] = 'Too many incorrect attempts. The code is now invalid. Please request a new one.';
            unset_fp_session_vars();
            header('Location: index.php?action=forgot_email');
            exit;
        }

        if ($entered_code === $_SESSION['fp_code']) {
            $_SESSION['fp_code_verified'] = true;
            $_SESSION['fp_code'] = null;
            $_SESSION['fp_code_sent_time'] = 0;
            $_SESSION['fp_code_attempts'] = 0;

            header('Location: index.php?action=forgot_new_password');
        } else {
            $_SESSION['fp_error'] = "Invalid code. Please try again.";
            header('Location: index.php?action=forgot_code');
        }
        exit;
    }

    // Етап 3: Скидання (встановлення нового) пароля
    elseif ($step === 'reset_password') {
        if (empty($_SESSION['fp_email_for_code']) || !isset($_SESSION['fp_code_verified']) || $_SESSION['fp_code_verified'] !== true) {
            $_SESSION['fp_error'] = 'Invalid session or code not verified. Please start over.';
            unset_fp_session_vars(); header('Location: index.php?action=forgot_email');
            exit;
        }

        $new_password = $_POST['fp_new_password'] ?? '';
        $confirm_password = $_POST['fp_confirm_password'] ?? '';

        if (empty($new_password) || empty($confirm_password)) {
            $_SESSION['fp_error'] = 'Both password fields are required.';
            header('Location: index.php?action=forgot_new_password');
            exit;
        }
        if ($new_password !== $confirm_password) {
            $_SESSION['fp_error'] = 'Passwords do not match.';
            header('Location: index.php?action=forgot_new_password');
            exit;
        }
        if (strlen($new_password) < 6) {
            $_SESSION['fp_error'] = 'Password must be at least 6 characters long.';
            header('Location: index.php?action=forgot_new_password');
            exit;

        }
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $email_to_reset = $_SESSION['fp_email_for_code'];

        try {
            if (!isset($pdo) || !$pdo instanceof PDO) {
                error_log("CRITICAL ERROR in forgot_password_process.php (reset_password step): \$pdo is unexpectedly unavailable BEFORE DB operation.");
                $_SESSION['fp_error'] = 'Critical internal server error (DB Handle Lost). Please try again.';
                header('Location: index.php?action=forgot_new_password');
                exit;
            }
            $stmt = $pdo->prepare("UPDATE users SET pass = ? WHERE email = ?");

            if ($stmt->execute([$hashed_password, $email_to_reset])) {
                if ($stmt->rowCount() > 0) {
                    $_SESSION['reg_success'] = 'Your password has been successfully reset. Please login with your new password.';
                    unset_fp_session_vars();
                    header('Location: index.php');
                }
                else { $_SESSION['fp_error'] = 'Could not find an account with that email address to reset. Please try again or register.';
                    error_log("Forgot Password: Attempt to reset password for non-existent email: " . $email_to_reset);
                    unset_fp_session_vars(); header('Location: index.php?action=forgot_email');
                }
            } else {
                $_SESSION['fp_error'] = 'Failed to update password. Please try again.';
                header('Location: index.php?action=forgot_new_password');
            }
            exit;
        } catch (PDOException $e) {
            error_log("Password Reset PDOException for email " . $email_to_reset . ": " . $e->getMessage());
            $_SESSION['fp_error'] = 'A database error occurred. Could not reset password.';
            header('Location: index.php?action=forgot_new_password');
            exit;
        }
    }
    else {
        unset_fp_session_vars();
        unset($_SESSION['fp_email_for_code']);
        header('Location: index.php?action=forgot_email');
        exit;
    }
} else {
    unset_fp_session_vars();
    unset($_SESSION['fp_email_for_code']);
    header('Location: index.php?action=forgot_email');
    exit;
}

