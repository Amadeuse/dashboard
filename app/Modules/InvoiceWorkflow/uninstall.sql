-- Run by ModuleRegistry::uninstall(). The settings page exports this table to
-- storage/backups/ first and says so in its confirmation, so removing the
-- module is recoverable: reinstall, then replay that file.
DROP TABLE IF EXISTS `invoice_workflow`;
