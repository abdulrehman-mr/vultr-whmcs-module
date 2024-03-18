<?php

/*
WHMCS server module for Vultr using the Vultr V2 API

Features of admin panel:
1) Admin will enter his API key in the settings
2) When he will go to add the VPS server product in admin panel to sell he can select Vultr Module as server provider it will load the the VPS plans from Vultr API to select system which will be used later to create instance. "https://api.vultr.com/v2/plans"


Client Area Features:
After VPS Server purchase, in Client area if Status is Active of VPS in our system
On product details page first time after purchase if instance not created yet:
  - Ask client to choose the OS, load options using planID assigned to product from Vultr API
  - Ask client to select location of server, load options from Vultr API
  - Ask for hostname for the server
  - Create the instance
  - Save the instanceID, IP, username, default_password in system

Once instance is active:
  - Client can Start/Stop/Reboot/Reinstall Operating System on Server
  - Show client details like password, username, IP, OS, region of server from Vultr API
*/

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

function vultr_MetaData()
{
    return array(
        'DisplayName' => 'Vultr VPS Lvato',
        'APIVersion' => '1.0',
    );
}

function vultr_ConfigOptions()
{
    // Fetch VPS plans from Vultr API
    $apiKey = ''; // Load your API key from module configuration
    $plans = getVultrPlans($apiKey);

    $configarray = array(
        "API Key" => array("Type" => "text", "Size" => "50", "Description" => "Enter your Vultr API Key"),
        "VPS Plan" => array("Type" => "dropdown", "Options" => $plans, "Description" => "Select VPS plan")
    );
    return $configarray;
}


// Helper function to fetch VPS plans from Vultr API
function getVultrPlans($apiKey)
{
    $url = 'https://api.vultr.com/v2/plans';
    $headers = array(
        'Authorization: Bearer ' . $apiKey,
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);

    $plans = array();
    $decodedResponse = json_decode($response, true);
    foreach ($decodedResponse['plans'] as $plan) {
        $plans[$plan['id']] = $plan['name'];
    }
    return $plans;
}
function vultr_CreateAccount($params)
{
    // Fetch necessary parameters
    $apiKey = $params['configoption1'];
    $planID = $params['configoption2']; // Get selected VPS plan ID
    $hostname = $params['customfields']['Hostname'];
    $region = $params['configoption3']; // Get selected region

    // Make API call to Vultr to create the VPS instance
    $instanceData = createVultrInstance($apiKey, $planID, $hostname, $region);

    // Save instance data to WHMCS database
    saveInstanceData($instanceData, $params['serviceid']);

    return 'success'; // Return 'success' or an error message if something went wrong
}

function vultr_AdminServicesTabFields($params)
{
    $fields = array();

    // Fetch necessary parameters
    $apiKey = $params['configoption1'];
    $instanceID = $params['customfields']['Instance ID'];

    // Fetch instance details from Vultr API
    $instanceDetails = getInstanceDetails($apiKey, $instanceID);

    // Add custom fields to display in admin area
    $fields['Vultr Instance ID'] = $instanceID;
    $fields['IP Address'] = $instanceDetails['ip_address'];
    // Add more fields as needed

    return $fields;
}


function vultr_ClientArea($params)
{
    // Fetch necessary parameters
    $apiKey = $params['configoption1'];
    $instanceID = $params['customfields']['Instance ID'];

    // Fetch instance details from Vultr API
    $instanceDetails = getInstanceDetails($apiKey, $instanceID);

    // Construct HTML to display instance details
    $html = '<h2>Your Vultr VPS Details</h2>';
    $html .= '<p>IP Address: ' . $instanceDetails['ip_address'] . '</p>';
    $html .= '<p>Username: ' . $instanceDetails['username'] . '</p>';
    $html .= '<p>Password: ' . $instanceDetails['default_password'] . '</p>';
    // Add more details as needed

    return $html;
}

function vultr_Start($params)
{
    // Fetch necessary parameters
    $apiKey = $params['configoption1'];
    $instanceID = $params['customfields']['Instance ID'];

    // Make API call to start the VPS instance
    $response = vultrInstanceAction($apiKey, $instanceID, 'start');

    if ($response['status'] == 'success') {
        return 'success';
    } else {
        return 'Error: ' . $response['error'];
    }
}

function vultr_Stop($params)
{
    // Fetch necessary parameters
    $apiKey = $params['configoption1'];
    $instanceID = $params['customfields']['Instance ID'];

    // Make API call to stop the VPS instance
    $response = vultrInstanceAction($apiKey, $instanceID, 'stop');

    if ($response['status'] == 'success') {
        return 'success';
    } else {
        return 'Error: ' . $response['error'];
    }
}

function vultr_Reboot($params)
{
    // Fetch necessary parameters
    $apiKey = $params['configoption1'];
    $instanceID = $params['customfields']['Instance ID'];

    // Make API call to reboot the VPS instance
    $response = vultrInstanceAction($apiKey, $instanceID, 'reboot');

    if ($response['status'] == 'success') {
        return 'success';
    } else {
        return 'Error: ' . $response['error'];
    }
}

function vultr_Reinstall($params)
{
    // Fetch necessary parameters
    $apiKey = $params['configoption1'];
    $instanceID = $params['customfields']['Instance ID'];
    $osID = $params['configoption3']; // OS ID from product configuration

    // Make API call to reinstall the operating system on the VPS instance
    $response = reinstallOperatingSystem($apiKey, $instanceID, $osID);

    if ($response['status'] == 'success') {
        return 'success';
    } else {
        return 'Error: ' . $response['error'];
    }
}

// Helper function to perform instance actions (start, stop, reboot)
function vultrInstanceAction($apiKey, $instanceID, $action)
{
    $url = 'https://api.vultr.com/v2/instances/' . $instanceID . '/actions';

    $data = array(
        'type' => $action
    );

    $headers = array(
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// Helper function to reinstall the operating system
function reinstallOperatingSystem($apiKey, $instanceID, $osID)
{
    $url = 'https://api.vultr.com/v2/instances/' . $instanceID . '/reinstall';

    $data = array(
        'os_id' => $osID
    );

    $headers = array(
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// Helper function to make API call to create Vultr instance
function createVultrInstance($apiKey, $planID, $hostname, $region)
{
    $url = 'https://api.vultr.com/v2/instances';

    $data = array(
        'region' => $region,
        'plan' => $planID,
        'label' => $hostname
        // Add more parameters as needed
    );

    $headers = array(
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// Helper function to save instance data to database
function saveInstanceData($instanceData, $serviceID)
{
    $instanceID = $instanceData['instanceid'];
    $ipAddress = $instanceData['main_ip'];
    $username = 'admin'; // You might generate a random username
    $password = 'password'; // You might generate a random password

    // Save instance data to WHMCS database
    Capsule::table('mod_vultr_instances')->insert([
        'service_id' => $serviceID,
        'instance_id' => $instanceID,
        'ip_address' => $ipAddress,
        'username' => $username,
        'password' => $password,
        // Add more fields as needed
    ]);
}

// Helper function to fetch instance details from Vultr API
function getInstanceDetails($apiKey, $instanceID)
{
    $url = 'https://api.vultr.com/v2/instances/' . $instanceID;

    $headers = array(
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

?>
