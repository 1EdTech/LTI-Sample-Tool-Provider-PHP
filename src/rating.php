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
 * This page processes an AJAX request to save a user rating for an item.
 */

  require_once('lib.php');

// Initialise session and database
  $db = NULL;
  $ok = init($db, TRUE);
  if ($ok) {
// Ensure request is complete and for a student
    $ok = isset($_POST['id']) && isset($_POST['value']) && $_SESSION['isStudent'];
  }
  if ($ok) {
// Save rating
    $ok = FALSE;
    $item = getItem($db, $_SESSION['resource_pk'], intval($_POST['id']));
    if (($item !== FALSE) && saveRating($db, $_SESSION['user_pk'], $_POST['id'], $_POST['value'])) {
      updateGradebook($db, $_SESSION['user_resource_pk'], $_SESSION['user_pk']);
      $ok = TRUE;
    }
  }

// Generate response
  if ($ok) {
    $response = array('response' => 'Success');
  } else {
    $response = array('response' => 'Fail');
  }

// Return response
  header('Content-type: application/json');
  echo json_encode($response);

?>
