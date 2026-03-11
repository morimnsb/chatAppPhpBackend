<?php declare(strict_types = 1);

// odsl-C:/Users/31687/Desktop/chatAppPhpBackend/vendor/composer/../laravel/framework/src/Illuminate/Foundation/helpers.php-PHPStan\BetterReflection\Reflection\ReflectionFunction-response
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.2.30-49a95a6e1bc49d328168eeec535499a754b6e51606ede8484a46d39f0d831ef7',
   'data' => 
  array (
    'name' => 'response',
    'parameters' => 
    array (
      'content' => 
      array (
        'name' => 'content',
        'default' => 
        array (
          'code' => '\\null',
          'attributes' => 
          array (
            'startLine' => 848,
            'endLine' => 848,
            'startTokenPos' => 3743,
            'startFilePos' => 23667,
            'endTokenPos' => 3743,
            'endFilePos' => 23670,
          ),
        ),
        'type' => NULL,
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 848,
        'endLine' => 848,
        'startColumn' => 23,
        'endColumn' => 37,
        'parameterIndex' => 0,
        'isOptional' => true,
      ),
      'status' => 
      array (
        'name' => 'status',
        'default' => 
        array (
          'code' => '200',
          'attributes' => 
          array (
            'startLine' => 848,
            'endLine' => 848,
            'startTokenPos' => 3750,
            'startFilePos' => 23683,
            'endTokenPos' => 3750,
            'endFilePos' => 23685,
          ),
        ),
        'type' => NULL,
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 848,
        'endLine' => 848,
        'startColumn' => 40,
        'endColumn' => 52,
        'parameterIndex' => 1,
        'isOptional' => true,
      ),
      'headers' => 
      array (
        'name' => 'headers',
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 848,
            'endLine' => 848,
            'startTokenPos' => 3759,
            'startFilePos' => 23705,
            'endTokenPos' => 3760,
            'endFilePos' => 23706,
          ),
        ),
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 848,
        'endLine' => 848,
        'startColumn' => 55,
        'endColumn' => 73,
        'parameterIndex' => 2,
        'isOptional' => true,
      ),
    ),
    'returnsReference' => false,
    'returnType' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
      'data' => 
      array (
        'types' => 
        array (
          0 => 
          array (
            'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
            'data' => 
            array (
              'name' => 'Illuminate\\Contracts\\Routing\\ResponseFactory',
              'isIdentifier' => false,
            ),
          ),
          1 => 
          array (
            'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
            'data' => 
            array (
              'name' => 'Illuminate\\Http\\Response',
              'isIdentifier' => false,
            ),
          ),
        ),
      ),
    ),
    'attributes' => 
    array (
    ),
    'docComment' => '/**
 * Return a new response from the application.
 *
 * @param  \\Illuminate\\Contracts\\View\\View|string|array|null  $content
 * @param  int  $status
 * @return ($content is null ? \\Illuminate\\Contracts\\Routing\\ResponseFactory : \\Illuminate\\Http\\Response)
 */',
    'startLine' => 848,
    'endLine' => 857,
    'startColumn' => 5,
    'endColumn' => 5,
    'couldThrow' => false,
    'isClosure' => false,
    'isGenerator' => false,
    'isVariadic' => true,
    'isStatic' => false,
    'namespace' => NULL,
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'response',
        'filename' => 'C:/Users/31687/Desktop/chatAppPhpBackend/vendor/composer/../laravel/framework/src/Illuminate/Foundation/helpers.php',
      ),
    ),
  ),
));