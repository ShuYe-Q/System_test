<?php
// 退出登录页面
require_once 'config/db.php';

session_start();

// 记录退出日志
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'logout', '用户退出系统', ?)";
    execute_query($log_sql, [$user_id, $ip_address]);
}

// 清除会话信息
session_unset();
session_destroy();

// 跳转到登录页面
header('Location: login.php');
exit;
?>