<?php

if (!CM_Db_Db::existsColumn('cm_streamChannel', 'adapterType')) {
	CM_Mysql::exec('ALTER TABLE  `cm_streamChannel` ADD  `adapterType` INT NOT NULL');
	CM_Mysql::exec('ALTER TABLE  `cm_streamChannel` ADD INDEX `type` (`type`)');
	CM_Mysql::exec('ALTER TABLE  `cm_streamChannel` ADD UNIQUE `key-adapterType` (`key`, `adapterType`)');
}
