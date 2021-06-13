<?php

namespace Vidavh\Gateway;

use Vidavh\Gateway\Gateways\Mellat\Mellat;
use Vidavh\Gateway\Gateways\Zarinpal\Zarinpal;
use Vidavh\Gateway\Exceptions\RetryException;
use Vidavh\Gateway\Exceptions\PortNotFoundException;
use Vidavh\Gateway\Exceptions\InvalidRequestException;
use Vidavh\Gateway\Exceptions\NotFoundTransactionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class GatewayResolver
{

    protected $request;

    /**
     * @var Config
     */
    public $config;

    /**
     * Keep current port driver
     *
     * @var Mellat|Zarinpal
     */
    protected $port;

    /**
     * Gateway constructor.
     * @param null $config
     * @param null $port
     * @throws PortNotFoundException
     */
    public function __construct($config = null, $port = null)
    {
        $this->config = app('config');
        $this->request = app('request');

        $timezone = $this->config->get('gateway.timezone');
        if ($timezone) {
            date_default_timezone_set($timezone);
        }

        if (!is_null($port)) $this->make($port);
    }

    /**
     * Get supported ports
     *
     * @return array
     */
    public function getSupportedPorts()
    {
        return [
            Constants::MELLAT,
            Constants::ZARINPAL,
        ];
    }

    /**
     * Call methods of current driver
     *
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws PortNotFoundException
     */
    public function __call($name, $arguments)
    {
        // calling by this way ( Gateway::mellat()->.. , Gateway::parsian()->.. )
        if (in_array(strtoupper($name), $this->getSupportedPorts())) {
            return $this->make($name);
        }

        return call_user_func_array([$this->port, $name], $arguments);
    }

    /**
     * Gets query builder from you transactions table
     * @return mixed
     */
    function getTable()
    {
        return DB::table($this->config->get('gateway.table'));
    }

    /**
     * Callback
     *
     * @return Mellat|Zarinpal ->port
     *
     * @throws InvalidRequestException
     * @throws NotFoundTransactionException
     * @throws PortNotFoundException
     * @throws RetryException
     * @throws \SoapFault
     */
    public function verify()
    {
        if (!$this->request->has('transaction_id') && !$this->request->has('iN'))
            throw new InvalidRequestException;
        if ($this->request->has('transaction_id')) {
            $id = $this->request->get('transaction_id');
        } else {
            $id = $this->request->get('iN');
        }

        $transaction = $this->getTable()->whereId($id)->first();

        if (!$transaction)
            throw new NotFoundTransactionException;

        if (in_array($transaction->status, [Constants::TRANSACTION_SUCCEED, Constants::TRANSACTION_FAILED]))
            throw new RetryException;

        $this->make($transaction->port);

        return $this->port->verify($transaction);
    }


    /**
     * Create new object from port class
     *
     * @param int $port
     * @return GatewayResolver
     * @throws PortNotFoundException
     */
    function make($port)
    {
        if ($port InstanceOf Mellat) {
            $name = Constants::MELLAT;
        } elseif ($port InstanceOf Zarinpal) {
            $name = Constants::ZARINPAL;
        }elseif (in_array(strtoupper($port), $this->getSupportedPorts())) {
            $port = ucfirst(strtolower($port));
            $name = strtoupper($port);
            $class = __NAMESPACE__ . '\\Gateways' . '\\' . $port . '\\' . $port;
            $port = new $class;
        } else
            throw new PortNotFoundException;

        $this->port = $port;
        $this->port->setConfig($this->config); // injects config
        $this->port->setPortName($name); // injects config
        $this->port->boot();

        return $this;
    }
}
