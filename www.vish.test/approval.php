<?php
// 审批流程页面
require_once 'config/db.php';

session_start();

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 生成CSRF令牌
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
$department_name = htmlspecialchars($dept_result[0]['name'] ?? '未知部门');

// 初始化变量
$error = '';
$success = '';

// 从session获取错误和成功消息（用于POST提交后的显示）
if (isset($_SESSION['approval_error'])) {
    $error = $_SESSION['approval_error'];
    unset($_SESSION['approval_error']);
}
if (isset($_SESSION['approval_success'])) {
    $success = $_SESSION['approval_success'];
    unset($_SESSION['approval_success']);
}

// 获取当前用户可以查看的资产（只能看到本部门的资产，除非是管理员）
$assets_sql = "SELECT id, name FROM assets WHERE status != 'scrapped'";
$assets_params = [];

if ($role === 'admin') {
    // 管理员可以看到所有资产
    // 不需要添加额外的WHERE条件
} elseif ($role === 'manager') {
    $assets_sql .= " AND department_id = ?";
    $assets_params = [$department_id];
} elseif ($role === 'employee') {
    // 员工可以看到本部门的资产
    $assets_sql .= " AND department_id = ?";
    $assets_params = [$department_id];
}

$assets = execute_query($assets_sql, $assets_params);

// 获取审批流程列表（权限控制）
// 使用 LEFT JOIN 确保即使关联表中没有对应记录也能显示审批流程
$approvals_sql = "SELECT ap.*, a.name as asset_name, u.name as requester_name, 
                  u2.name as approver_name, d.name as department_name 
                  FROM approval_processes ap 
                  LEFT JOIN assets a ON ap.asset_id = a.id 
                  LEFT JOIN users u ON ap.requester_id = u.id 
                  LEFT JOIN users u2 ON ap.approver_id = u2.id
                  LEFT JOIN departments d ON a.department_id = d.id
                  WHERE 1=1";

$approvals_params = [];

// 权限控制：员工只能看到自己申请的，经理看到本部门的或自己申请的，管理员看到所有
if ($role === 'employee') {
    $approvals_sql .= " AND ap.requester_id = ?";
    $approvals_params = [$user_id];
} elseif ($role === 'manager') {
    // 经理可以看到：1. 自己提交的审批 2. 本部门资产的审批
    $approvals_sql .= " AND (ap.requester_id = ? OR a.department_id = ?)";
    $approvals_params = [$user_id, $department_id];
}

$approvals_sql .= " ORDER BY ap.created_at DESC";
$approvals = execute_query($approvals_sql, $approvals_params);

// 处理审批流程添加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_approval'])) {
    // 验证CSRF令牌
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['approval_error'] = "安全令牌验证失败";
        header('Location: approval.php');
        exit;
    } else {
        $asset_id = (int)$_POST['asset_id'];
        $type = $_POST['type'];
        $reason = trim($_POST['reason']);
        
        // 验证输入
        if ($asset_id <= 0) {
            $_SESSION['approval_error'] = "请选择有效的资产";
            header('Location: approval.php');
            exit;
        } elseif (!in_array($type, ['transfer', 'scrap', 'maintenance', 'purchase'])) {
            $_SESSION['approval_error'] = "无效的审批类型";
            header('Location: approval.php');
            exit;
        } elseif (empty($reason) || strlen($reason) > 500) {
            $_SESSION['approval_error'] = "申请原因不能为空且不能超过500个字符";
            header('Location: approval.php');
            exit;
        } else {
            // 检查资产是否存在
            $check_asset_sql = "SELECT id, department_id FROM assets WHERE id = ? AND status != 'scrapped'";
            $asset_check = execute_query($check_asset_sql, [$asset_id]);
            
            if (empty($asset_check)) {
                $_SESSION['approval_error'] = "资产不存在或不可用";
                header('Location: approval.php');
                exit;
            } elseif ($role === 'manager') {
                // 经理只能申请本部门的资产
                if ($asset_check[0]['department_id'] != $department_id) {
                    $_SESSION['approval_error'] = "您只能申请本部门的资产";
                    header('Location: approval.php');
                    exit;
                }
            }
            
            if (empty($error)) {
                $insert_sql = "INSERT INTO approval_processes (asset_id, type, status, requester_id, reason) 
                              VALUES (?, ?, 'pending', ?, ?)";
                $result = execute_query($insert_sql, [$asset_id, $type, $user_id, $reason]);
                
                // 检查插入是否成功
                if (isset($result['error'])) {
                    $_SESSION['approval_error'] = "审批流程提交失败：" . $result['error'];
                    header('Location: approval.php');
                    exit;
                } elseif (isset($result['insert_id']) && $result['insert_id'] > 0) {
                    // 记录操作日志（即使日志记录失败也不影响主流程）
                    try {
                        $asset_name_sql = "SELECT name FROM assets WHERE id = ?";
                        $asset_name_result = execute_query($asset_name_sql, [$asset_id]);
                        $asset_name = htmlspecialchars($asset_name_result[0]['name'] ?? '');
                        
                        $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'add_approval', ?, ?)";
                        $log_desc = "提交审批流程：" . $type . " - " . $asset_name;
                        execute_query($log_sql, [$user_id, $log_desc, $_SERVER['REMOTE_ADDR'] ?? '']);
                    } catch (Exception $e) {
                        // 日志记录失败不影响主流程，继续执行
                    }
                    
                    $_SESSION['approval_success'] = "审批流程提交成功";
                    // 刷新页面避免重复提交
                    header('Location: approval.php');
                    exit;
                } else {
                    $error_msg = "审批流程提交失败：数据库插入失败";
                    if (isset($result['insert_id']) && $result['insert_id'] == 0) {
                        $error_msg .= "（插入ID为0，可能是数据库约束问题）";
                    }
                    $_SESSION['approval_error'] = $error_msg;
                    header('Location: approval.php');
                    exit;
                }
            }
        }
    }
}

// 处理审批流程审批
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // 验证CSRF令牌
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "安全令牌验证失败";
    } elseif ($role !== 'admin' && $role !== 'manager') {
        $error = "您没有审批权限";
    } else {
        $id = (int)$_POST['id'];
        $action = $_POST['action']; // 'approve' 或 'reject'
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        
        // 检查审批流程是否存在且待审批
        $check_sql = "SELECT ap.*, a.name as asset_name, a.department_id 
                     FROM approval_processes ap 
                     JOIN assets a ON ap.asset_id = a.id 
                     WHERE ap.id = ? AND ap.status = 'pending'";
        $check_result = execute_query($check_sql, [$id]);
        
        if (empty($check_result)) {
            $error = "审批流程不存在或已被处理";
        } elseif ($role === 'manager') {
            // 经理只能审批本部门的申请
            if ($check_result[0]['department_id'] != $department_id) {
                $error = "您只能审批本部门的申请";
            }
        } else {
            // 检查审批流程是否存在（再次确认）
            if (empty($check_result)) {
                $error = "审批流程不存在或已被处理";
            } else {
                $approval = $check_result[0];
                
                // 检查调拨审批的目标部门
                if ($approval['type'] === 'transfer' && $status === 'approved') {
                    if (!isset($_POST['target_department']) || (int)$_POST['target_department'] <= 0) {
                        $error = "调拨审批必须选择目标部门";
                    } else {
                        $target_dept = (int)$_POST['target_department'];
                        // 检查目标部门是否存在
                        $dept_check_sql = "SELECT id FROM departments WHERE id = ?";
                        $dept_check_result = execute_query($dept_check_sql, [$target_dept]);
                        if (empty($dept_check_result)) {
                            $error = "目标部门不存在";
                        }
                    }
                }
                
                if (empty($error)) {
                    $update_sql = "UPDATE approval_processes SET status = ?, approver_id = ?, approved_at = NOW() WHERE id = ?";
                    $result = execute_query($update_sql, [$status, $user_id, $id]);
                    
                    if (isset($result['affected_rows']) && $result['affected_rows'] > 0) {
                        // 记录操作日志
                        $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'approve_approval', ?, ?)";
                        $log_desc = "审批流程：" . $approval['type'] . " - " . htmlspecialchars($approval['asset_name']) . " - " . ($status === 'approved' ? '批准' : '拒绝');
                        execute_query($log_sql, [$user_id, $log_desc, $_SERVER['REMOTE_ADDR']]);
                        
                        // 如果是报废审批且批准，更新资产状态
                        if ($approval['type'] === 'scrap' && $status === 'approved') {
                            $update_asset_sql = "UPDATE assets SET status = 'scrapped' WHERE id = ?";
                            execute_query($update_asset_sql, [$approval['asset_id']]);
                        }
                        
                        // 如果是调拨审批且批准，更新资产部门
                        if ($approval['type'] === 'transfer' && $status === 'approved' && isset($_POST['target_department'])) {
                            $target_dept = (int)$_POST['target_department'];
                            if ($target_dept > 0) {
                                $update_transfer_sql = "UPDATE assets SET department_id = ? WHERE id = ?";
                                execute_query($update_transfer_sql, [$target_dept, $approval['asset_id']]);
                            }
                        }
                        
                        $success = "审批流程处理成功";
                        header('Location: approval.php?success=' . urlencode($success));
                        exit;
                    } else {
                        $error = "审批流程处理失败";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>企业资源管理系统 - 审批流程</title>
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
                    <li><a href="approval.php" class="active">审批流程</a></li>
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
            <h2>审批流程</h2>
            
            <?php if (isset($_GET['success']) || $success): ?>
                <div class="error-message" style="background-color: rgba(0, 180, 42, 0.08); color: #00B42A; border: 1px solid rgba(0, 180, 42, 0.2); padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 18px;">✓</span>
                    <span><?php echo htmlspecialchars($success ?: $_GET['success']); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error-message" style="background-color: rgba(245, 63, 63, 0.08); color: #F53F3F; border: 1px solid rgba(245, 63, 63, 0.2); padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 18px;">✗</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <!-- 顶部操作按钮 -->
            <div style="margin-bottom: 16px; display: flex; justify-content: flex-end;">
                <?php if (empty($assets)): ?>
                    <button class="btn btn-primary" disabled title="您没有可申请的资产">提交审批流程</button>
                    <div style="margin-left: 10px; color: var(--text-secondary); font-size: 14px; line-height: 36px;">
                        提示：您当前没有可申请的资产
                    </div>
                <?php else: ?>
                    <button class="btn btn-primary" data-modal="approval-modal">提交审批流程</button>
                <?php endif; ?>
            </div>

            <!-- 审批流程添加弹窗 -->
            <div id="approval-modal" class="modal">
                <div class="modal-content">
                    <div class="modal-card">
                        <div class="modal-header">
                            <h3>提交审批流程</h3>
                            <button type="button" class="modal-close close">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form method="post" id="approval-form" onsubmit="return validateApprovalForm();">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="asset_id">资产名称</label>
                                        <select id="asset_id" name="asset_id" required>
                                            <option value="">请选择资产</option>
                                            <?php if (!empty($assets)): ?>
                                                <?php foreach ($assets as $asset): ?>
                                                    <option value="<?php echo (int)$asset['id']; ?>">
                                                        <?php echo htmlspecialchars($asset['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <option value="" disabled>暂无可用资产</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="type">流程类型</label>
                                        <select id="type" name="type" required>
                                            <option value="">请选择类型</option>
                                            <option value="transfer">调拨</option>
                                            <option value="scrap">报废</option>
                                            <option value="maintenance">维修</option>
                                            <option value="purchase">采购</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="reason">申请原因</label>
                                    <textarea id="reason" name="reason" rows="4" required maxlength="500" placeholder="请详细说明申请原因，不超过500个字符" style="width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 14px; transition: var(--transition); background-color: var(--bg-white); resize: vertical;"></textarea>
                                    <div class="char-count" style="text-align: right; font-size: 12px; color: var(--text-tertiary); margin-top: 5px;">
                                        剩余字数: <span id="char-remaining">500</span>
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" name="add_approval" class="btn btn-primary">提交审批</button>
                                    <button type="button" class="btn btn-secondary close">取消</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 审批流程列表 -->
            <div class="activity-section">
                <h3>审批流程列表</h3>
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>资产名称</th>
                            <th>流程类型</th>
                            <th>申请人</th>
                            <th>部门</th>
                            <th>审批人</th>
                            <th>申请原因</th>
                            <th>状态</th>
                            <th>提交时间</th>
                            <th>审批时间</th>
                            <?php if ($role === 'admin' || $role === 'manager'): ?>
                                <th>操作</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($approvals)): ?>
                            <?php foreach ($approvals as $approval): ?>
                                <tr>
                                    <td><?php echo (int)$approval['id']; ?></td>
                                    <td><?php echo htmlspecialchars($approval['asset_name'] ?? '未知资产'); ?></td>
                                    <td>
                                        <?php 
                                            $type_names = [
                                                'transfer' => '调拨',
                                                'scrap' => '报废',
                                                'maintenance' => '维修',
                                                'purchase' => '采购'
                                            ];
                                            echo $type_names[$approval['type']] ?? '未知';
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($approval['requester_name'] ?? '未知申请人'); ?></td>
                                    <td><?php echo htmlspecialchars($approval['department_name'] ?? '未知'); ?></td>
                                    <td><?php echo htmlspecialchars($approval['approver_name'] ?? '未审批'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($approval['reason'], 0, 50)) . (strlen($approval['reason']) > 50 ? '...' : ''); ?></td>
                                    <td>
                                        <span class="status <?php echo htmlspecialchars($approval['status']); ?>">
                                            <?php 
                                                $status_names = [
                                                    'pending' => '待审批',
                                                    'approved' => '已批准',
                                                    'rejected' => '已拒绝'
                                                ];
                                                echo $status_names[$approval['status']] ?? '未知';
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo $approval['created_at']; ?></td>
                                    <td><?php echo $approval['approved_at'] ?? '未审批'; ?></td>
                                    <?php if ($role === 'admin' || $role === 'manager'): ?>
                                        <td>
                                            <?php if ($approval['status'] === 'pending'): ?>
                                                <form method="post" class="approval-form" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="id" value="<?php echo (int)$approval['id']; ?>">
                                                    
                                                    <?php if ($approval['type'] === 'transfer'): ?>
                                                        <div style="margin-bottom: 10px;">
                                                            <select name="target_department" required style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 14px;">
                                                                <option value="">选择目标部门</option>
                                                                <?php 
                                                                    $depts_sql = "SELECT id, name FROM departments WHERE id != ?";
                                                                    $depts = execute_query($depts_sql, [$approval['department_id'] ?? 0]);
                                                                    foreach ($depts as $dept): ?>
                                                                        <option value="<?php echo (int)$dept['id']; ?>">
                                                                            <?php echo htmlspecialchars($dept['name']); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    <?php endif; ?>
                                                      
                                                    <div class="action-buttons" style="display: flex; gap: 8px;">
                                                        <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">批准</button>
                                                        <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger">拒绝</button>
                                                    </div>
                                                </form>
                                            <?php else: ?>
                                                <span class="no-action">已处理</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo ($role === 'admin' || $role === 'manager') ? 11 : 10; ?>" class="no-data">
                                    暂无审批流程
                                    <?php 
                                    // 调试信息（生产环境应移除）
                                    // echo "<br>调试信息：查询结果为空";
                                    // echo "<br>用户ID: " . $user_id;
                                    // echo "<br>角色: " . $role;
                                    // echo "<br>部门ID: " . $department_id;
                                    ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
        </div>
    </div>

    <script>
        // 页面加载后自动滚动到成功消息
        document.addEventListener('DOMContentLoaded', function() {
            const successMessage = document.querySelector('.error-message[style*="00B42A"]');
            if (successMessage) {
                successMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                // 5秒后自动隐藏成功消息
                setTimeout(function() {
                    successMessage.style.transition = 'opacity 0.5s ease';
                    successMessage.style.opacity = '0';
                    setTimeout(function() {
                        successMessage.style.display = 'none';
                    }, 500);
                }, 5000);
            }
        });
        
        // 字符计数
        const reasonField = document.getElementById('reason');
        if (reasonField) {
            reasonField.addEventListener('input', function() {
                const maxLength = 500;
                const currentLength = this.value.length;
                const remaining = maxLength - currentLength;
                const charRemaining = document.getElementById('char-remaining');
                if (charRemaining) {
                    charRemaining.textContent = remaining;
                }
                
                if (remaining < 0) {
                    this.value = this.value.substring(0, maxLength);
                    if (charRemaining) {
                        charRemaining.textContent = 0;
                    }
                }
            });
        }
        
        // 表单验证
        function validateApprovalForm() {
            const assetId = document.getElementById('asset_id').value;
            const type = document.getElementById('type').value;
            const reason = document.getElementById('reason').value.trim();
            
            if (!assetId) {
                alert('请选择资产');
                document.getElementById('asset_id').focus();
                return false;
            }
            
            if (!type) {
                alert('请选择审批类型');
                document.getElementById('type').focus();
                return false;
            }
            
            if (!reason) {
                alert('请输入申请原因');
                document.getElementById('reason').focus();
                return false;
            }
            
            if (reason.length > 500) {
                alert('申请原因不能超过500个字符');
                document.getElementById('reason').focus();
                return false;
            }
            
            return true;
        }
        
        // 表单提交成功后关闭模态框
        document.getElementById('approval-form')?.addEventListener('submit', function(e) {
            // 如果验证通过，表单会正常提交
            // 服务器端处理后会重定向，所以这里不需要手动关闭
        });
        
        // 重置表单（当模态框关闭时）
        const modal = document.getElementById('approval-modal');
        if (modal) {
            // 监听模态框关闭事件，重置表单
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                        const display = modal.style.display;
                        if (display === 'none' || display === '') {
                            // 模态框已关闭，重置表单
                            const form = document.getElementById('approval-form');
                            if (form) {
                                form.reset();
                                document.getElementById('char-remaining').textContent = '500';
                            }
                        }
                    }
                });
            });
            observer.observe(modal, { attributes: true, attributeFilter: ['style'] });
        }
        
        // 防止重复提交
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitButtons = this.querySelectorAll('button[type="submit"]');
                submitButtons.forEach(button => {
                    button.disabled = true;
                    button.innerHTML = '处理中...';
                });
            });
        });
        
        // 审批表单确认
        document.querySelectorAll('.approval-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const action = e.submitter ? e.submitter.value : '';
                const typeText = this.closest('tr').querySelector('td:nth-child(3)').textContent;
                
                if (action === 'approve') {
                    if (!confirm(`确定要批准这个${typeText}申请吗？`)) {
                        e.preventDefault();
                    }
                } else if (action === 'reject') {
                    if (!confirm(`确定要拒绝这个${typeText}申请吗？`)) {
                        e.preventDefault();
                    }
                }
            });
        });
    </script>
</body>
</html>