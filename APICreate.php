#!/usr/bin/env php
<?php

$options = getopt( NULL, ['site:']);

if (empty($options['site']))
{
	echo basename(__FILE__) . " usage --site=<sitename 16character limit>\n";
	exit(1);
}

$site = trim($options['site']);


// Read the configs
require __DIR__ . '/config.php';
$osApiURL = $config['osApiURL'];
$kApiURL = $config['kApiURL'];
$osNamespace = $config['osNamespace'];
$osAuthHeader = $config['osAuthHeader']; 


$ch = curl_init(); 
curl_setopt(
	$ch, 
	CURLOPT_HTTPHEADER, 
	[
		'Authorization: Bearer ' . $osAuthHeader
	]
); 

curl_setopt($ch, CURLOPT_HTTPGET, 1); 
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); 
curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );

curl_setopt($ch, CURLOPT_URL, $osApiURL . 'namespaces/' . $osNamespace . '/templates/drupal8-standalone'); 
//curl_setopt($ch, CURLOPT_URL, 'https://bitosmaster.ent.iastate.edu:8443/' ); 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); 

$chResponse = curl_exec($ch);

if (!$chResponse)
{
	echo curl_error($ch);
	echo "\n";
	exit(1);
}
curl_close($ch);

$template = json_decode($chResponse);

if ( !isset($template->kind) ||  $template->kind != 'Template' )
{
	echo "Template not returned\n";
	exit(1);
}

// Loop Through the Parameters and sets the Site Name
foreach( $template->parameters as $key => $param )
{
	if($param->name == 'SITE')
	{
		$template->parameters[$key]->value = $site;
	}
}

$templateJson = json_encode($template);


// Submit the template with the parameter set for processing to get the objects
$ch = curl_init(); 
curl_setopt(
	$ch, 
	CURLOPT_HTTPHEADER, 
	[
		'Authorization: Bearer ' . $osAuthHeader,
		'Content-Type: application/json',
	]
); 
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); 
curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); 
curl_setopt($ch, CURLOPT_POSTFIELDS, $templateJson); 
curl_setopt($ch, CURLOPT_URL, $osApiURL . 'namespaces/' . $osNamespace . '/processedtemplates'); 
$chResponse = curl_exec($ch);
if (!$chResponse)
{
	echo curl_error($ch);
	echo "\n";
	exit(1);
}
curl_close($ch);



//
$drupalAdminPassword = '';
$processedTemplate = json_decode( $chResponse );
if ( !isset($processedTemplate->kind) ||  $processedTemplate->kind != 'Template' )
{
	echo "Template not returned\n";
	exit(1);
}
foreach( $processedTemplate->parameters as $key => $param )
{
	if($param->name == 'ADMINPASSWORD')
	{
		$drupalAdminPassword = $processedTemplate->parameters[$key]->value;
	}
}


//$kindToEnpoint = [
//	'ImageStream' => 'imagestreams',
//	'BuildConfig' => 'buildconfigs',
//	'DeploymentConfig' => 'deploymentconfigs',
//	'Route' => 'routes',
//	'Service' => 'services',
//];

$ch = curl_init(); 
curl_setopt(
	$ch, 
	CURLOPT_HTTPHEADER, 
	[
		'Authorization: Bearer ' . $osAuthHeader,
		'Content-Type: application/json',
	]
); 
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); 
curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 


// Loop through the objects and create theme
foreach( $processedTemplate->objects AS $key => $osObject  )
{

	$kind = $osObject->kind;
	$endpoint = strtolower($osObject->kind) . 's';
	$osObjectJson = json_encode($osObject);

	curl_setopt($ch, CURLOPT_POSTFIELDS, $osObjectJson);
	
	if($kind == 'Service')
	{
		// kuberneties API for the service
		curl_setopt($ch, CURLOPT_URL, $kApiURL . 'namespaces/' . $osNamespace . '/' . $endpoint); 
	}
	else
	{
		// openshift API for the ImageStream, BuildConfig, Deploymentconfig and Route
		curl_setopt($ch, CURLOPT_URL, $osApiURL . 'namespaces/' . $osNamespace . '/' . $endpoint); 
	}


	// Result for building of each object
	$chResponse = curl_exec($ch);
	if (!$chResponse)
	{
		echo curl_error($ch);
		echo "\n";
	}
	// Should return an API of the object
	$osResponse = json_decode($chResponse);
	if ( !isset($osResponse->kind) || $osResponse->kind != $osObject->kind ) 
	{
		echo "Error creating " . $osObject->kind . "\n";
	}
	
}

curl_close($ch);


echo "\nYour site is located at http://$site.cloud.las.iastate.edu.  Admin password set to $drupalAdminPassword\n\n";


