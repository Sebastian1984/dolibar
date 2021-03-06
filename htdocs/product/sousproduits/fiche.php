<?php
/* Copyright (C) 2001-2007 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2008 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005      Eric Seigne          <eric.seigne@ryxeo.com>
 * Copyright (C) 2005-2009 Regis Houssin        <regis@dolibarr.fr>
 * Copyright (C) 2006      Andre Cianfarani     <acianfa@free.fr>
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
 *  \file       htdocs/product/sousproduits/fiche.php
 *  \ingroup    product
 *  \brief      Page de la fiche produit
 *  \version    $Id$
 */

require("./pre.inc.php");
require_once(DOL_DOCUMENT_ROOT."/lib/product.lib.php");
require_once(DOL_DOCUMENT_ROOT."/product.class.php");
require_once(DOL_DOCUMENT_ROOT."/categories/categorie.class.php");

$langs->load("bills");
$langs->load("products");

// Security check
if (isset($_GET["id"]) || isset($_GET["ref"]))
{
	$id = isset($_GET["id"])?$_GET["id"]:(isset($_GET["ref"])?$_GET["ref"]:'');
}
$fieldid = isset($_GET["ref"])?'ref':'rowid';
if ($user->societe_id) $socid=$user->societe_id;
$result=restrictedArea($user,'produit|service',$id,'product','','',$fieldid);

$mesg = '';

$id=isset($_GET["id"])?$_GET["id"]:$_POST["id"];
$ref=isset($_GET["ref"])?$_GET["ref"]:$_POST["ref"];
$key=isset($_GET["key"])?$_GET["key"]:$_POST["key"];
$catMere=isset($_GET["catMere"])?$_GET["catMere"]:$_POST["catMere"];
$action=isset($_GET["action"])?$_GET["action"]:$_POST["action"];
$cancel=isset($_GET["cancel"])?$_GET["cancel"]:$_POST["cancel"];

$product = new Product($db);
$productid=0;
if ($id || $ref)
{
	$result = $product->fetch($id,$ref);
	$productid=$product->id;
}


// Action association d'un sousproduit
if ($action == 'add_prod' &&
$cancel <> $langs->trans("Cancel") &&
($user->rights->produit->creer || $user->rights->service->creer))
{

	for($i=0;$i<$_POST["max_prod"];$i++)
	{
		// print "<br> : ".$_POST["prod_id_chk".$i];
		if($_POST["prod_id_chk".$i] != "")
		{
			if($product->add_sousproduit($id, $_POST["prod_id_".$i],$_POST["prod_qty_".$i]) > 0)
			{
				$action = 'edit';
			}
			else
			{
				$action = 're-edit';
				if($product->error == "isFatherOfThis")
				$mesg = $langs->trans("ErrorAssociationIsFatherOfThis");
			}
		}
		else
		{
			if($product->del_sousproduit($id, $_POST["prod_id_".$i]))
			{
				$action = 'edit';
			}
			else
			{
				$action = 're-edit';
			}


		}
	}
}
// action recherche des produits par mot-cle et/ou par categorie
if($action == 'search' )
{
	$sql = 'SELECT DISTINCT p.rowid, p.ref, p.label, p.price, p.fk_product_type as type';
	$sql.= ' FROM '.MAIN_DB_PREFIX.'product as p';
	$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie_product as cp ON p.rowid = cp.fk_product';
	$sql.= " WHERE p.entity = ".$conf->entity;
	if($key != "")
	{
		$sql.= " AND (p.ref like '%".$key."%'";
		$sql.= " OR p.label like '%".$key."%')";
	}
	if ($conf->categorie->enabled && $catMere != -1 and $catMere)
	{
		$sql.= " AND cp.fk_categorie ='".addslashes($catMere)."'";
	}
	$sql.= " ORDER BY p.ref ASC";
	// $sql.= $db->plimit($limit + 1 ,$offset);

	$resql = $db->query($sql) ;
}

if ($cancel == $langs->trans("Cancel"))
{
	$action = '';
	Header("Location: fiche.php?id=".$_POST["id"]);
	exit;
}


/*
 * View
 */

$productstatic = new Product($db);
$html = new Form($db);

llxHeader("","",$langs->trans("CardProduct".$product->type));
$html = new Form($db);


if ($mesg) {
	print '<br><div class="error">'.$mesg.'</div><br>';
}

$head=product_prepare_head($product, $user);
$titre=$langs->trans("CardProduct".$product->type);
$picto=($product->type==1?'service':'product');
dol_fiche_head($head, 'subproduct', $titre, 0, $picto);

/*
 * Fiche produit
 */
if ($id || $ref)
{
	if ( $result )
	{

		if ($action <> 'edit' &&$action <> 'search' && $action <> 're-edit')
		{
			/*
			 *  En mode visu
			 */

			print '<table class="border" width="100%">';

			print "<tr>";

			$nblignes=6;
			if ($product->isproduct() && $conf->stock->enabled) $nblignes++;
			if ($product->isservice()) $nblignes++;

			// Reference
			print '<td width="25%">'.$langs->trans("Ref").'</td><td>';
			print $html->showrefnav($product,'ref','',1,'ref');
			print '</td></tr>';

			// Libelle
			print '<tr><td>'.$langs->trans("Label").'</td><td>'.$product->libelle.'</td>';
			print '</tr>';

			$product->get_sousproduits_arbo ();
			print '<tr><td>'.$langs->trans("AssociatedProductsNumber").'</td><td>'.sizeof($product->get_arbo_each_prod()).'</td>';

			// associations sousproduits
			$prods_arbo = $product->get_arbo_each_prod();
			if(sizeof($prods_arbo) > 0)
			{
				print '<tr><td colspan="2">';
				print '<b>'.$langs->trans("ProductAssociationList").'</b><br>';
				foreach($prods_arbo as $key => $value)
				{
					$productstatic->id=$value[1];
					$productstatic->type=0;
					//$productstatic->ref=$value[0];
					//var_dump($value);
					//print '<pre>'.$productstatic->ref.'</pre>';
					//print $productstatic->getNomUrl(1).'<br>';
					print $value[0];	// This contains a tr line.
				}


				print '</td></tr>';
			}

			print "</table>\n";

		}
	}

	/*
	 * Fiche en mode edition
	 */
	if (($action == 'edit' || $action == 'search' || $action == 're-edit') && ($user->rights->produit->creer || $user->rights->service->creer))
	{
		print '<table class="border" width="100%">';

		print "<tr>";

		$nblignes=6;
		if ($product->isproduct() && $conf->stock->enabled) $nblignes++;
		if ($product->isservice()) $nblignes++;

			// Reference
			print '<td width="25%">'.$langs->trans("Ref").'</td><td>';
			print $html->showrefnav($product,'ref','',1,'ref');
			print '</td></tr>';

		if ($product->is_photo_available($conf->produit->dir_output))
		{
			// Photo
			print '<td valign="middle" align="center" rowspan="'.$nblignes.'">';
			$nbphoto=$product->show_photos($conf->produit->dir_output,1,1,0);
			print '</td>';
		}

		print '</tr>';

		// Libelle
		print '<tr><td>'.$langs->trans("Label").'</td><td>'.$product->libelle.'</td>';
		print '</tr>';

		// Nombre de sousproduits associes
		$product->get_sousproduits_arbo ();
		print '<tr><td>'.$langs->trans("AssociatedProductsNumber").'</td><td>'.sizeof($product->get_arbo_each_prod()).'</td>';
		print '</tr>';

		print '</table>';

		print '<br>';

		print '<form action="'.DOL_URL_ROOT.'/product/sousproduits/fiche.php?id='.$id.'" method="post">';
		print '<table class="nobordernopadding">';
		print '<tr><td><b>'.$langs->trans("ProductToAddSearch").'</b></td></tr>';
		print '<tr><td>';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print $langs->trans("KeywordFilter");
		print '</td><td><input type="text" name="key" value="'.$key.'">';
		print '<input type="hidden" name="action" value="search">';
		print '<input type="hidden" name="id" value="'.$id.'">';
		print '</td></tr>';

		if($conf->categorie->enabled)
		{
			print '<tr><td>'.$langs->trans("CategoryFilter");
			print '</td><td>'.$html->select_all_categories(0,$catMere).'</td></tr>';
		}

		print '<tr><td colspan="2"><input type="submit" class="button" value="'.$langs->trans("Search").'"></td></tr>';
		print '</table>';
		print '</form>';

		if($action == 'search')
		{
			print '<br>';
			print '<form action="'.DOL_URL_ROOT.'/product/sousproduits/fiche.php?id='.$id.'" method="post">';
			print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
			print '<input type="hidden" name="action" value="add_prod"';
			print '<input type="hidden" name="id" value="'.$id.'"';
			print '<table class="nobordernopadding" width="100%">';
			print '<tr class="liste_titre">';
			print '<td class="liste_titre">'.$langs->trans("Ref").'</td>';
			print '<td class="liste_titre">'.$langs->trans("Label").'</td>';
			print '<td class="liste_titre" align="center">'.$langs->trans("AddDel").'</td>';
			print '<td class="liste_titre" align="right">'.$langs->trans("Quantity").'</td>';
			print '</tr>';
			if ($resql)
			{
				$num = $db->num_rows($resql);
				$i=0;
				$var=true;

				if($num == 0) print '<tr><td colspan="4">'.$langs->trans("NoMatchFound").'</td></tr>';

				while ($i < $num)
				{
					$objp = $db->fetch_object($resql);
					if($objp->rowid != $id)
					{
						// check if a product is not already a parent product of this one
						$prod_arbo=new Product($db,$objp->rowid);
						if ($prod_arbo->type==2 || $prod_arbo->type==3)
						{
							$is_pere=0;
							$prod_arbo->get_sousproduits_arbo ();
							// associations sousproduits
							$prods_arbo = $prod_arbo->get_arbo_each_prod();
							if(sizeof($prods_arbo) > 0) {
								foreach($prods_arbo as $key => $value)
								{
									if ($value[1]==$id)
									{
										$is_pere=1;
									}
								}
							}
							if ($is_pere==1) {
								$i++;
								continue;
							}
						}
						$var=!$var;
						print "\n<tr ".$bc[$var].">";
						$productstatic->id=$objp->rowid;
						$productstatic->ref=$objp->ref;
						$productstatic->libelle=$objp->label;
						$productstatic->type=$objp->type;

						print '<td>'.$productstatic->getNomUrl(1,'',24).'</td>';
						print '<td>'.$objp->label.'</td>';
						if($product->is_sousproduit($id, $objp->rowid))
						{
							$addchecked = ' checked="true"';
							$qty=$product->is_sousproduit_qty;
						}
						else
						{
							$addchecked = '';
							$qty="1";
						}
						print '<td align="center"><input type="hidden" name="prod_id_'.$i.'" value="'.$objp->rowid.'">';
						print '<input type="checkbox" '.$addchecked.'name="prod_id_chk'.$i.'" value="'.$objp->rowid.'"></td>';
						print '<td align="right"><input type="text" size="3" name="prod_qty_'.$i.'" value="'.$qty.'"></td>';
						print '</td>';
						print '</tr>';
					}
					$i++;
				}

			}
			else
			{
				dol_print_error($db);
			}
			print '<input type="hidden" name="max_prod" value="'.$i.'">';
			if($num > 0) print '<tr><td colspan="4" align="center"><br><input type="submit" class="button" value="'.$langs->trans("Add").'/'.$langs->trans("Update").'"></td></tr>';
			print '</table>';
			print '</form>';
		}

	}
}

print "</div>\n";


/* ************************************************************************** */
/*                                                                            */
/* Barre d'action                                                             */
/*                                                                            */
/* ************************************************************************** */

print "\n<div class=\"tabsAction\">\n";

if ($action == '')
{
	if ($user->rights->produit->creer || $user->rights->service->creer)
	{
		print '<a class="butAction" href="'.DOL_URL_ROOT.'/product/sousproduits/fiche.php?action=edit&amp;id='.$productid.'">'.$langs->trans("EditAssociate").'</a>';
	}
}

print "\n</div>\n";



$db->close();

llxFooter('$Date$ - $Revision$');
?>
