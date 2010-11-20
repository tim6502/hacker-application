<?php

require('../instance.cgi');

require_once('spiriter/model/drinks.php');

siteInit();


$drink_ident = $_GET['spirit'];
if ($drink_ident == null) {
	webError('Spirit not found');
	webRedirect('spirits');
}

$drink_id = spiriterLookupDrink($drink_ident);
if ($drink_id == null) {
	webError('Spirit not found');
	webRedirect('spirits');
}

$drink_rec = spiriterGetFullDrink($drink_id);

$maker_rec = spiriterGetFullMaker($drink_rec['maker_id']);



$errors = array();

if (Ctx::$auth->auth()) {

	$user_id = Ctx::$auth->getUserId();

	$comment_form = new Form(CURR_URL_PATH, array(
		'id' => 'form',
		'submit' => 'Comment'
	));
	$comment_form->setFieldTable(array(
		'action' => array(
			'type' => 'hidden',
			'default' => 'comment'
		),
		'comment' => array(
			'type' => 'textarea',
			'label' => 'Comment',
			'default' => '',
			'max_length' => 512,
			'trim' => true,
			'nullable' => false,
			'required' => true,
			'attributes' => array(
				'rows' => 3,
				'cols' => 26
			)
		)
	));


	if ($_POST['action'] == 'comment') {
		$comment_form->readArgs($_POST);
		$errors += $comment_form->getErrors();

		if ($errors == null) {
			$comment_rec = $comment_form->getData();

			$comment_rec['user_id'] = $user_id;
			$comment_rec['drink_id'] = $drink_id;

			$comment_id = spiriterAddComment($comment_rec);
			Ctx::$log->audit('spirit comment added, comment_id = ' . $comment_id, $comment_rec);


			webMessage('Comment Added');
			webRedirect(CURR_URL_PATH);
		}
	}
}


$page = sitePage($drink_rec['name'], $errors);

$page->body = template('pages/spirits/spirit', array(
	'drink_rec' => $drink_rec,
	'maker_rec' => $maker_rec,
	'comment_form' => $comment_form
));

siteShowPage($page);


