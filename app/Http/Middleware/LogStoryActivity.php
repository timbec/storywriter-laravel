<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class LogStoryActivity
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $response = $next($request);
        $duration = round((microtime(true) - $startTime) * 1000);

        try {
            // Grab everything the user sent, but remove sensitive stuff if any
            $inputs = $request->except(['password', 'token', 'api_key']);

            DB::table('story_analytics')->insert([
                'user_id' => Auth::id(),
                'story_inputs' => json_encode($inputs), // <--- SAVES EVERYTHING
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'generation_time_ms' => $duration,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Silently fail
        }

        return $response;
    }
}