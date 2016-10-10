<?php
include_once 'GerencianetIntegration.php';
include_once 'GerencianetValidation.php';

$data = $_POST;

$document 	  			= preg_replace('/[^0-9]/', '',$data['customer_document']);

$invoiceId    			= $data['invoiceid'];                 
$total        			= $data['total']; 
$dueDate	  			= $data['due_at'];                                  
$name         			= $data['customer_name'];             
$companyName  			= $data['customer_company_name'];    
$phone		  			= $data['customer_phone'];             
$email		 			= $data['customer_email'];           
$notify_url				= $data['notify_url'];
$itemName     			= $data['item_name'];                 
$itemNumber	  			= $data['item_number'];                
$currency	  			= $data['currency_code'];              
      
$clientIDProd 			= $data['client_id_prod'];             
$clientSecretProd 		= $data['client_secret_prod'];         
$clientIDDev 			= $data['client_id_dev'];              
$clientSecretDev 		= $data['client_secret_dev'];          
$idConta 				= $data['account_id'];                       
$sendEmailGN 			= (boolean)$data['email_gn'];  
$configSandbox 		    = (boolean)$data['sandbox'];
$chargeId               = (int)$data['charge_id'];

$dueDate = explode(' ', $dueDate);
$dueDate = $dueDate[0];

if ($dueDate < date('Y-m-d'))
    $dueDate = date('Y-m-d');

$gnIntegration = new GerencianetIntegration($clientIDProd, $clientSecretProd, $clientIDDev, $clientSecretDev, $configSandbox, $idConta);

$items = getInvoiceItems($itemName, $total);
$customer = getInvoiceCustomer($name, $companyName, $phone, $email, $document, $sendEmailGN);
generateBillet($items, $invoiceId, $chargeId, $dueDate, $customer, $gnIntegration, $notify_url);

function getInvoiceItems($itemName, $total)
{
	$items = array();
	$item = array(
		'name'   => $itemName,
		'amount' => 1,
		'value'  => (int)($total * 100)
		);
	array_push($items, $item);

	return $items;
}

function getInvoiceCustomer($name, $companyName, $phone, $email, $document, $sendEmailGN=true)
{
	if (strlen((string)$document) <= 11)
	{
		if ($sendEmailGN == true)
			$customer = array(
				'name'          => $name,
				'cpf'           => (string)$document,
				'email'         => $email,
				'phone_number'  => $phone
				);
		else
			$customer = array(
				'name'          => $name,
				'cpf'           => (string)$document,
				'phone_number'  => $phone
				);
	}

	else
	{
		$juridical_data = array(
			'corporate_name' => (string)$companyName,
			'cnpj'           => (string)$document
			);

		if ($sendEmailGN == true)
			$customer = array(
				'email'             => $email,
				'phone_number'      => $phone,
				'juridical_person'  => $juridical_data
				);
		else
			$customer = array(
				'phone_number'      => $phone,
				'juridical_person'  => $juridical_data
				);
	}

	return $customer;
}

function generateBillet($items, $invoiceId=null, $chargeId, $dueDate, $customer, $gnIntegration, $urlCallback='')
{
	$response = array();
	if((int)$chargeId == 0)
	{
		$gnApiResult = $gnIntegration->create_charge($items, $invoiceId, $urlCallback);
		$resultCheck = json_decode($gnApiResult, true);
		if ($resultCheck['code'] != 0)
			$chargeId = $resultCheck['data']['charge_id'];
		else die($gnApiResult);
	}

	$resultPayment = $gnIntegration->pay_billet($chargeId, $dueDate, $customer);
	$resultPaymentDecoded = json_decode($resultPayment, true);
	echo $resultPayment;
}

?>