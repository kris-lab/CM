<?php

if (!CM_Db_Db::existsColumn(TBL_CM_SPLITTESTVARIATION_FIXTURE, 'conversionWeight')) {
	CM_Mysql::exec('ALTER TABLE TBL_CM_SPLITTESTVARIATION_FIXTURE ADD `conversionWeight` DECIMAL(10,2) DEFAULT 1 NOT NULL');
}
