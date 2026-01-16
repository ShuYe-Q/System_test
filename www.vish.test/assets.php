<?php
// 资产管理页面
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

// 获取所有部门
$departments_sql = "SELECT * FROM departments";
$departments = execute_query($departments_sql);

// 获取所有用户
$users_sql = "SELECT * FROM users";
$users = execute_query($users_sql);

// 获取资产列表
$assets_sql = "SELECT a.*, d.name as department_name, u.name as user_name FROM assets a 
              LEFT JOIN departments d ON a.department_id = d.id 
              LEFT JOIN users u ON a.user_id = u.id 
              ORDER BY a.id DESC";
$assets = execute_query($assets_sql);

// 处理资产添加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_asset'])) {
    $name = $_POST['name'];
    $category = $_POST['category'];
    $model = $_POST['model'];
    $serial_number = $_POST['serial_number'];
    $rfid_code = $_POST['rfid_code'] ?? null;
    $purchase_date = $_POST['purchase_date'];
    $price = $_POST['price'];
    $status = $_POST['status'];
    $location = $_POST['location'];
    $department_id = $_POST['department_id'];
    $user_id = $_POST['user_id'];
    $description = $_POST['description'];
    
    $insert_sql = "INSERT INTO assets (name, category, model, serial_number, rfid_code, purchase_date, price, status, location, department_id, user_id, description) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $result = execute_query($insert_sql, [$name, $category, $model, $serial_number, $rfid_code, $purchase_date, $price, $status, $location, $department_id, $user_id, $description]);
    
    if (isset($result['insert_id'])) {
        // 记录操作日志
        $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'add_asset', '添加资产：$name', ?)";
        execute_query($log_sql, [$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
        
        header('Location: assets.php?success=资产添加成功');
        exit;
    } else {
        $error = "资产添加失败：" . ($result['error'] ?? '未知错误');
    }
}

// 处理资产更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_asset'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $category = $_POST['category'];
    $model = $_POST['model'];
    $serial_number = $_POST['serial_number'];
    $rfid_code = $_POST['rfid_code'] ?? null;
    $purchase_date = $_POST['purchase_date'];
    $price = $_POST['price'];
    $status = $_POST['status'];
    $location = $_POST['location'];
    $department_id = $_POST['department_id'];
    $user_id = $_POST['user_id'];
    $description = $_POST['description'];
    
    $update_sql = "UPDATE assets SET name = ?, category = ?, model = ?, serial_number = ?, rfid_code = ?, purchase_date = ?, price = ?, status = ?, location = ?, department_id = ?, user_id = ?, description = ? WHERE id = ?";
    $result = execute_query($update_sql, [$name, $category, $model, $serial_number, $rfid_code, $purchase_date, $price, $status, $location, $department_id, $user_id, $description, $id]);
    
    if (isset($result['affected_rows']) && $result['affected_rows'] > 0) {
        // 记录操作日志
        $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'update_asset', '更新资产：$name', ?)";
        execute_query($log_sql, [$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
        
        header('Location: assets.php?success=资产更新成功');
        exit;
    } else {
        $error = "资产更新失败：" . ($result['error'] ?? '未知错误');
    }
}

// 处理资产删除
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // 获取资产名称
    $asset_sql = "SELECT name FROM assets WHERE id = ?";
    $asset_result = execute_query($asset_sql, [$id]);
    $asset_name = $asset_result[0]['name'] ?? '';
    
    $delete_sql = "DELETE FROM assets WHERE id = ?";
    $result = execute_query($delete_sql, [$id]);
    
    if (isset($result['affected_rows']) && $result['affected_rows'] > 0) {
        // 记录操作日志
        $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'delete_asset', '删除资产：$asset_name', ?)";
        execute_query($log_sql, [$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
        
        header('Location: assets.php?success=资产删除成功');
        exit;
    } else {
        $error = "资产删除失败：" . ($result['error'] ?? '未知错误');
    }
}

// 获取单个资产信息（用于编辑）
$edit_asset = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $asset_sql = "SELECT * FROM assets WHERE id = ?";
    $asset_result = execute_query($asset_sql, [$id]);
    $edit_asset = $asset_result[0] ?? null;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>企业资源管理系统 - 资产管理</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/script.js"></script>
    <style>
        .rfid-input-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .rfid-input-wrap input[type="text"] {
            flex: 1;
        }
        .rfid-scan-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: #1677ff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            cursor: pointer;
        }
        .rfid-scan-btn svg {
            width: 20px;
            height: 20px;
        }
        .rfid-camera {
            margin-top: 8px;
            border-radius: 8px;
            overflow: hidden;
            background: #000;
            display: none;
            position: relative;
        }
        .rfid-camera video {
            width: 100%;
            max-height: 260px;
            object-fit: cover;
        }
        .rfid-camera-close {
            position: absolute;
            right: 6px;
            top: 6px;
            background: rgba(0,0,0,.5);
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            cursor: pointer;
        }
    </style>
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
                    <li><a href="assets.php" class="active">资产管理</a></li>
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
            <h2>资产管理</h2>
            
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
                <button class="btn btn-primary" data-modal="asset-modal">添加资产</button>
            </div>

            <!-- 资产添加弹窗 -->
            <div id="asset-modal" class="modal">
                <div class="modal-content">
                    <div class="modal-card">
                        <div class="modal-header">
                            <h3>添加资产</h3>
                            <button type="button" class="modal-close close">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form method="post">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="name">资产名称</label>
                                        <input type="text" id="name" name="name" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="category">资产类别</label>
                                        <input type="text" id="category" name="category" required>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="model">型号</label>
                                        <input type="text" id="model" name="model">
                                    </div>
                                    <div class="form-group">
                                        <label for="serial_number">序列号</label>
                                        <input type="text" id="serial_number" name="serial_number">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="rfid_code">RFID 编码</label>
                                        <div class="rfid-input-wrap">
                                            <input type="text" id="rfid_code" name="rfid_code" placeholder="可通过扫码枪/盘点机扫描输入或点击右侧图标扫码">
                                            <button type="button" id="btn-rfid-scan" class="rfid-scan-btn" title="使用摄像头扫描">
                                                <svg viewBox="0 0 24 24">
                                                    <path d="M20 5h-2.2l-1.1-1.7A1.99 1.99 0 0 0 15 2h-6c-.7 0-1.3.3-1.7.8L6.2 5H4c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 13H4V7h3.2l1.5-2.3c.1-.1.2-.2.3-.2h6c.1 0 .2.1.3.2L16.8 7H20v11zm-8-9a5 5 0 1 0 .001 10.001A5 5 0 0 0 12 9zm0 8a3 3 0 1 1 0-6 3 3 0 0 1 0 6z" fill="currentColor"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="purchase_date">购买日期</label>
                                        <input type="date" id="purchase_date" name="purchase_date">
                                    </div>
                                    <div class="form-group">
                                        <label for="price">价格</label>
                                        <input type="number" id="price" name="price" step="0.01">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="status">状态</label>
                                        <select id="status" name="status" required>
                                            <option value="in_store">库存</option>
                                            <option value="in_use">在用</option>
                                            <option value="maintenance">维修中</option>
                                            <option value="scrapped">已报废</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="location">存放位置</label>
                                        <input type="text" id="location" name="location">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="department_id">所属部门</label>
                                        <select id="department_id" name="department_id">
                                            <option value="">请选择部门</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?php echo $dept['id']; ?>">
                                                    <?php echo $dept['name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="user_id">使用人</label>
                                        <select id="user_id" name="user_id">
                                            <option value="">请选择使用人</option>
                                            <?php foreach ($users as $user): ?>
                                                <option value="<?php echo $user['id']; ?>">
                                                    <?php echo $user['name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description">描述</label>
                                    <textarea id="description" name="description" rows="4" style="width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 14px; transition: var(--transition); background-color: var(--bg-white); resize: vertical;"></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" name="add_asset" class="btn btn-primary">添加资产</button>
                                    <button type="button" class="btn btn-secondary close">取消</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($edit_asset): ?>
            <!-- 编辑资产（仍采用页面内卡片，避免影响现有逻辑） -->
            <div class="form-container" style="margin-bottom: 24px;">
                <h3>编辑资产</h3>
                <form method="post">
                    <input type="hidden" name="id" value="<?php echo $edit_asset['id']; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name_edit">资产名称</label>
                            <input type="text" id="name_edit" name="name" value="<?php echo $edit_asset['name'] ?? ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="category_edit">资产类别</label>
                            <input type="text" id="category_edit" name="category" value="<?php echo $edit_asset['category'] ?? ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="model_edit">型号</label>
                            <input type="text" id="model_edit" name="model" value="<?php echo $edit_asset['model'] ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="serial_number_edit">序列号</label>
                            <input type="text" id="serial_number_edit" name="serial_number" value="<?php echo $edit_asset['serial_number'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                                    <div class="form-group">
                                        <label for="rfid_code_edit">RFID 编码</label>
                                        <div class="rfid-input-wrap">
                                            <input type="text" id="rfid_code_edit" name="rfid_code" value="<?php echo $edit_asset['rfid_code'] ?? ''; ?>" placeholder="可通过扫码枪/盘点机扫描输入或点击右侧图标扫码">
                                            <button type="button" id="btn-rfid-scan-edit" class="rfid-scan-btn" title="使用摄像头扫描">
                                                <svg viewBox="0 0 24 24">
                                                    <path d="M20 5h-2.2l-1.1-1.7A1.99 1.99 0 0 0 15 2h-6c-.7 0-1.3.3-1.7.8L6.2 5H4c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 13H4V7h3.2l1.5-2.3c.1-.1.2-.2.3-.2h6c.1 0 .2.1.3.2L16.8 7H20v11zm-8-9a5 5 0 1 0 .001 10.001A5 5 0 0 0 12 9zm0 8a3 3 0 1 1 0-6 3 3 0 0 1 0 6z" fill="currentColor"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="purchase_date_edit">购买日期</label>
                            <input type="date" id="purchase_date_edit" name="purchase_date" value="<?php echo $edit_asset['purchase_date'] ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="price_edit">价格</label>
                            <input type="number" id="price_edit" name="price" step="0.01" value="<?php echo $edit_asset['price'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="status_edit">状态</label>
                            <select id="status_edit" name="status" required>
                                <option value="in_store" <?php echo ($edit_asset['status'] ?? '') === 'in_store' ? 'selected' : ''; ?>>库存</option>
                                <option value="in_use" <?php echo ($edit_asset['status'] ?? '') === 'in_use' ? 'selected' : ''; ?>>在用</option>
                                <option value="maintenance" <?php echo ($edit_asset['status'] ?? '') === 'maintenance' ? 'selected' : ''; ?>>维修中</option>
                                <option value="scrapped" <?php echo ($edit_asset['status'] ?? '') === 'scrapped' ? 'selected' : ''; ?>>已报废</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="location_edit">存放位置</label>
                            <input type="text" id="location_edit" name="location" value="<?php echo $edit_asset['location'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="department_id_edit">所属部门</label>
                            <select id="department_id_edit" name="department_id">
                                <option value="">请选择部门</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo ($edit_asset['department_id'] ?? '') == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo $dept['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="user_id_edit">使用人</label>
                            <select id="user_id_edit" name="user_id">
                                <option value="">请选择使用人</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo ($edit_asset['user_id'] ?? '') == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo $user['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description_edit">描述</label>
                        <textarea id="description_edit" name="description" rows="4" style="width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 14px; transition: var(--transition); background-color: var(--bg-white); resize: vertical;"><?php echo $edit_asset['description'] ?? ''; ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_asset" class="btn btn-primary">
                            更新资产
                        </button>
                        <a href="assets.php" class="btn btn-secondary">取消</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- 资产列表 -->
            <div class="activity-section">
                <h3>资产列表</h3>
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>资产名称</th>
                            <th>类别</th>
                            <th>型号</th>
                            <th>序列号</th>
                            <th>RFID 编码</th>
                            <th>购买日期</th>
                            <th>价格</th>
                            <th>状态</th>
                            <th>存放位置</th>
                            <th>所属部门</th>
                            <th>使用人</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($assets)): ?>
                            <?php foreach ($assets as $asset): ?>
                                <tr>
                                    <td><?php echo $asset['id']; ?></td>
                                    <td><?php echo $asset['name']; ?></td>
                                    <td><?php echo $asset['category']; ?></td>
                                    <td><?php echo $asset['model']; ?></td>
                                    <td><?php echo $asset['serial_number']; ?></td>
                                    <td><?php echo $asset['rfid_code']; ?></td>
                                    <td><?php echo $asset['purchase_date']; ?></td>
                                    <td>¥<?php echo $asset['price']; ?></td>
                                    <td>
                                        <span class="status <?php echo $asset['status']; ?>">
                                            <?php 
                                                switch ($asset['status']) {
                                                    case 'in_store': echo '库存'; break;
                                                    case 'in_use': echo '在用'; break;
                                                    case 'maintenance': echo '维修中'; break;
                                                    case 'scrapped': echo '已报废'; break;
                                                    default: echo '未知';
                                                }
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo $asset['location']; ?></td>
                                    <td><?php echo $asset['department_name']; ?></td>
                                    <td><?php echo $asset['user_name']; ?></td>
                                    <td>
                                        <a href="assets.php?edit=<?php echo $asset['id']; ?>" class="btn btn-sm btn-primary">编辑</a>
                                        <a href="assets.php?delete=<?php echo $asset['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('确定要删除此资产吗？');">删除</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="12" class="no-data">暂无资产</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
        </div>
    </div>

<!-- ZXing 浏览器扫码库，用于摄像头识别条码/二维码 -->
<script src="https://unpkg.com/@zxing/library@latest"></script>
<script>
    (function () {
        const addInput = document.getElementById('rfid_code');
        const addBtn = document.getElementById('btn-rfid-scan');
        const editInput = document.getElementById('rfid_code_edit');
        const editBtn = document.getElementById('btn-rfid-scan-edit');

        // 动态创建一个摄像头预览区域，挂在 body 最后，避免破坏布局
        const cameraWrap = document.createElement('div');
        cameraWrap.className = 'rfid-camera';
        cameraWrap.id = 'rfid-camera-global';
        cameraWrap.innerHTML = '<video id="rfid-video" playsinline></video><button type="button" class="rfid-camera-close" id="rfid-camera-close">&times;</button>';
        document.body.appendChild(cameraWrap);

        const video = document.getElementById('rfid-video');
        const closeBtn = document.getElementById('rfid-camera-close');

        let currentTargetInput = null;
        let codeReader = null;
        let currentStream = null;

        function stopCamera() {
            if (currentStream) {
                currentStream.getTracks().forEach(t => t.stop());
                currentStream = null;
            }
            if (codeReader) {
                try { codeReader.reset(); } catch (e) {}
            }
            cameraWrap.style.display = 'none';
        }

        async function startScan(targetInput) {
            if (!targetInput) return;
            currentTargetInput = targetInput;

            if (!window.ZXing || !ZXing.BrowserMultiFormatReader) {
                alert('扫码库加载失败，请检查网络后重试');
                return;
            }

            cameraWrap.style.display = 'block';

            if (!codeReader) {
                codeReader = new ZXing.BrowserMultiFormatReader();
            }

            try {
                const devices = await ZXing.BrowserMultiFormatReader.listVideoInputDevices();
                let deviceId = null;
                if (devices.length > 1) {
                    const back = devices.find(d => /back|rear|environment/i.test(d.label));
                    deviceId = (back || devices[0]).deviceId;
                } else if (devices.length === 1) {
                    deviceId = devices[0].deviceId;
                }

                const constraints = deviceId ? { video: { deviceId: { exact: deviceId } } } : { video: { facingMode: 'environment' } };
                const stream = await navigator.mediaDevices.getUserMedia(constraints);
                currentStream = stream;
                video.srcObject = stream;
                video.play();

                codeReader.decodeFromVideoDevice(deviceId || null, video, (result, err) => {
                    if (result) {
                        const text = result.getText();
                        currentTargetInput.value = text;
                        stopCamera();
                    }
                });
            } catch (e) {
                console.error(e);
                alert('无法访问摄像头，请检查浏览器权限或使用 https 访问');
                stopCamera();
            }
        }

        if (addBtn && addInput) {
            addBtn.addEventListener('click', function () {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    alert('当前浏览器不支持摄像头调用，请使用最新版 Chrome / Edge / 手机浏览器');
                    return;
                }
                startScan(addInput);
            });
        }

        if (editBtn && editInput) {
            editBtn.addEventListener('click', function () {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    alert('当前浏览器不支持摄像头调用，请使用最新版 Chrome / Edge / 手机浏览器');
                    return;
                }
                startScan(editInput);
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', stopCamera);
        }
    })();
</script>
</body>
</html>