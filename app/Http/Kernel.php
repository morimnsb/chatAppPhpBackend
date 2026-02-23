protected $middlewareAliases = [
    'auth' => \App\Http\Middleware\Authenticate::class,
    'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,

    // ✅ Node JWT
    'node.jwt' => \App\Http\Middleware\NodeJwtAuth::class,
];
