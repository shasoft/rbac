CREATE TABLE `rbac.cache` (
`type` CHAR(2), 
`name` CHAR(32), 
`ref` CHAR(64) NOT NULL, 
`state` INTEGER,
PRIMARY KEY (`type`,`name`,`ref`)
 )