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
 * This page provides general functions to support the application.
 */

  use IMSGlobal\LTI\ToolProvider;
  use IMSGlobal\LTI\ToolProvider\DataConnector;

  error_reporting(0);
  @ini_set('display_errors', false);

###  Uncomment the next line to enable error messages
//  error_reporting(E_ALL);

  require_once('db.php');

###
###  Initialise application session and database connection
###
  function init(&$db, $checkSession = NULL) {

    $ok = TRUE;

// Set timezone
    if (!ini_get('date.timezone')) {
      date_default_timezone_set('UTC');
    }

// Set session cookie path
    ini_set('session.cookie_path', getAppPath());

// Open session
    session_name(SESSION_NAME);
    session_start();

    if (!is_null($checkSession) && $checkSession) {
      $ok = isset($_SESSION['consumer_pk']) && (isset($_SESSION['resource_pk']) || is_null($_SESSION['resource_pk'])) &&
            isset($_SESSION['user_consumer_pk']) && (isset($_SESSION['user_pk']) || is_null($_SESSION['user_pk'])) && isset($_SESSION['isStudent']);
    }

    if (!$ok) {
      $_SESSION['error_message'] = 'Unable to open session.';
    } else {
// Open database connection
      $db = open_db(!$checkSession);
      $ok = $db !== FALSE;
      if (!$ok) {
        if (!is_null($checkSession) && $checkSession) {
// Display a more user-friendly error message to LTI users
          $_SESSION['error_message'] = 'Unable to open database.';
        }
      } else if (!is_null($checkSession) && !$checkSession) {
// Create database tables (if needed)
        $ok = init_db($db);  // assumes a MySQL/SQLite database is being used
        if (!$ok) {
          $_SESSION['error_message'] = 'Unable to initialise database.';
        }
      }
    }

    return $ok;

  }


###
###  Return the number of items to be rated for a specified resource link
###
  function getNumItems($db, $resource_pk) {

    $prefix = DB_TABLENAME_PREFIX;
    $sql = <<< EOD
SELECT COUNT(i.item_pk)
FROM {$prefix}item i
WHERE (i.resource_link_pk = :resource_pk)
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('resource_pk', $resource_pk, PDO::PARAM_INT);
    $query->execute();

    $row = $query->fetch(PDO::FETCH_NUM);
    if ($row === FALSE) {
      $num = 0;
    } else {
      $num = intval($row[0]);
    }

    return $num;

  }


###
###  Return an array containing the items for a specified resource link
###
  function getItems($db, $resource_pk) {

    $prefix = DB_TABLENAME_PREFIX;
    $sql = <<< EOD
SELECT i.item_pk, i.item_title, i.item_text, i.item_url, i.max_rating mr, i.step st, i.visible vis, i.sequence seq,
   i.created cr, i.updated upd, COUNT(r.user_pk) num, SUM(r.rating) total
FROM {$prefix}item i LEFT OUTER JOIN {$prefix}rating r ON i.item_pk = r.item_pk
WHERE (i.resource_link_pk = :resource_pk)
GROUP BY i.item_pk, i.item_title, i.item_text, i.item_url, i.max_rating, i.step, i.visible, i.sequence, i.created, i.updated
ORDER BY i.sequence
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('resource_pk', $resource_pk, PDO::PARAM_INT);
    $query->execute();

    $rows = $query->fetchAll(PDO::FETCH_CLASS, 'Item');
    if ($rows === FALSE) {
      $rows = array();
    }

    return $rows;

  }


###
###  Return an array of ratings made for items for a specified resource link by a specified user
###
  function getUserRated($db, $resource_pk, $user_pk) {

    $prefix = DB_TABLENAME_PREFIX;
    $sql = <<< EOD
SELECT r.item_pk
FROM {$prefix}item i INNER JOIN {$prefix}rating r ON i.item_pk = r.item_pk
WHERE (i.resource_link_pk = :resource_pk) AND (r.user_pk = :user_pk)
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('resource_pk', $resource_pk, PDO::PARAM_INT);
    $query->bindValue('user_pk', $user_pk, PDO::PARAM_INT);
    $query->execute();

    $rows = $query->fetchAll(PDO::FETCH_OBJ);
    $rated = array();
    if ($rows !== FALSE) {
      foreach ($rows as $row) {
        $rated[] = $row->item_pk;
      }
    }

    return $rated;

  }


###
###  Return details for a specific item for a specified resource link
###
  function getItem($db, $resource_pk, $item_pk) {

    $item = new Item();

    if (!empty($item_pk)) {
      $prefix = DB_TABLENAME_PREFIX;
      $sql = <<< EOD
SELECT i.item_pk, i.item_title, i.item_text, i.item_url, i.max_rating mr, i.step st, i.visible vis, i.sequence seq, i.created cr, i.updated upd
FROM {$prefix}item i
WHERE (i.resource_link_pk = :resource_pk) AND (i.item_pk = :item_pk)
EOD;

      $query = $db->prepare($sql);
      $query->bindValue('resource_pk', $resource_pk, PDO::PARAM_INT);
      $query->bindValue('item_pk', $item_pk, PDO::PARAM_INT);
      $query->setFetchMode(PDO::FETCH_CLASS, 'Item');
      $query->execute();

      $row = $query->fetch();
      if ($row !== FALSE) {
        $item = $row;
      }
    }

    return $item;

  }


###
###  Save the details for an item for a specified resource link
###
  function saveItem($db, $resource_pk, $item) {

    $prefix = DB_TABLENAME_PREFIX;
    if (!isset($item->item_pk)) {
      $sql = <<< EOD
INSERT INTO {$prefix}item (resource_link_pk, item_title, item_text, item_url, max_rating, step, visible, sequence, created, updated)
VALUES (:resource_pk, :item_title, :item_text, :item_url, :max_rating, :step, :visible, :sequence, :created, :updated)
EOD;
    } else {
      $sql = <<< EOD
UPDATE {$prefix}item
SET item_title = :item_title, item_text = :item_text, item_url = :item_url, max_rating = :max_rating, step = :step, visible = :visible,
    sequence = :sequence, updated = :updated
WHERE (item_pk = :item_pk) AND (resource_link_pk = :resource_pk)
EOD;
    }
    $query = $db->prepare($sql);
    $item->updated = new DateTime();
    if (!isset($item->item_pk)) {
      $item->created = $item->updated;
      $item->sequence = getNumItems($db, $resource_pk) + 1;
      $query->bindValue('created', $item->created->format('Y-m-d H:i:s'), PDO::PARAM_STR);
    } else {
      $query->bindValue('item_pk', $item->item_pk, PDO::PARAM_INT);
    }
    $query->bindValue('item_title', $item->item_title, PDO::PARAM_STR);
    $query->bindValue('item_text', $item->item_text, PDO::PARAM_STR);
    $query->bindValue('item_url', $item->item_url, PDO::PARAM_STR);
    $query->bindValue('max_rating', $item->max_rating, PDO::PARAM_INT);
    $query->bindValue('step', $item->step, PDO::PARAM_INT);
    $query->bindValue('visible', $item->visible, PDO::PARAM_INT);
    $query->bindValue('sequence', $item->sequence, PDO::PARAM_INT);
    $query->bindValue('updated', $item->updated->format('Y-m-d H:i:s'), PDO::PARAM_STR);
    $query->bindValue('resource_pk', $resource_pk, PDO::PARAM_INT);

    return $query->execute();

  }


###
###  Delete the ratings for an item
###
  function deleteRatings($db, $item_pk) {

    $prefix = DB_TABLENAME_PREFIX;
    $sql = <<< EOD
DELETE FROM {$prefix}rating
WHERE item_pk = :item_pk
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('item_pk', $item_pk, PDO::PARAM_INT);
    $query->execute();

  }


###
###  Delete a specific item for a specified resource link including any related ratings
###
  function deleteItem($db, $resource_pk, $item_pk) {

// Update order for other items for the same resource link
    reorderItem($db, $resource_pk, $item_pk, 0);

// Delete any ratings
    deleteRatings($db, $item_pk);

// Delete the item
    $prefix = DB_TABLENAME_PREFIX;
    $sql = <<< EOD
DELETE FROM {$prefix}item
WHERE (item_pk = :item_pk) AND (resource_link_pk = :resource_pk)
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('item_pk', $item_pk, PDO::PARAM_INT);
    $query->bindValue('resource_pk', $resource_pk, PDO::PARAM_STR);
    $ok = $query->execute();

    return $ok;

  }


###
###  Change the position of an item in the list displayed for the resource link
###
  function reorderItem($db, $resource_pk, $item_pk, $new_pos) {

    $item = getItem($db, $resource_pk, $item_pk);

    $ok = !empty($item->item_pk);
    if ($ok) {
      $old_pos = $item->sequence;
      $ok = ($old_pos != $new_pos);
    }
    if ($ok) {
      $prefix = DB_TABLENAME_PREFIX;
      if ($new_pos <= 0) {
        $sql = <<< EOD
UPDATE {$prefix}item
SET sequence = sequence - 1
WHERE (resource_link_pk = :resource_pk) AND (sequence > :old_pos)
EOD;
      } else if ($old_pos < $new_pos) {
        $sql = <<< EOD
UPDATE {$prefix}item
SET sequence = sequence - 1
WHERE (resource_link_pk = :resource_pk) AND (sequence > :old_pos) AND (sequence <= :new_pos)
EOD;
      } else {
        $sql = <<< EOD
UPDATE {$prefix}item
SET sequence = sequence + 1
WHERE (resource_link_pk = :resource_pk) AND (sequence < :old_pos) AND (sequence >= :new_pos)
EOD;
      }

      $query = $db->prepare($sql);
      $query->bindValue('resource_pk', $resource_pk, PDO::PARAM_INT);
      $query->bindValue('old_pos', $old_pos, PDO::PARAM_INT);
      if ($new_pos > 0) {
        $query->bindValue('new_pos', $new_pos, PDO::PARAM_INT);
      }

      $ok = $query->execute();

      if ($ok && ($new_pos > 0)) {
        $item->sequence = $new_pos;
        $ok = saveItem($db, $resource_pk, $item);
      }

    }

    return $ok;

  }


###
###  Delete all the ratings for an resource link
###
  function deleteAllRatings($db, $resource_pk) {

    $prefix = DB_TABLENAME_PREFIX;
    $sql = <<< EOD
DELETE FROM {$prefix}rating
WHERE resource_link_pk = :resource_pk
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('resource_pk', $resource_pk, PDO::PARAM_INT);
    $query->execute();

  }


###
###  Delete all items for a specified resource link including any related ratings
###
  function deleteAllItems($db, $resource_pk) {

// Delete any ratings
    deleteAllRatings($db, $resource_pk);

// Delete the items
    $prefix = DB_TABLENAME_PREFIX;
    $sql = <<< EOD
DELETE FROM {$prefix}item
WHERE (resource_link_pk = :resource_pk)
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('resource_pk', $resource_pk, PDO::PARAM_STR);
    $ok = $query->execute();

    return $ok;

  }


###
###  Save the rating for an item for a specified user
###
  function saveRating($db, $user_pk, $item_pk, $rating) {

    $prefix = DB_TABLENAME_PREFIX;
    $sql = <<< EOD
INSERT INTO {$prefix}rating (item_pk, user_pk, rating)
VALUES (:item_pk, :user_pk, :rating)
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('item_pk', $item_pk, PDO::PARAM_INT);
    $query->bindValue('user_pk', $user_pk, PDO::PARAM_INT);
    $query->bindValue('rating', $rating);

    $ok = $query->execute();

    return $ok;

  }


###
###  Update the gradebook with proportion of visible items which have been rated by each user
###
  function updateGradebook($db, $user_resource_pk = NULL, $user_user_pk = NULL) {

    $data_connector = DataConnector\DataConnector::getDataConnector(DB_TABLENAME_PREFIX, $db);
    $consumer = ToolProvider\ToolConsumer::fromRecordId($_SESSION['consumer_pk'], $data_connector);
    $resource_link = ToolProvider\ResourceLink::fromRecordId($_SESSION['resource_pk'], $data_connector);

    $num = getVisibleItemsCount($db, $_SESSION['resource_pk']);
    $ratings = getVisibleRatingsCounts($db, $_SESSION['resource_pk']);
    $users = $resource_link->getUserResultSourcedIDs();
    foreach ($users as $user) {
      $resource_pk = $user->getResourceLink()->getRecordId();
      $user_pk = $user->getRecordId();
      $update = is_null($user_resource_pk) || is_null($user_user_pk) || (($user_resource_pk === $resource_pk) && ($user_user_pk === $user_pk));
      if ($update) {
        if ($num > 0) {
          $count = 0;
          if (isset($ratings[$resource_pk]) && isset($ratings[$resource_pk][$user_pk])) {
            $count = $ratings[$resource_pk][$user_pk];
          }
          $lti_outcome = new ToolProvider\Outcome(strval($count/$num));
          $resource_link->doOutcomesService(ToolProvider\ResourceLink::EXT_WRITE, $lti_outcome, $user);
        } else {
          $lti_outcome = new ToolProvider\Outcome();
          $resource_link->doOutcomesService(ToolProvider\ResourceLink::EXT_DELETE, $lti_outcome, $user);
        }
      }
    }

  }


###
###  Return a count of visible items for a specified resource link
###
  function getVisibleItemsCount($db, $resource_pk) {

    $prefix = DB_TABLENAME_PREFIX;
    $sql = <<< EOD
SELECT COUNT(i.item_pk) count
FROM {$prefix}item i
WHERE (i.resource_link_pk = :resource_pk) AND (i.visible = 1)
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('resource_pk', $resource_pk, PDO::PARAM_STR);
    $query->execute();

    $row = $query->fetch(PDO::FETCH_NUM);
    if ($row === FALSE) {
      $num = 0;
    } else {
      $num = intval($row[0]);
    }

    return $num;

  }


###
###  Return a count of visible ratings made for items for a specified resource link by each user
###
  function getVisibleRatingsCounts($db, $resource_pk) {

    $prefix = DB_TABLENAME_PREFIX;
    $user_table_name = DataConnector\DataConnector::USER_RESULT_TABLE_NAME;
    $sql = <<< EOD
SELECT u.resource_link_pk, r.user_pk, COUNT(r.item_pk) count
FROM {$prefix}item i INNER JOIN {$prefix}rating r ON i.item_pk = r.item_pk
  INNER JOIN {$prefix}{$user_table_name} u ON r.user_pk = u.user_pk
WHERE (i.resource_link_pk = :resource_pk) AND (i.visible = 1)
GROUP BY u.resource_link_pk, r.user_pk
EOD;
    $query = $db->prepare($sql);
    $query->bindValue('resource_pk', $resource_pk, PDO::PARAM_STR);
    $query->execute();

    $rows = $query->fetchAll(PDO::FETCH_OBJ);
    $ratings = array();
    if ($rows !== FALSE) {
      foreach ($rows as $row) {
        $ratings[$row->resource_link_pk][$row->user_pk] = $row->count;
      }
    }

    return $ratings;

  }


###
###  Return an array containing all the ratings for a specific user
###
  function getUserSummary($db, $user_consumer_pk, $user_pk) {

    $prefix = DB_TABLENAME_PREFIX;
    $sql = <<< EOD
SELECT c.context_pk, i.resource_link_pk, i.max_rating, r.rating
FROM {$prefix}lti_context c INNER JOIN  {$prefix}item i ON c.consumer_key = i.consumer_key AND c.context_pk = i.resource_link_pk
  INNER JOIN {$prefix}rating r ON i.item_pk = r.item_pk
WHERE r.consumer_pk = :consumer_pk AND r.user_pk = :user_pk
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('consumer_pk', $user_consumer_pk, PDO::PARAM_INT);
    $query->bindValue('user_pk', $user_pk, PDO::PARAM_STR);
    $query->execute();

    $rows = $query->fetchAll(PDO::FETCH_OBJ);
    if ($rows === FALSE) {
      $rows = array();
    }

    return $rows;

  }


###
###  Return an array containing all of a user's ratings for a specific context
###
  function getUserRatings($db, $context_pk, $user_pk) {

    $prefix = DB_TABLENAME_PREFIX;
    $resource_link_table_name = DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME;
    $sql = <<< EOD
SELECT rl.resource_link_pk, i.max_rating, r.rating
FROM {$prefix}{$resource_link_table_name} rl INNER JOIN  {$prefix}item i ON rl.resource_link_pk = i.resource_link_pk
  INNER JOIN {$prefix}rating r ON i.item_pk = r.item_pk
WHERE rl.context_pk = :context_pk AND r.user_pk = :user_pk
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('context_pk', $context_pk, PDO::PARAM_INT);
    $query->bindValue('user_pk', $user_pk, PDO::PARAM_INT);
    $query->execute();

    $rows = $query->fetchAll(PDO::FETCH_OBJ);
    if ($rows === FALSE) {
      $rows = array();
    }

    return $rows;

  }


###
###  Return an array containing all ratings for a specific context
###
  function getContextRatings($db, $context_pk) {

    $prefix = DB_TABLENAME_PREFIX;
    $resource_link_table_name = DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME;
    $sql = <<< EOD
SELECT rl.resource_link_pk title, i.max_rating, r.rating
FROM {$prefix}{$resource_link_table_name} rl INNER JOIN {$prefix}item i ON rl.resource_link_pk = i.resource_link_pk
  INNER JOIN {$prefix}rating r ON i.item_pk = r.item_pk
WHERE rl.context_pk = :context_pk
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('context_pk', $context_pk, PDO::PARAM_INT);
    $query->execute();

    $rows = $query->fetchAll(PDO::FETCH_OBJ);
    if ($rows === FALSE) {
      $rows = array();
    }

    return $rows;

  }


###
###  Get the web path to the application
###
  function getAppPath() {

    $root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    if (substr($root, -1) === '/') {  // remove any trailing / which should not be there
      $root = substr($root, 0, -1);
    }
    $dir = str_replace('\\', '/', dirname(__FILE__));

    $path = str_replace($root, '', $dir) . '/';

    return $path;

  }


###
###  Get the application domain URL
###
  function getHost() {

    $scheme = (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on")
              ? 'http'
              : 'https';
    $url = $scheme . '://' . $_SERVER['HTTP_HOST'];

    return $url;

  }


###
###  Get the URL to the application
###
  function getAppUrl() {

    $url = getHost() . getAppPath();

    return $url;

  }


###
###  Return a string representation of a float value
###
  function floatToStr($num) {

    $str = sprintf('%f', $num);
    $str = preg_replace('/0*$/', '', $str);
    if (substr($str, -1) == '.') {
      $str = substr($str, 0, -1);
    }

    return $str;

  }


###
###  Return the value of a POST parameter
###
  function postValue($name, $defaultValue = NULL) {

    $value = $defaultValue;
    if (isset($_POST[$name])) {
      $value = $_POST[$name];
    }

    return $value;

  }


/**
 * Returns a string representation of a version 4 GUID, which uses random
 * numbers.There are 6 reserved bits, and the GUIDs have this format:
 *     xxxxxxxx-xxxx-4xxx-[8|9|a|b]xxx-xxxxxxxxxxxx
 * where 'x' is a hexadecimal digit, 0-9a-f.
 *
 * See http://tools.ietf.org/html/rfc4122 for more information.
 *
 * Note: This function is available on all platforms, while the
 * com_create_guid() is only available for Windows.
 *
 * Source: https://github.com/Azure/azure-sdk-for-php/issues/591
 *
 * @return string A new GUID.
 */
  function getGuid() {

    return sprintf('%04x%04x-%04x-%04x-%02x%02x-%04x%04x%04x',
       mt_rand(0, 65535),
       mt_rand(0, 65535),        // 32 bits for "time_low"
       mt_rand(0, 65535),        // 16 bits for "time_mid"
       mt_rand(0, 4096) + 16384, // 16 bits for "time_hi_and_version", with
                                 // the most significant 4 bits being 0100
                                 // to indicate randomly generated version
       mt_rand(0, 64) + 128,     // 8 bits  for "clock_seq_hi", with
                                 // the most significant 2 bits being 10,
                                 // required by version 4 GUIDs.
       mt_rand(0, 256),          // 8 bits  for "clock_seq_low"
       mt_rand(0, 65535),        // 16 bits for "node 0" and "node 1"
       mt_rand(0, 65535),        // 16 bits for "node 2" and "node 3"
       mt_rand(0, 65535)         // 16 bits for "node 4" and "node 5"
      );

  }


###
###  Class representing an item
###
  class Item {

    public $item_pk = NULL;
    public $item_title = '';
    public $item_text = '';
    public $item_url = '';
    public $max_rating = 3;
    public $step = 1;
    public $visible = FALSE;
    public $sequence = 0;
    public $created = NULL;
    public $updated = NULL;
    public $num_ratings = 0;
    public $tot_ratings = 0;

// ensure non-string properties have the appropriate data type
    function __set($name, $value) {
      if ($name == 'mr') {
        $this->max_rating = intval($value);
      } else if ($name == 'st') {
        $this->step = intval($value);
      } else if ($name == 'vis') {
        $this->visible = $value == '1';
      } else if ($name == 'seq') {
        $this->sequence = intval($value);
      } else if ($name == 'cr') {
        $this->created = DateTime::createFromFormat('Y-m-d H:i:s', $value);
      } else if ($name == 'upd') {
        $this->updated = DateTime::createFromFormat('Y-m-d H:i:s', $value);
      } else if ($name == 'num') {
        $this->num_ratings = intval($value);
      } else if ($name == 'total') {
        $this->tot_ratings = floatval($value);
      }

    }

  }

?>