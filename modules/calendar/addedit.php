<?php
if (!defined('W2P_BASE_DIR')) {
	die('You should not access this file directly.');
}
global $AppUI, $cal_sdf;
$AppUI->loadCalendarJS();

$event_id = intval(w2PgetParam($_GET, 'event_id', 0));
$is_clash = isset($_SESSION['event_is_clash']) ? $_SESSION['event_is_clash'] : false;

$obj = new CEvent();
$obj->event_id = $event_id;

$canAddEdit = $obj->canAddEdit();
$canAuthor = $obj->canCreate();
$canEdit = $obj->canEdit();
if (!$canAddEdit) {
	$AppUI->redirect(ACCESS_DENIED);
}

// get the passed timestamp (today if none)
$date = w2PgetParam($_GET, 'date', null);
$time = w2PgetParam($_GET, 'time', null);
// get the passed timestamp (today if none)
$event_project = (int) w2PgetParam($_GET, 'event_project', 0);

// load the record data
if ($is_clash) {
	$obj->bind($_SESSION['add_event_post']);
} else {
	if (!$obj->load($event_id) && $event_id) {
		$AppUI->setMsg('Event');
		$AppUI->setMsg('invalidID', UI_MSG_ERROR, true);
		$AppUI->redirect();
	}
}
$obj->event_project = ($event_project) ? $event_project : $obj->event_project;

// load the event types
$types = w2PgetSysVal('EventType');

// Load the users
$perms = &$AppUI->acl();
$users = $perms->getPermittedUsers('calendar');

//check if the user has view permission over the project
if ($obj->event_project && !$perms->checkModuleItem('projects', 'view', $obj->event_project)) {
	$AppUI->redirect(ACCESS_DENIED);
}

// setup the title block
$titleBlock = new w2p_Theme_TitleBlock(($event_id ? 'Edit Event' : 'Add Event'), 'myevo-appointments.png', $m, $m . '.' . $a);
$titleBlock->addCrumb('?m=calendar', 'month view');
if ($event_id) {
	$titleBlock->addCrumb('?m=calendar&amp;a=view&event_id=' . $event_id, 'view this event');
}
$titleBlock->show();

// format dates
$df = $AppUI->getPref('SHDATEFORMAT');

// pull projects
$all_projects = '(' . $AppUI->_('All', UI_OUTPUT_RAW) . ')';

$prj = new CProject();
$projects = $prj->getAllowedProjects($AppUI->user_id);
foreach ($projects as $project_id => $project_info) {
	$projects[$project_id] = $project_info['project_name'];
}
$projects = arrayMerge(array(0 => $all_projects), $projects);

$inc = intval(w2PgetConfig('cal_day_increment')) ? intval(w2PgetConfig('cal_day_increment')) : 30;
if (!$event_id && !$is_clash) {

	$seldate = new w2p_Utilities_Date($date, $AppUI->getPref('TIMEZONE'));
	// If date is today, set start time to now + inc
	if ($date == date('Ymd')) {
		$h = date('H');
		// an interval after now.
		$min = intval(date('i') / $inc) + 1;
		$min *= $inc;
		if ($min > 60) {
			$min = 0;
			$h++;
		}
	}
	if(!empty($time)){
		$h=(int)substr($time,0,2);
		$min=intval((int)substr($time,2,2)/$inc);
		$min *= $inc;
		if ($min > 60) {
			$min = 0;
			$h++;
		}
	}
	if ($h && $h < w2PgetConfig('cal_day_end') && $h>w2PgetConfig('cal_day_start')) {
		$seldate->setTime($h, $min, 0);
        $seldate->convertTZ('UTC');
		$obj->event_start_date = $seldate->format(FMT_TIMESTAMP);
		$seldate->addSeconds($inc * 60);
        $seldate->convertTZ('UTC');
		$obj->event_end_date = $seldate->format(FMT_TIMESTAMP);
	} else {
		$seldate->setTime(w2PgetConfig('cal_day_start'), 0, 0);
        $seldate->convertTZ('UTC');
		$obj->event_start_date = $seldate->format(FMT_TIMESTAMP);
        $seldate->convertTZ($AppUI->getPref('TIMEZONE'));
		$seldate->setTime(w2PgetConfig('cal_day_end'), 0, 0);
        $seldate->convertTZ('UTC');
		$obj->event_end_date = $seldate->format(FMT_TIMESTAMP);
	}
}

$start_date = intval($obj->event_start_date) ? new w2p_Utilities_Date($AppUI->formatTZAwareTime($obj->event_start_date, '%Y-%m-%d %T')) : null;
$end_date = intval($obj->event_end_date) ? new w2p_Utilities_Date($AppUI->formatTZAwareTime($obj->event_end_date, '%Y-%m-%d %T')) : $start_date;

$recurs = array('Never', 'Hourly', 'Daily', 'Weekly', 'Bi-Weekly', 'Every Month', 'Quarterly', 'Every 6 months', 'Every Year');

$remind = array (
	"900" => '15 '.$AppUI->_('mins'),
	"1800" => '30 '.$AppUI->_('mins'),
	"3600" => '1 '.$AppUI->_('hour'),
	"7200" => '2 '.$AppUI->_('hours'),
	"14400" => '4 '.$AppUI->_('hours'),
	"28800" => '8 '.$AppUI->_('hours'),
//	"56600" => '16 '.$AppUI->_('hours'),
	"86400" => '1 '.$AppUI->_('day'),
	"172800" => '2 '.$AppUI->_('days')
);

// build array of times in 30 minute increments
$times = array();
$t = new w2p_Utilities_Date();
$t->setTime(0, 0, 0);
//$m clashes with global $m (module)
for ($minutes = 0; $minutes < ((24 * 60) / $inc); $minutes++) {
	$times[$t->format('%H%M%S')] = $t->format($AppUI->getPref('TIMEFORMAT'));
	$t->addSeconds($inc * 60);
}
?>
<script language="javascript" type="text/javascript" src="/modules/calendar/addedit.js">
</script>
<form name="editFrm" action="?m=calendar" method="post" accept-charset="utf-8">
	<input type="hidden" name="dosql" value="do_event_aed" />
	<input type="hidden" name="event_id" value="<?php echo $event_id; ?>" />
	<input type="hidden" name="event_assigned" value="" />
    <input type="hidden" name="datePicker" value="event" />

<table border="0" cellpadding="4" cellspacing="0" width="100%" class="std addedit">
<tr>
	<td colspan="2">
		<table cellspacing="1" cellpadding="2" width="100%" class="well">
			<tr>
				<td width="20%" align="right" nowrap="nowrap"><?php echo $AppUI->_('Event Title'); ?>:</td>
				<td width="20%">
					<input type="text" class="text" size="25" name="event_name" value="<?php echo $obj->event_name; ?>" maxlength="255" />
				</td>
				<td align="left" rowspan="4" valign="top" colspan="2" width="40%">
					<?php echo $AppUI->_('Description'); ?> :<br/>
					<textarea class="textarea" name="event_description" rows="5" cols="45"><?php echo $obj->event_description; ?></textarea>
				</td>
			</tr>
			<tr>
				<td align="right"><?php echo $AppUI->_('Type'); ?>:</td>
				<td>
					<?php
					echo arraySelect($types, 'event_type', 'size="1" class="text"', $obj->event_type, true);
					?>
				</td>
			</tr>
			<tr>
				<td align="right"><?php echo $AppUI->_('Project'); ?>:</td>
				<td>
					<?php
					echo arraySelect($projects, 'event_project', 'size="1" class="text"', $obj->event_project);
					?>
				</td>
			</tr>
			<tr>
				<td align="right" nowrap="nowrap"><label for="event_owner"><?php echo $AppUI->_('Event Owner'); ?>:</label></td>
				<td>
					<?php
					echo arraySelect($users, 'event_owner', 'size="1" class="text"', ($obj->event_owner ? $obj->event_owner : $AppUI->user_id));
					?>
				</td>
			</tr>
			<tr>
				<td align="right" nowrap="nowrap"><label for="event_private"><?php echo $AppUI->_('Private Entry'); ?>:</label></td>
				<td>
					<input type="checkbox" value="1" name="event_private" id="event_private" <?php echo ($obj->event_private ? 'checked="checked"' : ''); ?> />
				</td>
			</tr>
			<tr>
				<td align="right" nowrap="nowrap"><?php echo $AppUI->_('Start Date'); ?>:</td>
				<td nowrap="nowrap">
					<input type="hidden" name="event_start_date" id="event_start_date" value="<?php echo $start_date ? $start_date->format(FMT_TIMESTAMP_DATE) : ''; ?>" />
					<input type="text" name="start_date" id="start_date" onchange="setDate_new('editFrm', 'start_date');" value="<?php echo $start_date ? $start_date->format($df) : ''; ?>" class="text" />
					<a href="javascript: void(0);" onclick="return showCalendar('start_date', '<?php echo $df ?>', 'editFrm', null, true, true)">
						<img src="<?php echo w2PfindImage('calendar.gif'); ?>" width="24" height="12" alt="<?php echo $AppUI->_('Calendar'); ?>" border="0" />
					</a>
					<?php echo '&nbsp;&nbsp;&nbsp;' .$AppUI->_('Time') . ':' . arraySelect($times, 'start_time', 'size="1" class="text"', $AppUI->formatTZAwareTime($obj->event_start_date, '%H%M%S')); ?>
				</td>
			</tr>

			<tr>
				<td align="right" nowrap="nowrap"><?php echo $AppUI->_('End Date'); ?>:</td>
				<td nowrap="nowrap">
					<input type="hidden" name="event_end_date" id="event_end_date" value="<?php echo $end_date ? $end_date->format(FMT_TIMESTAMP_DATE) : ''; ?>" />
					<input type="text" name="end_date" id="end_date" onchange="setDate_new('editFrm', 'end_date');" value="<?php echo $end_date ? $end_date->format($df) : ''; ?>" class="text" />
					<a href="javascript: void(0);" onclick="return showCalendar('end_date', '<?php echo $df ?>', 'editFrm', null, true, true)">
						<img src="<?php echo w2PfindImage('calendar.gif'); ?>" width="24" height="12" alt="<?php echo $AppUI->_('Calendar'); ?>" border="0" />
					</a>
					<?php echo '&nbsp;&nbsp;&nbsp;' . $AppUI->_('Time') . ':' . arraySelect($times, 'end_time', 'size="1" class="text"', $AppUI->formatTZAwareTime($obj->event_end_date, '%H%M%S')); ?>
				</td>
			</tr>
			<tr>
				<td align="right" nowrap="nowrap"><?php echo $AppUI->_('Recurs'); ?>:</td>
				<td>
					<?php echo arraySelect($recurs, 'event_recurs', 'size="1" class="text"', $obj->event_recurs, true); ?>
					&nbsp;&nbsp;&nbsp; x<input type="text" class="text" name="event_times_recuring" value="<?php echo ((isset($obj->event_times_recuring)) ? ($obj->event_times_recuring) : '1'); ?>" maxlength="2" size="3" /> <?php echo $AppUI->_('times'); ?>
				</td>
			</tr>
			<tr>
				<td align="right" nowrap="nowrap"><?php echo $AppUI->_('Remind Me'); ?>:</td>
				<td><?php echo arraySelect($remind, 'event_remind', 'size="1" class="text"', $obj->event_remind); ?> <?php echo $AppUI->_('in advance'); ?></td>
			</tr>
			<tr>
				<td align="right" nowrap="nowrap"><label for="event_cwd"><?php echo $AppUI->_('Show only on Working Days'); ?>:</label></td>
				<td>
					<input type="checkbox" value="1" name="event_cwd" id="event_cwd" <?php echo ($obj->event_cwd ? 'checked="checked"' : ''); ?> />
				</td>
			</tr>
			<tr>
				<td colspan="2" align="right">
					<?php
					  // $m does not equal 'calendar' here???
					  $custom_fields = new w2p_Core_CustomFields('calendar', 'addedit', $obj->event_id, 'edit');
					  $custom_fields->printHTML();
					  ?>
				</td>
			<tr>
				<td colspan="2">
					<input type="button" value="<?php echo $AppUI->_('back'); ?>" class="button btn btn-danger" onclick="javascript:history.back();" />
				</td>
				<td align="right" colspan="2">
					<input type="button" value="<?php echo $AppUI->_('submit'); ?>" class="button btn btn-primary" onclick="submitIt()" />
				</td>
			</tr>
		</table>
	</td>
</tr>
</table>

</form>
<?php

$tabBox = new CTabBox();
$tabBox->add(W2P_BASE_DIR . '/modules/calendar/ae_human_resource', 'Human Resources');
$tabBox->show('', true);
?>
