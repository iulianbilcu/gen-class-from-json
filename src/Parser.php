<?php

namespace App;


use Exception;
use Nette\PhpGenerator\ClassType;
use plejus\PhpPluralize\Inflector;

class Parser
{

    public function process($className, $namespace, $array): void
    {
        $this->cleanup();

        [$class, $imports] = $this->getClass($array, $className, $namespace);

        $this->saveClass($className, $class, $namespace, false);
    }

    private function snakeToCamel($input): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $input))));
    }

    public function cleanup(): void
    {
        $files = glob(__DIR__ . '/../classes/*'); // get all file names
        var_dump($files);
        foreach ($files as $file) { // iterate files
            if (is_file($file)) {
                unlink($file); // delete file
            }
        }

        // add recursivity here, doh
        $files = glob(__DIR__ . '/../classes/SubModels*'); // get all file names
        var_dump($files);
        foreach ($files as $file) { // iterate files
            if (is_file($file)) {
                unlink($file); // delete file
            }
        }
    }

    public function saveClass($className, $class, $namespace, $subModels = false): void
    {
        echo $className . PHP_EOL;
        $begin = '<?php ';

        $importString = PHP_EOL;

//        foreach ($imports as $import) {
//            $importString .= "use $namespace\\$import;" . PHP_EOL;
//        } // todo use imports, as all the classes are in the same namespace ?

        $importString .= 'use JMS\Serializer\Annotation as Serializer;' . PHP_EOL;
        $importString .= 'use JMS\Serializer\Annotation\AccessType;' . PHP_EOL;

        $path = 'classes/' . ($subModels ? 'SubModels/' : '');
        if (!is_dir($path)){
            if (!mkdir($path) && !is_dir($path)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
            }
        }
        file_put_contents(
            $path . $className . '.php',
            $begin . 'namespace ' . $namespace . ($subModels ? '\SubModels' : '') . ';' . PHP_EOL . $importString . PHP_EOL . PHP_EOL . $class
        );
    }

    private function isArrayOfArrays($array): bool
    {
        foreach ($array as $child) {
            if (!(is_array($child))) {
                return false;
            }
        }
        return true;
    }

    public function getClass($array, $name, $namespace): array
    {
        $class = new ClassType($name);

        $imports = [];

        $class->addComment('@AccessType("public_method")');

        try {
            foreach ($array as $property => $type) {
                $type    = gettype($type);
                $varType = $type;

                // fix type
                switch ($type) {
                    case 'boolean':
                        $varType = 'bool';
                        break;
                    case 'integer':
                        $varType = 'int';
                        break;
                    case 'double':
                        $varType = 'float';
                        break;
                }
                if ($type === 'boolean') {
                    $varType = 'bool';
                }


                if ($type === 'array') {
                    $newClassName = (new Inflector())->singular(ucfirst($this->snakeToCamel($property)));

                    $imports[] = $newClassName;

                    if ($this->isArrayOfArrays($array[$property]) && isset($array[$property][0])) {
//                    $typeComment = "@var array";
                        [$newClass, $localImports] = $this->getClass(
                            $array[$property][0],
                            $newClassName,
                            $namespace
                        );
                        $type    = $newClassName . "[]";
                        $varType = 'array';
                    } else {
//                    $typeComment = "@var {$namespace}\\{$newClassName}";
                        [$newClass, $localImports] = $this->getClass($array[$property], $newClassName, $namespace);
                        $type    = $newClassName;
                        $varType = $newClassName;
                    }

                    $this->saveClass($newClassName, $newClass, $namespace, true);

                    $serializerType = ($this->isArrayOfArrays($array[$property])) ?
                        '@Serializer\Type("array<' . $newClassName . '>")' :
                        '@Serializer\Type("' . $newClassName . '")';

                    $prop = $class->addProperty($this->snakeToCamel($property))
                        ->setVisibility('private')
                        ->setType($varType)
//                    ->addComment($typeComment)
//                    ->addComment('')
                        ->addComment(PHP_EOL . $serializerType . PHP_EOL);
                } else {
                    $prop = $class->addProperty($this->snakeToCamel($property))
                        ->setVisibility('private')
                        ->setType($varType)
//                    ->addComment("@var {$type}")
//                    ->addComment('')
                        ->addComment(PHP_EOL . '@Serializer\Type("' . $type . '")' . PHP_EOL);
                }
                if ($property !== $this->snakeToCamel($property)) {
                    $prop->addComment('@Serializer\SerializedName("' . $property . '")');
                }


                // add getter

                $formattedProperty = $this->snakeToCamel($property);

                $class->addMethod('get' . ucfirst($formattedProperty))
                    ->addComment('@return ' . $type)
                    ->setReturnType($varType) // method return type
                    ->setBody('return $this->' . $formattedProperty . ';');

                // add setter
                $method = $class->addMethod('set' . ucfirst($formattedProperty))
                    ->addComment('@param ' . $type . ' $' . $formattedProperty)
                    ->addComment('@return ' . $name)
                    ->setReturnType($name) // method return type
                    ->setBody(
                        '$this->' . $formattedProperty . ' = $' . $formattedProperty . ';' . PHP_EOL . 'return $this;'
                    );

                $method->addParameter($formattedProperty)->setType($varType);
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . PHP_EOL;
        }

        return [$class, $imports];
    }
}