<?php
// 部门管理页面
require_once 'config/db.php';

session_start();

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 检查用户权限
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// 获取用户信息
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$name = $_SESSION['name'];
$role = $_SESSION['role'];
$department_id = $_SESSION['department_id'];

// 获取部门名称
$dept_sql = "SELECT name FROM departments WHERE id = ?";
$dept_result = execute_query($dept_sql, [$department_id]);
$department_name = $dept_result[0]['name'] ?? '未知部门';

// 获取部门列表
$departments_sql = "SELECT * FROM departments ORDER BY id DESC";
$departments = execute_query($departments_sql);

// 处理部门添加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_department'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    
    // 检查部门名称是否已存在
    $check_sql = "SELECT * FROM departments WHERE name = ?";
    $check_result = execute_query($check_sql, [$name]);
    
    if (!empty($check_result)) {
        $error = "部门名称已存在";
    } else {
        $insert_sql = "INSERT INTO departments (name, description) VALUES (?, ?)";
        $result = execute_query($insert_sql, [$name, $description]);
        
        if (isset($result['insert_id'])) {
            // 记录操作日志
            $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'add_department', '添加部门：$name', ?)";
            execute_query($log_sql, [$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
            
            header('Location: departments.php?success=部门添加成功');
            exit;
        } else {
            $error = "部门添加失败：" . ($result['error'] ?? '未知错误');
        }
    }
}

// 处理部门更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_department'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    
    // 检查部门名称是否已存在（排除当前部门）
    $check_sql = "SELECT * FROM departments WHERE name = ? AND id != ?";
    $check_result = execute_query($check_sql, [$name, $id]);
    
    if (!empty($check_result)) {
        $error = "部门名称已存在";
    } else {
        $update_sql = "UPDATE departments SET name = ?, description = ? WHERE id = ?";
        $result = execute_query($update_sql, [$name, $description, $id]);
        
        if (isset($result['affected_rows']) && $result['affected_rows'] > 0) {
            // 记录操作日志
            $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'update_department', '更新部门：$name', ?)";
            execute_query($log_sql, [$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
            
            header('Location: departments.php?success=部门更新成功');
            exit;
        } else {
            $error = "部门更新失败：" . ($result['error'] ?? '未知错误');
        }
    }
}

// 处理部门删除
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // 检查是否有用户属于该部门
    $check_users_sql = "SELECT * FROM users WHERE department_id = ?";
    $check_users_result = execute_query($check_users_sql, [$id]);
    
    if (!empty($check_users_result)) {
        $error = "该部门下存在用户，无法删除";
    } else {
        // 获取部门名称
        $dept_name_sql = "SELECT name FROM departments WHERE id = ?";
        $dept_name_result = execute_query($dept_name_sql, [$id]);
        $dept_name = $dept_name_result[0]['name'] ?? '';
        
        $delete_sql = "DELETE FROM departments WHERE id = ?";
        $result = execute_query($delete_sql, [$id]);
        
        if (isset($result['affected_rows']) && $result['affected_rows'] > 0) {
            // 记录操作日志
            $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'delete_department', '删除部门：$dept_name', ?)";
            execute_query($log_sql, [$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
            
            header('Location: departments.php?success=部门删除成功');
            exit;
        } else {
            $error = "部门删除失败：" . ($result['error'] ?? '未知错误');
        }
    }
}

// 获取单个部门信息（用于编辑）
$edit_department = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $dept_sql = "SELECT * FROM departments WHERE id = ?";
    $dept_result = execute_query($dept_sql, [$id]);
    $edit_department = $dept_result[0] ?? null;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>企业资源管理系统 - 部门管理</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/script.js"></script>
</head>
<body>
    <div class="container">
        <!-- 顶部导航栏 -->
        <header class="header">
            <div class="header-left">
                <h1>企业资源管理系统</h1>
            </div>
            <div class="header-right">
                <div class="user-info">
                    <span class="user-name">欢迎，<?php echo $name; ?></span>
                    <span class="role">(<?php echo $role === 'admin' ? '管理员' : ($role === 'manager' ? '部门经理' : '员工'); ?>)</span>
                    <span class="department"><?php echo $department_name; ?></span>
                </div>
                <a href="logout.php" class="logout-btn">退出登录</a>
            </div>
        </header>

        <!-- 主要内容容器 -->
        <div class="main-container">
            <!-- 侧边导航栏 -->
            <nav class="sidebar">
                <ul>
                    <li><a href="index.php">首页</a></li>
                    <li><a href="assets.php">资产管理</a></li>
                    <li><a href="approval.php">审批流程</a></li>
                    <li><a href="maintenance.php">维护管理</a></li>
                    <li><a href="inventory.php">盘点管理</a></li>
                    <?php if ($role === 'admin' || $role === 'manager'): ?>
                        <li><a href="reports.php">统计报表</a></li>
                        <li><a href="users.php">用户管理</a></li>
                    <?php endif; ?>
                    <?php if ($role === 'admin'): ?>
                        <li><a href="departments.php" class="active">部门管理</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <!-- 主内容区 -->
            <main class="main-content">
            <h2>部门管理</h2>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="error-message" style="background-color: rgba(0, 180, 42, 0.08); color: var(--success-color); border-color: rgba(0, 180, 42, 0.2);">
                    <?php echo $_GET['success']; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- 顶部操作按钮 -->
            <div style="margin-bottom: 16px; display: flex; justify-content: flex-end;">
                <button class="btn btn-primary" data-modal="department-modal">添加部门</button>
            </div>

            <!-- 部门添加弹窗 -->
            <div id="department-modal" class="modal">
                <div class="modal-content">
                    <div class="modal-card">
                        <div class="modal-header">
                            <h3>添加部门</h3>
                            <button type="button" class="modal-close close">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form method="post">
                                <div class="form-group">
                                    <label for="name">部门名称</label>
                                    <input type="text" id="name" name="name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description">部门描述</label>
                                    <textarea id="description" name="description" rows="4" style="width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 14px; transition: var(--transition); background-color: var(--bg-white); resize: vertical;"></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" name="add_department" class="btn btn-primary">添加部门</button>
                                    <button type="button" class="btn btn-secondary close">取消</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($edit_department): ?>
            <!-- 编辑部门仍在页面内展示 -->
            <div class="form-container" style="margin-bottom: 24px;">
                <h3>编辑部门</h3>
                <form method="post">
                    <input type="hidden" name="id" value="<?php echo $edit_department['id']; ?>">
                    
                    <div class="form-group">
                        <label for="name_edit">部门名称</label>
                        <input type="text" id="name_edit" name="name" value="<?php echo $edit_department['name'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description_edit">部门描述</label>
                        <textarea id="description_edit" name="description" rows="4" style="width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 14px; transition: var(--transition); background-color: var(--bg-white); resize: vertical;"><?php echo $edit_department['description'] ?? ''; ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_department" class="btn btn-primary">
                            更新部门
                        </button>
                        <a href="departments.php" class="btn btn-secondary">取消</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- 部门列表 -->
            <div class="activity-section">
                <h3>部门列表</h3>
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>部门名称</th>
                            <th>部门描述</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($departments)): ?>
                            <?php foreach ($departments as $department): ?>
                                <tr>
                                    <td><?php echo $department['id']; ?></td>
                                    <td><?php echo $department['name']; ?></td>
                                    <td><?php echo $department['description']; ?></td>
                                    <td><?php echo $department['created_at']; ?></td>
                                    <td>
                                        <a href="departments.php?edit=<?php echo $department['id']; ?>" class="btn btn-sm btn-primary">编辑</a>
                                        <a href="departments.php?delete=<?php echo $department['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('确定要删除此部门吗？');">删除</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="no-data">暂无部门</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
        </div>
    </div>
</body>
</html>