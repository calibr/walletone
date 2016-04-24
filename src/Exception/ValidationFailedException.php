<?php


namespace Calibr\WalletOne\Exception;

class ValidationFailedException extends \Exception {
  public function __construct($message = '', $code = 0, \Exception $previous = null) {
    parent::__construct('Incoming data validation failed. '.$message, $code, $previous);
  }
}