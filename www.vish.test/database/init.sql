-- 创建资产表
CREATE TABLE IF NOT EXISTS `assets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `model` VARCHAR(255),
  `serial_number` VARCHAR(255),
  `purchase_date` DATE,
  `price` DECIMAL(10,2),
  `status` ENUM('in_use', 'in_store', 'maintenance', 'scrapped') DEFAULT 'in_store',
  `location` VARCHAR(255),
  `department_id` INT,
  `user_id` INT,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 创建部门表
CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 创建用户表
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `department_id` INT,
  `role` ENUM('admin', 'manager', 'employee') DEFAULT 'employee',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 创建审批流程表
CREATE TABLE IF NOT EXISTS `approval_processes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `asset_id` INT NOT NULL,
  `type` ENUM('transfer', 'scrap', 'maintenance', 'purchase') NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `requester_id` INT NOT NULL,
  `approver_id` INT,
  `reason` TEXT,
  `approved_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 创建维护记录表
CREATE TABLE IF NOT EXISTS `maintenance_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `asset_id` INT NOT NULL,
  `maintenance_type` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `cost` DECIMAL(10,2),
  `maintenance_date` DATE NOT NULL,
  `maintainer_id` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 创建盘点记录表
CREATE TABLE IF NOT EXISTS `inventory_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `asset_id` INT NOT NULL,
  `inventory_date` DATE NOT NULL,
  `status` ENUM('normal', 'missing', 'damaged') DEFAULT 'normal',
  `inventory_user_id` INT NOT NULL,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 创建系统日志表
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT,
  `action` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `ip_address` VARCHAR(50),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 插入示例数据
-- 插入部门
INSERT INTO `departments` (`name`, `description`) VALUES
('技术部', '负责系统开发和维护'),
('财务部', '负责财务管理和审计'),
('人力资源部', '负责人事管理和招聘'),
('行政部', '负责行政事务和资产管理');

-- 插入用户
INSERT INTO `users` (`username`, `password`, `name`, `department_id`, `role`) VALUES
('admin', MD5('admin123'), '系统管理员', 4, 'admin'),
('manager_tech', MD5('manager123'), '技术部经理', 1, 'manager'),
('employee_tech', MD5('employee123'), '技术部员工', 1, 'employee'),
('manager_finance', MD5('manager123'), '财务部经理', 2, 'manager');

-- 插入资产
INSERT INTO `assets` (`name`, `category`, `model`, `serial_number`, `purchase_date`, `price`, `status`, `location`, `department_id`, `user_id`, `description`) VALUES
('笔记本电脑', '电子设备', 'MacBook Pro', 'MBP2023001', '2023-01-15', 12999.00, 'in_use', '技术部办公室', 1, 3, '技术部员工使用'),
('打印机', '办公设备', 'HP LaserJet Pro', 'HP2023001', '2023-02-10', 2599.00, 'in_use', '行政部办公室', 4, NULL, '公共使用打印机'),
('服务器', '网络设备', 'Dell PowerEdge', 'DELL2023001', '2023-03-05', 39999.00, 'in_use', '机房', 1, NULL, '公司核心服务器'),
('办公桌', '家具', '现代简约', 'DESK2023001', '2023-01-20', 899.00, 'in_use', '技术部办公室', 1, 3, '技术部员工办公桌');
