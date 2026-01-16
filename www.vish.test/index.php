<?php
// 系统首页
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

// 获取资产统计信息
$asset_stats = [];

// 总资产数
$total_assets_sql = "SELECT COUNT(*) as count FROM assets";
$total_assets_result = execute_query($total_assets_sql);
$asset_stats['total'] = $total_assets_result[0]['count'] ?? 0;

// 在用资产数
$in_use_assets_sql = "SELECT COUNT(*) as count FROM assets WHERE status = 'in_use'";
$in_use_assets_result = execute_query($in_use_assets_sql);
$asset_stats['in_use'] = $in_use_assets_result[0]['count'] ?? 0;

// 待审批流程数
$pending_approvals_sql = "SELECT COUNT(*) as count FROM approval_processes WHERE status = 'pending'";
$pending_approvals_result = execute_query($pending_approvals_sql);
$asset_stats['pending_approvals'] = $pending_approvals_result[0]['count'] ?? 0;

// 最近的审批流程
$recent_approvals_sql = "SELECT ap.*, a.name as asset_name, u.name as requester_name FROM approval_processes ap 
                        JOIN assets a ON ap.asset_id = a.id 
                        JOIN users u ON ap.requester_id = u.id 
                        ORDER BY ap.created_at DESC LIMIT 5";
$recent_approvals = execute_query($recent_approvals_sql);

// 最近的维护记录
$recent_maintenance_sql = "SELECT mr.*, a.name as asset_name, u.name as maintainer_name FROM maintenance_records mr 
                          JOIN assets a ON mr.asset_id = a.id 
                          LEFT JOIN users u ON mr.maintainer_id = u.id 
                          ORDER BY mr.maintenance_date DESC LIMIT 5";
$recent_maintenance = execute_query($recent_maintenance_sql);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>企业资源管理系统 - 首页</title>
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
                    <li><a href="index.php" class="active">首页</a></li>
                    <li><a href="assets.php">资产管理</a></li>
                    <li><a href="approval.php">审批流程</a></li>
                    <li><a href="maintenance.php">维护管理</a></li>
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
            <!-- 统计卡片 -->
            <div class="stats-cards">
                <div class="stat-card">
                    <h3>总资产数</h3>
                    <p class="stat-value"><?php echo $asset_stats['total']; ?></p>
                    <div class="stat-change">+0.0%</div>
                </div>
                <div class="stat-card">
                    <h3>在用资产数</h3>
                    <p class="stat-value"><?php echo $asset_stats['in_use']; ?></p>
                    <div class="stat-change">+0.0%</div>
                </div>
                <div class="stat-card">
                    <h3>待审批流程</h3>
                    <p class="stat-value"><?php echo $asset_stats['pending_approvals']; ?></p>
                    <div class="stat-change">+0.0%</div>
                </div>
                <div class="stat-card">
                    <h3>资产利用率</h3>
                    <p class="stat-value">
                        <?php 
                            $utilization = $asset_stats['total'] > 0 ? round(($asset_stats['in_use'] / $asset_stats['total']) * 100, 2) : 0;
                            echo $utilization . '%';
                        ?>
                    </p>
                    <div class="stat-change">+0.0%</div>
                </div>
            </div>

            <!-- 最近动态 -->
            <div class="recent-activities">
                <div class="activity-section">
                    <h2>最近审批流程</h2>
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>流程ID</th>
                                <th>资产名称</th>
                                <th>流程类型</th>
                                <th>申请人</th>
                                <th>状态</th>
                                <th>提交时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_approvals)): ?>
                                <?php foreach ($recent_approvals as $approval): ?>
                                    <tr>
                                        <td><?php echo $approval['id']; ?></td>
                                        <td><?php echo $approval['asset_name']; ?></td>
                                        <td>
                                            <?php 
                                                switch ($approval['type']) {
                                                    case 'transfer': echo '调拨'; break;
                                                    case 'scrap': echo '报废'; break;
                                                    case 'maintenance': echo '维修'; break;
                                                    case 'purchase': echo '采购'; break;
                                                    default: echo '未知';
                                                }
                                            ?>
                                        </td>
                                        <td><?php echo $approval['requester_name']; ?></td>
                                        <td>
                                            <span class="status <?php echo $approval['status']; ?>">
                                                <?php 
                                                    switch ($approval['status']) {
                                                        case 'pending': echo '待审批'; break;
                                                        case 'approved': echo '已批准'; break;
                                                        case 'rejected': echo '已拒绝'; break;
                                                        default: echo '未知';
                                                    }
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo $approval['created_at']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="no-data">暂无审批流程</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="activity-section">
                    <h2>最近维护记录</h2>
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>记录ID</th>
                                <th>资产名称</th>
                                <th>维护类型</th>
                                <th>维护日期</th>
                                <th>维护人员</th>
                                <th>维护费用</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_maintenance)): ?>
                                <?php foreach ($recent_maintenance as $maintenance): ?>
                                    <tr>
                                        <td><?php echo $maintenance['id']; ?></td>
                                        <td><?php echo $maintenance['asset_name']; ?></td>
                                        <td><?php echo $maintenance['maintenance_type']; ?></td>
                                        <td><?php echo $maintenance['maintenance_date']; ?></td>
                                        <td><?php echo $maintenance['maintainer_name'] ?? '未知'; ?></td>
                                        <td>¥<?php echo $maintenance['cost']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="no-data">暂无维护记录</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
        </div>
    </div>
</body>
</html>