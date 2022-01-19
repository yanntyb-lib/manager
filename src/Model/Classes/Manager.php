<?php

namespace Yanntyb\Manager\Model\CLasses;

use RedBeanPHP\OODBBean;
use RedBeanPHP\R;

class Manager
{

    private static bool $setupFlag = false;
    private static array $ps4;

    public static function setup(string $dbname, string $user, string $password, string $psr4Entity){
        if(!self::$setupFlag){
            if(!R::testConnection()){
                R::setup("mysql:host=localhost;dbname=$dbname","$user","$password");
            }
            static::$ps4 = [
                "entity" => $psr4Entity,
            ];
            self::$setupFlag = true;
        }
    }

    /**
     * @throws MethodNotFound
     */
    public static function getAllEntity(string $col, string $sql = "", array $sqlParameter = []): ?array
    {
        $beans = R::findAll($col, $sql, $sqlParameter);
        if($beans){
            $items = [];
            /**
             * @var OODBBean $bean
             */
            foreach($beans as $bean){
                $item = self::createItem($col, $bean);
                $items[] = $item;

            }
            return $items;
        }
        return null;

    }

    /**
     * @throws MethodNotFound
     */
    public static function getSingleEntity(string $col, int|string $sqlOrId, array $sqlParameter = [])
    {
        $bean = R::findOne($col," " . $sqlOrId,$sqlParameter);
        if($bean){
            return self::createItem($col, $bean);
        }
        return null;
    }

    /**
     * Create Class based on psr4 provided and $bean
     * @param string $col
     * @param OODBBean|null $bean
     * @return mixed
     * @throws MethodNotFound
     */
    public static function createItem(string $col, ?OODBBean $bean): mixed
    {
        $item = new (self::$ps4["entity"] . "\\" . ucfirst($col));

        foreach ($bean->getProperties() as $property => $value) {
            if(str_contains($property,"_fk")){
                $methode = "set" . ucfirst(str_replace("_fk","",$property));
                $fk = true;
            }
            else{
                $methode = "set" . ucfirst($property);
                $fk = false;
            }
            if (method_exists($item, $methode)) {
                if($fk){
                    $subItem = self::getSingleEntity(str_replace("_fk","",$property), $value);
                    $item = $item->$methode($subItem);
                }
                else{
                    $item = $item->$methode($value);
                }
            }
            else{
                throw new MethodNotFound($methode, get_class($item));
            }
        }
        return $item;
    }
}