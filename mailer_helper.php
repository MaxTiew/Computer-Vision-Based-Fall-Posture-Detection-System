<?php

use PHPMailer\PHPMailer\PHPMailer;

if (!function_exists('create_system_mailer')) {
    function create_system_mailer(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'xxx@gmail.com';
        $mail->Password = 'xxxx xxxx xxxx xxxx';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom('xxx@gmail.com', 'GoodLife Vision');

        return $mail;
    }
}

if (!function_exists('get_application_base_url')) {
    function get_application_base_url(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/goodlife/index.php';
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        if ($basePath === '') {
            $basePath = '/goodlife';
        }

        return $scheme . '://' . $host . $basePath . '/';
    }
}
