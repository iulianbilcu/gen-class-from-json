<?php

namespace App;


use Exception;
use Nette\PhpGenerator\ClassType;
use plejus\PhpPluralize\Inflector;

class Parser
{

    public function process($className, $namespace, $array)
    {
        $this->cleanup();

        list($class, $imports) = $this->getClass($array, $className, $namespace);

        $this->saveClass($className, $class, $namespace, $imports);
    }

    private function snakeToCamel($input)
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $input))));
    }

    public function cleanup()
    {
        $files = glob(__DIR__ . '/../classes/*'); // get all file names
        var_dump($files);
        foreach ($files as $file) { // iterate files
            if (is_file($file)) {
                unlink($file); // delete file
            }
        }
    }

    public function saveClass($className, $class, $namespace, $imports = [])
    {
        echo $className . PHP_EOL;
        $begin = '<?php ';

        $importString = PHP_EOL;

//        foreach ($imports as $import) {
//            $importString .= "use $namespace\\$import;" . PHP_EOL;
//        } // todo use imports, as all the classes are in the same namespace ?

        $importString .= 'use JMS\Serializer\Annotation as Serializer;' . PHP_EOL;
        $importString .= 'use JMS\Serializer\Annotation\AccessType;' . PHP_EOL;

        file_put_contents(
            'classes/' . $className . '.php',
            $begin . 'namespace ' . $namespace . ';' . PHP_EOL . $importString . PHP_EOL . PHP_EOL . $class
        );
    }

    private function isArrayOfArrays($array)
    {
        foreach ($array as $child) {
            if (!(is_array($child))) {
                return false;
            }
        }
        return true;
    }

    public function getClass($array, $name, $namespace)
    {
        $class = new ClassType($name);

        $imports = [];

        $class->addComment('@AccessType("public_method")');

        try {
            foreach ($array as $property => $type) {
                $type = gettype($type);

                if ($type == 'array') {
                    $newClassName = (new Inflector())->singular(ucfirst($this->snakeToCamel($property)));

                    $imports[] = $newClassName;

                    if ($this->isArrayOfArrays($array[$property]) && isset($array[$property][0])) {
//                    $typeComment = "@var array";
                        list($newClass, $localImports) = $this->getClass(
                            $array[$property][0],
                            $newClassName,
                            $namespace
                        );
                        $type = $newClassName . "[]";
                    } else {
//                    $typeComment = "@var {$namespace}\\{$newClassName}";
                        list($newClass, $localImports) = $this->getClass($array[$property], $newClassName, $namespace);
                        $type = $newClassName;
                    }

                    $this->saveClass($newClassName, $newClass, $namespace, $localImports);

                    $serializerType = ($this->isArrayOfArrays($array[$property])) ?
                        '@Serializer\Type("array<' . $newClassName . '>")' :
                        '@Serializer\Type("' . $newClassName . '")';

                    $prop = $class->addProperty($this->snakeToCamel($property))
                        ->setVisibility('private')
//                    ->addComment($typeComment)
//                    ->addComment('')
                        ->addComment($serializerType);
                } else {
                    $prop = $class->addProperty($this->snakeToCamel($property))
                        ->setVisibility('private')
//                    ->addComment("@var {$type}")
//                    ->addComment('')
                        ->addComment('@Serializer\Type("' . $type . '")');
                }
                if ($property != $this->snakeToCamel($property)) {
                    $prop->addComment('@Serializer\SerializedName("' . $property . '")');
                }

                // fix type
                switch ($type) {
                    case 'boolean':
                        $type = 'bool';
                        break;
                    case 'integer':
                        $type = 'int';
                        break;
                    case 'double':
                        $type = 'float';
                        break;
                }
                if ($type == 'boolean') {
                    $type = 'bool';
                }

                // add getter

                $formattedProperty = $this->snakeToCamel($property);

                $class->addMethod('get' . ucfirst($formattedProperty))
                    ->addComment('@return ' . $type)
//                ->setReturnType($type) // method return type
                    ->setBody('return $this->' . $formattedProperty . ';');

                // add setter
                $method = $class->addMethod('set' . ucfirst($formattedProperty))
                    ->addComment('@param ' . $type . ' $' . $formattedProperty)
                    ->addComment('@return ' . $name)
//                ->setReturnType($type) // method return type
                    ->setBody(
                        '$this->' . $formattedProperty . ' = $' . $formattedProperty . ';' . PHP_EOL . 'return $this;'
                    );

                $method->addParameter($formattedProperty);
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . PHP_EOL;
        }

        return [$class, $imports];
    }
}