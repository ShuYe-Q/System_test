<?php
// 数据库配置文件
$servername = "localhost";
$username = "test";
$password = "123456";
$dbname = "php_wish";

// 创建数据库连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接是否成功
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 设置字符集
$conn->set_charset("utf8mb4");

// 数据库操作函数
function execute_query($sql, $params = []) {
    global $conn;
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        return ["error" => $conn->error];
    }
    
    if (!empty($params)) {
        $types = str_repeat("s", count($params));
        $stmt->bind_param($types, ...$params);
    }
    
    $result = $stmt->execute();
    
    if ($result === false) {
        return ["error" => $stmt->error];
    }
    
    // 对于查询语句，返回结果集
    if (strtolower(substr(trim($sql), 0, 6)) === "select") {
        $result_set = $stmt->get_result();
        $rows = [];
        while ($row = $result_set->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
    
    // 对于插入语句，返回插入的ID
    if (strtolower(substr(trim($sql), 0, 6)) === "insert") {
        $affected_rows = $stmt->affected_rows;
        $insert_id = $stmt->insert_id;
        $stmt->close();
        
        // 检查是否真的插入了数据
        if ($affected_rows > 0) {
            return ["insert_id" => $insert_id];
        } else {
            return ["error" => "插入失败：没有数据被插入"];
        }
    }
    
    // 对于更新和删除语句，返回影响的行数
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    return ["affected_rows" => $affected_rows];
}

// 关闭数据库连接的函数
function close_connection() {
    global $conn;
    if (isset($conn)) {
        $conn->close();
    }
}
?>