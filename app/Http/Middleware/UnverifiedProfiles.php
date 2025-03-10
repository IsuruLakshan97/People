<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Guard;
use App\Models\User;

class UnverifiedProfiles
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    protected $auth;

    public function __construct(Guard $auth)
    {
        $this->auth = $auth;
    }

    public function handle(Request $request, Closure $next)
    {
        // dd($request->route('username'));
        $thisUser = $request->route('username');

        $student = User::where('username', $thisUser)->firstOrfail()->students()->firstOrfail();

        if($student->is_verified == 0){
            if($this->auth->user()) {
                if($this->auth->user()->username == $thisUser) {
                    return $next($request);
                }
            }
        } else {
            return $next($request);
        }

        abort(404, 'Not Found.');
    }
}
