<?php

namespace Vidavh\Gateway;

use Vidavh\Gateway\Contracts\PortInterface;
use Vidavh\Gateway\Exceptions\BankException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Carbon\Carbon;
use SoapFault;

abstract class Port implements PortInterface
{
    /**
     * @var null
     */
    protected $uid = null;

    /**
     * Transaction id
     *
     * @var null|int
     */
    protected $transactionId = null;

    /**
     * Transaction row in database
     */
    protected $transaction = null;

    /**
     * Customer card number
     *
     * @var string
     */
    protected $cardNumber = '';

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var DB
     */
    protected $db;

    /**
     * Port id
     *
     * @var int
     */
    protected $portName;

    /**
     * Reference id
     *
     * @var string
     */
    protected $refId;

    /**
     * Amount in Rial
     *
     * @var int
     */
    protected $amount;

    /**
     * Description of transaction
     *
     * @var string
     */
    protected $description;

    /**
     * callback URL
     *
     * @var string
     */
    protected $callbackUrl;

    /**
     * Tracking code payment
     *
     * @var string
     */
    protected $trackingCode;

    /**
     * User Id
     *
     * @var int
     */
    protected $userId;

    /**
     * Initialize of class
     *
     */
    function __construct()
    {
        $this->db = app('db');
    }

    /** bootstraper */
    function boot()
    {

    }

    function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * @return mixed
     */
    function getTable()
    {
        return $this->db->table($this->config->get('gateway.table'));
    }

    /**
     * @return mixed
     */
    function getLogTable()
    {
        return $this->db->table($this->config->get('gateway.table') . '_logs');
    }

    /**
     * Get port id, $this->port
     *
     * @return int
     */
    function getPortName()
    {
        return $this->portName;
    }

    /**
     * Get port id, $this->port
     *
     * @param $name
     * @return void
     */
    function setPortName($name)
    {
        $this->portName = $name;
    }

    /**
     * Set custom description on current transaction
     *
     * @param string $description
     *
     * @return void
     */
    function setCustomDesc($description)
    {
        $this->description = $description;
    }

    /**
     * Get custom description of current transaction
     *
     * @return string | null
     */
    function getCustomDesc()
    {
        return $this->description;
    }

    /**
     * Return card number
     *
     * @return string
     */
    function cardNumber()
    {
        return $this->cardNumber;
    }

    /**
     * Return tracking code
     */
    function trackingCode()
    {
        return $this->trackingCode;
    }

    /**
     * Get transaction id
     *
     * @return int|null
     */
    function transactionId()
    {
        return $this->transactionId;
    }

    /**
     * Return reference id
     */
    function refId()
    {
        return $this->refId;
    }

    /**
     * Sets price
     * @param $price
     * @return mixed
     */
    function price($price)
    {
        return $this->set($price);
    }

    /**
     * get price
     */
    function getPrice()
    {
        return $this->amount;
    }

    /**
     * @return int
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @param int $userId
     */
    public function setUserId(int $userId)
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * Get uid
     *
     * @return int|null
     */
    function uid()
    {
        return $this->uid;
    }

    /**
     * Return result of payment
     * If result is done, return true, otherwise throws an related exception
     *
     * This method must be implements in child class
     *
     * @param object $transaction row of transaction in database
     *
     * @return void
     *
     * @throws BankException
     * @throws SoapFault
     */
    function verify($transaction)
    {
        $this->transaction = $transaction;
        $this->transactionId = $transaction->id;
        $this->uid = $transaction->uid;
        $this->amount = intval($transaction->creditor);
        $this->refId = $transaction->ref_id;
    }

    function getTimeId()
    {
        $genuid = function () {
            return substr(str_pad(str_replace('.', '', microtime(true)), 12, 0), 0, 12);
        };
        $uid = $genuid();
        while ($this->getTable()->whereUid($uid)->first())
            $uid = $genuid();
        return $uid;
    }

    /**
     * Insert new transaction to poolport_transactions table
     *
     * @return int last inserted id
     */
    protected function newTransaction()
    {
        $this->uid = $this->getTimeId();

        $this->transactionId = $this->getTable()->insertGetId([
            'uid' => $this->uid ,
            'port' => $this->getPortName(),
            'creditor' => $this->amount,
            'user_id' => $this->getUserId(),
            'status' => Constants::TRANSACTION_INIT,
            'ip' => Request::ip(),
            'description' => $this->description,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        return $this->transactionId;
    }

    /**
     * Commit transaction
     * Set status field to success status
     *
     * @param array $fields
     * @return mixed
     */
    protected function transactionSucceed(array $fields = [])
    {
        $updateFields = [
            'status' => Constants::TRANSACTION_SUCCEED,
            'tracking_code' => $this->trackingCode,
            'card_number' => $this->cardNumber,
            'date' => Carbon::now(), //payment_date
            'updated_at' => Carbon::now(),
        ];

        if (!empty($fields)) {
            $updateFields = array_merge($updateFields, $fields);
        }

        return $this->getTable()->whereId($this->transactionId)->update($updateFields);
    }

    /**
     * Failed transaction
     * Set status field to error status
     *
     * @return bool
     */
    protected function transactionFailed()
    {
        return $this->getTable()->whereId($this->transactionId)->update([
            'status' => Constants::TRANSACTION_FAILED,
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * Update transaction refId
     *
     * @return mixed
     */
    protected function transactionSetRefId()
    {
        return $this->getTable()->whereId($this->transactionId)->update([
            'ref_id' => $this->refId,
            'updated_at' => Carbon::now(),
        ]);

    }

    /**
     * New log
     *
     * @param string|int $statusCode
     * @param string $statusMessage
     * @return mixed
     */
    protected function newLog($statusCode, $statusMessage)
    {
        return $this->getLogTable()->insert([
            'transaction_id' => $this->transactionId,
            'result_code' => $statusCode,
            'result_message' => $statusMessage,
            'log_date' => Carbon::now(),
        ]);
    }

    /**
     * Add query string to a url
     *
     * @param string $url
     * @param array $query
     * @return string
     */
    protected function makeCallback($url, array $query)
    {
        return $this->url_modify(array_merge($query, ['_token' => '']), url($url));
    }

    /**
     * manipulate the Current/Given URL with the given parameters
     *
     * @param $changes
     * @param  $url
     * @return string
     */
    protected function url_modify($changes, $url)
    {
        // Parse the url into pieces
        $url_array = parse_url($url);

        // The original URL had a query string, modify it.
        if (!empty($url_array['query'])) {
            parse_str($url_array['query'], $query_array);
            $query_array = array_merge($query_array, $changes);
        } // The original URL didn't have a query string, add it.
        else {
            $query_array = $changes;
        }

        return (!empty($url_array['scheme']) ? $url_array['scheme'] . '://' : null) .
            (!empty($url_array['host']) ? $url_array['host'] : null) .
            (!empty($url_array['port']) ? ':' . $url_array['port'] : null) .
            (!empty($url_array['path']) ? $url_array['path'] : null) .
            '?' . http_build_query($query_array);
    }
}
