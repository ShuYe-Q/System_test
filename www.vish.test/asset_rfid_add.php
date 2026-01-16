<?php
// RFID 扫描快速新增资产（移动端友好页面）
require_once 'config/db.php';

session_start();

// 要求登录后才能使用
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'];
$role = $_SESSION['role'];
$department_id = $_SESSION['department_id'];

$message = null;
$message_type = null;
$asset_info = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rfid_code = trim($_POST['rfid_code'] ?? '');

    if ($rfid_code === '') {
        $message = 'RFID 编码不能为空';
        $message_type = 'error';
    } else {
        // 1. 先查是否已存在该 RFID 的资产
        $sql = "SELECT * FROM assets WHERE rfid_code = ?";
        $rows = execute_query($sql, [$rfid_code]);

        if (!empty($rows)) {
            $asset_info = $rows[0];
            $message = '该 RFID 对应的资产已存在';
            $message_type = 'info';
        } else {
            // 2. 不存在则自动新增一条资产记录
            $auto_name = 'RFID 资产 - ' . $rfid_code;
            $category = 'RFID导入';
            $status = 'in_store';

            $insert_sql = "INSERT INTO assets (name, category, model, serial_number, rfid_code, status, location, department_id, user_id, description) 
                           VALUES (?, ?, NULL, NULL, ?, ?, NULL, ?, ?, ?)";

            $desc = '通过 RFID 扫描自动建档';
            $result = execute_query($insert_sql, [
                $auto_name,
                $category,
                $rfid_code,
                $status,
                $department_id,
                $user_id,
                $desc
            ]);

            if (isset($result['insert_id'])) {
                $asset_id = $result['insert_id'];
                // 查询刚刚新增的资产详情
                $asset_info_rows = execute_query("SELECT * FROM assets WHERE id = ?", [$asset_id]);
                $asset_info = $asset_info_rows[0] ?? null;

                // 记录操作日志
                $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'add_asset_by_rfid', 'RFID 自动建档：" . $rfid_code . "', ?)";
                execute_query($log_sql, [$user_id, $_SERVER['REMOTE_ADDR']]);

                $message = '资产已根据 RFID 自动添加成功';
                $message_type = 'success';
            } else {
                $message = '资产自动添加失败：' . ($result['error'] ?? '未知错误');
                $message_type = 'error';
            }
        }
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>RFID 扫描快速建档</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .rfid-page {
            max-width: 480px;
            margin: 0 auto;
            padding: 16px;
        }
        .rfid-card {
            background: #fff;
            border-radius: 12px;
            padding: 16px 16px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,.06);
        }
        .rfid-title {
            font-size: 18px;
            margin-bottom: 8px;
        }
        .rfid-sub {
            font-size: 12px;
            color: #666;
            margin-bottom: 16px;
        }
        .rfid-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .rfid-input {
            flex: 1;
            padding: 10px 12px;
            font-size: 16px;
            border-radius: 8px;
            border: 1px solid #ddd;
            box-sizing: border-box;
        }
        .rfid-scan-btn {
            width: 44px;
            height: 44px;
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
            width: 22px;
            height: 22px;
        }
        .rfid-btn {
            margin-top: 12px;
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border-radius: 8px;
            border: none;
            background: #1677ff;
            color: #fff;
        }
        .rfid-msg {
            margin-top: 12px;
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 14px;
        }
        .rfid-msg.success {
            background: #f6ffed;
            color: #389e0d;
            border: 1px solid #b7eb8f;
        }
        .rfid-msg.error {
            background: #fff2f0;
            color: #cf1322;
            border: 1px solid #ffa39e;
        }
        .rfid-msg.info {
            background: #e6f4ff;
            color: #0958d9;
            border: 1px solid #91caff;
        }
        .rfid-asset {
            margin-top: 12px;
            font-size: 13px;
            background: #fafafa;
            border-radius: 8px;
            padding: 8px 10px;
            border: 1px dashed #ddd;
        }
        .rfid-asset-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }
        .rfid-asset-row span:first-child {
            color: #666;
        }
        .rfid-camera {
            margin-top: 12px;
            border-radius: 10px;
            overflow: hidden;
            background: #000;
            position: relative;
            display: none;
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
            width: 26px;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            cursor: pointer;
        }
        .rfid-footer-link {
            margin-top: 16px;
            text-align: center;
            font-size: 13px;
        }
        .rfid-footer-link a {
            color: #1677ff;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="rfid-page">
    <div class="rfid-card">
        <div class="rfid-title">RFID 扫描快速建档</div>
        <div class="rfid-sub">
            当前用户：<?php echo htmlspecialchars($name); ?>（<?php echo $role === 'admin' ? '管理员' : ($role === 'manager' ? '部门经理' : '员工'); ?>）
        </div>

        <?php if ($message): ?>
            <div class="rfid-msg <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <label for="rfid_code" style="font-size:13px;color:#555;display:block;margin-bottom:6px;">
                请将光标置于此处，然后使用 RFID 读写器 / 扫码枪扫描，或点击右侧相机图标调起摄像头：
            </label>
            <div class="rfid-input-wrap">
                <input id="rfid_code" name="rfid_code" class="rfid-input" autocomplete="off" autofocus>
                <button type="button" id="btn-scan" class="rfid-scan-btn" title="使用摄像头扫描">
                    <!-- 简单相机图标 -->
                    <svg viewBox="0 0 24 24">
                        <path d="M20 5h-2.2l-1.1-1.7A1.99 1.99 0 0 0 15 2h-6c-.7 0-1.3.3-1.7.8L6.2 5H4c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 13H4V7h3.2l1.5-2.3c.1-.1.2-.2.3-.2h6c.1 0 .2.1.3.2L16.8 7H20v11zm-8-9a5 5 0 1 0 .001 10.001A5 5 0 0 0 12 9zm0 8a3 3 0 1 1 0-6 3 3 0 0 1 0 6z" fill="currentColor"/>
                    </svg>
                </button>
            </div>
            <div id="camera-container" class="rfid-camera">
                <video id="video" playsinline></video>
                <button type="button" id="camera-close" class="rfid-camera-close">&times;</button>
            </div>
            <button type="submit" class="rfid-btn">提交（如设备未自动回车）</button>
        </form>

        <?php if ($asset_info): ?>
            <div class="rfid-asset">
                <div class="rfid-asset-row">
                    <span>资产 ID</span>
                    <span><?php echo $asset_info['id']; ?></span>
                </div>
                <div class="rfid-asset-row">
                    <span>资产名称</span>
                    <span><?php echo htmlspecialchars($asset_info['name']); ?></span>
                </div>
                <div class="rfid-asset-row">
                    <span>类别</span>
                    <span><?php echo htmlspecialchars($asset_info['category']); ?></span>
                </div>
                <div class="rfid-asset-row">
                    <span>型号</span>
                    <span><?php echo htmlspecialchars($asset_info['model'] ?? ''); ?></span>
                </div>
                <div class="rfid-asset-row">
                    <span>价格</span>
                    <span><?php echo $asset_info['price'] !== null ? ('¥' . $asset_info['price']) : ''; ?></span>
                </div>
                <div class="rfid-asset-row">
                    <span>RFID 编码</span>
                    <span><?php echo htmlspecialchars($asset_info['rfid_code']); ?></span>
                </div>
                <div class="rfid-asset-row">
                    <span>状态</span>
                    <span><?php echo htmlspecialchars($asset_info['status']); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <div class="rfid-footer-link">
            <a href="assets.php" target="_self">进入资产管理查看更多信息</a>
        </div>
    </div>
</div>

<!-- ZXing 浏览器扫码库（支持条码/二维码） -->
<script src="https://unpkg.com/@zxing/library@latest"></script>
<script>
    const input = document.getElementById('rfid_code');
    const btnScan = document.getElementById('btn-scan');
    const cameraContainer = document.getElementById('camera-container');
    const video = document.getElementById('video');
    const btnClose = document.getElementById('camera-close');

    if (input) {
        input.focus();
        // 如果扫码枪/盘点机会发送回车，则自动提交表单
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.form.submit();
            }
        });
    }

    let codeReader = null;
    let currentStream = null;

    function stopCamera() {
        if (currentStream) {
            currentStream.getTracks().forEach(track => track.stop());
            currentStream = null;
        }
        if (codeReader) {
            try { codeReader.reset(); } catch (e) {}
        }
        cameraContainer.style.display = 'none';
    }

    async function startScan() {
        if (!window.ZXing || !ZXing.BrowserMultiFormatReader) {
            alert('扫码库加载失败，请检查网络后重试');
            return;
        }
        cameraContainer.style.display = 'block';

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
                    input.value = text;
                    stopCamera();
                    // 自动提交表单
                    setTimeout(() => {
                        input.form.submit();
                    }, 100);
                }
            });
        } catch (e) {
            console.error(e);
            alert('无法访问摄像头，请检查浏览器权限或使用 https 访问');
            stopCamera();
        }
    }

    if (btnScan) {
        btnScan.addEventListener('click', () => {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('当前浏览器不支持摄像头调用，请使用最新版 Chrome / Edge / 手机浏览器');
                return;
            }
            startScan();
        });
    }

    if (btnClose) {
        btnClose.addEventListener('click', stopCamera);
    }
</script>
</body>
</html>

