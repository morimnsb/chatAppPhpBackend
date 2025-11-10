// app/Http/Middleware/SimpleCors.php
namespace App\Http\Middleware;

use Closure;

class SimpleCors
{
    public function handle($request, Closure $next)
    {
        $origin = 'http://localhost:3000';

        if ($request->getMethod() === 'OPTIONS') {
            return response('OK', 200)
                ->header('Access-Control-Allow-Origin', $origin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Authorization, Content-Type');
        }

        $response = $next($request);

        return $response
            ->header('Access-Control-Allow-Origin', $origin)
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Authorization, Content-Type');
    }
}
