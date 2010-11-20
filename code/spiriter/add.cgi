<?php

require('../instance.cgi');

require_once('spiriter/model/drinks.php');

require_once('szosz/reference.php');
require_once('szosz/array.php');
require_once('szosz/image.php');
require_once('szosz/file.php');

siteInit();


if (!Ctx::$auth->auth()) {
	webReturnRedirect('profile/login');
}

$user_id = Ctx::$auth->getUserId();

if (!sitePriv($user_id, PRIV_MANAGE_DRINKS)) {
	webError('Sorry you do not have permission to access this page');
	webRedirect();
}


if ($_GET['added_spirit'] != null) {
	$added_drink_id = spiriterLookupDrink($_GET['added_spirit']);
	if ($added_drink_id != null) {
		$added_drink_rec = spiriterGetDrink($added_drink_id);
	}
}


$maker_table = array(null => '<Add New Producer>') + arrayMapFromFields(spiriterGetMakers(), 'maker_ident', 'name');


$grain_list = spiriterGetGrains();
$grain_table = arrayMapFromFields($grain_list, 'grain_ident', 'grain');

$age_time_table = spiriterAgeTimes();
$barrel_type_table = spiriterBarrelTypes();


$country_table = refCountries();
$country_table = refCountries();
unset($country_table['US']);
$country_table = array(null => '<Select a Country>', 'US' => 'USA') + $country_table;

$state_table = array(null => '<Select a State>') + refStates();



$form = new Form(CURR_URL_PATH, array(
	'id' => 'form',
	'submit' => 'Add Spirit',
	'method' => 'post',
	'upload' => true,
	'template' => 'forms/group'
));
$form->setFieldTable(array(
	'action' => array(
		'type' => 'hidden',
		'default' => 'save'
	),
	'name' => array(
		'type' => 'text',
		'label' => 'Name',
		'default' => '',
		'size' => 20,
		'max_length' => 48,
		'trim' => true,
		'nullable' => false,
		'required' => true
	),

	'category' => array(
		'type' => 'text',
		'label' => 'Category',
		'default' => '',
		'size' => 20,
		'max_length' => 64,
		'trim' => true,
		'nullable' => true,
		'required' => false
	),

	'primary_grain' => array(
		'type' => 'select',
		'label' => 'Base Distillate',
		'default' => '',
		'nullable' => false,
		'required' => true,
		'options' => array(null => '<Select a Distillate>') + $grain_table
	),
	'secondary_grain' => array(
		'type' => 'select',
		'label' => 'Secondary Distillate',
		'default' => '',
		'nullable' => true,
		'required' => false,
		'options' => array(null => '<Select a Distillate>') + $grain_table
	),
	'tertiary_grain' => array(
		'type' => 'select',
		'label' => 'Tertiary Distillate',
		'default' => '',
		'nullable' => true,
		'required' => false,
		'options' => array(null => '<Select a Distillate>') + $grain_table
	),
	'grain_other' => array(
		'type' => 'text',
		'label' => 'Other Distillates (separate with commas)',
		'default' => '',
		'size' => 20,
		'max_length' => 1024,
		'trim' => true,
		'nullable' => true,
		'required' => false,
		'position' => 'subfield'
	),

	'aged_ind' => array(
		'type' => 'checkbox',
		'label' => 'Aged',
		'default' => 0,
		'required' => true
	),
	'age_time' => array(
		'type' => 'text',
		'label' => 'Aging Time',
		'default' => '',
		'size' => 4,
		'max_length' => 8,
		'trim' => true,
		'nullable' => true,
		'required' => false,
		'attributes' => array('class' => 'aging'),
		'side_count' => 1
	),
	'age_time_units' => array(
		'type' => 'select',
		'label' => 'Aging Time Units',
		'default' => '',
		'nullable' => true,
		'required' => false,
		'options' => array(null => '<Select a Time>') + $age_time_table,
		'attributes' => array('class' => 'aging')
	),
	'barrel_type' => array(
		'type' => 'select',
		'label' => 'Barrel Type',
		'default' => '',
		'nullable' => true,
		'required' => false,
		'options' => array(null => '<Select a Type>') + $barrel_type_table,
		'attributes' => array('class' => 'aging')
	),

	'vintage' => array(
		'type' => 'text',
		'label' => 'Vintage',
		'default' => '',
		'size' => 20,
		'max_length' => 16,
		'trim' => true,
		'nullable' => true,
		'required' => false
	),

	'alcohol_by_volume' => array(
		'type' => 'text',
		'label' => 'Alcohol By Volume (% ABV)',
		'default' => '',
		'size' => 20,
		'max_length' => 16,
		'trim' => true,
		'nullable' => true,
		'required' => false
	),

	'retired_ind' => array(
		'type' => 'checkbox',
		'label' => 'Retired',
		'default' => 0,
		'required' => true
	),

	'description' => array(
		'type' => 'textarea',
		'label' => 'Description',
		'default' => '',
		'max_length' => 512,
		'trim' => true,
		'nullable' => false,
		'required' => false,
		'attributes' => array(
			'rows' => 3,
			'cols' => 26
		)
	),

	'image' => array(
		'type' => 'file',
		'label' => 'Image (' . spiriterSupportedImages() . ')',
		'default' => '',
		'size' => 50,
		'trim' => true,
		'nullable' => true,
		'required' => true
	),
	'image_filename' => array(
		'type' => 'text',
		'label' => 'Relative Image Path',
		'default' => '',
		'size' => 30,
		'max_length' => 64,
		'trim' => true,
		'nullable' => true,
		'required' => false
	),

	'maker' => array(
		'type' => 'select',
		'label' => 'Producer',
		'default' => '',
		'nullable' => false,
		'required' => false,
		'options' => $maker_table
	),

	'maker_name' => array(
		'type' => 'text',
		'label' => 'Producer Name',
		'default' => '',
		'size' => 20,
		'max_length' => 32,
		'trim' => true,
		'nullable' => false,
		'required' => false,
		'attributes' => array('class' => 'new_maker')
	),
	'maker_category' => array(
		'type' => 'text',
		'label' => 'Primary Product Category',
		'default' => '',
		'size' => 20,
		'max_length' => 64,
		'trim' => true,
		'nullable' => true,
		'required' => false,
		'attributes' => array('class' => 'new_maker')
	),
	'maker_year_established' => array(
		'type' => 'text',
		'label' => 'Year Established',
		'default' => '',
		'size' => 4,
		'max_length' => 8,
		'trim' => true,
		'nullable' => true,
		'required' => false,
		'attributes' => array('class' => 'new_maker')
	),
	'maker_website_url' => array(
		'type' => 'text',
		'label' => 'Producer Website',
		'default' => '',
		'size' => 30,
		'max_length' => 64,
		'trim' => true,
		'nullable' => false,
		'required' => false,
		'attributes' => array('class' => 'new_maker')
	),
	'maker_country_code' => array(
		'type' => 'select',
		'label' => 'Producer Country',
		'default' => null,
		'nullable' => false,
		'required' => false,
		'options' => $country_table,
		'attributes' => array('class' => 'new_maker')
	),
	'maker_city' => array(
		'type' => 'text',
		'label' => 'Producer City',
		'default' => '',
		'size' => 20,
		'max_length' => 64,
		'trim' => true,
		'nullable' => false,
		'required' => false,
		'attributes' => array('class' => 'new_maker'),
		'group' => 'group_maker_city_state'
	),
	'maker_state_code' => array(
		'type' => 'select',
		'label' => 'Producer State',
		'default' => null,
		'nullable' => false,
		'required' => false,
		'options' => $state_table,
		'attributes' => array('class' => 'new_maker'),
		'group' => 'group_maker_city_state'
	),
	'maker_location' => array(
		'type' => 'text',
		'label' => 'Producer Location',
		'default' => '',
		'size' => 20,
		'max_length' => 64,
		'trim' => true,
		'nullable' => false,
		'required' => false,
		'attributes' => array('class' => 'new_maker'),
		'group' => 'group_maker_location'
	)
));



if ($_POST['action'] == 'save') {

	$form->readArgs($_POST);
	$errors = $form->getErrors();

	if ($errors == null) {
		$form_data = $form->getData();

		$drink_rec = arrayPullFields($form_data, array(
			'name',
			'category',
			'grain_other',
			'aged_ind',
			'age_time',
			'age_time_units',
			'barrel_type',
			'vintage',
			'alcohol_by_volume',
			'retired_ind',
			'description',
			'image_filename'
		));

		$maker_ident = $form_data['maker'];

		$maker_rec = arrayPullRenameFields($form_data, array(
			'maker_name' => 'name',
			'maker_category' => 'category',
			'maker_year_established' => 'year_established',
			'maker_website_url' => 'website_url',
			'maker_location' => 'location',
			'maker_city' => 'city',
			'maker_state_code' => 'state_code',
			'maker_country_code' => 'country_code'
		));

		if ($maker_rec['country_code'] == 'US') {
			$maker_rec['location'] = null;
		} else {
			$maker_rec['city'] = null;
			$maker_rec['state_code'] = null;
		}


		if ($form_data['primary_grain'] != null) {
			$drink_rec['primary_grain_id'] = spiriterLookupGrain($form_data['primary_grain']);

			if ($form_data['secondary_grain'] != null) {
				$drink_rec['secondary_grain_id'] = spiriterLookupGrain($form_data['secondary_grain']);

				if ($form_data['tertiary_grain'] != null) {
					$drink_rec['tertiary_grain_id'] = spiriterLookupGrain($form_data['tertiary_grain']);
				}
			}
		}


		if ($_FILES['image']['name'] != null) {
			if (Ctx::$config['upload']['scan_mime_type'] == 1) {
				$drink_image_mime_type = fileMime($_FILES['image']['tmp_name']);
			} else {
  	    $drink_image_mime_type = $_FILES['image']['type'];
			}

			if (!spiriterIsSupportedImage($drink_image_mime_type)) {
				Ctx::$log->error("Unsupported mime type", array(
						'mime_type' => $drink_image_mime_type,
						'file_kind' => fileKind($drink_image_mime_type)
					) + 
					$_FILES['image']
				);
				$errors[] = 'Upload file must be an image. Supported file types: ' . spiriterSupportedImages();
			}
		}

		if ($maker_ident == null) {
			if ($maker_rec['name'] == null) {
				$errors[] = 'Producer name required when adding a new producer';
			}

			if ($maker_rec['country_code'] == null) {
				$errors[] = 'Producer country required when adding a new producer';
			}
		}


		if ($errors == null) {
			$drink_rec['created_user_id'] = $user_id;
			$drink_rec['kind'] = 'SPIRIT';

			if ($maker_ident == null) {
				$maker_rec['created_user_id'] = $user_id;

				$maker_id = spiriterAddMaker($maker_rec);

				Ctx::$log->audit('maker added, maker_id = ' . $maker_id, $maker_rec);
			} else {
				$maker_id = spiriterLookupMaker($maker_ident);
			}

			$drink_rec['maker_id'] = $maker_id;


			$drink_id = spiriterAddDrink($drink_rec);


			if ($_FILES['image']['name'] != null) {
				$drink_image_fname = 'drink_' . $drink_id . '_' . time() . '.' . fileMimeExtension($drink_image_mime_type);
				$drink_image_cfname = fileSplicePaths(WWW_CPATH, fileSplicePaths(Ctx::$config['drink_image']['rpath'], $drink_image_fname));

				$stat = move_uploaded_file($_FILES['image']['tmp_name'], $drink_image_cfname);

				if ($stat) {
					imageResize(
						$drink_image_cfname,
						$drink_image_cfname,
						Ctx::$config['drink_image']['max_width'],
						Ctx::$config['drink_image']['max_height'],
						$drink_image_mime_type
					);

					@chmod($drink_image_cfname, 0664);
					@chgrp($drink_image_cfname, Ctx::$config['server']['group']);

					spiriterEditDrink($drink_id, array('image_filename' => $drink_image_fname));

					$drink_rec['drink_id'] = $drink_id;
				} else {
					Ctx::$log->error('Failed to copy uploaded file to drink image directory [' . $drink_image_cfname . ']');
				}
			}


			webMessage('Spirit Added');
			Ctx::$log->audit('spirit added, drink_id = ' . $drink_id, $drink_rec);

			webRedirect('spirits/add?added_spirit=' . $drink_rec['drink_ident']);
		}

	}
}




$page = sitePage('Add Spirit', $errors, PAGE_SPIRITS_ADD);

$page->addCssFile('local/dl_group_no_legend.css');

$page->addJsFile('http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js');

ob_start();
?>
/*
var category_tables = <?= json_encode($secondary_categories) ?>;

function setSelectOptions(ob, option_map) {
	ob.options.length = 0;

	ob.options[0] = new Option("<Select a Category>", "");

	var i = 1;
	for (var k in option_map) {
		ob.options[i++] = new Option(option_map[k], k);
	}
}



function syncMakerPrimaryCategory() {
	if ($('#maker_primary_category').val() != "") {
		setSelectOptions($('#maker_secondary_category').get(0), category_tables[$('#maker_primary_category').val()]);
	}

	syncMakerCategory();
}

function syncMakerCategory() {
	if ($('#maker_primary_category').val() == "") {
		$('#maker_secondary_category').attr('disabled', 'disabled');
		$('#maker_category_other').attr('disabled', 'disabled');
	} else {
		$('#maker_secondary_category').removeAttr('disabled');

		if ($('#maker_secondary_category').val() == "") {
			$('#maker_category_other').removeAttr('disabled');
		} else {
			$('#maker_category_other').attr('disabled', 'disabled');
		}
	}
}


function syncDrinkPrimaryCategory() {
	if ($('#primary_category').val() != "") {
		setSelectOptions($('#secondary_category').get(0), category_tables[$('#primary_category').val()]);
	}

	syncDrinkCategory();
}

function syncDrinkCategory() {
	if ($('#primary_category').val() == "") {
		$('#secondary_category').attr('disabled', 'disabled');
		$('#category_other').attr('disabled', 'disabled');
	} else {
		$('#secondary_category').removeAttr('disabled');

		if ($('#secondary_category').val() == "") {
			$('#category_other').removeAttr('disabled');
		} else {
			$('#category_other').attr('disabled', 'disabled');
		}
	}
}
*/

function syncMaker() {
	if ($('#maker').val() == "") {
		$('.new_maker').removeAttr('disabled');
		//syncMakerCategory();
	} else {
		$('.new_maker').attr('disabled', 'disabled');
	}
}


function syncDrinkGrain() {
	if ($('#primary_grain').val() == "") {
		$('#secondary_grain').attr('disabled', 'disabled');
		$('#tertiary_grain').attr('disabled', 'disabled');

	} else {
		$('#secondary_grain').removeAttr('disabled');

		if ($('#secondary_grain').val() == "") {
			$('#tertiary_grain').attr('disabled', 'disabled');
		} else {
			$('#tertiary_grain').removeAttr('disabled');
		}
	}
}

function syncDrinkAging() {
	if ($('#aged_ind').attr('checked')) {
		$('.aging').removeAttr('disabled');
	} else {
		$('.aging').attr('disabled', 'disabled');
	}
}

function syncMakerCountry() {
	if ($('#maker_country_code').val() == 'US') {
		$('#group_maker_location').hide();
		$('#group_maker_city_state').show();
	} else {
		$('#group_maker_city_state').hide();
		$('#group_maker_location').show();
	}
}


$(function() {
	syncMaker();
	syncMakerCountry();
	syncDrinkGrain();
	syncDrinkAging();


	$('#maker').change(syncMaker);
	$('#maker_country_code').change(syncMakerCountry);
	$('#aged_ind').change(syncDrinkAging);
	$('#primary_grain').change(syncDrinkGrain);
	$('#secondary_grain').change(syncDrinkGrain);
	//$('#tertiary_grain').change(syncDrinkGrain);


/*
	$('#maker_primary_category').change(syncMakerPrimaryCategory);
	$('#maker_secondary_category').change(syncMakerCategory);

	$('#primary_category').change(syncDrinkPrimaryCategory);
	$('#secondary_category').change(syncDrinkCategory);

	syncDrinkPrimaryCategory();
	syncMakerPrimaryCategory();
*/

});
<?
$page->javascript = ob_get_clean();



$page->body = template('pages/spirits/add', array(
	'page' => $page,
	'form' => $form,
	'added_rec' => array(
		'drink_rec' => $added_drink_rec
	)
));

siteShowPage($page);


