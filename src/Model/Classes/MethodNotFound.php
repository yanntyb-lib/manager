<?php

namespace Yanntyb\Manager\Model\CLasses;

use Exception;

class MethodNotFound extends Exception
{
    public function __construct($methode , $class) {
        parent::__construct($methode . " Not found in " . $class);
    }
}