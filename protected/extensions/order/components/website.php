<?php

namespace Extensions\Components;

class Website
{
    public $_order;
    public $_ext_order;

    public function __construct($order, $settings)
    {
        $this->_order = $order;
        $this->_settings = $settings;
        if (in_array('ext_order', array_keys($settings['params']))) {
            $ext_order = json_decode($settings['params']['ext_order'], true);
            $this->_ext_order = $ext_order;
        }
    }

    public function create()
    {
        $configs = json_decode($this->_order->config, true);
        if (empty($configs['domain_name']))
            throw new \Exception( 'Nama domain tidak ditemukan.' );

        $params = ['domain' => $configs['domain_name']];

        try {
            $info = $this->_request('v-list-web-domain', $params);
            if (empty($info)) {
                $this->_request('v-add-domain', $params);
                $this->createDb($configs);
            }
        } catch (\Exception $e) {
            throw new \Exception( $e->getMessage() );
        }

        return true;
    }

    private function createDb($configs)
    {
        if (empty($configs['domain_name']))
            return false;

        $pecah = explode( ".", $configs['domain_name'] );
        $params = [
            'db_name' => $pecah[0].'d',
            'db_user' => $pecah[0].'u',
            'db_pass' => $pecah[0].'123'
        ];

        $add = $this->_request('v-add-database', $params);

        return true;
    }

    private function _request($command = 'v-list-web-domain', $params = null)
    {
        $postvars = array(
            'user' => $this->_ext_order['server_username'],
            'password' => $this->_ext_order['server_password'],
            'cmd' => $command
        );

        switch ($command) {
            case 'v-list-web-domain':
                $postvars['arg1'] = $this->_ext_order['server_username'];
                $postvars['arg2'] = $params['domain'];
                $postvars['arg3'] = 'json';
                break;
            case 'v-add-domain':
                $postvars['returncode'] = true;
                $postvars['arg1'] = $this->_ext_order['server_username'];
                $postvars['arg2'] = $params['domain'];
                break;
            case 'v-add-database':
                $postvars['returncode'] = true;
                $postvars['arg1'] = $this->_ext_order['server_username'];
                $postvars['arg2'] = $params['db_name'];
                $postvars['arg3'] = $params['db_user'];
                $postvars['arg4'] = $params['db_pass'];
                break;
        }

        $postdata = http_build_query($postvars);

        // Send POST query via cURL
        $postdata = http_build_query($postvars);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://' . $this->_ext_order['server_ip'] . ':8083/api/');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
        $answer = curl_exec($curl);

        // Parse JSON output
        $data = json_decode($answer, true);

        // Print result
        return $data;
    }
}
