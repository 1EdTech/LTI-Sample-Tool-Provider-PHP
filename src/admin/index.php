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
 * This page manages the definition of tool consumer records.  A tool consumer record is required to
 * enable each VLE to securely connect to this application.
 *
 * *** IMPORTANT ***
 * Access to this page should be restricted to prevent unauthorised access to the configuration of tool
 * consumers (for example, using an entry in an Apache .htaccess file); access to all other pages is
 * authorised by LTI.
 * ***           ***
*/

  use IMSGlobal\LTI\ToolProvider;
  use IMSGlobal\LTI\ToolProvider\DataConnector;

  require_once('../lib.php');

// Initialise session and database
  $db = NULL;
  $ok = init($db, FALSE);
// Initialise parameters
  $id = NULL;
  if ($ok) {
// Create LTI Tool Provider instance
    $data_connector = DataConnector\DataConnector::getDataConnector(DB_TABLENAME_PREFIX, $db);
    $tool = new ToolProvider\ToolProvider($data_connector);
// Check for consumer id and action parameters
    $action = '';
    if (isset($_REQUEST['id'])) {
      $id = intval($_REQUEST['id']);
    }
    if (isset($_REQUEST['do'])) {
      $action = $_REQUEST['do'];
    }

// Process add consumer action
    if (($action == 'add') && (!empty($id) || !empty($_REQUEST['key']))) {
      if (empty($id)) {
        $update_consumer = new ToolProvider\ToolConsumer($_REQUEST['key'], $data_connector);
        $update_consumer->ltiVersion = ToolProvider\ToolProvider::LTI_VERSION1;
      } else {
        $update_consumer = ToolProvider\ToolConsumer::fromRecordId($id, $data_connector);
      }
      $update_consumer->name = $_POST['name'];
      if (isset($_POST['secret'])) {
        $update_consumer->secret = $_POST['secret'];
      }
      $update_consumer->enabled = isset($_POST['enabled']);
      $date = $_POST['enable_from'];
      if (empty($date)) {
        $update_consumer->enableFrom = NULL;
      } else {
        $update_consumer->enableFrom = strtotime($date);
      }
      $date = $_POST['enable_until'];
      if (empty($date)) {
        $update_consumer->enableUntil = NULL;
      } else {
        $update_consumer->enableUntil = strtotime($date);
      }
      $update_consumer->protected = isset($_POST['protected']);
// Ensure all required fields have been provided
      if ($update_consumer->save()) {
        $_SESSION['message'] = 'The consumer has been saved.';
      } else {
        $_SESSION['error_message'] = 'Unable to save the consumer; please check the data and try again.';
      }
      header('Location: ./');
      exit;

// Process delete consumer action
    } else if ($action == 'delete') {
      if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ok = true;
        foreach ($_POST['ids'] as $id) {
          $consumer = ToolProvider\ToolConsumer::fromRecordId($id, $data_connector);
          $ok = $ok && $consumer->delete();
        }
        if ($ok) {
          $_SESSION['message'] = 'The selected consumers have been deleted.';
        } else {
          $_SESSION['error_message'] = 'Unable to delete at least one of the selected consumers; please try again.';
        }
      } else {
        $consumer = ToolProvider\ToolConsumer::fromRecordId($id, $data_connector);
        if ($consumer->delete()) {
          $_SESSION['message'] = 'The consumer has been deleted.';
        } else {
          $_SESSION['error_message'] = 'Unable to delete the consumer; please try again.';
        }
      }
      header('Location: ./');
      exit;

    } else {
// Initialise an empty tool consumer instance
      $update_consumer = new ToolProvider\ToolConsumer(NULL, $data_connector);
    }

// Fetch a list of existing tool consumer records
    $consumers = $tool->getConsumers();

// Set launch URL for information
    $launchUrl = getAppUrl() . 'connect.php';

  }

// Page header
  $title = APP_NAME . ': Manage tool consumers';
  $page = <<< EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en" xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="content-language" content="EN" />
<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
<title>{$title}</title>
<link href="../css/rating.css" media="screen" rel="stylesheet" type="text/css" />
<script type="text/javascript">
//<![CDATA[
var numSelected = 0;
function toggleSelect(el) {
  if (el.checked) {
    numSelected++;
  } else {
    numSelected--;
  }
  document.getElementById('delsel').disabled = (numSelected <= 0);
}
//]]>
</script>
</head>

<body>
<h1>{$title}</h1>

EOD;

// Display warning message if access does not appear to have been restricted
  if (!(isset($_SERVER['AUTH_TYPE']) && isset($_SERVER['REMOTE_USER']) && isset($_SERVER['PHP_AUTH_PW']))) {
    $page .= <<< EOD
<p><strong>*** WARNING *** Access to this page should be restricted to application administrators only.</strong></p>

EOD;
  }

// Check for any messages to be displayed
  if (isset($_SESSION['error_message'])) {
  $page .= <<< EOD
<p style="font-weight: bold; color: #f00;">ERROR: {$_SESSION['error_message']}</p>

EOD;
    unset($_SESSION['error_message']);
  }

  if (isset($_SESSION['message'])) {
  $page .= <<< EOD
<p style="font-weight: bold; color: #00f;">{$_SESSION['message']}</p>

EOD;
    unset($_SESSION['message']);
  }

// Display table of existing tool consumer records
  if ($ok) {

    if (count($consumers) <= 0) {
      $page .= <<< EOD
<p>No consumers have been added yet.</p>

EOD;
    } else {
      $page .= <<< EOD
<form action="./?do=delete" method="post" onsubmit="return confirm('Delete selected consumers; are you sure?');">
<table class="items" border="1" cellpadding="3">
<thead>
  <tr>
    <th>&nbsp;</th>
    <th>Name</th>
    <th>Key</th>
    <th>Version</th>
    <th>Available?</th>
    <th>Protected?</th>
    <th>Last access</th>
    <th>Options</th>
  </tr>
</thead>
<tbody>

EOD;
      foreach ($consumers as $consumer) {
        $trid = urlencode($consumer->getRecordId());
        if ($consumer->getRecordId() === $id) {
          $update_consumer = $consumer;
        }
        if (!$consumer->getIsAvailable()) {
          $available = 'cross';
          $available_alt = 'Not available';
          $trclass = 'notvisible';
        } else {
          $available = 'tick';
          $available_alt = 'Available';
          $trclass = '';
        }
        if ($consumer->protected) {
          $protected = 'tick';
          $protected_alt = 'Protected';
        } else {
          $protected = 'cross';
          $protected_alt = 'Not protected';
        }
        if (is_null($consumer->lastAccess)) {
          $last = 'None';
        } else {
          $last = date('j-M-Y', $consumer->lastAccess);
        }
        $page .= <<< EOD
  <tr class="{$trclass}">
    <td><input type="checkbox" name="ids[]" value="{$trid}" onclick="toggleSelect(this);" /></td>
    <td>{$consumer->name}</td>
    <td>{$consumer->getKey()}</td>
    <td><span title="{$consumer->consumerGuid}">{$consumer->consumerVersion}</span></td>
    <td class="aligncentre"><img src="../images/{$available}.gif" alt="{$available_alt}" title="{$available_alt}" /></td>
    <td class="aligncentre"><img src="../images/{$protected}.gif" alt="{$protected_alt}" title="{$protected_alt}" /></td>
    <td>{$last}</td>
    <td class="iconcolumn aligncentre">
      <a href="./?id={$trid}#edit"><img src="../images/edit.png" title="Edit consumer" alt="Edit consumer" /></a>&nbsp;<a href="./?do=delete&amp;id={$trid}" onclick="return confirm('Delete consumer; are you sure?');"><img src="../images/delete.png" title="Delete consumer" alt="Delete consumer" /></a>
    </td>
  </tr>

EOD;
      }
      $page .= <<< EOD
</tbody>
</table>
<p>
<input type="submit" value="Delete selected tool consumers" id="delsel" disabled="disabled" />
</p>
</form>

EOD;

    }

// Display form for adding/editing a tool consumer
    $update = '';
    $lti2 = '';
    if (!isset($update_consumer->created)) {
      $mode = 'Add new';
    } else {
      $mode = 'Update';
      $update = ' disabled="disabled"';
      if ($update_consumer->ltiVersion === ToolProvider\ToolProvider::LTI_VERSION2) {
        $lti2 = ' disabled="disabled"';
      }
    }
    $name = htmlentities($update_consumer->name);
    $key = htmlentities($update_consumer->getKey());
    $secret = htmlentities($update_consumer->secret);
    if ($update_consumer->enabled) {
      $enabled = ' checked="checked"';
    } else {
      $enabled = '';
    }
    $enable_from = '';
    if (!is_null($update_consumer->enableFrom)) {
      $enable_from = date('j-M-Y H:i', $update_consumer->enableFrom);
    }
    $enable_until = '';
    if (!is_null($update_consumer->enableUntil)) {
      $enable_until = date('j-M-Y H:i', $update_consumer->enableUntil);
    }
    if ($update_consumer->protected) {
      $protected = ' checked="checked"';
    } else {
      $protected = '';
    }
    $page .= <<< EOD
<h2><a name="edit">{$mode} consumer</a></h2>

<form action="./" method="post">
<div class="box">
  <span class="label">Name:<span class="required" title="required">*</span></span>&nbsp;<input name="name" type="text" size="50" maxlength="50" value="{$name}" /><br />
  <span class="label">Key:<span class="required" title="required">*</span></span>&nbsp;<input name="key" type="text" size="75" maxlength="50" value="{$key}"{$update} /><br />
  <span class="label">Secret:<span class="required" title="required">*</span></span>&nbsp;<input name="secret" type="text" size="75" maxlength="200" value="{$secret}"{$lti2} /><br />
  <span class="label">Enabled?</span>&nbsp;<input name="enabled" type="checkbox" value="1"{$enabled} /><br />
  <span class="label">Enable from:</span>&nbsp;<input name="enable_from" type="text" size="50" maxlength="200" value="{$enable_from}" /><br />
  <span class="label">Enable until:</span>&nbsp;<input name="enable_until" type="text" size="50" maxlength="200" value="{$enable_until}" /><br />
  <span class="label">Protected?</span>&nbsp;<input name="protected" type="checkbox" value="1"{$protected} /><br />
  <br />
  <input type="hidden" name="do" value="add" />
  <input type="hidden" name="id" value="{$id}" />
  <span class="label"><span class="required" title="required">*</span>&nbsp;=&nbsp;required field</span>&nbsp;<input type="submit" value="{$mode} consumer" />

EOD;

    if (isset($update_consumer->created)) {
      $page .= <<< EOD
  &nbsp;<input type="reset" value="Cancel" onclick="location.href='./';" />

EOD;

    }
  }

// Page footer
  $page .= <<< EOD
</div>
<p class="clear">
NB The launch URL for this instance is {$launchUrl}
</p>
</form>
</body>
</html>

EOD;

// Display page
  echo $page;

?>
