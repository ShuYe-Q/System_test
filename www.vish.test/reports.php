<?php
// 统计报表页面
require_once 'config/db.php';

session_start();

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 检查用户权限
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager') {
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

// 获取资产状态统计
$status_stats_sql = "SELECT status, COUNT(*) as count FROM assets GROUP BY status";
$status_stats = execute_query($status_stats_sql);

// 获取全部资产列表（用于弹窗展示明细）
$assets_sql = "SELECT a.*, d.name as department_name, u.name as user_name 
               FROM assets a 
               LEFT JOIN departments d ON a.department_id = d.id 
               LEFT JOIN users u ON a.user_id = u.id 
               ORDER BY a.id DESC";
$all_assets = execute_query($assets_sql);

// 获取资产类别统计
$category_stats_sql = "SELECT category, COUNT(*) as count FROM assets GROUP BY category";
$category_stats = execute_query($category_stats_sql);

// 获取部门资产统计
$department_stats_sql = "SELECT d.name as department_name, COUNT(*) as count FROM assets a 
                       LEFT JOIN departments d ON a.department_id = d.id 
                       GROUP BY a.department_id";
$department_stats = execute_query($department_stats_sql);

// 获取资产价值统计
$value_stats_sql = "SELECT SUM(price) as total_value FROM assets";
$value_stats_result = execute_query($value_stats_sql);
$total_value = $value_stats_result[0]['total_value'] ?? 0;

// 获取维护费用统计
$maintenance_cost_sql = "SELECT SUM(cost) as total_cost FROM maintenance_records";
$maintenance_cost_result = execute_query($maintenance_cost_sql);
$total_maintenance_cost = $maintenance_cost_result[0]['total_cost'] ?? 0;

// 获取最近的系统日志（仅管理员可见）
$logs = [];
if ($role === 'admin') {
    $logs_sql = "SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 50";
    $logs = execute_query($logs_sql);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>企业资源管理系统 - 统计报表</title>
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
                        <li><a href="reports.php" class="active">统计报表</a></li>
                        <li><a href="users.php">用户管理</a></li>
                    <?php endif; ?>
                    <?php if ($role === 'admin'): ?>
                        <li><a href="departments.php">部门管理</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <!-- 主内容区 -->
            <main class="main-content">
            <h2>统计报表</h2>

            <!-- 总览统计卡片 -->
            <div class="stats-cards">
                <div class="stat-card">
                    <h3>总资产价值</h3>
                    <p class="stat-value">¥<?php echo number_format($total_value, 2); ?></p>
                </div>
                <div class="stat-card">
                    <h3>总维护费用</h3>
                    <p class="stat-value">¥<?php echo number_format($total_maintenance_cost, 2); ?></p>
                </div>
            </div>

            <!-- 资产状态统计 -->
            <div class="activity-section">
                <h3>资产状态统计</h3>
                <div class="chart-container">
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>状态</th>
                                <th>数量</th>
                                <th>占比</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($status_stats)): ?>
                                <?php 
                                    // 计算总资产数
                                    $total_assets = 0;
                                    foreach ($status_stats as $stat) {
                                        $total_assets += $stat['count'];
                                    }
                                ?>
                                <?php foreach ($status_stats as $stat): ?>
                                    <tr class="status-row" data-status="<?php echo htmlspecialchars($stat['status']); ?>">
                                        <td style="cursor:pointer; color: var(--primary-color);">
                                            <?php 
                                                switch ($stat['status']) {
                                                    case 'in_store': echo '库存'; break;
                                                    case 'in_use': echo '在用'; break;
                                                    case 'maintenance': echo '维修中'; break;
                                                    case 'scrapped': echo '已报废'; break;
                                                    default: echo $stat['status'];
                                                }
                                            ?>
                                        </td>
                                        <td><?php echo $stat['count']; ?></td>
                                        <td><?php echo $total_assets > 0 ? round(($stat['count'] / $total_assets) * 100, 2) . '%' : '0%'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="no-data">暂无数据</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 资产类别统计 -->
            <div class="activity-section">
                <h3>资产类别统计</h3>
                <div class="chart-container">
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>类别</th>
                                <th>数量</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($category_stats)): ?>
                                <?php foreach ($category_stats as $stat): ?>
                                    <tr>
                                        <td><?php echo $stat['category']; ?></td>
                                        <td><?php echo $stat['count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="no-data">暂无数据</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 部门资产统计 -->
            <div class="activity-section">
                <h3>部门资产统计</h3>
                <div class="chart-container">
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>部门</th>
                                <th>资产数量</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($department_stats)): ?>
                                <?php foreach ($department_stats as $stat): ?>
                                    <tr>
                                        <td><?php echo $stat['department_name'] ?? '未分配'; ?></td>
                                        <td><?php echo $stat['count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="no-data">暂无数据</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 系统日志（仅管理员可见） -->
            <?php if ($role === 'admin'): ?>
            <div class="activity-section">
                <h3>系统日志</h3>
                <div class="chart-container">
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>用户ID</th>
                                <th>操作</th>
                                <th>描述</th>
                                <th>IP地址</th>
                                <th>时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($logs)): ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo $log['id']; ?></td>
                                        <td><?php echo $log['user_id']; ?></td>
                                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                                        <td><?php echo htmlspecialchars($log['description']); ?></td>
                                        <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                        <td><?php echo $log['created_at']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="no-data">暂无日志</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </main>
        </div>
    </div>

    <!-- 资产状态明细弹窗 -->
    <div id="status-assets-modal" class="modal">
        <div class="modal-content">
            <div class="modal-card">
                <div class="modal-header">
                    <h3 id="status-modal-title">资产明细</h3>
                    <button type="button" class="modal-close close">&times;</button>
                </div>
                <div class="modal-body">
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>资产名称</th>
                                <th>类别</th>
                                <th>型号</th>
                                <th>状态</th>
                                <th>存放位置</th>
                                <th>所属部门</th>
                                <th>使用人</th>
                            </tr>
                        </thead>
                        <tbody id="status-assets-tbody">
                            <?php if (!empty($all_assets)): ?>
                                <?php foreach ($all_assets as $asset): ?>
                                    <tr data-status="<?php echo htmlspecialchars($asset['status']); ?>">
                                        <td><?php echo $asset['id']; ?></td>
                                        <td><?php echo htmlspecialchars($asset['name']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['category']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['model']); ?></td>
                                        <td>
                                            <?php 
                                                switch ($asset['status']) {
                                                    case 'in_store': echo '库存'; break;
                                                    case 'in_use': echo '在用'; break;
                                                    case 'maintenance': echo '维修中'; break;
                                                    case 'scrapped': echo '已报废'; break;
                                                    default: echo htmlspecialchars($asset['status']);
                                                }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($asset['location']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['department_name']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['user_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="no-data">暂无资产</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const rows = document.querySelectorAll('.status-row');
            const modal = document.getElementById('status-assets-modal');
            const titleEl = document.getElementById('status-modal-title');
            const tbody = document.getElementById('status-assets-tbody');

            const statusTextMap = {
                'in_store': '库存资产明细',
                'in_use': '在用资产明细',
                'maintenance': '维修中资产明细',
                'scrapped': '已报废资产明细'
            };

            function openStatusModal(status) {
                if (!modal) return;
                const rows = tbody ? tbody.querySelectorAll('tr[data-status]') : [];
                rows.forEach(function (tr) {
                    if (!status) {
                        tr.style.display = '';
                    } else {
                        tr.style.display = (tr.getAttribute('data-status') === status) ? '' : 'none';
                    }
                });

                if (titleEl) {
                    titleEl.textContent = statusTextMap[status] || '资产明细';
                }

                modal.style.display = 'flex';
                modal.style.opacity = '0';
                modal.style.transition = 'opacity 0.3s ease';
                setTimeout(function () {
                    modal.style.opacity = '1';
                }, 10);
            }

            if (rows) {
                rows.forEach(function (row) {
                    row.addEventListener('click', function () {
                        var status = this.getAttribute('data-status');
                        openStatusModal(status);
                    });
                });
            }

            if (modal) {
                var closeBtn = modal.querySelector('.close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function () {
                        modal.style.opacity = '0';
                        setTimeout(function () {
                            modal.style.display = 'none';
                        }, 300);
                    });
                }
                window.addEventListener('click', function (e) {
                    if (e.target === modal) {
                        modal.style.opacity = '0';
                        setTimeout(function () {
                            modal.style.display = 'none';
                        }, 300);
                    }
                });
            }
        });
    </script>
</body>
</html>