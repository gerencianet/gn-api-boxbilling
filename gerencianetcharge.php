<?php
include_once 'bb-library/Payment/Adapter/gerencianet_lib/GerencianetIntegration.php';
const gerencianet_plugin_name = "Gerencianet-BoxBilling";
const gerencianet_plugin_version = "0.0.1";

class Payment_Adapter_GerencianetCharge 
{
    private $config = array();

    public function __construct($config)
    {
        $this->config = $config;
    }

    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'       =>  true,
            'description'     =>  'Módulo de pagamentos online da Gerencianet integrado ao BoxBilling',
            'form'  => array(
                'client_id_prod' => array('text', array(
                    'label' => 'Client_Id Produção(*)',
                    'description' => 'preenchimento obrigatório',
                    ),
                ),
                'client_secret_prod' => array('text', array(
                    'label' => 'Client_Secret Produção(*)',
                    'description' => 'preenchimento obrigatório',
                    ),
                ),
                'client_id_dev' => array('text', array(
                    'label' => 'Client_Id Desenvolvimento(*)',
                    'description' => 'preenchimento obrigatório',
                    ),
                ),
                'client_secret_dev' => array('text', array(
                    'label' => 'Client_Secret Desenvolvimento(*)',
                    'description' => 'preenchimento obrigatório',
                    ),
                ),
                'account_id' => array('text', array(
                    'label' => 'Idenfificador da conta(*)',
                    'description' => 'preenchimento obrigatório',
                    ),
                ),
                'email_gn' => array('radio', array(
                    'multiOptions' => array('1'=>'sim', '0'=>'nao'),
                    'label' => 'Permitir que a Gerencianet notifique seu cliente sempre que houver mudanças no status de uma transação.',
                    ),
                ),
                'sandbox' => array('radio', array(
                    'multiOptions' => array('1'=>'sim', '0'=>'nao'),
                    'label' => 'Ativar o modo Sandbox da Gerencianet (Todas as cobranças geradas na Gerencianet serão apenas para teste)',
                    ),
                ),
            ),
        );
    }


    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        if (version_compare(PHP_VERSION, '5.4.39') < 0) 
        {   
            return '<div>A versão do PHP do servidor onde o Boxbilling está hospedado não é compatível com o módulo Gerencianet. 
                            O proprietário deve atualizar o PHP para uma versão igual ou superior à versão 5.4.39</div>';
        }

        $invoice = $api_admin->invoice_get(array('id'=>$invoice_id));
        $transactions = $api_admin->invoice_transaction_get_list();
        $status     = null;
        $chargeId   = 0;
        $existingChargeId = 0;
        $amount     = '0.00';
        $gateway    = '';

        $data = array();
        if($subscription) {
            return '<div>O módulo Gerencianet não suporta o recurso de assinaturas.</div>';
        } else {
            $data = $this->getOneTimePaymentFields($invoice);
        }

        foreach ($transactions['list'] as $transaction) 
        {
            if($transaction['invoice_id'] == $invoice_id && $transaction['gateway'] == 'gerencianetcharge')
            {
                $transactionId = $transaction['id'];
                $status     = $transaction['txn_status'];
                $chargeId   = $transaction['txn_id'];
                $amount     = $transaction['amount'];
                $gateway    = $transaction['gateway'];
                break;
            }
        }

        $clientIDProd             = $this->config['client_id_prod'];
        $clientSecretProd         = $this->config['client_secret_prod'];
        $clientIDDev              = $this->config['client_id_dev'];
        $clientSecretDev          = $this->config['client_secret_dev'];
        $idConta                  = $this->config['account_id'];
        if((int)$this->config['sandbox'] == 0)
            $configSandbox = false;
        else $configSandbox = true;
         
        $gnIntegration = new GerencianetIntegration($clientIDProd, $clientSecretProd, $clientIDDev, $clientSecretDev, $configSandbox, $idConta);

        if((int)$chargeId > 0)
        {
            $chargeDetailsJson = $gnIntegration->detail_charge($chargeId);
            $chargeDetails     = json_decode($chargeDetailsJson, true);
      

            if(isset($chargeDetails['code']) && $chargeDetails['code'] == 200)
            {
                if(isset($chargeDetails['data']['custom_id']) && (int)$chargeDetails['data']['custom_id'] == (int)$invoice_id)
                {  
                    $existCharge = true;
                    $existingChargeId = $chargeId;
                    if(isset($chargeDetails['data']['payment']['banking_billet']['pdf']['charge']))
                    {
                        $url  = $chargeDetails['data']['payment']['banking_billet']['pdf']['charge'];
                        $code = "<meta http-equiv='refresh' content='0;url=" . $url . "'>";
                        return $code;
                    }
                }
            }
        }

        $html = $this->_generateForm($data, $existingChargeId);
        return $html;
    }

    public function processTransaction($api_admin, $id, $data, $gateway_id)
    { 
        $clientIDProd             = $this->config['client_id_prod'];
        $clientSecretProd         = $this->config['client_secret_prod'];
        $clientIDDev              = $this->config['client_id_dev'];
        $clientSecretDev          = $this->config['client_secret_dev'];
        $idConta                  = $this->config['account_id'];
        if((int)$this->config['sandbox'] == 0)
            $configSandbox = false;
        else $configSandbox = true;
         
        $gnIntegration = new GerencianetIntegration($clientIDProd, $clientSecretProd, $clientIDDev, $clientSecretDev, $configSandbox, $idConta);

        $received = $data['post'];

        if (!isset($received['notification'])) {
            die("Don't exist any response");
        }

        $notificationToken  = $received['notification'];

        $gnApiResult = $gnIntegration->notificationCheck($notificationToken);

        $gnApiResult = json_decode($gnApiResult, true);
        $callback = end($gnApiResult['data']);
        $tx = $api_admin->invoice_transaction_get(array('id'=>$id));

        if(!$tx['invoice_id']) {
            $api_admin->invoice_transaction_update(array('id'=>$id, 'invoice_id'=>$data['get']['bb_invoice_id']));
        }

        if(!$tx['amount'] && isset($callback['value'])) {
            $api_admin->invoice_transaction_update(array('id'=>$id, 'amount' => number_format($callback['value']/100, 2, '.', '')));
        }

        if(!$tx['txn_id'] && isset($callback['identifiers']['charge_id'])) {
            $chargeId = $callback['identifiers']['charge_id'];
            $api_admin->invoice_transaction_update(array('id'=>$id, 'txn_id'=>$chargeId));
        }
        
        if(isset($callback['status']['current'])) 
        {
            $status = $callback['status']['current'];
            $params = array('id' => $tx['invoice_id']);
            if(!$tx['txn_status'])
            { 
                $api_admin->invoice_transaction_update(array('id'=>$id, 'txn_status'=>$status));
            }
            if($status == "paid")
            {
                $invoice = $api_admin->invoice_get(array('id' => $tx['invoice_id']));
                if($invoice['status'] != 'paid')
                {
                    if((int)$callback['value'] >= (int)($invoice['total']*100))
                    {
                        $api_admin->invoice_mark_as_paid($params);
                        die("Gerencianet/BoxBilling: Fatura paga com sucesso");
                    }
                    else die("Gerencianet/BoxBilling: Fatura paga com valor inferior");
                }
                else die("Gerencianet/BoxBilling: Fatura com pagamento duplicado");
            }
            else if($status == "canceled")
            {
                $api_admin->invoice_delete($params);
                die("Gerencianet/BoxBilling: Fatura cancelada com sucesso");
            }
        }

    }

    private function moneyFormat($amount, $currency)
    {
        if($currency != 'BRL') {
            return false;
        }
        return number_format($amount, 2, '.', '');
    }

    private function _generateForm($data, $chargeId=0, $method = 'post')
    {
        $form = '<link rel="stylesheet" href="bb-library/Payment/Adapter/gerencianet_lib/style/gerencianet.css"> 
                <script src="bb-themes/boxbilling/assets/jquery.min.js"></script>
                <script src="bb-library/Payment/Adapter/gerencianet_lib/gerencianet.js"></script>';

        $form .= '<div id="myModal" class="gn-modal">
                  <!-- Modal content -->
                  <div class="gn-modal-content">
                    <div class="gn-modal-header">
                      <span class="gn-close">×</span>
                      <h2>Gerencianet Erro</h2>
                    </div>
                    <div class="gn-modal-body" id="gn-modal-body"></div>
                    <div class="gn-modal-footer">
                    </div>
                  </div>
                </div>
                ';
        $form .= '<div><b>Para que o boleto da Gerencianet seja gerado é necessário que o cliente nos forneça CPF, caso queira fazer uma compra como Pessoa Física, ou CNPJ,
        caso queira fazer uma compra como Pessoa Jurídica. Esta exigência se tornou necessária para seguir as normas da Febraban e do Banco central para emissão de boletos.
        Assim, após fornecer seu CPF ou CNPJ, basta clicar no botão "Boleto". Se todas as informações repassadas no momento da compra estiverem corretas o boleto será gerado normalmente.</b></div><br>';

        $form .= '<b>CPF/CNPJ</b>: <input type="text" id="customer-document" name="customer_document" onkeypress="mascaraMutuario(this,cpfCnpj)" onblur="valida(this)"><br><br>';

        $data['charge_id'] = $chargeId;
        $data = json_encode($data);
        $data = str_replace("\"", "'", $data);
        $form .=  "<button class=\"bb-button bb-button-submit botao\" id=\"gnbutton\" disabled=true onclick=\"sendData($data)\" >Boleto</button>". PHP_EOL;

        return $form;
    }

    public function getOneTimePaymentFields(array $invoice)
    {
        $data = array();
        $client = $invoice['client'];
        $data['invoiceid']                  = $invoice['id'];
        $data['total']                      = $this->moneyFormat($invoice['total'], $invoice['currency']);
        $data['due_at']                     = $invoice['due_at'];
        $data['subTotal']                   = $invoice['subtotal'];
        $data['customer_name']              = $client['first_name'] . ' ' . $client['last_name'];
        $data['customer_company_name']      = $client['company'];
        $data['customer_phone']             = preg_replace('/[^0-9]/', '', $client['phone']);
        $data['customer_email']             = $client['email'];

        $data['item_name']                  = $this->getInvoiceTitle($invoice);
        $data['item_number']                = $invoice['nr'];
        $data['currency_code']              = $invoice['currency'];

        $data['return']                     = $this->config['return_url'];
        $data['cancel_return']              = $this->config['cancel_url'];
        $data['notify_url']                 = $this->config['notify_url'];
        $data['client_id_prod']             = $this->config['client_id_prod'];
        $data['client_secret_prod']         = $this->config['client_secret_prod'];
        $data['client_id_dev']              = $this->config['client_id_dev'];
        $data['client_secret_dev']          = $this->config['client_secret_dev'];
        $data['account_id']                 = $this->config['account_id'];
        $data['email_gn']                   = $this->config['email_gn'];
        $data['sandbox']                    = $this->config['sandbox'];

        $data['charset']            = "utf-8";
        return $data;
    }


    public function getInvoiceTitle(array $invoice)
    {
        $p = array(
            ':id'=>sprintf('%05s', $invoice['nr']),
            ':serie'=>$invoice['serie'],
            ':title'=>$invoice['lines'][0]['title']
            );
        return __('Pagamento da fatura :serie:id [:title]', $p);
    }

}

?>
