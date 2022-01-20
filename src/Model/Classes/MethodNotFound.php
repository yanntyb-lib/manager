<?php

namespace Yanntyb\Manager\Model\CLasses;

use Exception;

class MethodNotFound extends Exception
{
    public function __construct(string $methode , object $class) {
        parent::__construct($methode . " Not found in " . get_class($class));
    }
}