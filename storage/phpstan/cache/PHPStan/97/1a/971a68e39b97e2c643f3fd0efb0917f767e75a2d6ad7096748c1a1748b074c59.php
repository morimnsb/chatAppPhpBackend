<?php declare(strict_types = 1);

// osfsl-C:/Users/31687/Desktop/chatAppPhpBackend/vendor/composer/../laravel/framework/src/Illuminate/Log/ParsesLogConfiguration.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Log\ParsesLogConfiguration
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-d8b686ef7a78ea971d8fe70688d8d14dc7d8d60e3ea48c861b7e854d0e575ce2-8.2.30-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Log\\ParsesLogConfiguration',
        'filename' => 'C:/Users/31687/Desktop/chatAppPhpBackend/vendor/composer/../laravel/framework/src/Illuminate/Log/ParsesLogConfiguration.php',
      ),
    ),
    'namespace' => 'Illuminate\\Log',
    'name' => 'Illuminate\\Log\\ParsesLogConfiguration',
    'shortName' => 'ParsesLogConfiguration',
    'isInterface' => false,
    'isTrait' => true,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 8,
    'endLine' => 81,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'levels' => 
      array (
        'declaringClassName' => 'Illuminate\\Log\\ParsesLogConfiguration',
        'implementingClassName' => 'Illuminate\\Log\\ParsesLogConfiguration',
        'name' => 'levels',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'debug\' => \\Monolog\\Level::Debug, \'info\' => \\Monolog\\Level::Info, \'notice\' => \\Monolog\\Level::Notice, \'warning\' => \\Monolog\\Level::Warning, \'error\' => \\Monolog\\Level::Error, \'critical\' => \\Monolog\\Level::Critical, \'alert\' => \\Monolog\\Level::Alert, \'emergency\' => \\Monolog\\Level::Emergency]',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 24,
            'startTokenPos' => 31,
            'startFilePos' => 217,
            'endTokenPos' => 105,
            'endFilePos' => 514,
          ),
        ),
        'docComment' => '/**
 * The Log levels.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 6,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      'getFallbackChannelName' => 
      array (
        'name' => 'getFallbackChannelName',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get fallback log channel name.
 *
 * @return string
 */',
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 57,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 66,
        'namespace' => 'Illuminate\\Log',
        'declaringClassName' => 'Illuminate\\Log\\ParsesLogConfiguration',
        'implementingClassName' => 'Illuminate\\Log\\ParsesLogConfiguration',
        'currentClassName' => 'Illuminate\\Log\\ParsesLogConfiguration',
        'aliasName' => NULL,
      ),
      'level' => 
      array (
        'name' => 'level',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
            'default' => NULL,
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
            'startLine' => 41,
            'endLine' => 41,
            'startColumn' => 30,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Parse the string level into a Monolog constant.
 *
 * @param  array  $config
 * @return int
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 41,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Log',
        'declaringClassName' => 'Illuminate\\Log\\ParsesLogConfiguration',
        'implementingClassName' => 'Illuminate\\Log\\ParsesLogConfiguration',
        'currentClassName' => 'Illuminate\\Log\\ParsesLogConfiguration',
        'aliasName' => NULL,
      ),
      'actionLevel' => 
      array (
        'name' => 'actionLevel',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
            'default' => NULL,
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
            'startLine' => 60,
            'endLine' => 60,
            'startColumn' => 36,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Parse the action level from the given configuration.
 *
 * @param  array  $config
 * @return int
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 60,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Log',
        'declaringClassName' => 'Illuminate\\Log\\ParsesLogConfiguration',
        'implementingClassName' => 'Illuminate\\Log\\ParsesLogConfiguration',
        'currentClassName' => 'Illuminate\\Log\\ParsesLogConfiguration',
        'aliasName' => NULL,
      ),
      'parseChannel' => 
      array (
        'name' => 'parseChannel',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
            'default' => NULL,
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
            'startLine' => 77,
            'endLine' => 77,
            'startColumn' => 37,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Extract the log channel from the given configuration.
 *
 * @param  array  $config
 * @return string
 */',
        'startLine' => 77,
        'endLine' => 80,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Log',
        'declaringClassName' => 'Illuminate\\Log\\ParsesLogConfiguration',
        'implementingClassName' => 'Illuminate\\Log\\ParsesLogConfiguration',
        'currentClassName' => 'Illuminate\\Log\\ParsesLogConfiguration',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
      ),
    ),
  ),
));