<?php
// 简单调试脚本
require_once 'config/db.php';

// 检查数据库连接
echo "数据库连接状态: " . ($conn->ping() ? "成功" : "失败") . "\n";

// 检查 approval_processes 表是否存在
$table_check_sql = "SHOW TABLES LIKE 'approval_processes'";
$table_check_result = execute_query($table_check_sql);
echo "approval_processes 表是否存在: " . (!empty($table_check_result) ? "是" : "否") . "\n";

// 如果表存在，显示表中的数据
if (!empty($table_check_result)) {
    // 检查表中的数据
    echo "\napproval_processes 表数据:\n";
    $data_sql = "SELECT * FROM approval_processes ORDER BY created_at DESC";
    $data_result = execute_query($data_sql);
    if (!empty($data_result)) {
        echo "ID | Asset ID | Type | Status | Requester ID | Approver ID | Created At\n";
        echo "---------------------------------------------------------------------------\n";
        foreach ($data_result as $row) {
            echo $row['id'] . " | " . $row['asset_id'] . " | " . $row['type'] . " | " . $row['status'] . " | " . $row['requester_id'] . " | " . ($row['approver_id'] ?? "NULL") . " | " . $row['created_at'] . "\n";
        }
    } else {
        echo "表中无数据\n";
    }
    
    // 检查关联的表
    echo "\n关联表检查:\n";
    
    // 检查 assets 表
    $assets_check_sql = "SHOW TABLES LIKE 'assets'";
    $assets_check_result = execute_query($assets_check_sql);
    echo "assets 表是否存在: " . (!empty($assets_check_result) ? "是" : "否") . "\n";
    
    // 检查 users 表
    $users_check_sql = "SHOW TABLES LIKE 'users'";
    $users_check_result = execute_query($users_check_sql);
    echo "users 表是否存在: " . (!empty($users_check_result) ? "是" : "否") . "\n";
    
    // 检查 departments 表
    $depts_check_sql = "SHOW TABLES LIKE 'departments'";
    $depts_check_result = execute_query($depts_check_sql);
    echo "departments 表是否存在: " . (!empty($depts_check_result) ? "是" : "否") . "\n";
    
    // 检查当前用户信息（模拟）
    echo "\n模拟用户信息:\n";
    echo "用户ID: 1\n";
    echo "角色: manager\n";
    echo "部门ID: 1\n";
    
    // 测试查询
    echo "\n测试查询结果:\n";
    $test_sql = "SELECT ap.*, a.name as asset_name, u.name as requester_name, 
                  u2.name as approver_name, d.name as department_name 
                  FROM approval_processes ap 
                  JOIN assets a ON ap.asset_id = a.id 
                  JOIN users u ON ap.requester_id = u.id 
                  LEFT JOIN users u2 ON ap.approver_id = u2.id
                  LEFT JOIN departments d ON a.department_id = d.id
                  WHERE (ap.requester_id = ? OR a.department_id = ?) ORDER BY ap.created_at DESC";
    $test_result = execute_query($test_sql, [1, 1]);
    echo "查询结果数量: " . count($test_result) . "\n";
    if (!empty($test_result)) {
        echo "找到数据!\n";
    }
} else {
    echo "\napproval_processes 表不存在，需要创建\n";
}

// 关闭连接
close_connection();
?>