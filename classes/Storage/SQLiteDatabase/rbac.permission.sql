CREATE TABLE `rbac.permission` (
`name` CHAR(64) NOT NULL, 
`description` CHAR(255), 
`exists` INTEGER,
`linkToBan` INTEGER,
`offsetValue` INTEGER,
PRIMARY KEY (`name`)
 )