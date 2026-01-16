<?php
// 盘点管理页面
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

// 获取盘点记录列表
$inventory_sql = "SELECT ir.*, a.name as asset_name, u.name as inventory_user_name FROM inventory_records ir 
                 JOIN assets a ON ir.asset_id = a.id 
                 JOIN users u ON ir.inventory_user_id = u.id 
                 ORDER BY ir.inventory_date DESC";
$inventory_records = execute_query($inventory_sql);

// 处理盘点记录添加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_inventory'])) {
    $asset_id = $_POST['asset_id'];
    $inventory_date = $_POST['inventory_date'];
    $status = $_POST['status'];
    $notes = $_POST['notes'];
    
    $insert_sql = "INSERT INTO inventory_records (asset_id, inventory_date, status, inventory_user_id, notes) 
                  VALUES (?, ?, ?, ?, ?)";
    $result = execute_query($insert_sql, [$asset_id, $inventory_date, $status, $user_id, $notes]);
    
    if (isset($result['insert_id'])) {
        // 记录操作日志
        $asset_name_sql = "SELECT name FROM assets WHERE id = ?";
        $asset_name_result = execute_query($asset_name_sql, [$asset_id]);
        $asset_name = $asset_name_result[0]['name'] ?? '';
        
        $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'add_inventory', '添加盘点记录：$status - $asset_name', ?)";
        execute_query($log_sql, [$user_id, $_SERVER['REMOTE_ADDR']]);
        
        // 如果盘点状态为损坏或丢失，更新资产状态
        if ($status === 'damaged') {
            $update_asset_sql = "UPDATE assets SET status = 'maintenance' WHERE id = ?";
            execute_query($update_asset_sql, [$asset_id]);
        } elseif ($status === 'missing') {
            $update_asset_sql = "UPDATE assets SET status = 'scrapped' WHERE id = ?";
            execute_query($update_asset_sql, [$asset_id]);
        }
        
        header('Location: inventory.php?success=盘点记录添加成功');
        exit;
    } else {
        $error = "盘点记录添加失败：" . ($result['error'] ?? '未知错误');
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>企业资源管理系统 - 盘点管理</title>
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
                    <li><a href="inventory.php" class="active">盘点管理</a></li>
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
            <h2>盘点管理</h2>
            
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
                <button class="btn btn-primary" data-modal="inventory-modal">添加盘点记录</button>
            </div>

            <!-- 盘点记录添加弹窗 -->
            <div id="inventory-modal" class="modal">
                <div class="modal-content">
                    <div class="modal-card">
                        <div class="modal-header">
                            <h3>添加盘点记录</h3>
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
                                        <label for="inventory_date">盘点日期</label>
                                        <input type="date" id="inventory_date" name="inventory_date" required>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="status">盘点状态</label>
                                        <select id="status" name="status" required>
                                            <option value="normal">正常</option>
                                            <option value="missing">丢失</option>
                                            <option value="damaged">损坏</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="notes">备注</label>
                                    <textarea id="notes" name="notes" rows="4" style="width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 14px; transition: var(--transition); background-color: var(--bg-white); resize: vertical;"></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" name="add_inventory" class="btn btn-primary">添加盘点记录</button>
                                    <button type="button" class="btn btn-secondary close">取消</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 盘点记录列表 -->
            <div class="activity-section">
                <h3>盘点记录列表</h3>
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>资产名称</th>
                            <th>盘点日期</th>
                            <th>盘点状态</th>
                            <th>盘点人员</th>
                            <th>备注</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($inventory_records)): ?>
                            <?php foreach ($inventory_records as $record): ?>
                                <tr>
                                    <td><?php echo $record['id']; ?></td>
                                    <td><?php echo $record['asset_name']; ?></td>
                                    <td><?php echo $record['inventory_date']; ?></td>
                                    <td>
                                        <span class="status <?php echo $record['status']; ?>">
                                            <?php 
                                                switch ($record['status']) {
                                                    case 'normal': echo '正常'; break;
                                                    case 'missing': echo '丢失'; break;
                                                    case 'damaged': echo '损坏'; break;
                                                    default: echo '未知';
                                                }
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo $record['inventory_user_name']; ?></td>
                                    <td><?php echo $record['notes']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="no-data">暂无盘点记录</td>
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