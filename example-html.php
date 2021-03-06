<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<title>jQuery FormBuilder Demo (Output)</title>
		<meta http-equiv="content-type" content="text/html; charset=utf-8" />

		<link rel="stylesheet" href="css/formbuilder.css" />
		<script type="text/javascript" src="js/jquery-1.5.1.min.js"></script>
		<script type="text/javascript" src="js/jquery-ui-1.8.11.custom.min.js"></script>
		<script type="text/javascript" src="js/jquery.scrollTo-min.js"></script>
		<script type="text/javascript" src="js/jquery.formbuilder.js"></script>

	</head>
	<body>

<?php

require('Formbuilder/Formbuilder.php');

// At this stage, we simulate getting an array of the
// form_structure and hash from our database. This is
// how the form data would have been saved using
// the $form->store() method.
include('fake-form-db-vals.php');

$form = new Formbuilder($fake_db_vals);

$form->render_form_html(TRUE, 'example-submit.php');

?>

	</body>
</html>