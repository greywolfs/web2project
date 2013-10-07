<?php
if (!defined('W2P_BASE_DIR')) {
	die('You should not access this file directly.');
}

$AppUI->savePlace();
$AppUI->loadCalendarJS();
$perms = &$AppUI->acl();
// retrieve any state parameters
$user_id = $AppUI->user_id;
if ($AppUI->user_is_admin || $AppUI->user_is_controller) { // Only sysadmins or controllers are able to change users
	if (w2PgetParam($_POST, 'user_id', 0) != 0) { // this means that
		$user_id = w2PgetParam($_POST, 'user_id', 0);
		$AppUI->setState('user_id', $_POST['user_id']);
	} elseif ($AppUI->getState('user_id')) {
		$user_id = $AppUI->getState('user_id');
	} else {
		$AppUI->setState('user_id', $user_id);
	}
}

$projectPriorityFilter=array('not selected','low','normal','high');
if (isset($_POST['projectPriorityFilterChoose'])) {
	$AppUI->setState('projectPriorityIdxFilter', $_POST['projectPriorityFilterChoose']);
}
$projectPriorityFilterChoose = $AppUI->getState('projectPriorityIdxFilter') ? $AppUI->getState('projectPriorityIdxFilter') : 'not_selected';

if (isset($_POST['f'])) {
	$AppUI->setState('TaskIdxFilter', $_POST['f']);
}
$f = $AppUI->getState('TaskIdxFilter') ? $AppUI->getState('TaskIdxFilter') :
        w2PgetConfig('task_filter_default', 'myunfinished');

if (isset($_POST['f2'])) {
	$AppUI->setState('CompanyIdxFilter', $_POST['f2']);
}

$f2 = ($AppUI->getState('CompanyIdxFilter')) ? $AppUI->getState('CompanyIdxFilter') :
        ((w2PgetConfig('company_filter_default', 'user') == 'user') ? $AppUI->user_company : 'allcompanies');

if (isset($_POST['fDepartmentsFilter'])) {
	$AppUI->setState('fDepartmentsFilter', (int)$_POST['fDepartmentsFilter']);
	$AppUI->setState('sDepartmentsFilter', $_POST['sDepartmentsFilter']);
}
if (isset($_POST['project_create_date_start'])) {
	$AppUI->setState('project_create_date_start_filter', $_POST['project_create_date_start']);
}
if (isset($_POST['project_create_date_end'])) {
	$AppUI->setState('project_create_date_end_filter', $_POST['project_create_date_end']);
}
$fDepartmentsFilter = $AppUI->getState('fDepartmentsFilter') ? $AppUI->getState('fDepartmentsFilter') :	'0';
$sDepartmentsFilter = $AppUI->getState('sDepartmentsFilter') ? $AppUI->getState('sDepartmentsFilter') :	'only';
$sDepartmentsFilterArray = array('only'=>'Только','without'=>'Исключая');
$departmentListArray = CDepartment::getDepartmentList($AppUI, (int)$f2);
$departmentList=array('0'=>'Все департаменты');
foreach ($departmentListArray as $dep){
	$departmentList[$dep['dept_id']]=$dep['dept_name'];
}

if (isset($_GET['project_id'])) {
	$AppUI->setState('TaskIdxProject', w2PgetParam($_GET, 'project_id', null));
}
$project_id = $AppUI->getState('TaskIdxProject') ? $AppUI->getState('TaskIdxProject') : 0;
if (isset($_POST['show_task_options'])) {
	$AppUI->setState('TaskListShowIncomplete', w2PgetParam($_POST, 'show_incomplete', 0));
}
$showIncomplete = $AppUI->getState('TaskListShowIncomplete', 0);

// get CCompany() to filter tasks by company
$obj = new CCompany();
$companies = $obj->getAllowedRecords($AppUI->user_id, 'company_id,company_name', 'company_name');
$filters2 = arrayMerge(array('allcompanies' => $AppUI->_('All Companies', UI_OUTPUT_RAW)), $companies);

// setup the title block
$titleBlock = new w2p_Theme_TitleBlock('Tasks', 'applet-48.png', $m, $m . '.' . $a);

// patch 2.12.04 text to search entry box
if (isset($_POST['searchtext'])) {
	$AppUI->setState('searchtext', $_POST['searchtext']);
}

$search_text = $AppUI->getState('searchtext') ? $AppUI->getState('searchtext') : '';
$search_text = w2PformSafe($search_text, true);

$titleBlock->addCell($AppUI->_('Search') . ':<form action="?m=tasks" method="post" id="searchfilter" accept-charset="utf-8"><input type="text" class="text" size="20" name="searchtext" onChange="document.searchfilter.submit();" value="' . $search_text . '" title="' . $AppUI->_('Search in name and description fields') . '"/></form>');

// Let's see if this user has admin privileges
if ($AppUI->user_is_admin || $AppUI->user_is_controller) {
	$user_list = $perms->getPermittedUsers('tasks');
	$titleBlock->addCell($AppUI->_('User') . ':<form action="?m=tasks" method="post" name="userIdForm" accept-charset="utf-8">' . arraySelect($user_list, 'user_id', 'size="1" class="text" onChange="document.userIdForm.submit();"', $user_id, false) . '</form>');
}

$titleBlock->addCell($AppUI->_('Departments') . ':<form action="?m=tasks" method="post" name="departmentsFilter" accept-charset="utf-8">' . arraySelect($sDepartmentsFilterArray, 'sDepartmentsFilter', 'class="text" onChange="document.departmentsFilter.submit();"', $sDepartmentsFilter, false) . arraySelect($departmentList, 'fDepartmentsFilter', 'size="1" style="max-width: 125px;" class="text" onChange="document.departmentsFilter.submit();"', $fDepartmentsFilter, false) . '</form>');

$titleBlock->addCell($AppUI->_('Company') . ':<form action="?m=tasks" method="post" name="companyFilter" accept-charset="utf-8">' . arraySelect($filters2, 'f2', 'size="1" class="text" onChange="document.companyFilter.submit();"', $f2, false) . '</form>');

$titleBlock->addCell($AppUI->_('Project priority') . ':<form action="?m=tasks" method="post" name="projectPriorityFilter" accept-charset="utf-8">' . arraySelect($projectPriorityFilter, 'projectPriorityFilterChoose', 'size="1" class="text" onChange="document.projectPriorityFilter.submit();"', $projectPriorityFilterChoose, true) . '</form>');

$project_create_date_start_filter=$AppUI->getState('project_create_date_start_filter');
$project_create_date_start_filter_text='';
if (!empty ($project_create_date_start_filter) && $start_date=new w2p_Utilities_Date($project_create_date_start_filter)){
	$project_create_date_start_filter=$start_date->format('%Y%m%d');
	$project_create_date_start_filter_text=$start_date->format($AppUI->getPref('SHDATEFORMAT'));
}
$project_create_date_end_filter=$AppUI->getState('project_create_date_end_filter');
$project_create_date_end_filter_text='';
if (!empty ($project_create_date_end_filter) && $end_date=new w2p_Utilities_Date($project_create_date_end_filter)){
	$project_create_date_end_filter=$end_date->format('%Y%m%d');
	$project_create_date_end_filter_text=$end_date->format($AppUI->getPref('SHDATEFORMAT'));
}
$titleBlock->addCell($AppUI->_('created project(interval)').':<form name="project_create_date_form" id="project_create_date_form" method="post" accept-charset="utf-8">
	<input type="hidden" name="datePicker" value="project_create">
	<input type="hidden" name="project_create_date_start" id="project_create_date_start" value="'.$project_create_date_start_filter.'">
	<input type="text" name="date_start" id="date_start" onchange="setDate_new(\'project_create_date_form\', \'date_start\');" value="'.$project_create_date_start_filter_text.'" class="text" style="">
	<a href="javascript: void(0);" onclick="showCalendar(\'date_start\', \''.$AppUI->getPref('SHDATEFORMAT').'\', \'project_create_date_form\', null, true, true)">
		<img src="'.w2PfindImage('calendar.gif').'" width="24" height="12" alt="'.$AppUI->_('Calendar').'" border="0">
	</a> -
	<input type="hidden" name="project_create_date_end" id="project_create_date_end" value="'.$project_create_date_end_filter.'">
	<input type="text" name="date_end" id="date_end" onchange="setDate_new(\'project_create_date_form\', \'date_end\');" value="'.$project_create_date_end_filter_text.'" class="text" style="">
	<a href="javascript: void(0);" onclick="showCalendar(\'date_end\', \''.$AppUI->getPref('SHDATEFORMAT').'\', \'project_create_date_form\', null, true, true)">
		<img src="'.w2PfindImage('calendar.gif').'" width="24" height="12" alt="'.$AppUI->_('Calendar').'" border="0">
	</a>
	<button>'.$AppUI->_('set').'</button>
	</form>
');

if ($canEdit && $project_id) {
	$titleBlock->addCell('<form action="?m=tasks&amp;a=addedit&amp;task_project=' . $project_id . '" method="post" accept-charset="utf-8"><input type="submit" class="button" value="' . $AppUI->_('new task') . '"></form>');
}

$titleBlock->addCell($AppUI->_('Task Filter') . ':<form action="?m=tasks" method="post" name="taskFilter" accept-charset="utf-8">' . arraySelect($filters, 'f', 'size="1" class="text" onChange="document.taskFilter.submit();"', $f, true) . '</form>');

if (w2PgetParam($_GET, 'inactive', '') == 'toggle') {
	$AppUI->setState('inactive', $AppUI->getState('inactive') == -1 ? 0 : -1);
}
$in = $AppUI->getState('inactive') == -1 ? '' : 'in';

$titleBlock->showhelp = false;

$titleBlock->addCrumb('?m=tasks&amp;a=todo&amp;user_id=' . $user_id, 'my todo');
if (w2PgetParam($_GET, 'pinned') == 1) {
	$titleBlock->addCrumb('?m=tasks', 'all tasks');
} else {
	$titleBlock->addCrumb('?m=tasks&amp;pinned=1', 'my pinned tasks');
}
$titleBlock->addCrumb('?m=tasks&amp;inactive=toggle', 'show ' . $in . 'active tasks');
$titleBlock->addCrumb('?m=tasks&amp;a=tasksperuser', 'tasks per user');
if (!$project_id) {
    $titleBlock->addCell('
        <form name="task_list_options" method="post" action="?m=tasks" accept-charset="utf-8">
            <input type="hidden" name="show_task_options" value="1" />
            <input type="checkbox" name="show_incomplete" id="show_incomplete" onclick="document.task_list_options.submit();"' .
                ($showIncomplete ? 'checked="checked"' : '') . '/>
            <label for="show_incomplete">' . $AppUI->_("Incomplete Tasks Only") . '</label>
        </form>');

}

$titleBlock->show();

// include the re-usable sub view
$min_view = false;
include (W2P_BASE_DIR . '/modules/tasks/tasks.php');