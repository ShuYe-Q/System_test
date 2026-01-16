<?php
// 调试脚本
require_once 'config/db.php';

// 检查数据库连接
echo "数据库连接状态: " . ($conn->ping() ? "成功" : "失败") . "<br>";

// 检查 approval_processes 表是否存在
$table_check_sql = "SHOW TABLES LIKE 'approval_processes'";
$table_check_result = execute_query($table_check_sql);
echo "approval_processes 表是否存在: " . (!empty($table_check_result) ? "是" : "否") . "<br>";

// 如果表存在，显示表结构
if (!empty($table_check_result)) {
    echo "<h3>approval_processes 表结构:</h3>";
    $structure_sql = "DESCRIBE approval_processes";
    $structure_result = execute_query($structure_sql);
    if (!empty($structure_result)) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($structure_result as $row) {
            echo "<tr>";
            echo "<td>".$row['Field'].'</td>';
            echo "<td>".$row['Type'].'</td>';
            echo "<td>".$row['Null'].'</td>';
            echo "<td>".$row['Key'].'</td>';
            echo "<td>".$row['Default'].'</td>';
            echo "<td>".$row['Extra'].'</td>';
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 检查表中的数据
    echo "<h3>approval_processes 表数据:</h3>";
    $data_sql = "SELECT * FROM approval_processes ORDER BY created_at DESC";
    $data_result = execute_query($data_sql);
    if (!empty($data_result)) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>id</th><th>asset_id</th><th>type</th><th>status</th><th>requester_id</th><th>approver_id</th><th>reason</th><th>created_at</th><th>approved_at</th></tr>";
        foreach ($data_result as $row) {
            echo "<tr>";
            echo "<td>".$row['id'].'</td>';
            echo "<td>".$row['asset_id'].'</td>';
            echo "<td>".$row['type'].'</td>';
            echo "<td>".$row['status'].'</td>';
            echo "<td>".$row['requester_id'].'</td>';
            echo "<td>".$row['approver_id'].'</td>';
            echo "<td>".$row['reason'].'</td>';
            echo "<td>".$row['created_at'].'</td>';
            echo "<td>".$row['approved_at'].'</td>';
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "表中无数据<br>";
    }
    
    // 检查关联的表
    echo "<h3>关联表检查:</h3>";
    
    // 检查 assets 表
    $assets_check_sql = "SHOW TABLES LIKE 'assets'";
    $assets_check_result = execute_query($assets_check_sql);
    echo "assets 表是否存在: " . (!empty($assets_check_result) ? "是" : "否") . "<br>";
    
    // 检查 users 表
    $users_check_sql = "SHOW TABLES LIKE 'users'";
    $users_check_result = execute_query($users_check_sql);
    echo "users 表是否存在: " . (!empty($users_check_result) ? "是" : "否") . "<br>";
    
    // 检查 departments 表
    $depts_check_sql = "SHOW TABLES LIKE 'departments'";
    $depts_check_result = execute_query($depts_check_sql);
    echo "departments 表是否存在: " . (!empty($depts_check_result) ? "是" : "否") . "<br>";
} else {
    echo "approval_processes 表不存在，需要创建<br>";
    // 提供创建表的 SQL 语句
    echo "<h3>创建 approval_processes 表的 SQL:</h3>";
    $create_table_sql = "CREATE TABLE approval_processes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    type ENUM('transfer', 'scrap', 'maintenance', 'purchase') NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    requester_id INT NOT NULL,
    approver_id INT NULL,
    reason TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    FOREIGN KEY (asset_id) REFERENCES assets(id),
    FOREIGN KEY (requester_id) REFERENCES users(id),
    FOREIGN KEY (approver_id) REFERENCES users(id)
);";
    echo "<pre>".$create_table_sql.'</pre>';
}

// 关闭连接
close_connection();
?>