<?php
/**
 * rating - Rating: an example LTI tool provider
 *
 * @author  Stephen P Vickers <svickers@imsglobal.org>
 * @copyright  IMS Global Learning Consortium Inc
 * @date  2016
 * @version 2.0.0
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3.0
 */

/*
 * This page provides functions for accessing the database.
 */

  use IMSGlobal\LTI\ToolProvider;
  use IMSGlobal\LTI\ToolProvider\DataConnector;

  require_once('config.php');
  require_once('vendor/autoload.php');


###
###  Return a connection to the database, return FALSE if an error occurs
###
  function open_db() {

    try {
      $db = new PDO(DB_NAME, DB_USERNAME, DB_PASSWORD);
    } catch(PDOException $e) {
      $db = FALSE;
      $_SESSION['error_message'] = "Database error {$e->getCode()}: {$e->getMessage()}";
    }

    return $db;

  }


###
###  Check if a table exists
###
  function table_exists($db, $name) {

    $sql = "select 1 from {$name}";
    $query = $db->prepare($sql);
    return $query->execute() !== FALSE;

  }


###
###  Create any missing database tables (only for MySQL and SQLite databases)
###
  function init_db($db) {

    $db_type = '';
    $pos = strpos(DB_NAME, ':');
    if ($pos !== FALSE) {
      $db_type = strtolower(substr(DB_NAME, 0,$pos));
    }

    $ok = TRUE;
    $prefix = DB_TABLENAME_PREFIX;

    if (!table_exists($db, $prefix . DataConnector\DataConnector::CONSUMER_TABLE_NAME)) {
      $sql = "CREATE TABLE {$prefix}" . DataConnector\DataConnector::CONSUMER_TABLE_NAME . ' (' .
             'consumer_pk int(11) NOT NULL AUTO_INCREMENT, ' .
             'name varchar(50) NOT NULL, ' .
             'consumer_key256 varchar(256) NOT NULL, ' .
             'consumer_key text DEFAULT NULL, ' .
             'secret varchar(1024) NOT NULL, ' .
             'lti_version varchar(10) DEFAULT NULL, ' .
             'consumer_name varchar(255) DEFAULT NULL, ' .
             'consumer_version varchar(255) DEFAULT NULL, ' .
             'consumer_guid varchar(1024) DEFAULT NULL, ' .
             'profile text DEFAULT NULL, ' .
             'tool_proxy text DEFAULT NULL, ' .
             'settings text DEFAULT NULL, ' .
             'protected tinyint(1) NOT NULL, ' .
             'enabled tinyint(1) NOT NULL, ' .
             'enable_from datetime DEFAULT NULL, ' .
             'enable_until datetime DEFAULT NULL, ' .
             'last_access date DEFAULT NULL, ' .
             'created datetime NOT NULL, ' .
             'updated datetime NOT NULL, ' .
             'PRIMARY KEY (consumer_pk))';
      $ok = $db->exec($sql) !== FALSE;
      if ($ok) {
        $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::CONSUMER_TABLE_NAME . ' ' .
               "ADD UNIQUE INDEX {$prefix}" . DataConnector\DataConnector::CONSUMER_TABLE_NAME . '_' .
               'consumer_key_UNIQUE (consumer_key256 ASC)';
        $ok = $db->exec($sql) !== FALSE;
      }
    }

    if (!table_exists($db, $prefix . DataConnector\DataConnector::TOOL_PROXY_TABLE_NAME)) {
      $sql = "CREATE TABLE {$prefix}" . DataConnector\DataConnector::TOOL_PROXY_TABLE_NAME . ' (' .
             'tool_proxy_pk int(11) NOT NULL AUTO_INCREMENT, ' .
             'tool_proxy_id varchar(32) NOT NULL, ' .
             'consumer_pk int(11) NOT NULL, ' .
             'tool_proxy text NOT NULL, ' .
             'created datetime NOT NULL, ' .
             'updated datetime NOT NULL, ' .
             'PRIMARY KEY (tool_proxy_pk))';
      $ok = $db->exec($sql) !== FALSE;
      if ($ok) {
        $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::TOOL_PROXY_TABLE_NAME . ' ' .
               "ADD CONSTRAINT {$prefix}" . DataConnector\DataConnector::TOOL_PROXY_TABLE_NAME . '_' .
               DataConnector\DataConnector::CONSUMER_TABLE_NAME . '_FK1 FOREIGN KEY (consumer_pk) ' .
               "REFERENCES {$prefix}" . DataConnector\DataConnector::CONSUMER_TABLE_NAME . ' (consumer_pk)';
        $ok = $db->exec($sql) !== FALSE;
      }
      if ($ok) {
        $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::TOOL_PROXY_TABLE_NAME . ' ' .
               "ADD INDEX {$prefix}" . DataConnector\DataConnector::TOOL_PROXY_TABLE_NAME . '_' .
               'consumer_id_IDX (consumer_pk ASC)';
        $ok = $db->exec($sql) !== FALSE;
      }
      if ($ok) {
        $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::TOOL_PROXY_TABLE_NAME . ' ' .
               "ADD UNIQUE INDEX {$prefix}" . DataConnector\DataConnector::TOOL_PROXY_TABLE_NAME . '_' .
               'tool_proxy_id_UNIQUE (tool_proxy_id ASC)';
        $ok = $db->exec($sql) !== FALSE;
      }
    }

    if ($ok && !table_exists($db, $prefix . DataConnector\DataConnector::NONCE_TABLE_NAME)) {
      $sql = "CREATE TABLE {$prefix}" . DataConnector\DataConnector::NONCE_TABLE_NAME . ' (' .
             'consumer_pk int(11) NOT NULL, ' .
             'value varchar(32) NOT NULL, ' .
             'expires datetime NOT NULL, ' .
             'PRIMARY KEY (consumer_pk, value))';
      $ok = $db->exec($sql) !== FALSE;
      if ($ok) {
        $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::NONCE_TABLE_NAME . ' ' .
               "ADD CONSTRAINT {$prefix}" . DataConnector\DataConnector::NONCE_TABLE_NAME . '_' .
               DataConnector\DataConnector::CONSUMER_TABLE_NAME . '_FK1 FOREIGN KEY (consumer_pk) ' .
               "REFERENCES {$prefix}" . DataConnector\DataConnector::CONSUMER_TABLE_NAME . ' (consumer_pk)';
        $ok = $db->exec($sql) !== FALSE;
      }
    }

    if (!table_exists($db, $prefix . DataConnector\DataConnector::CONTEXT_TABLE_NAME)) {
      $sql = "CREATE TABLE {$prefix}" . DataConnector\DataConnector::CONTEXT_TABLE_NAME . ' (' .
             'context_pk int(11) NOT NULL AUTO_INCREMENT, ' .
             'consumer_pk int(11) NOT NULL, ' .
             'lti_context_id varchar(255) NOT NULL, ' .
             'settings text DEFAULT NULL, ' .
             'created datetime NOT NULL, ' .
             'updated datetime NOT NULL, ' .
             'PRIMARY KEY (context_pk))';
      $ok = $db->exec($sql) !== FALSE;
      if ($ok) {
        $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::CONTEXT_TABLE_NAME . ' ' .
               "ADD CONSTRAINT {$prefix}" . DataConnector\DataConnector::CONTEXT_TABLE_NAME . '_' .
               DataConnector\DataConnector::CONSUMER_TABLE_NAME . '_FK1 FOREIGN KEY (consumer_pk) ' .
               "REFERENCES {$prefix}" . DataConnector\DataConnector::CONSUMER_TABLE_NAME . ' (consumer_pk)';
        $ok = $db->exec($sql) !== FALSE;
      }
      if ($ok) {
        $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::CONTEXT_TABLE_NAME . ' ' .
               "ADD INDEX {$prefix}" . DataConnector\DataConnector::CONTEXT_TABLE_NAME . '_' .
               'consumer_id_IDX (consumer_pk ASC)';
        $ok = $db->exec($sql) !== FALSE;
      }
    }

    if ($ok && !table_exists($db, $prefix . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME)) {
      $sql = "CREATE TABLE {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . ' (' .
             'resource_link_pk int(11) AUTO_INCREMENT, ' .
             'context_pk int(11) DEFAULT NULL, ' .
             'consumer_pk int(11) DEFAULT NULL, ' .
             'lti_resource_link_id varchar(255) NOT NULL, ' .
             'settings text, ' .
             'primary_resource_link_pk int(11) DEFAULT NULL, ' .
             'share_approved tinyint(1) DEFAULT NULL, ' .
             'created datetime NOT NULL, ' .
             'updated datetime NOT NULL, ' .
             'PRIMARY KEY (resource_link_pk))';
      $ok = $db->exec($sql) !== FALSE;
      if ($ok) {
        $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . ' ' .
               "ADD CONSTRAINT {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . '_' .
               DataConnector\DataConnector::CONTEXT_TABLE_NAME . '_FK1 FOREIGN KEY (context_pk) ' .
               "REFERENCES {$prefix}" . DataConnector\DataConnector::CONTEXT_TABLE_NAME . ' (context_pk)';
        $ok = $db->exec($sql) !== FALSE;
      }
      if ($ok) {
        $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . ' ' .
               "ADD CONSTRAINT {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . '_' .
               DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . '_FK1 FOREIGN KEY (primary_resource_link_pk) ' .
               "REFERENCES {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . ' (resource_link_pk)';
        $ok = $db->exec($sql) !== FALSE;
      }
      if ($ok) {
        $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . ' ' .
               "ADD INDEX {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . '_' .
               'consumer_pk_IDX (consumer_pk ASC)';
        $ok = $db->exec($sql) !== FALSE;
      }
      if ($ok) {
        $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . ' ' .
               "ADD INDEX {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . '_' .
               'context_pk_IDX (context_pk ASC)';
        $ok = $db->exec($sql) !== FALSE;
      }
    }

    if ($ok && !table_exists($db, $prefix . DataConnector\DataConnector::USER_RESULT_TABLE_NAME)) {
      $sql = "CREATE TABLE {$prefix}" . DataConnector\DataConnector::USER_RESULT_TABLE_NAME . ' (' .
             'user_pk int(11) AUTO_INCREMENT, ' .
             'resource_link_pk int(11) NOT NULL, ' .
             'lti_user_id varchar(255) NOT NULL, ' .
             'lti_result_sourcedid varchar(1024) NOT NULL, ' .
             'created datetime NOT NULL, ' .
             'updated datetime NOT NULL, ' .
             'PRIMARY KEY (user_pk))';
      $ok = $db->exec($sql) !== FALSE;
      if ($ok) {
        $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::USER_RESULT_TABLE_NAME . ' ' .
               "ADD CONSTRAINT {$prefix}" . DataConnector\DataConnector::USER_RESULT_TABLE_NAME . '_' .
               DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . '_FK1 FOREIGN KEY (resource_link_pk) ' .
               "REFERENCES {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . ' (resource_link_pk)';
        $ok = $db->exec($sql) !== FALSE;
        $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::USER_RESULT_TABLE_NAME . ' ' .
               "ADD INDEX {$prefix}" . DataConnector\DataConnector::USER_RESULT_TABLE_NAME . '_' .
               'resource_link_pk_IDX (resource_link_pk ASC)';
        $ok = $db->exec($sql) !== FALSE;
      }
    }

    if ($ok && !table_exists($db, $prefix . DataConnector\DataConnector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME)) {
      $sql = "CREATE TABLE {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' (' .
             'share_key_id varchar(32) NOT NULL, ' .
             'resource_link_pk int(11) NOT NULL, ' .
             'auto_approve tinyint(1) NOT NULL, ' .
             'expires datetime NOT NULL, ' .
             'PRIMARY KEY (share_key_id))';
      $ok = $db->exec($sql) !== FALSE;
      if ($ok) {
        $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' ' .
               "ADD CONSTRAINT {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . '_' .
               DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . '_FK1 FOREIGN KEY (resource_link_pk) ' .
               "REFERENCES {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . ' (resource_link_pk)';
        $ok = $db->exec($sql) !== FALSE;
      }
      if ($ok) {
        $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' ' .
               "ADD INDEX {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . '_' .
               'resource_link_pk_IDX (resource_link_pk ASC)';
        $ok = $db->exec($sql) !== FALSE;
      }
    }
    if ($ok && !table_exists($db, "{$prefix}item")) {
// Adjust for different syntax of autoincrement columns
      $sql = "CREATE TABLE {$prefix}item (" .
             "item_pk int(11) NOT NULL AUTO_INCREMENT," .
             'resource_link_pk int(11) NOT NULL, ' .
             'item_title varchar(200) NOT NULL, ' .
             'item_text text, ' .
             'item_url varchar(200) DEFAULT NULL, ' .
             'max_rating int(2) NOT NULL DEFAULT \'5\', ' .
             'step int(1) NOT NULL DEFAULT \'1\', ' .
             'visible tinyint(1) NOT NULL DEFAULT \'0\', ' .
             'sequence int(3) NOT NULL DEFAULT \'0\', ' .
             'created datetime NOT NULL, ' .
             'updated datetime NOT NULL, ' .
             'PRIMARY KEY (item_pk)' .
             ') ENGINE=InnoDB DEFAULT CHARSET=utf8';
      $ok = $db->exec($sql) !== FALSE;
      if ($ok) {
        $sql = "ALTER TABLE {$prefix}item " .
               "ADD CONSTRAINT {$prefix}item_" .
               DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . '_FK1 FOREIGN KEY (resource_link_pk) ' .
               "REFERENCES {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . ' (resource_link_pk) ' .
               'ON UPDATE CASCADE';
        $ok = $db->exec($sql) !== FALSE;
      }
    }

    if ($ok && !table_exists($db, "{$prefix}rating")) {
      $sql = "CREATE TABLE {$prefix}rating (" .
             'item_pk int(11) NOT NULL, ' .
             'user_pk int(11) NOT NULL, ' .
             'rating decimal(10,2) NOT NULL, ' .
             'PRIMARY KEY (item_pk, user_pk)' .
             ') ENGINE=InnoDB DEFAULT CHARSET=utf8';
      $ok = $db->exec($sql) !== FALSE;
      if ($ok) {
        $sql = "ALTER TABLE {$prefix}rating " .
               "ADD CONSTRAINT {$prefix}rating_item_FK1 FOREIGN KEY (item_pk) " .
               "REFERENCES {$prefix}item (item_pk)";
        $ok = $db->exec($sql) !== FALSE;
      }
    }

    return $ok;

  }

?>
