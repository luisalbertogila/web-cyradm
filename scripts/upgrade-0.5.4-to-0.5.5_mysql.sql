ALTER TABLE `domain` ADD `domainquota` int(10) DEFAULT '0' NOT NULL AFTER `quota`;
ALTER TABLE `domain` ADD `folders` varchar(255) NOT NULL DEFAULT '';

ALTER TABLE `accountuser` ADD `imap` int(10) DEFAULT '1' NOT NULL AFTER `domain_name`;
ALTER TABLE `accountuser` ADD `pop` int(10) DEFAULT '1' NOT NULL AFTER `imap`;
ALTER TABLE `accountuser` ADD `sieve` int(10) DEFAULT '1' NOT NULL AFTER `pop`;
ALTER TABLE `accountuser` ADD `smtpauth` int(10) DEFAULT '1' NOT NULL AFTER `sieve`;


CREATE TABLE IF NOT EXISTS `settings` (
  `username` varchar(50) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '',
  `style` varchar(50) NOT NULL DEFAULT 'default',
  `maxdisplay` int(4) NOT NULL DEFAULT '15',
  `warnlevel` int(3) NOT NULL DEFAULT '90'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

ALTER TABLE `settings` ADD PRIMARY KEY (`username`);

INSERT INTO `settings` (username) SELECT `username` FROM adminuser;

ALTER TABLE `log` ADD INDEX `idx_log_user` ( `user` ); 
