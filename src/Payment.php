<?php

namespace Calibr\WalletOne;

use Calibr\WalletOne\Exception\ValidationFailedException;

class Payment {
  private $submitURL = "https://wl.walletone.com/checkout/checkout/Index";
  private $key;
  private $merchantId;

  private $amount = 0.0;
  // set by validation
  private $commission;
  // set by validation
  private $payerId;
  // set by validation
  private $orderId;
  // set by validation
  private $createDate;
  // set by validation
  private $updateDate;
  private $currencyId = 0;
  private $paymentId = "";
  private $description = "";
  private $successUrl = "";
  private $failUrl = "";
  private $expiredDate;

  private $state = "undefined";

  private $customParameters = [];

  public function __construct($merchantId, $key = "") {
    $this->merchantId = $merchantId;
    $this->key = $key;
    // 30 days payment TTL by default
    $this->setExpiredDateTimestamp(time() + 24 * 3600 * 30);
  }

  public function setAmount($amount) {
    $this->amount = sprintf("%0.2f", $amount);
    return $this;
  }

  public function getAmount() {
    return $this->amount;
  }

  public function setCurrencyId($currencyId) {
    $this->currencyId = $currencyId;
    return $this;
  }

  public function getCurrencyId() {
    return $this->currencyId;
  }

  public function setPaymentId($paymentId) {
    $this->paymentId = $paymentId;
    return $this;
  }

  public function getPaymentId() {
    return $this->paymentId;
  }

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  public function getDescription() {
    return $this->description;
  }

  public function setSuccessUrl($successUrl) {
    $this->successUrl = $successUrl;
    return $this;
  }

  public function setFailUrl($failUrl) {
    $this->failUrl = $failUrl;
    return $this;
  }

  public function setExpiredDateTimestamp($time) {
    $d = new \DateTime(null, new \DateTimeZone("UTC"));
    $date = $d->format("c");
    // get the part before +
    list($date) = explode("+", $date);
    $this->expiredDate = $date;
    return $this;
  }

  public function setExpiredDate($date) {
    $this->expiredDate = $date;
    return $this;
  }

  public function getExpiredDate() {
    return $this->expiredDate;
  }

  public function getCommission() {
    return $this->commission;
  }

  public function getPayerId() {
    return $this->payerId;
  }

  public function getOrderId() {
    return $this->orderId;
  }

  public function getCreateDate() {
    return $this->createDate;
  }

  public function getUpdateDate() {
    return $this->updateDate;
  }

  public function getState() {
    return $this->state;
  }

  public function setCustomParameters($customParameters) {
    $this->customParameters = $customParameters;
  }

  private function getFormPayload() {
    $parameters = [
      "WMI_MERCHANT_ID" => $this->merchantId,
      "WMI_PAYMENT_AMOUNT" => $this->amount,
      "WMI_CURRENCY_ID" => $this->currencyId,
      "WMI_PAYMENT_NO" => $this->paymentId,
      "WMI_DESCRIPTION" => $this->description,
      "WMI_EXPIRED_DATE" => $this->expiredDate,
      "WMI_SUCCESS_URL" => $this->successUrl,
      "WMI_FAIL_URL" => $this->failUrl
    ];
    if($this->customParameters) {
      $parameters = array_merge($parameters, $this->customParameters);
    }
    return $parameters;
  }

  public function getSignature($params = null) {
    if(!$params) {
      $params = $this->getFormPayload();
    }
    ksort($params, SORT_FLAG_CASE | SORT_STRING);
    $data = [];
    foreach($params as $value) {
      $data[] = $value;
    }
    $data[] = $this->key;
    $data = implode("", $data);
    $data = iconv("utf-8", "windows-1251", $data);
    $signature = base64_encode(
      pack(
        "H*",
        md5($data)
      )
    );
    return $signature;
  }

  public function getPaymentForm($options = []) {
    $defaultOptions = [
      "extraHTML" => "",
      "formID" => "",
      "autoSubmit" => false
    ];
    $options = array_merge($defaultOptions, $options);
    if($options["autoSubmit"] && empty($options["formID"])) {
      $options["formID"] = "form_".uniqid();
    }
    $params = $this->getFormPayload();
    if(!empty($this->key)) {
      $signature = $this->getSignature();
      $params["WMI_SIGNATURE"] = $signature;
    }
    $formHtml = [];
    $formHtml[] = '<meta charset="utf-8"><form id="'.$options["formID"].'" method="post" action="https://wl.walletone.com/checkout/checkout/Index" accept-charset="UTF-8">';
    foreach($params as $k => $v) {
      $formHtml[] = '<input type="hidden" name="'.$k.'" value="'.htmlspecialchars($v).'"/>';
    }
    $html = implode("\n", $formHtml);
    $html .= $options["extraHTML"];
    $html .= "</form>";

    if($options["autoSubmit"]) {
      $html .= '
        <script>document.getElementById("'.addslashes($options["formID"]).'").submit()</script>
      ';
    }

    return $html;
  }

  public function validate($data) {
    if(empty($data["WMI_MERCHANT_ID"])) {
      throw new ValidationFailedException("WMI_MERCHANT_ID is not specified");
    }
    if($this->merchantId != $data["WMI_MERCHANT_ID"]) {
      throw new ValidationFailedException("Merchants don't match");
    }
    if(empty($data["WMI_SIGNATURE"])) {
      throw new ValidationFailedException("WMI_SIGNATURE is not specified");
    }
    $origSignature = $data["WMI_SIGNATURE"];
    unset($data["WMI_SIGNATURE"]);
    $calculatedSignature = $this->getSignature($data);
    if($calculatedSignature !== $origSignature) {
      throw new ValidationFailedException("Signatures don't match");
    }
    // successullfy verififed, set payment variables from the request data
    $fieldsMap = [
      "WMI_PAYMENT_AMOUNT" => "amount",
      "WMI_COMMISSION_AMOUNT" => "commision",
      "WMI_CURRENCY_ID" => "currency",
      "WMI_TO_USER_ID" => "payerId",
      "WMI_PAYMENT_NO" => "paymentId",
      "WMI_ORDER_ID" => "orderId",
      "WMI_DESCRIPTION" => "description",
      "WMI_SUCCESS_URL" => "successUrl",
      "WMI_FAIL_URL" => "failUrl",
      "WMI_EXPIRED_DATE" => "expiredDate",
      "WMI_CREATE_DATE" => "createDate",
      "WMI_UPDATE_DATE" => "updateDate",
      "WMI_ORDER_STATE" => "state"
    ];
    $this->customParameters = [];
    foreach($data as $k => $v) {
      if(isset($fieldsMap[$k])) {
        $key = $fieldsMap[$k];
        $this->{$key} = $v;
      }
      elseif(stripos($k, "WMI_") === false) {
        $this->customParameters[$k] = $v;
      }
    }
  }

  public function getSuccessAnswer($message = "") {
    $res = "WMI_RESULT=OK";
    if($message) {
      $res .= "&WMI_DESCRIPTION=".urlencode($message);
    }
    return $res;
  }
}

