<?php
// 用户管理页面
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

// 获取所有部门
$departments_sql = "SELECT * FROM departments";
$departments = execute_query($departments_sql);

// 获取用户列表
$users_sql = "SELECT u.*, d.name as department_name FROM users u 
             LEFT JOIN departments d ON u.department_id = d.id 
             ORDER BY u.id DESC";
$users = execute_query($users_sql);

// 处理用户添加
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    // 验证CSRF令牌
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "安全令牌验证失败";
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $name = trim($_POST['name']);
        $department_id = (int)$_POST['department_id'];
        $role = $_POST['role'];
        
        // 验证输入
        if (empty($username) || strlen($username) < 3 || strlen($username) > 50) {
            $error = "用户名长度应在3-50个字符之间";
        } elseif (empty($password) || strlen($password) < 6) {
            $error = "密码长度至少为6个字符";
        } elseif (empty($name) || strlen($name) > 50) {
            $error = "姓名不能为空且长度不能超过50个字符";
        } elseif ($department_id <= 0) {
            $error = "请选择有效的部门";
        } elseif (!in_array($role, ['admin', 'manager', 'employee'])) {
            $error = "无效的角色";
        } else {
            // 检查用户名是否已存在
            $check_sql = "SELECT * FROM users WHERE username = ?";
            $check_result = execute_query($check_sql, [$username]);
            
            if (!empty($check_result)) {
                $error = "用户名已存在";
            } else {
                // 使用password_hash进行密码加密
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $insert_sql = "INSERT INTO users (username, password, name, department_id, role) 
                              VALUES (?, ?, ?, ?, ?)";
                $result = execute_query($insert_sql, [$username, $hashed_password, $name, $department_id, $role]);
                
                if (isset($result['insert_id'])) {
                    // 记录操作日志
                    $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'add_user', ?, ?)";
                    $log_desc = "添加用户：" . $username;
                    execute_query($log_sql, [$user_id, $log_desc, $_SERVER['REMOTE_ADDR']]);
                    
                    header('Location: users.php?success=用户添加成功');
                    exit;
                } else {
                    $error = "用户添加失败：" . ($result['error'] ?? '未知错误');
                }
            }
        }
    }
}

// 处理用户更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    // 验证CSRF令牌
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "安全令牌验证失败";
    } else {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $department_id = (int)$_POST['department_id'];
        $role = $_POST['role'];
        $password = $_POST['password'];
        
        // 验证输入
        if (empty($name) || strlen($name) > 50) {
            $error = "姓名不能为空且长度不能超过50个字符";
        } elseif ($department_id <= 0) {
            $error = "请选择有效的部门";
        } elseif (!in_array($role, ['admin', 'manager', 'employee'])) {
            $error = "无效的角色";
        } else {
            // 构建更新SQL
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    $error = "密码长度至少为6个字符";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update_sql = "UPDATE users SET name = ?, department_id = ?, role = ?, password = ? WHERE id = ?";
                    $result = execute_query($update_sql, [$name, $department_id, $role, $hashed_password, $id]);
                }
            } else {
                $update_sql = "UPDATE users SET name = ?, department_id = ?, role = ? WHERE id = ?";
                $result = execute_query($update_sql, [$name, $department_id, $role, $id]);
            }
            
            if (isset($result['affected_rows']) && $result['affected_rows'] > 0) {
                // 记录操作日志
                $user_sql = "SELECT username FROM users WHERE id = ?";
                $user_result = execute_query($user_sql, [$id]);
                $username = htmlspecialchars($user_result[0]['username'] ?? '');
                
                $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'update_user', ?, ?)";
                $log_desc = "更新用户：" . $username;
                execute_query($log_sql, [$user_id, $log_desc, $_SERVER['REMOTE_ADDR']]);
                
                header('Location: users.php?success=用户更新成功');
                exit;
            } else {
                $error = "用户更新失败：" . ($result['error'] ?? '未知错误');
            }
        }
    }
}

// 处理用户删除
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // 不能删除自己
    if ($id == $user_id) {
        $error = "不能删除当前登录用户";
    } else {
        // 获取用户信息
        $user_sql = "SELECT username FROM users WHERE id = ?";
        $user_result = execute_query($user_sql, [$id]);
        
        if (empty($user_result)) {
            $error = "用户不存在";
        } else {
            $username = htmlspecialchars($user_result[0]['username']);
            
            $delete_sql = "DELETE FROM users WHERE id = ?";
            $result = execute_query($delete_sql, [$id]);
            
            if (isset($result['affected_rows']) && $result['affected_rows'] > 0) {
                // 记录操作日志
                $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'delete_user', ?, ?)";
                $log_desc = "删除用户：" . $username;
                execute_query($log_sql, [$user_id, $log_desc, $_SERVER['REMOTE_ADDR']]);
                
                header('Location: users.php?success=用户删除成功');
                exit;
            } else {
                $error = "用户删除失败：" . ($result['error'] ?? '未知错误');
            }
        }
    }
}

// 获取单个用户信息（用于编辑）
$edit_user = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $user_sql = "SELECT * FROM users WHERE id = ?";
    $user_result = execute_query($user_sql, [$id]);
    $edit_user = $user_result[0] ?? null;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>企业资源管理系统 - 用户管理</title>
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
                        <li><a href="users.php" class="active">用户管理</a></li>
                    <?php endif; ?>
                    <?php if ($role === 'admin'): ?>
                        <li><a href="departments.php">部门管理</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <!-- 主内容区 -->
            <main class="main-content">
            <h2>用户管理</h2>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="error-message" style="background-color: rgba(0, 180, 42, 0.08); color: var(--success-color); border-color: rgba(0, 180, 42, 0.2);">
                    <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- 顶部操作按钮 -->
            <div style="margin-bottom: 16px; display: flex; justify-content: flex-end;">
                <button class="btn btn-primary" data-modal="user-modal">添加用户</button>
            </div>

            <!-- 用户添加弹窗 -->
            <div id="user-modal" class="modal">
                <div class="modal-content">
                    <div class="modal-card">
                        <div class="modal-header">
                            <h3>添加用户</h3>
                            <button type="button" class="modal-close close">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form method="post" onsubmit="return validateUserForm()">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="username">用户名</label>
                                        <input type="text" id="username" name="username" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="password">密码</label>
                                        <input type="password" id="password" name="password" required>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="name">姓名</label>
                                        <input type="text" id="name" name="name" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="department_id">所属部门</label>
                                        <select id="department_id" name="department_id" required>
                                            <option value="">请选择部门</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?php echo (int)$dept['id']; ?>">
                                                    <?php echo htmlspecialchars($dept['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="role">角色</label>
                                        <select id="role" name="role" required>
                                            <option value="admin">管理员</option>
                                            <option value="manager">部门经理</option>
                                            <option value="employee">员工</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" name="add_user" class="btn btn-primary">
                                        添加用户
                                    </button>
                                    <button type="button" class="btn btn-secondary close">取消</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($edit_user): ?>
            <!-- 编辑用户仍在页面内展示 -->
            <div class="form-container" style="margin-bottom: 24px;">
                <h3>编辑用户</h3>
                <form method="post" onsubmit="return validateUserForm()">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id" value="<?php echo (int)$edit_user['id']; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username_edit">用户名</label>
                            <input type="text" id="username_edit" name="username" value="<?php echo htmlspecialchars($edit_user['username'] ?? ''); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="password_edit">密码（留空表示不修改）</label>
                            <input type="password" id="password_edit" name="password">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name_edit">姓名</label>
                            <input type="text" id="name_edit" name="name" value="<?php echo htmlspecialchars($edit_user['name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="department_id_edit">所属部门</label>
                            <select id="department_id_edit" name="department_id" required>
                                <option value="">请选择部门</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo (int)$dept['id']; ?>" <?php echo ($edit_user['department_id'] ?? '') == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="role_edit">角色</label>
                            <select id="role_edit" name="role" required>
                                <option value="admin" <?php echo ($edit_user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>管理员</option>
                                <option value="manager" <?php echo ($edit_user['role'] ?? '') === 'manager' ? 'selected' : ''; ?>>部门经理</option>
                                <option value="employee" <?php echo ($edit_user['role'] ?? '') === 'employee' ? 'selected' : ''; ?>>员工</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_user" class="btn btn-primary">
                            更新用户
                        </button>
                        <a href="users.php" class="btn btn-secondary">取消</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- 用户列表 -->
            <div class="activity-section">
                <h3>用户列表</h3>
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户名</th>
                            <th>姓名</th>
                            <th>所属部门</th>
                            <th>角色</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo (int)$user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['department_name'] ?? '未分配'); ?></td>
                                    <td><?php echo $user['role'] === 'admin' ? '管理员' : ($user['role'] === 'manager' ? '部门经理' : '员工'); ?></td>
                                    <td><?php echo $user['created_at']; ?></td>
                                    <td>
                                        <a href="users.php?edit=<?php echo (int)$user['id']; ?>" class="btn btn-sm btn-primary">编辑</a>
                                        <?php if ($user['id'] != $user_id): ?>
                                            <a href="users.php?delete=<?php echo (int)$user['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('确定要删除此用户吗？');">删除</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="no-data">暂无用户</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
        </div>
    </div>

    <script>
        // 表单验证
        function validateUserForm() {
            const username = document.getElementById('username');
            const password = document.getElementById('password');
            const name = document.getElementById('name');
            const departmentId = document.getElementById('department_id');
            const role = document.getElementById('role');
            
            // 验证用户名
            if (!username.disabled && (!username.value || username.value.length < 3 || username.value.length > 50)) {
                alert('用户名长度应在3-50个字符之间');
                username.focus();
                return false;
            }
            
            // 验证密码
            if (!password.value && !document.querySelector('input[name="id"]')) {
                alert('密码不能为空');
                password.focus();
                return false;
            }
            
            if (password.value && password.value.length < 6) {
                alert('密码长度至少为6个字符');
                password.focus();
                return false;
            }
            
            // 验证姓名
            if (!name.value || name.value.length > 50) {
                alert('姓名不能为空且长度不能超过50个字符');
                name.focus();
                return false;
            }
            
            // 验证部门
            if (!departmentId.value || parseInt(departmentId.value) <= 0) {
                alert('请选择有效的部门');
                departmentId.focus();
                return false;
            }
            
            // 验证角色
            if (!role.value || !['admin', 'manager', 'employee'].includes(role.value)) {
                alert('请选择有效的角色');
                role.focus();
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>