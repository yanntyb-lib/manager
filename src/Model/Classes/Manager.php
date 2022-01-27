<?php

namespace Yanntyb\Manager\Model\CLasses;

use RedBeanPHP\OODBBean;
use RedBeanPHP\R;
use RedBeanPHP\RedException\SQL;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

class Manager
{

    private static bool $setupFlag = false;
    private static string $ps4;

    /**
     * Setup database connection and ps4
     * @param string $dbname
     * @param string $user
     * @param string $password
     * @param string $psr4Entity
     * @return void
     */
    public static function setup(string $psr4Entity, string $dbname, string $user = "root", string $password = ""){
        if(!self::$setupFlag){
            if(!R::testConnection()){
                R::setup("mysql:host=localhost;dbname=$dbname","$user","$password");
            }
            static::$ps4 = $psr4Entity;
            self::$setupFlag = true;
        }
    }

    /**
     * Return all entity matching parameters
     * @throws MethodNotFound
     */
    public static function getAllEntity(string $col, string $sql = "", array $sqlParameter = []): ?array
    {
        $beans = R::findAll($col, Manager::sanitize($sql), $sqlParameter);
        if($beans){
            $items = [];
            /**
             * @var OODBBean $bean
             */
            foreach($beans as $bean){
                $item = self::createItem($col, $bean);
                $items[$bean->id] = $item;

            }
            return $items;
        }
        return [];

    }

    /**
     * Return single entity matching parameters
     * @throws MethodNotFound
     */
    public static function getSingleEntity(string $col, int|string $sqlOrId, array $sqlParameter = [])
    {
        if(is_numeric($sqlOrId)){
            $bean = R::findOne($col, " id = :id", [":id" => Manager::sanitize($sqlOrId) ] );
        }
        else{
            $bean = R::findOne($col," " . Manager::sanitize($sqlOrId),$sqlParameter);
        }
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
    protected static function createItem(string $col, ?OODBBean $bean): mixed
    {
        $item = new (self::$ps4 . "\\" . ucfirst($col));

        foreach ($bean->getProperties() as $property => $value) {
            if(str_contains($property,"_fk")){
                $methode = "set" . ucfirst(str_replace("_fk","",$property));
                $fk = true;
            }
            else{
                $methode = "set" . ucfirst($property);
                $fk = false;
            }
            if($value || $value === 0 || $value === "0"){
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
                    throw new MethodNotFound($methode, $item);
                }
            }

        }
        return $item;
    }

    /**
     * Dispense a Object(s) into Database
     * @param object|array $object $object
     * @return int|array
     * @throws ReflectionException
     * @throws SQL
     */
    public static function store(object|array $object, array $ignore = []): int|array
    {
        if(is_array($object)){
            $objects = $object;
            $id = [];
            foreach($objects as $object){
                $id[] = R::store(Manager::getBeanFromObject($object, false, $ignore));
            }
            return $id;
        }
        else{
            return R::store(Manager::getBeanFromObject($object, false, $ignore));
        }

    }

    /**
     * Update an item in database based on $object property
     * @throws ReflectionException
     * @throws MethodNotFound
     */
    public static function update(object $object, array $ignore = []){
        $classNameWithNamespace = explode("\\",get_class($object));
        $className = strtolower($classNameWithNamespace[count($classNameWithNamespace) - 1]);
        $props  = (new ReflectionClass(get_class($object)))->getProperties();
        $bean = R::load($className,$object->getId());
        foreach($props as $prop){
            $propName = $prop->name;
            $bean = self::setProperty($prop, $propName, $object, $bean, $ignore);
        }
        return R::store($bean);
    }

    /**
     * @param ReflectionProperty $prop
     * @param string $propName
     * @param object $object
     * @param OODBBean $bean
     * @return OODBBean
     * @throws ReflectionException
     * @throws MethodNotFound
     */
    public static function setProperty(ReflectionProperty $prop, string $propName, object $object, OODBBean $bean, $ignores = []): OODBBean
    {
        $propType = (new ReflectionProperty($prop->class, $propName))->getType()->getName();
        $getter = "get" . ucfirst($propName);
        if(!method_exists($object, $getter)){
            throw new MethodNotFound($getter, $object);
        }
        if (!str_contains($propType, "\\")) {
            foreach($ignores as $ignore){
                if($propName === $ignore){
                    return $bean;
                }
            }
            $bean->$propName = Manager::sanitize($object->$getter());
        } else {
            $propName = $propName . "_fk";
            $bean->$propName = $object->$getter()->getId();
        }
        return $bean;
    }

    public static function deleteFromObject(object|array|null $object){
        if($object){
            if(is_array($object)){
                $objects = $object;
                foreach($objects as $object){
                    Manager::trashSingleObject($object);
                }
            }
            else{
                Manager::trashSingleObject($object);
            }
        }
    }

    protected static function trashSingleObject(object $object){
        R::trash(Manager::getBeanFromObject($object));
    }

    /**
     * Get bean from object
     * @param object $object
     * @return OODBBean
     * @throws ReflectionException
     */
    protected static function getBeanFromObject(object $object,bool $withId = false, array $ignore = []): OODBBean
    {
        $classNameWithNamespace = explode("\\",get_class($object));
        $className = strtolower($classNameWithNamespace[count($classNameWithNamespace) - 1]);
        $props  = (new ReflectionClass(get_class($object)))->getProperties();

        if($withId){
            $bean = R::load($className,$object->getId());
        }
        else{
            $bean = R::load($className,0);
        }

        foreach($props as $prop){
            $propName = $prop->name;
            if(!$withId){
                $bean = self::setProperty($prop, $propName, $object, $bean, $ignore);
            }
            else{
                if($propName !== "id"){
                    $bean = self::setProperty($prop, $propName, $object, $bean, $ignore);
                }
            }

        }
        return $bean;
    }

    private static function sanitize(string $data): string
    {
        return trim(stripslashes(htmlspecialchars(addslashes($data))));
    }

}