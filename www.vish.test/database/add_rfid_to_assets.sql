-- 为资产表添加 RFID 编码字段的升级脚本
-- 执行本脚本前请先备份数据库

ALTER TABLE `assets`
ADD COLUMN `rfid_code` VARCHAR(64) NULL COMMENT 'RFID 标签编码' AFTER `serial_number`,
ADD UNIQUE KEY `uniq_rfid_code` (`rfid_code`);

