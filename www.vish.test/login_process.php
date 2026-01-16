<?php
// 登录处理页面
require_once 'config/db.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // 查询用户信息
    $sql = "SELECT * FROM users WHERE username = ?";
    $result = execute_query($sql, [$username]);
    
    if (empty($result)) {
        header('Location: login.php?error=用户名或密码错误');
        exit;
    }
    
    $user = $result[0];
    
    // 验证密码（这里使用MD5，实际项目中应该使用更安全的加密方式）
    if (md5($password) !== $user['password']) {
        header('Location: login.php?error=用户名或密码错误');
        exit;
    }
    
    // 保存用户信息到会话
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['department_id'] = $user['department_id'];
    
    // 记录登录日志
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'login', '用户登录系统', ?)";
    execute_query($log_sql, [$user['id'], $ip_address]);
    
    // 跳转到首页
    header('Location: index.php');
    exit;
} else {
    header('Location: login.php');
    exit;
}
?>