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
 * This page processes a launch request from an LTI tool consumer.
 */

  use IMSGlobal\LTI\Profile;
  use IMSGlobal\LTI\ToolProvider;
  use IMSGlobal\LTI\ToolProvider\Service;

  require_once('lib.php');


  class RatingToolProvider extends ToolProvider\ToolProvider {

    function __construct($data_connector) {

      parent::__construct($data_connector);

      $this->baseUrl = getAppUrl();

      $this->vendor = new Profile\Item('ims', 'IMSGlobal', 'IMS Global Learning Consortium Inc', 'https://www.imsglobal.org/');
      $this->product = new Profile\Item('d751f24f-140e-470f-944c-2d92b114db40', 'Rating', 'Sample LTI tool provider to create lists of items to be rated.',
                                    'http://www.spvsoftwareproducts.com/php/rating/', VERSION);

      $requiredMessages = array(new Profile\Message('basic-lti-launch-request', 'connect.php', array('User.id', 'Membership.role')));
      $optionalMessages = array(new Profile\Message('ContentItemSelectionRequest', 'connect.php', array('User.id', 'Membership.role')),
                                new Profile\Message('DashboardRequest', 'connect.php', array('User.id'), array('a' => 'User.id'), array('b' => 'User.id')));

      $this->resourceHandlers[] = new Profile\ResourceHandler(
        new Profile\Item('rating', 'Rating app', 'An example tool provider which generates lists of items for rating.'), 'images/icon50.png',
        $requiredMessages, $optionalMessages);

      $this->requiredServices[] = new Profile\ServiceDefinition(array('application/vnd.ims.lti.v2.toolproxy+json'), array('POST'));

    }

    function onLaunch() {

      global $db;

// Check the user has an appropriate role
      if ($this->user->isLearner() || $this->user->isStaff()) {
// Initialise the user session
        $_SESSION['consumer_pk'] = $this->consumer->getRecordId();
        $_SESSION['resource_pk'] = $this->resourceLink->getRecordId();
        $_SESSION['user_consumer_pk'] = $this->user->getResourceLink()->getConsumer()->getRecordId();
        $_SESSION['user_resource_pk'] = $this->user->getResourceLink()->getRecordId();
        $_SESSION['user_pk'] = $this->user->getRecordId();
        $_SESSION['isStudent'] = $this->user->isLearner();
        $_SESSION['isContentItem'] = FALSE;

// Redirect the user to display the list of items for the resource link
        $this->redirectUrl = getAppUrl();

      } else {

        $this->reason = 'Invalid role.';
        $this->ok = FALSE;

      }

    }

    function onContentItem() {

// Check that the Tool Consumer is allowing the return of an LTI link
      $this->ok = in_array(ToolProvider\ContentItem::LTI_LINK_MEDIA_TYPE, $this->mediaTypes) || in_array('*/*', $this->mediaTypes);
      if (!$this->ok) {
        $this->reason = 'Return of an LTI link not offered';
      } else {
        $this->ok = !in_array('none', $this->documentTargets) || (count($this->documentTargets) > 1);
        if (!$this->ok) {
          $this->reason = 'No visible document target offered';
        }
      }
      if ($this->ok) {
// Initialise the user session
        $_SESSION['consumer_pk'] = $this->consumer->getRecordId();
        $_SESSION['resource_id'] = getGuid();
        $_SESSION['resource_pk'] = NULL;
        $_SESSION['user_consumer_pk'] = $_SESSION['consumer_pk'];
        $_SESSION['user_pk'] = NULL;
        $_SESSION['isStudent'] = FALSE;
        $_SESSION['isContentItem'] = TRUE;
        $_SESSION['lti_version'] = $_POST['lti_version'];
        $_SESSION['return_url'] = $this->returnUrl;
        $_SESSION['title'] = postValue('title');
        $_SESSION['text'] = postValue('text');
        $_SESSION['data'] = postValue('data');
        $_SESSION['document_targets'] = $this->documentTargets;
// Redirect the user to display the list of items for the resource link
        $this->redirectUrl = getAppUrl();
      }

    }

    function onDashboard() {

      global $db;

      $title = APP_NAME;
      $app_url = 'http://www.spvsoftwareproducts.com/php/rating/';
      $icon_url = getAppUrl() . 'images/icon50.png';
      $context_id = postValue('context_id', '');
      if (empty($this->context)) {
        $ratings = getUserSummary($db, $this->user->getResourceLink()->getConsumer()->getRecordId(), $this->user->getRecordId());
        $num_ratings = count($ratings);
        $courses = array();
        $lists = array();
        $tot_rating = 0;
        foreach ($ratings as $rating) {
          $courses[$rating->lti_context_id] = TRUE;
          $lists[$rating->resource_id] = TRUE;
          $tot_rating += ($rating->rating / $rating->max_rating);
        }
        $num_courses = count($courses);
        $num_lists = count($lists);
        if ($num_ratings > 0) {
          $av_rating = floatToStr($tot_rating / $num_ratings * 5);
        }
        $html = <<< EOD
        <p>
          Here is a summary of your rating of items:
        </p>
        <ul>
          <li><em>Number of courses:</em> {$num_courses}</li>
          <li><em>Number of rating lists:</em> {$num_lists}</li>
          <li><em>Number of ratings made:</em> {$num_ratings}</li>

EOD;
        if ($num_ratings > 0) {
          $html .= <<< EOD
          <li><em>Average rating:</em> {$av_rating} out of 5</li>

EOD;
        }
        $html .= <<< EOD
        </ul>

EOD;
        $this->output = $html;
      } else {
        if ($this->user->isLearner()) {
          $ratings = getUserRatings($db, $this->context->getRecordId(), $this->user->getRecordId());
        } else {
          $ratings = getContextRatings($db, $this->context->getRecordId());
        }
        $resources = array();
        $totals = array();
        foreach ($ratings as $rating) {
          $tot = ($rating->rating / $rating->max_rating);
          if (array_key_exists($rating->title, $resources)) {
            $resources[$rating->title] += 1;
            $totals[$rating->title] += $tot;
          } else {
            $resources[$rating->title] = 1;
            $totals[$rating->title] = $tot;
          }
        }
        ksort($resources);
        $items = '';
        $n = 0;
        foreach ($resources as $title => $value) {
          $n++;
          $av = floatToStr($totals[$title] / $value * 5);
          $plural = '';
          if ($value <> 1) {
            $plural = 's';
          }
          $items .= <<< EOD
    <item>
      <title>Link {$n}</title>
      <description>{$value} item{$plural} rated (average {$av} out of 5)</description>
    </item>
EOD;
        }
        $rss = <<< EOD
<rss xmlns:a10="http://www.w3.org/2005/Atom" version="2.0">
  <channel>
    <title>Dashboard</title>
    <link>{$app_url}</link>
    <description />
    <image>
      <url>{$icon_url}</url>
      <title>Dashboard</title>
      <link>{$app_url}</link>
      <description>{$title} Dashboard</description>
    </image>{$items}
  </channel>
</rss>
EOD;
        header('Content-type: text/xml');
        $this->output = $rss;
      }

    }

    function onRegister() {

// Initialise the user session
      $_SESSION['consumer_pk'] = $this->consumer->getRecordId();
      $_SESSION['tc_profile_url'] = $_POST['tc_profile_url'];
      $_SESSION['tc_profile'] = $this->consumer->profile;
      $_SESSION['return_url'] = $_POST['launch_presentation_return_url'];

// Redirect the user to process the registration
      $this->redirectUrl = getAppUrl() . 'register.php';

    }

    function onError() {

      $msg = $this->message;
      if ($this->debugMode && !empty($this->reason)) {
        $msg = $this->reason;
      }
      $title = APP_NAME;

      $this->errorOutput = <<< EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en" xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="content-language" content="EN" />
<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
<title>{$title}</title>
<link href="css/rateit.css" media="screen" rel="stylesheet" type="text/css" />
<script src="js/jquery.min.js" type="text/javascript"></script>
<script src="js/jquery.rateit.min.js" type="text/javascript"></script>
<link href="css/rating.css" media="screen" rel="stylesheet" type="text/css" />
</head>
<body>
<h1>Error</h1>
<p style="font-weight: bold; color: #f00;">{$msg}</p>
</body>
</html>
EOD;
    }

  }

?>
