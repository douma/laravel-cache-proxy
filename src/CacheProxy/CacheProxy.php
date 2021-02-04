<?php

namespace Douma\CacheProxy;

class CacheProxy
{
    public static function register() : void
    {
        spl_autoload_register(function($class) use($stub)
        {
            if(strpos($class, 'Cache') === 0) {
                $original = str_replace("Cache\\", "", $class);
                $reflection = new ReflectionClass($original);
                $fileName = $reflection->getFileName();
                $file = storage_path('framework/cache') . "/" . sha1($original) . "-" . sha1_file($fileName) . ".php";
                \Illuminate\Support\Facades\File::put($file, replaceStub($stub, $original));
                require_once $file;
            }
        });
    }

    public static function getStub() : string
    {
        return $stub = '<?php namespace {namespace};

            class {className} {implementsOrExtends}
            {
                private $subject;
                private $constructor = [];

                public function __construct()
                {
                    $this->constructor = func_get_args();
                }

                private function call(string $name, array $arguments)
                {
                    $hash = sha1(__CLASS__) . $name . print_r($arguments, true);
                    if(cache()->has($hash)) {
                        return unserialize(cache()->get($hash));
                    }
                    if(!$this->subject) {
                        $this->subject = app()->make({classWithNameSpace}, $this->constructor);
                    }
                    $result = $this->subject->{$name}(...$arguments);
                    if($result) {
                        cache()->put($hash, serialize($result));
                        return $result;
                    }
                }

                {methods}
            }';
    }

    public static function replaceStub($class) : string
    {
        $stub = self::getStub();
        $reflection = new \ReflectionClass($class);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        $parentClass = $reflection->getParentClass();
        $interfaces = $reflection->getInterfaces();

        $className = $reflection->getShortName();
        $fullClass = "\\" . $reflection->getName() . "::class";
        $fullClassWithoutClass = "\\" . $reflection->getName();
        $implementsOrExtends = "";
        $namespace = "Cache\\" . $reflection->getNamespaceName();
        if($parentClass) {
            $implementsOrExtends .= "extends \\" . $parentClass->getName();
        }
        if(count($interfaces) !== 0) {
            $implementsOrExtends .= " implements ";
        }
        $count = 0;
        foreach($interfaces as $interface) {
            if($count > 0) {
                $implementsOrExtends .= ",";
            }
            $implementsOrExtends .= "\\" . $interface->getName();
            $count++;
        }

        $methodsReplace = "";
        foreach($methods as $method) {
            $parameters = "";
            if(!$method=="createFrom")
            {
                continue;
            }
            foreach($method->getParameters() as $count=>$parameter) {
                if($count > 0) {
                    $parameters .= ",";
                }
                if($parameter->getType()) {
                    if($parameter->getClass() && (string)$parameter->getType() !== "self") {
                        $parameters .= "\\";
                    }
                    if($parameter->isOptional()) {
                        $parameters .= "?";
                    }
                    $parameters .= (string)$parameter->getType() . " ";
                }
                if($parameter->isVariadic()) {
                    $parameters .= "... ";
                }
                $parameters .= '$' . $parameter->getName();
                if($parameter->isDefaultValueAvailable()) {
                    if (is_array($parameter->getDefaultValue())) {
                        $parameters .= " = []";
                    } elseif(is_null($parameter->getDefaultValue())) {
                        $parameters .= " = null";
                    } elseif(is_string($parameter->getDefaultValue())) {
                        $parameters .= " = '".$parameter->getDefaultValue()."'";
                    } elseif($parameter->getDefaultValue() == false) {
                        $parameters .= " = false";
                    } elseif($parameter->getDefaultValue() == true) {
                        $parameters .= " = true";
                    } else {
                        $parameters .= " = " . $parameter->getDefaultValue();
                    }
                }
            }

            if(!$method->isStatic() && $method->getShortName() !== "__construct") {
                $methodsReplace .= "\tpublic ";
                $methodsReplace .= " function " . $method->getShortName() . "(" . $parameters . ")";
                if ($method->hasReturnType()) {
                    $methodsReplace .= " : " . $method->getReturnType();
                }
                $methodsReplace .= "\t{";
                if ($method->hasReturnType() && $method->getReturnType()->getName() !== "void") {
                    $methodsReplace .= " return ";
                } elseif(!$method->hasReturnType()) {
                    $methodsReplace .= " return ";
                }
                $methodsReplace .= '$this->call("' . $method->getShortName() . '", func_get_args());';
                $methodsReplace .= "\t}" . PHP_EOL;
            } elseif($method->getShortName() !== "__construct"){
                $methodsReplace .= "\tpublic ";
                $methodsReplace .= " static function " . $method->getShortName() . "(" . $parameters . ")";
                if ($method->hasReturnType()) {
                    $methodsReplace .= " : " . $method->getReturnType();
                }
                $methodsReplace .= "\t{";
                if ($method->hasReturnType() && $method->getReturnType()->getName() !== "void") {
                    $methodsReplace .= " return ";
                } elseif(!$method->hasReturnType()) {
                    $methodsReplace .= " return ";
                }
                $methodsReplace .= $fullClassWithoutClass.'::' . $method->getShortName() . '(...func_get_args());';
                $methodsReplace .= "\t}" . PHP_EOL;
            }
        }

        return str_replace([
            '{implementsOrExtends}',
            '{classWithNameSpace}',
            '{className}',
            '{namespace}',
            '{methods}'
        ],[
            $implementsOrExtends,
            $fullClass,
            $className,
            $namespace,
            $methodsReplace
        ], $stub);
    }
}
