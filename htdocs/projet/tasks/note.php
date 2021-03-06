<?php
/* Copyright (C) 2010 Regis Houssin  <regis@dolibarr.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 */

/**
 *	\file       htdocs/projet/tasks/note.php
 *	\ingroup    project
 *	\brief      Fiche d'information sur une tache
 *	\version    $Id$
 */

require('./pre.inc.php');
require_once(DOL_DOCUMENT_ROOT."/projet/tasks/task.class.php");
require_once(DOL_DOCUMENT_ROOT."/lib/project.lib.php");

$langs->load('projects');

$id = isset($_GET["id"])?$_GET["id"]:'';

// Security check
if ($user->societe_id) $socid=$user->societe_id;
if (!$user->rights->projet->lire) accessforbidden();
//$result = restrictedArea($user, 'projet', $id, '', 'task'); // TODO ameliorer la verification



/******************************************************************************/
/*                     Actions                                                */
/******************************************************************************/

if ($_POST["action"] == 'update_public' && $user->rights->projet->creer)
{
	$task = new Task($db);
	$task->fetch($_GET['id']);

	$db->begin();

	$res=$task->update_note_public($_POST["note_public"],$user);
	if ($res < 0)
	{
		$mesg='<div class="error">'.$task->error.'</div>';
		$db->rollback();
	}
	else
	{
		$db->commit();
	}
}

if ($_POST['action'] == 'update_private' && $user->rights->projet->creer)
{
	$task = new Task($db);
	$task->fetch($_GET['id']);

	$db->begin();

	$res=$task->update_note($_POST["note_private"],$user);
	if ($res < 0)
	{
		$mesg='<div class="error">'.$task->error.'</div>';
		$db->rollback();
	}
	else
	{
		$db->commit();
	}
}


/*
 * View
 */

llxHeader();

$html = new Form($db);

$id = $_GET['id'];
$ref= $_GET['ref'];
if ($id > 0 || ! empty($ref))
{
	if ($mesg) print $mesg;

	$now=gmmktime();

	$task = new Task($db);
	$projectstatic = new Project($db);
	$userstatic = new User($db);

	if ($task->fetch($id, $ref))
	{
		$result=$projectstatic->fetch($task->fk_project);
		if (! empty($projectstatic->socid)) $projectstatic->societe->fetch($projectstatic->socid);

		// To verify role of users
		$userAccess = 0;
		foreach(array('internal','external') as $source)
		{
			$userRole = $projectstatic->liste_contact(4,$source);
			$num=sizeof($userRole);

			$i = 0;
			while ($i < $num)
			{
				if ($userRole[$i]['code'] == 'PROJECTLEADER' && $user->id == $userRole[$i]['id'])
				{
					$userAccess++;
				}
				$i++;
			}
		}

		$head = task_prepare_head($task);
		dol_fiche_head($head, 'note', $langs->trans('Task'), 0, 'projecttask');

		print '<table class="border" width="100%">';

		//$linkback="<a href=\"".$_SERVER["PHP_SELF"]."?page=$page&socid=$socid&viewstatut=$viewstatut&sortfield=$sortfield&$sortorder\">".$langs->trans("BackToList")."</a>";

		// Ref
		print '<tr><td width="30%">'.$langs->trans("Ref").'</td><td>';
		print $html->showrefnav($task,'id','',1,'rowid','ref','','');
		print '</td></tr>';

		// Label
		print '<tr><td>'.$langs->trans("Label").'</td><td>'.$task->label.'</td></tr>';

		// Project
		print '<tr><td>'.$langs->trans("Project").'</td><td colspan="3">';
		print $projectstatic->getNomUrl(1);
		print '</td></tr>';

		// Third party
		print '<tr><td>'.$langs->trans("Company").'</td><td>';
		if ($projectstatic->societe->id > 0) print $projectstatic->societe->getNomUrl(1);
		else print'&nbsp;';
		print '</td></tr>';

		// Note publique
		print '<tr><td valign="top">'.$langs->trans("NotePublic").' :</td>';
		print '<td valign="top" colspan="3">';
		if ($_GET["action"] == 'edit')
		{
			print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$task->id.'">';
			print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
			print '<input type="hidden" name="action" value="update_public">';
			print '<textarea name="note_public" cols="80" rows="8">'.$task->note_public."</textarea><br>";
			print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
			print '</form>';
		}
		else
		{
			print ($task->note_public?nl2br($task->note_public):"&nbsp;");
		}
		print "</td></tr>";

		// Note privee
		if (! $user->societe_id)
		{
			print '<tr><td valign="top">'.$langs->trans("NotePrivate").' :</td>';
			print '<td valign="top" colspan="3">';
			if ($_GET["action"] == 'edit')
			{
				print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$task->id.'">';
				print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
				print '<input type="hidden" name="action" value="update_private">';
				print '<textarea name="note_private" cols="80" rows="8">'.$task->note_private."</textarea><br>";
				print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
				print '</form>';
			}
			else
			{
				print ($task->note_private?nl2br($task->note_private):"&nbsp;");
			}
			print "</td></tr>";
		}

		print "</table>";

		print '</div>';

		/*
		 * Actions
		 */

		print '<div class="tabsAction">';
		if ($user->rights->projet->creer && $_GET['action'] <> 'edit')
		{
			if ($userAccess)
			{
				print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$task->id.'&amp;action=edit">'.$langs->trans('Modify').'</a>';
			}
			else
			{
				print '<a class="butActionRefused" href="#" title="'.$langs->trans("NotOwnerOfProject").'">'.$langs->trans('Modify').'</a>';
			}
		}
		print '</div>';
	}
}
$db->close();

llxFooter('$Date$ - $Revision: 1.15 ');
?>
