<?php
// 检查数据库表结构并写入文件
require_once 'config/db.php';

// 打开文件准备写入
$file = fopen('db_check_result.txt', 'w');

if ($file) {
    // 检查数据库连接
    fwrite($file, "数据库连接状态: " . ($conn->ping() ? "成功" : "失败") . "\n\n");
    
    // 检查所有相关表
    $tables = ['approval_processes', 'assets', 'users', 'departments'];
    
    foreach ($tables as $table) {
        fwrite($file, "=== 检查 {$table} 表 ===\n");
        
        // 检查表是否存在
        $check_sql = "SHOW TABLES LIKE '{$table}'";
        $check_result = execute_query($check_sql);
        fwrite($file, "表是否存在: " . (!empty($check_result) ? "是" : "否") . "\n");
        
        if (!empty($check_result)) {
            // 检查表结构
            fwrite($file, "表结构:\n");
            $structure_sql = "DESCRIBE {$table}";
            $structure_result = execute_query($structure_sql);
            if (!empty($structure_result)) {
                foreach ($structure_result as $row) {
                    fwrite($file, "{$row['Field']} | {$row['Type']} | {$row['Null']} | {$row['Key']} | {$row['Default']} | {$row['Extra']}\n");
                }
            }
            
            // 检查表中的数据
            fwrite($file, "\n表数据:\n");
            $data_sql = "SELECT * FROM {$table} ORDER BY id DESC LIMIT 10";
            $data_result = execute_query($data_sql);
            if (!empty($data_result)) {
                foreach ($data_result as $row) {
                    fwrite($file, print_r($row, true) . "\n");
                }
            } else {
                fwrite($file, "表中无数据\n");
            }
        }
        
        fwrite($file, "\n");
    }
    
    // 测试审批流程查询
    fwrite($file, "=== 测试审批流程查询 ===\n");
    $test_sql = "SELECT ap.*, a.name as asset_name, u.name as requester_name, 
                  u2.name as approver_name, d.name as department_name 
                  FROM approval_processes ap 
                  JOIN assets a ON ap.asset_id = a.id 
                  JOIN users u ON ap.requester_id = u.id 
                  LEFT JOIN users u2 ON ap.approver_id = u2.id
                  LEFT JOIN departments d ON a.department_id = d.id
                  ORDER BY ap.created_at DESC";
    $test_result = execute_query($test_sql);
    fwrite($file, "查询结果数量: " . count($test_result) . "\n");
    if (!empty($test_result)) {
        foreach ($test_result as $row) {
            fwrite($file, print_r($row, true) . "\n");
        }
    }
    
    fclose($file);
    echo "数据库检查完成，结果已写入 db_check_result.txt 文件\n";
} else {
    echo "无法创建文件\n";
}

// 关闭连接
close_connection();
?>