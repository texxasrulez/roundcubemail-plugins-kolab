/**
 * Roundcube Calendar
 *
 * Plugin to add a calendar to Roundcube.
 *
 * @version 0.3 beta
 * @author Lazlo Westerhof
 * @author Thomas Bruederli
 * @url http://rc-calendar.lazlo.me
 * @licence GNU GPL
 * @copyright (c) 2010 Lazlo Westerhof - Netherlands
 *
 **/

CREATE TABLE `calendars` (
  `calendar_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL,
  `color` varchar(8) NOT NULL,
  PRIMARY KEY(`calendar_id`),
  CONSTRAINT `fk_calendars_user_id` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

CREATE TABLE `events` (
  `event_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `calendar_id` int(11) UNSIGNED NOT NULL DEFAULT '0',
  `recurrence_id` int(11) UNSIGNED NOT NULL DEFAULT '0',
  `uid` varchar(255) NOT NULL DEFAULT '',
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `start` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `end` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `recurrence` varchar(255) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(255) NOT NULL DEFAULT '',
  `categories` varchar(255) NOT NULL DEFAULT '',
  `all_day` tinyint(1) NOT NULL DEFAULT '0',
  `free_busy` tinyint(1) NOT NULL DEFAULT '0',
  `priority` tinyint(1) NOT NULL DEFAULT '1',
  `sensitivity` tinyint(1) NOT NULL DEFAULT '0',
  `alarms` varchar(255) DEFAULT NULL,
  `attendees` text DEFAULT NULL,
  `notifyat` datetime DEFAULT NULL,
  PRIMARY KEY(`event_id`),
  CONSTRAINT `fk_events_calendar_id` FOREIGN KEY (`calendar_id`)
    REFERENCES `calendars`(`calendar_id`) ON DELETE CASCADE ON UPDATE CASCADE
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

CREATE TABLE `attachments` (
  `attachment_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` int(11) UNSIGNED NOT NULL DEFAULT '0',
  `filename` varchar(255) NOT NULL DEFAULT '',
  `mimetype` varchar(255) NOT NULL DEFAULT '',
  `size` int(11) NOT NULL DEFAULT '0',
  `data` longtext NOT NULL DEFAULT '',
  PRIMARY KEY(`attachment_id`),
  CONSTRAINT `fk_attachments_event_id` FOREIGN KEY (`event_id`)
    REFERENCES `events`(`event_id`) ON DELETE CASCADE ON UPDATE CASCADE
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;
