<?php

namespace FormRelay\Request\Exception;

use FormRelay\Core\Exception\FormRelayException;

class InvalidUrlException extends FormRelayException
{
    public function __construct($url)
    {
        parent::__construct(sprintf('Bad URL %s', $url), 1565612422);
    }
}
