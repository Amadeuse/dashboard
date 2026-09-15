-- Every table this module created, dropped here. Required: the settings page
-- reads the DROP TABLE lines to know which tables to export to
-- storage/backups/ before removing the module, so a table missing from this
-- file is a table whose data is lost on uninstall.
DROP TABLE IF EXISTS `template_items`;
