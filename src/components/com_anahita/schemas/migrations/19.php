<?php

/**
 * LICENSE: ##LICENSE##
 *
 * @package    Com_Anahita
 * @subpackage Schema_Migration
 */

/**
 * Schema Migration
 *
 * @package    Com_Anahita
 * @subpackage Schema_Migration
 */
class ComAnahitaSchemaMigration19 extends ComMigratorMigrationVersion
{
   /**
    * Called when migrating up
    */
    public function up()
    {
        dbexec('ALTER TABLE `#__nodes` CHANGE `meta` `meta` text DEFAULT NULL');
        dbexec('ALTER TABLE `#__edges` CHANGE `meta` `meta` text DEFAULT NULL');
        dbexec('ALTER TABLE `#__components` CHANGE `params` `meta` text DEFAULT NULL');
        dbexec('ALTER TABLE `#__components` DROP COLUMN `link`');
        dbexec('ALTER TABLE `#__components` DROP COLUMN `menuid`');
        dbexec('ALTER TABLE `#__components` DROP COLUMN `admin_menu_link`');
        dbexec('ALTER TABLE `#__components` DROP COLUMN `admin_menu_alt`');
        dbexec('ALTER TABLE `#__components` DROP COLUMN `admin_menu_img`');

        //deleting legacy data
        dbexec('DELETE FROM `#__components` WHERE `option` IN ("com_config","com_cpanel","com_plugins","com_templates","com_components", "com_languages")');

        $this->_updateMeta('components');

        //legacy data clean up
        dbexec('DELETE FROM `#__plugins` WHERE `element` = "invite" ');
        dbexec('ALTER TABLE `#__plugins` DROP COLUMN `access`');
        dbexec('ALTER TABLE `#__plugins` DROP COLUMN `client_id`');
        dbexec('ALTER TABLE `#__plugins` DROP COLUMN `checked_out`');
        dbexec('ALTER TABLE `#__plugins` DROP COLUMN `checked_out_time`');

        $this->_updateMeta('plugins');

        dbexec('DROP TABLE IF EXISTS `#__templates_menu`');

        dbexec('ALTER TABLE `#__session` DROP COLUMN `client_id`');

        $query = "CREATE TABLE `#__sessions` ("
        ."`session_id` varchar(200) NOT NULL DEFAULT '0',"
        ."`node_id` bigint(11) NOT NULL,"
        ."`person_username` varchar(255) DEFAULT NULL,"
        ."`person_usertype` varchar(255) DEFAULT NULL,"
        ."`time` varchar(14) DEFAULT '',"
        ."`guest` tinyint(4) DEFAULT '1',"
        ."`data` longtext,"
        ."PRIMARY KEY (`session_id`(64)),"
        ."KEY `whosonline` (`guest`,`person_usertype`),"
        ."KEY `node_id` (`node_id`),"
        ."KEY `time` (`time`)"
        .") ENGINE=InnoDB CHARACTER SET=utf8";
        dbexec($query);
    }

   /**
    * Called when rolling back a migration
    */
    public function down()
    {
        //add your migration here
    }

    protected function _updateMeta($type)
    {
        $rows = dbfetch('SELECT `id`,`meta` FROM `#__'.$type.'`');

        foreach($rows as $row) {

            $meta = $row['meta'];

            if ($meta != '') {
                $json = array();
                $lines = explode("\n", $meta);

                foreach ($lines as $line) {

                    $line = explode('=', $line, 2);
                    $key = $line[0];

                    if (isset($line[1])) {
                          $value = $line[1];
                          $json[$key] = htmlentities($value, ENT_QUOTES, "UTF-8");
                    }
                }

                if (count($json)) {
                    $json = json_encode($json);
                    dbexec('UPDATE `#__'.$type.'` SET `meta` = \''.$json.'\' WHERE `id` = '.$row['id']);
                }
            }
        }
    }
}
