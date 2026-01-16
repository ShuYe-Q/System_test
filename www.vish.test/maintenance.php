<?php
// 维护管理页面
require_once 'config/db.php';

session_start();

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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

// 获取所有资产
$assets_sql = "SELECT * FROM assets";
$assets = execute_query($assets_sql);

// 获取所有用户
$users_sql = "SELECT * FROM users";
$users = execute_query($users_sql);

// 获取维护记录列表
$maintenance_sql = "SELECT mr.*, a.name as asset_name, u.name as maintainer_name FROM maintenance_records mr 
                   JOIN assets a ON mr.asset_id = a.id 
                   LEFT JOIN users u ON mr.maintainer_id = u.id 
                   ORDER BY mr.maintenance_date DESC";
$maintenance_records = execute_query($maintenance_sql);

// 处理维护记录添加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_maintenance'])) {
    $asset_id = $_POST['asset_id'];
    $maintenance_type = $_POST['maintenance_type'];
    $description = $_POST['description'];
    $cost = $_POST['cost'];
    $maintenance_date = $_POST['maintenance_date'];
    $maintainer_id = $_POST['maintainer_id'];
    
    $insert_sql = "INSERT INTO maintenance_records (asset_id, maintenance_type, description, cost, maintenance_date, maintainer_id) 
                  VALUES (?, ?, ?, ?, ?, ?)";
    $result = execute_query($insert_sql, [$asset_id, $maintenance_type, $description, $cost, $maintenance_date, $maintainer_id]);
    
    if (isset($result['insert_id'])) {
        // 记录操作日志
        $asset_name_sql = "SELECT name FROM assets WHERE id = ?";
        $asset_name_result = execute_query($asset_name_sql, [$asset_id]);
        $asset_name = $asset_name_result[0]['name'] ?? '';
        
        $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'add_maintenance', '添加维护记录：$maintenance_type - $asset_name', ?)";
        execute_query($log_sql, [$user_id, $_SERVER['REMOTE_ADDR']]);
        
        // 更新资产状态为维修中
        $update_asset_sql = "UPDATE assets SET status = 'maintenance' WHERE id = ?";
        execute_query($update_asset_sql, [$asset_id]);
        
        header('Location: maintenance.php?success=维护记录添加成功');
        exit;
    } else {
        $error = "维护记录添加失败：" . ($result['error'] ?? '未知错误');
    }
}

// 处理维护记录完成
if (isset($_GET['complete'])) {
    $id = $_GET['complete'];
    
    // 获取维护记录信息
    $maintenance_sql = "SELECT * FROM maintenance_records WHERE id = ?";
    $maintenance_result = execute_query($maintenance_sql, [$id]);
    $maintenance = $maintenance_result[0] ?? null;
    
    if ($maintenance) {
        // 更新资产状态为在用
        $update_asset_sql = "UPDATE assets SET status = 'in_use' WHERE id = ?";
        execute_query($update_asset_sql, [$maintenance['asset_id']]);
        
        // 记录操作日志
        $asset_name_sql = "SELECT name FROM assets WHERE id = ?";
        $asset_name_result = execute_query($asset_name_sql, [$maintenance['asset_id']]);
        $asset_name = $asset_name_result[0]['name'] ?? '';
        
        $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'complete_maintenance', '完成维护：$asset_name', ?)";
        execute_query($log_sql, [$user_id, $_SERVER['REMOTE_ADDR']]);
        
        header('Location: maintenance.php?success=维护完成，资产状态已更新');
        exit;
    } else {
        $error = "维护记录不存在";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>企业资源管理系统 - 维护管理</title>
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
                    <li><a href="maintenance.php" class="active">维护管理</a></li>
                    <li><a href="inventory.php">盘点管理</a></li>
                    <?php if ($role === 'admin' || $role === 'manager'): ?>
                        <li><a href="reports.php">统计报表</a></li>
                        <li><a href="users.php">用户管理</a></li>
                    <?php endif; ?>
                    <?php if ($role === 'admin'): ?>
                        <li><a href="departments.php">部门管理</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <!-- 主内容区 -->
            <main class="main-content">
            <h2>维护管理</h2>
            
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
                <button class="btn btn-primary" data-modal="maintenance-modal">添加维护记录</button>
            </div>

            <!-- 维护记录添加弹窗 -->
            <div id="maintenance-modal" class="modal">
                <div class="modal-content">
                    <div class="modal-card">
                        <div class="modal-header">
                            <h3>添加维护记录</h3>
                            <button type="button" class="modal-close close">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form method="post">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="asset_id">资产名称</label>
                                        <select id="asset_id" name="asset_id" required>
                                            <option value="">请选择资产</option>
                                            <?php foreach ($assets as $asset): ?>
                                                <option value="<?php echo $asset['id']; ?>">
                                                    <?php echo $asset['name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="maintenance_type">维护类型</label>
                                        <input type="text" id="maintenance_type" name="maintenance_type" required>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="cost">维护费用</label>
                                        <input type="number" id="cost" name="cost" step="0.01" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="maintenance_date">维护日期</label>
                                        <input type="date" id="maintenance_date" name="maintenance_date" required>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="maintainer_id">维护人员</label>
                                        <select id="maintainer_id" name="maintainer_id" required>
                                            <option value="">请选择维护人员</option>
                                            <?php foreach ($users as $user): ?>
                                                <option value="<?php echo $user['id']; ?>">
                                                    <?php echo $user['name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description">维护描述</label>
                                    <textarea id="description" name="description" rows="4" required style="width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 14px; transition: var(--transition); background-color: var(--bg-white); resize: vertical;"></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" name="add_maintenance" class="btn btn-primary">添加维护记录</button>
                                    <button type="button" class="btn btn-secondary close">取消</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 维护记录列表 -->
            <div class="activity-section">
                <h3>维护记录列表</h3>
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>资产名称</th>
                            <th>维护类型</th>
                            <th>维护描述</th>
                            <th>维护费用</th>
                            <th>维护日期</th>
                            <th>维护人员</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($maintenance_records)): ?>
                            <?php foreach ($maintenance_records as $record): ?>
                                <tr>
                                    <td><?php echo $record['id']; ?></td>
                                    <td><?php echo $record['asset_name']; ?></td>
                                    <td><?php echo $record['maintenance_type']; ?></td>
                                    <td><?php echo $record['description']; ?></td>
                                    <td>¥<?php echo $record['cost']; ?></td>
                                    <td><?php echo $record['maintenance_date']; ?></td>
                                    <td><?php echo $record['maintainer_name'] ?? '未知'; ?></td>
                                    <td>
                                        <?php 
                                            // 检查对应资产是否处于维修状态
                                            $asset_status_sql = "SELECT status FROM assets WHERE id = ?";
                                            $asset_status_result = execute_query($asset_status_sql, [$record['asset_id']]);
                                            $asset_status = $asset_status_result[0]['status'] ?? '';
                                            
                                            if ($asset_status === 'maintenance'):
                                        ?>
                                            <a href="maintenance.php?complete=<?php echo $record['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('确定要标记此维护为完成吗？');">标记完成</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="no-data">暂无维护记录</td>
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